<?php

/**
 * @file
 * Clears Word paste residue out of the four bio biographies.
 *
 * The three real biographies (and the one node holding a bare "-") came over
 * from Drupal 7 as text pasted from Word: `<span style="font-family: Times New
 * Roman; font-size: medium">` around every sentence, `margin: 0in 0in 0pt` on
 * every paragraph, and empty `<p>&nbsp;</p>` spacers top and tail. Rendered as
 * full HTML that lands on the page as serif text in the wrong size with a hole
 * above and below it, which no amount of theme CSS should have to fight.
 *
 * Two changes, both conservative:
 *
 * 1. The text format moves from full_html to content_format. That is a
 *    *rendering* change — the stored markup is untouched and the format can be
 *    switched back in the UI. content_format's allowed-HTML filter drops the
 *    style attributes and stray spans while keeping paragraphs, lists, links
 *    and emphasis, which is all these biographies actually use. mcc_bio.yml
 *    maps the same way now, so a re-import agrees with this.
 * 2. Paragraphs holding nothing but whitespace or &nbsp; are removed. Those
 *    are spacers from a word processor, not content, and CSS cannot target
 *    them (`:empty` does not match a paragraph containing &nbsp;).
 *
 * Safe to re-run, and worth re-running after a migration re-import.
 *
 * Run with: ddev drush php:script scripts/mcc_clean_bio_bodies.php
 */

$nodes = \Drupal::entityTypeManager()->getStorage('node')
  ->loadByProperties(['type' => 'bio']);

$changed = 0;

foreach ($nodes as $node) {
  if ($node->get('body')->isEmpty()) {
    continue;
  }

  $original = (string) $node->get('body')->value;
  $format = (string) $node->get('body')->format;

  // Drop paragraphs that hold only whitespace, non-breaking spaces or breaks.
  // Delimited with ~ rather than # — the pattern contains a numeric entity.
  $cleaned = preg_replace(
    '~<p\b[^>]*>(?:\s|&nbsp;|&#160;|<br\s*/?>)*</p>~i',
    '',
    $original
  );
  if ($cleaned === NULL) {
    throw new \RuntimeException('Paragraph cleanup failed on node ' . $node->id() . ': ' . preg_last_error_msg());
  }
  $cleaned = trim($cleaned);

  // Never let a failed clean-up empty a biography that had text in it.
  if ($cleaned === '' && trim(strip_tags($original)) !== '') {
    throw new \RuntimeException('Refusing to blank a non-empty biography on node ' . $node->id());
  }

  $new_format = $format === 'full_html' ? 'content_format' : $format;

  if ($cleaned === $original && $new_format === $format) {
    continue;
  }

  $node->set('body', [
    'value' => $cleaned,
    'summary' => $node->get('body')->summary,
    'format' => $new_format,
  ]);
  $node->setNewRevision(TRUE);
  $node->setRevisionLogMessage('Removed Word paste residue from the biography and moved it to the standard text format.');
  $node->setRevisionCreationTime(\Drupal::time()->getRequestTime());
  $node->save();

  printf(
    "%-5d %-22s %s  %d -> %d chars\n",
    $node->id(),
    $node->label(),
    $format === $new_format ? $format : "$format -> $new_format",
    strlen($original),
    strlen($cleaned)
  );
  $changed++;
}

printf("Done. %d biograph%s cleaned.\n", $changed, $changed === 1 ? 'y' : 'ies');
