<?php

declare(strict_types=1);

namespace Drupal\mcc_migration;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\focal_point\FocalPointManagerInterface;

/**
 * Converts legacy Manual Crop rectangles into Focal Point crop entities.
 *
 * The D7 site used Manual Crop, which stored an explicit crop rectangle per
 * file per image style in the `manualcrop` table. D11 uses Crop API + Focal
 * Point, which stores a single anchor point per file and re-derives every
 * aspect ratio from it.
 *
 * Every cropped file on the legacy site has exactly one rectangle, so the
 * conversion loses nothing: each rectangle collapses to its own centre,
 * expressed as a percentage of the original image.
 *
 * This runs automatically after the mcc_files migration and is safe to repeat
 * — it keys crops by file URI, so re-imports update the existing crop rather
 * than accumulating duplicates.
 */
class ManualCropToFocalPoint {

  /**
   * Rectangles smaller than this on either edge are treated as stray clicks.
   *
   * The legacy crop UI recorded a handful of accidental 3x1 and 8x2 selections.
   * Anchoring an image to one of those is worse than leaving it centred.
   */
  protected const MIN_CREDIBLE_EDGE = 20;

  public function __construct(
    protected readonly Connection $database,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly FileSystemInterface $fileSystem,
    protected readonly FocalPointManagerInterface $focalPointManager,
    protected readonly LoggerChannelInterface $logger,
  ) {}

  /**
   * Applies legacy crop data as focal points.
   *
   * @return array
   *   Report keyed by outcome: converted, unchanged, stray_click, no_file,
   *   unreadable.
   */
  public function convert(): array {
    $report = [
      'converted' => 0,
      'unchanged' => 0,
      'deduplicated' => 0,
      'stray_click' => [],
      'no_file' => [],
      'unreadable' => [],
    ];

    $legacy = $this->legacyConnection();
    if (!$legacy || !$legacy->schema()->tableExists('manualcrop')) {
      $this->logger->notice('No legacy manualcrop table available; skipping focal point conversion.');
      return $report;
    }

    $crop_type = \Drupal::config('focal_point.settings')->get('crop_type') ?: 'focal_point';
    $report['deduplicated'] = $this->deduplicateCrops($crop_type);
    $file_storage = $this->entityTypeManager->getStorage('file');

    $rows = $legacy->query('SELECT fid, style_name, x, y, width, height FROM {manualcrop} ORDER BY fid')
      ->fetchAll();

    foreach ($rows as $row) {
      $fid = $this->resolveFileId((int) $row->fid);
      $file = $fid ? $file_storage->load($fid) : NULL;
      if (!$file) {
        $report['no_file'][] = (int) $row->fid;
        continue;
      }

      $path = $this->fileSystem->realpath($file->getFileUri());
      $size = ($path && file_exists($path)) ? @getimagesize($path) : FALSE;
      if (!$size || empty($size[0]) || empty($size[1])) {
        $report['unreadable'][] = $file->getFileUri();
        continue;
      }
      [$orig_width, $orig_height] = $size;

      if ($row->width < static::MIN_CREDIBLE_EDGE || $row->height < static::MIN_CREDIBLE_EDGE) {
        $report['stray_click'][] = sprintf(
          'fid %d (%s) %dx%d rect on a %dx%d image',
          $row->fid, $row->style_name, $row->width, $row->height, $orig_width, $orig_height
        );
        continue;
      }

      // Centre of the legacy rectangle as a percentage of the original image.
      $x = (int) round((($row->x + $row->width / 2) / $orig_width) * 100);
      $y = (int) round((($row->y + $row->height / 2) / $orig_height) * 100);
      $x = max(0, min(100, $x));
      $y = max(0, min(100, $y));

      $crop = $this->focalPointManager->getCropEntity($file, $crop_type);
      $before = $crop->anchor();

      // A re-import can hand the same URI a new file entity ID. Keep the crop
      // pointing at the current one so the reference does not go stale.
      if (!$crop->isNew() && (int) $crop->get('entity_id')->value !== (int) $file->id()) {
        $crop->set('entity_id', $file->id());
        $crop->set('entity_type', 'file');
        $crop->save();
      }

      $this->focalPointManager->saveCropEntity($x, $y, $orig_width, $orig_height, $crop);

      $after = $crop->anchor();
      if ($before === $after && !$crop->isNew()) {
        $report['unchanged']++;
      }
      else {
        $report['converted']++;
      }
    }

    $this->logger->notice(
      'Manual Crop -> Focal Point: @converted applied, @unchanged already current, @stray stray clicks skipped, @nofile without a file entity, @unreadable unreadable.',
      [
        '@converted' => $report['converted'],
        '@unchanged' => $report['unchanged'],
        '@stray' => count($report['stray_click']),
        '@nofile' => count($report['no_file']),
        '@unreadable' => count($report['unreadable']),
      ]
    );

    return $report;
  }

