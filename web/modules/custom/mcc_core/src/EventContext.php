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
 * Both the monthly calendar (MccCalendarController) and the event detail page
 * (mcc_theme_preprocess_node) need the same three things: what a Mission
 * Category looks like, which events fall in a date window, and how to describe
 * a single occurrence in words. Keeping them here means a category's colour and
 * icon are resolved in exactly one place.
 */
class EventContext {

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
   * The accent colour and icon are fields on the taxonomy term, so adding or
   * restyling a category never requires a code change.
   *
   * @return array|null
   *   ['tid', 'label', 'accent', 'url', 'icon_url', 'weight'], or NULL when the
   *   event has no category.
   */
  public function category(NodeInterface $node): ?array {
    if (!$node->hasField('field_mission_category') || $node->get('field_mission_category')->isEmpty()) {
      return NULL;
    }
    $term = $node->get('field_mission_category')->entity;
    if (!$term) {
      return NULL;
    }

    $accent = 'default';
    if ($term->hasField('field_accent_color') && !$term->get('field_accent_color')->isEmpty()) {
      $accent = $term->get('field_accent_color')->value;
    }

    return [
      'tid' => (int) $term->id(),
      'label' => $term->label(),
      'accent' => $accent,
      'url' => $term->toUrl()->toString(),
      'icon_url' => $this->iconUrl($term),
      'weight' => (int) $term->getWeight(),
    ];
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
   *   description and `short` is just the start time (empty when all day).
   */
  public function describeOccurrence(int $start_ts, int $end_ts, ?\DateTimeZone $tz = NULL): array {
    $tz = $tz ?: $this->timezone();
    $start = DrupalDateTime::createFromTimestamp($start_ts, $tz);
    $end = DrupalDateTime::createFromTimestamp($end_ts, $tz);

    // An event marked "all day" spans a full day with no meaningful time.
    $all_day = $start->format('H:i') === '00:00' && $end->format('H:i') === '23:59';
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
      'short' => $all_day ? '' : $start->format('g:i A'),
    ];
  }

}
