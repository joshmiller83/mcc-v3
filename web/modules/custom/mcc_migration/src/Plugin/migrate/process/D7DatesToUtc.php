<?php

namespace Drupal\mcc_migration\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Marks Drupal 7 Date field values as UTC before Smart Date parsing.
 *
 * Drupal 7's Date module stored datetime values in UTC, but the downstream
 * `parse_dates` plugin runs each value through strtotime(), which interprets a
 * bare "Y-m-d H:i:s" string in PHP's default (site) timezone. On this site that
 * shifted every event by the America/New_York offset (e.g. a 10:30 AM service
 * stored as 14:30 UTC rendered as 1:30 PM). This plugin appends an explicit
 * " UTC" marker to each start/end value so the timestamps resolve correctly
 * regardless of the site timezone, then hands the standard Drupal 7 array on to
 * `parse_dates`.
 *
 * @MigrateProcessPlugin(
 *   id = "d7_dates_to_utc",
 *   handle_multiples = TRUE
 * )
 */
class D7DatesToUtc extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (!is_array($value)) {
      return $value;
    }
    foreach ($value as $delta => $item) {
      if (!is_array($item)) {
        continue;
      }
      // Only the start/end datetime strings need annotating; the rrule column
      // already uses UTC "Z" timestamps that strtotime() reads correctly.
      foreach (['value', 'value2', 'end_value'] as $key) {
        if (empty($item[$key]) || !is_string($item[$key])) {
          continue;
        }
        // Skip numeric timestamps and values that already declare a timezone.
        if (ctype_digit($item[$key]) || preg_match('/(UTC|Z|GMT|[+-]\d{2}:?\d{2})\s*$/', $item[$key])) {
          continue;
        }
        $item[$key] .= ' UTC';
      }
      $value[$delta] = $item;
    }
    return $value;
  }

}
