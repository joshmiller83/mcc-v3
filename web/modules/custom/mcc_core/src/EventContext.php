<?php

namespace Drupal\mcc_core;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\node\NodeInterface;

/**
 * Shared lookups for calendar events.
 *
 * The monthly calendar, the print sheet and the event detail page all need the
 * same three things: what a Mission Category looks like, which events fall in a
 * date window, and how to describe a single occurrence in words. Keeping them
 * here means a category's colour, marker and icon are resolved in exactly one
 * place.
 */
class EventContext {

  /**
   * Colour used for events whose category has no colour set (or no category).
   *
   * A muted warm grey: visibly "unclassified" without looking broken.
   */
  const FALLBACK_COLOR = '#6B6257';

  /**
   * Marker used when a category has no shape set.
   */
  const FALLBACK_SHAPE = 'circle';

  /**
   * An occurrence counts as all-day when it starts at midnight and runs at
   * least this long.
   *
   * Smart Date writes 23:59 for an all-day event, but events migrated from the
   * old Drupal 7 site landed on 23:45 and similar, so an exact end-time match
   * would mislabel them. 23 hours is comfortably longer than any real timed
   * event the church schedules.
   */
  const ALL_DAY_SECONDS = 82800;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ConfigFactoryInterface $configFactory,
    protected FileUrlGeneratorInterface $fileUrlGenerator,
  ) {}

  /**
   * The site's configured timezone, used for all day bucketing.
   */
  public function timezone(): \DateTimeZone {
    $tz = $this->configFactory->get('system.date')->get('timezone.default');
    return new \DateTimeZone($tz ?: 'America/New_York');
  }

  /**
   * Presentation data for an event's Mission Category.
   *
   * The colour, marker shape and icon are all fields on the taxonomy term, so
   * adding or restyling a category never requires a code change.
   *
   * @return array|null
   *   ['tid', 'label', 'color', 'shape', 'url', 'icon_url', 'weight'], or NULL
   *   when the event has no category.
   */
  public function category(NodeInterface $node): ?array {
    if (!$node->hasField('field_mission_category') || $node->get('field_mission_category')->isEmpty()) {
      return NULL;
    }
    $term = $node->get('field_mission_category')->entity;
    if (!$term) {
      return NULL;
    }

    return [
      'tid' => (int) $term->id(),
      'label' => $term->label(),
      'color' => $this->termColor($term),
      'shape' => $this->termShape($term),
      'url' => $term->toUrl()->toString(),
      'icon_url' => $this->iconUrl($term),
      'weight' => (int) $term->getWeight(),
    ];
  }

  /**
   * The category styling used for events with no category at all.
   */
  public function fallbackCategory(): array {
    return [
      'tid' => 0,
      'label' => 'Other',
      'color' => self::FALLBACK_COLOR,
      'shape' => self::FALLBACK_SHAPE,
      'url' => NULL,
      'icon_url' => NULL,
      'weight' => 999,
    ];
  }

  /**
   * A term's hex colour, normalised to `#rrggbb`.
   */
  protected function termColor($term): string {
    if (!$term->hasField('field_category_color') || $term->get('field_category_color')->isEmpty()) {
      return self::FALLBACK_COLOR;
    }
    $value = trim((string) $term->get('field_category_color')->color);
    // color_field's storage format is configurable, so accept it with or
    // without the leading hash rather than depending on one setting.
    $value = ltrim($value, '#');
    return preg_match('/^[0-9A-Fa-f]{6}$/', $value) ? '#' . strtoupper($value) : self::FALLBACK_COLOR;
  }

  /**
   * A term's marker shape key.
   */
  protected function termShape($term): string {
    if (!$term->hasField('field_marker_shape') || $term->get('field_marker_shape')->isEmpty()) {
      return self::FALLBACK_SHAPE;
    }
    return (string) $term->get('field_marker_shape')->value;
  }

  /**
   * Resolves a term's icon media to a plain file URL.
   *
   * Returned as a URL rather than inline markup because the icons are rendered
   * through CSS `mask-image`, which both tints them with the category colour
   * and avoids injecting editor-uploaded SVG markup into the page.
   */
  protected function iconUrl($term): ?string {
    if (!$term->hasField('field_icon') || $term->get('field_icon')->isEmpty()) {
      return NULL;
    }
    $media = $term->get('field_icon')->entity;
    if (!$media || !$media->hasField('field_media_svg_image')) {
      return NULL;
    }
    $file = $media->get('field_media_svg_image')->entity;
    return $file ? $this->fileUrlGenerator->generateString($file->getFileUri()) : NULL;
  }

  /**
   * Published events with at least one occurrence overlapping a window.
   *
   * Smart Date stores one row per occurrence, so this deliberately matches on
   * the field's own start/end columns rather than trying to expand recurrence
   * rules by hand.
   *
   * @return \Drupal\node\NodeInterface[]
   *   Loaded event nodes, keyed by node id.
   */
  public function findEventsInRange(int $start_ts, int $end_ts, ?int $exclude_nid = NULL): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'calendar_event')
      ->condition('status', 1)
      ->condition('field_event_date.value', $end_ts, '<=')
      ->condition('field_event_date.end_value', $start_ts, '>=');
    if ($exclude_nid !== NULL) {
      $query->condition('nid', $exclude_nid, '<>');
    }
    $nids = $query->execute();

    return $nids ? $storage->loadMultiple($nids) : [];
  }

  /**
   * Describes a single occurrence in human terms.
   *
   * @return array
   *   ['all_day', 'multi_day', 'label', 'short'] where `label` is the full
   *   description and `short` is the compact start time the calendar grid uses
   *   (empty when all day).
   */
  public function describeOccurrence(int $start_ts, int $end_ts, ?\DateTimeZone $tz = NULL): array {
    $tz = $tz ?: $this->timezone();
    $start = DrupalDateTime::createFromTimestamp($start_ts, $tz);
    $end = DrupalDateTime::createFromTimestamp($end_ts, $tz);

    $all_day = $this->isAllDay($start_ts, $end_ts, $tz);
    $multi_day = $start->format('Y-m-d') !== $end->format('Y-m-d');

    if ($all_day) {
      $label = $multi_day
        ? $start->format('M j') . ' – ' . $end->format('M j') . ' · All day'
        : 'All day';
    }
    elseif ($multi_day) {
      $label = $start->format('M j, g:i A') . ' – ' . $end->format('M j, g:i A');
    }
    else {
      $label = $start->format('g:i A') . ' – ' . $end->format('g:i A');
    }

    return [
      'all_day' => $all_day,
      'multi_day' => $multi_day,
      'label' => $label,
      'short' => $all_day ? '' : $this->shortTime($start),
    ];
  }

  /**
   * Whether an occurrence should read as "all day".
   */
  public function isAllDay(int $start_ts, int $end_ts, ?\DateTimeZone $tz = NULL): bool {
    $tz = $tz ?: $this->timezone();
    $start = DrupalDateTime::createFromTimestamp($start_ts, $tz);
    return $start->format('H:i') === '00:00' && ($end_ts - $start_ts) >= self::ALL_DAY_SECONDS;
  }

  /**
   * The compact time format the calendar grid uses: 9a, 8:30a, 7p, 6:45p.
   *
   * Grid cells are barely an inch wide on paper, so times are written as short
   * as they can be read: no leading zero, no ":00", single-letter meridiem.
   */
  public function shortTime(DrupalDateTime $time): string {
    $minutes = (int) $time->format('i');
    return $time->format('g')
      . ($minutes ? ':' . $time->format('i') : '')
      . substr(strtolower($time->format('a')), 0, 1);
  }

}