  /**
   * Collapses duplicate crop entities that share a file URI.
   *
   * Crop::findCrop() matches on URI with range(0, 1) and no sort order, so when
   * several crops share a URI it is undefined which one wins — the rendered
   * focal point can change between requests. Duplicates arrived here because
   * mcc_files forces D7 fids onto file entities, displacing the starter
   * content's files and leaving their crops pointing at URIs that later
   * belonged to something else.
   *
   * Keeps the crop that matches a live file entity, preferring the lowest ID
   * when several qualify, and deletes the rest.
   *
   * @return int
   *   Number of duplicate crops deleted.
   */
  protected function deduplicateCrops(string $crop_type): int {
    $query = $this->database->select('crop_field_data', 'c');
    $query->fields('c', ['uri']);
    $query->condition('type', $crop_type);
    $query->groupBy('uri');
    $query->having('COUNT(*) > :minimum', [':minimum' => 1]);
    $duplicate_uris = $query->execute()->fetchCol();

    if (!$duplicate_uris) {
      return 0;
    }

    $crop_storage = $this->entityTypeManager->getStorage('crop');
    $deleted = 0;

    foreach ($duplicate_uris as $uri) {
      $cids = $this->database->select('crop_field_data', 'c')
        ->fields('c', ['cid'])
        ->condition('type', $crop_type)
        ->condition('uri', $uri)
        ->orderBy('cid')
        ->execute()
        ->fetchCol();

      $crops = $crop_storage->loadMultiple($cids);

      // Prefer a crop whose entity_id still resolves to the file at this URI.
      $keep = NULL;
      foreach ($crops as $crop) {
        $file = $this->entityTypeManager->getStorage('file')->load($crop->get('entity_id')->value);
        if ($file && $file->getFileUri() === $uri) {
          $keep = $crop;
          break;
        }
      }
      $keep = $keep ?: reset($crops);

      foreach ($crops as $crop) {
        if ($crop->id() !== $keep->id()) {
          $crop->delete();
          $deleted++;
        }
      }
    }

    if ($deleted) {
      $this->logger->notice(
        'Removed @count duplicate focal point crops across @uris file URIs.',
        ['@count' => $deleted, '@uris' => count($duplicate_uris)]
      );
    }

    return $deleted;
  }

  /**
   * Returns the legacy D7 database connection, or NULL if unconfigured.
   */
  protected function legacyConnection(): ?Connection {
    foreach (['migrate', 'legacy'] as $key) {
      if (Database::getConnectionInfo($key)) {
        return Database::getConnection('default', $key);
      }
    }
    return NULL;
  }

  /**
   * Maps a legacy fid to its migrated file entity ID.
   *
   * mcc_files currently passes fids straight through, but going via the
   * migrate map keeps this correct if that ever stops being true.
   */
  protected function resolveFileId(int $legacy_fid): ?int {
    if (!$this->database->schema()->tableExists('migrate_map_mcc_files')) {
      return $legacy_fid;
    }
    $destid = $this->database->select('migrate_map_mcc_files', 'm')
      ->fields('m', ['destid1'])
      ->condition('sourceid1', $legacy_fid)
      ->execute()
      ->fetchField();

    return $destid ? (int) $destid : NULL;
  }

}
