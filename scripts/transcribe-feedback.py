#!/usr/bin/env python3
"""Transcribe an audio file with faster-whisper and write a plain-text transcript.

Usage:
    scripts/transcribe-feedback.py [audio_file] [-o output_file] [--model MODEL]

Defaults to ~/gdrive/mcc-website-feedback.m4a and writes
~/gdrive/mcc-website-feedback-transcript.txt.

Requires the faster-whisper package (see .venv-whisper) and ffmpeg on PATH.
"""

import argparse
import sys
from pathlib import Path

from faster_whisper import WhisperModel

GDRIVE_DIR = Path.home() / "gdrive"
DEFAULT_AUDIO = GDRIVE_DIR / "mcc-website-feedback.m4a"


def format_timestamp(seconds: float) -> str:
    total = int(seconds)
    h, rem = divmod(total, 3600)
    m, s = divmod(rem, 60)
    return f"{h:02d}:{m:02d}:{s:02d}"


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument(
        "audio_file", nargs="?", default=str(DEFAULT_AUDIO),
        help=f"Path to the audio file (default: {DEFAULT_AUDIO})",
    )
    parser.add_argument(
        "-o", "--output",
        help="Path to write the transcript (default: <audio_file stem>-transcript.txt next to it)",
    )
    parser.add_argument(
        "--model", default="small",
        help="faster-whisper model size (tiny, base, small, medium, large-v3). Default: small",
    )
    args = parser.parse_args()

    audio_path = Path(args.audio_file).expanduser()
    if not audio_path.is_file():
        sys.exit(f"Audio file not found: {audio_path}")

    output_path = (
        Path(args.output).expanduser()
        if args.output
        else audio_path.with_name(f"{audio_path.stem}-transcript.txt")
    )

    print(f"Loading faster-whisper model '{args.model}' (CPU, int8)...")
    model = WhisperModel(args.model, device="cpu", compute_type="int8")

    print(f"Transcribing {audio_path} ...")
    segments, info = model.transcribe(str(audio_path), vad_filter=True)

    print(f"Detected language: {info.language} (p={info.language_probability:.2f})")

    lines = []
    for segment in segments:
        start = format_timestamp(segment.start)
        end = format_timestamp(segment.end)
        text = segment.text.strip()
        lines.append(f"[{start} - {end}] {text}")
        print(lines[-1])

    output_path.write_text("\n".join(lines) + "\n", encoding="utf-8")
    print(f"\nTranscript written to {output_path}")


if __name__ == "__main__":
    main()
