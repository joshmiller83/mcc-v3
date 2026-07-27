<?php

namespace Drupal\mcc_core;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\node\NodeInterface;

/**
 * Turns `calendar_event` nodes into a month grid, ready for rendering.
 *
 * Shared by the screen calendar and the print sheet so the two can never
 * disagree about what is on in a month. The output is plain arrays — the
 * components decide how it looks; this class decides what it is.
 *
 * Two pieces of it are worth reading before changing anything.
 *
 * ## How many days an occurrence claims
 *
 * Not "start date through end date". Events migrated from the old Drupal 7
 * site frequently carry a nonsense end *time* (a 6:30pm Vacation Bible School
 * night whose stored end is 2:30pm the following afternoon), so trusting the
 * end date would smear half the calendar across two days each. Instead an
 * occurrence claims the day it starts on, plus one more day for every whole
 * 24 hours it lasts. A genuine three-day retreat entered as one long
 * occurrence still claims three days; a long evening does not.
 *
 * ## How multi-day events become bands
 *
 * Nothing in the data says "this is a multi-day event". The church enters
 * Vacation Bible School and the county fair as a Smart Date daily repeat — one
 * occurrence per evening — which is the natural way to type them and the way
 * every event in the migrated site is stored. So multi-day-ness is *derived*:
 * an event's claimed days are collected, split into maximal runs of
 * consecutive dates, and any run two days or longer becomes a band spanning
 * those columns. Runs are computed from every occurrence on the node, not just
 * the visible ones, so a band that starts in the previous month still knows it
 * is a continuation.
 */
class CalendarMonth {

  /**
   * Seconds in a day. Day indices below are integer counts of these.
   */
  const DAY = 86400;

  /**
   * Days from a run's length at which it stops being chips and becomes a band.
   */
  const BAND_MIN_DAYS = 2;

  public function __construct(
    protected EventContext $eventContext,
  ) {}

  /**
   * Builds the grid for one month.
   *
   * @param int $year
   *   Four-digit year.
   * @param int $month
   *   Month, 1-12.
   * @param array $options
   *   - show_adjacent_days: render the leading/trailing days from neighbouring
   *     months (TRUE) or leave those cells empty (FALSE). Default TRUE.
   *   - busy_threshold: how many events on one day before the print sheet
   *     switches that day to a condensed layout. Default 4.
   *
   * @return array
   *   Keys: year, month, month_label, weekdays, week_count, weeks, legend,
   *   standing, has_events, cache_tags.
   */
  public function build(int $year, int $month, array $options = []): array {
    $options += ['show_adjacent_days' => TRUE, 'busy_threshold' => 4];
    $tz = $this->eventContext->timezone();

    $first = new DrupalDateTime(sprintf('%04d-%02d-01 00:00:00', $year, $month), $tz);
    $days_in_month = (int) $first->format('t');
    $lead = (int) $first->format('w');
    $week_count = (int) ceil(($lead + $days_in_month) / 7);

    $grid_start = (clone $first)->modify('-' . $lead . ' days');
    $grid_end = (clone $grid_start)
      ->modify('+' . ($week_count * 7 - 1) . ' days')
      ->setTime(23, 59, 59);

    $grid_first_day = $this->dayIndex($grid_start->format('Y-m-d'));
    $grid_last_day = $grid_first_day + $week_count * 7 - 1;

    // Collect everything that touches the grid, as single-day entries keyed by
    // day index plus multi-day runs.
    $collected = $this->collect($grid_start, $grid_end, $grid_first_day, $grid_last_day, $tz);
    $singles = $collected['singles'];
    $runs = $collected['runs'];
    $legend = $collected['legend'];

    $this->markStanding($singles, $year, $month);

    $today = (new DrupalDateTime('now', $tz))->format('Y-m-d');
    $weeks = [];
    for ($w = 0; $w < $week_count; $w++) {
      $week_start = $grid_first_day + $w * 7;
      $bands = $this->packLanes($this->segmentsForWeek($runs, $week_start));
      $lane_count = $bands ? max(array_column($bands, 'lane')) + 1 : 0;

      $days = [];
      for ($i = 0; $i < 7; $i++) {
        $index = $week_start + $i;
        $date = $this->dateFromIndex($index);
        $day = new DrupalDateTime($date . ' 12:00:00', $tz);
        $in_month = (int) $day->format('n') === $month && (int) $day->format('Y') === $year;
        $blank = !$in_month && !$options['show_adjacent_days'];

        $events = $blank ? [] : ($singles[$index] ?? []);
        $days[] = [
          'column' => $i + 1,
          'day' => (int) $day->format('j'),
          'date' => $date,
          'label' => $day->format('l, F j'),
          'in_month' => $in_month,
          'is_today' => $date === $today,
          'blank' => $blank,
          'events' => array_values($events),
          'count' => count($events),
          'groups' => $this->groupByTime($events),
          'is_busy' => count($events) >= $options['busy_threshold'],
          'standing_count' => count(array_filter($events, fn($e) => $e['standing'])),
        ];
      }

      $weeks[] = [
        'days' => $days,
        'bands' => $bands,
        'lane_count' => $lane_count,
        // Row 1 holds the day numbers, then one row per band lane, then the
        // per-day event list.
        'singles_row' => $lane_count + 2,
      ];
    }

    // Order the legend the way editors ordered the vocabulary, so the sequence
    // stays meaningful as categories are added or renamed.
    uasort($legend, fn($a, $b) => [$a['weight'], $a['label']] <=> [$b['weight'], $b['label']]);

    return [
      'year' => $year,
      'month' => $month,
      'month_label' => $first->format('F Y'),
      'weekdays' => $this->weekdays(),
      'week_count' => $week_count,
      'weeks' => $weeks,
      'legend' => array_values($legend),
      'standing' => $this->standingClauses($singles, $year, $month),
      'has_events' => $singles !== [] || $runs !== [],
      'is_current_month' => $first->format('Y-m') === substr($today, 0, 7),
      'cache_tags' => $collected['cache_tags'],
      'density' => $this->density($weeks),
    ];
  }

  /**
   * Builds a single Sunday-to-Saturday week.
   *
   * The front page's "This week at MCC" panel is the month grid's own week row,
   * so it is built from the same collect / segment / lane-pack steps rather
   * than a parallel query. Recolour a Mission Category and the front page moves
   * with /calendar in the same breath; derive multi-day bands differently and
   * both change together or neither does.
   *
   * @param \Drupal\Core\Datetime\DrupalDateTime|null $date
   *   Any day in the week wanted. Defaults to today.
   * @param array $options
   *   - busy_threshold: as build().
   *
   * @return array
   *   Keys: week (the `mcc-calendar-week` prop), agenda, weekdays, legend,
   *   range_label, start, end, has_events, cache_tags.
   */
  public function week(?DrupalDateTime $date = NULL, array $options = []): array {
    $options += ['busy_threshold' => 4];
    $tz = $this->eventContext->timezone();

    $date = $date ? clone $date : new DrupalDateTime('now', $tz);
    $start = (clone $date)->setTime(0, 0, 0);
    // Columns are Sunday-first, matching the month grid.
    $start->modify('-' . (int) $start->format('w') . ' days');
    $end = (clone $start)->modify('+6 days')->setTime(23, 59, 59);

    $week_start = $this->dayIndex($start->format('Y-m-d'));
    $collected = $this->collect($start, $end, $week_start, $week_start + 6, $tz);

    $bands = $this->packLanes($this->segmentsForWeek($collected['runs'], $week_start));
    $lane_count = $bands ? max(array_column($bands, 'lane')) + 1 : 0;

    $today = (new DrupalDateTime('now', $tz))->format('Y-m-d');
    $days = [];
    for ($i = 0; $i < 7; $i++) {
      $index = $week_start + $i;
      $ymd = $this->dateFromIndex($index);
      $day = new DrupalDateTime($ymd . ' 12:00:00', $tz);
      $events = $collected['singles'][$index] ?? [];

      $days[] = [
        'column' => $i + 1,
        'day' => (int) $day->format('j'),
        'date' => $ymd,
        'label' => $day->format('l, F j'),
        // Every day of the week is the subject here. A week straddling a month
        // boundary must not grey out half of itself the way the month grid
        // greys the days either side of it.
        'in_month' => TRUE,
        'is_today' => $ymd === $today,
        'blank' => FALSE,
        'events' => array_values($events),
        'count' => count($events),
        'groups' => $this->groupByTime($events),
        'is_busy' => count($events) >= $options['busy_threshold'],
        // "Standing" is a statement about a whole month, so a single week has
        // no basis on which to claim it.
        'standing_count' => 0,
      ];
    }

    $week = [
      'days' => $days,
      'bands' => $bands,
      'lane_count' => $lane_count,
      'singles_row' => $lane_count + 2,
    ];

    $legend = $collected['legend'];
    uasort($legend, fn($a, $b) => [$a['weight'], $a['label']] <=> [$b['weight'], $b['label']]);

    return [
      'week' => $week,
      'agenda' => $this->agenda(['weeks' => [$week]]),
      'weekdays' => $this->weekdays(),
      'legend' => array_values($legend),
      'range_label' => $this->rangeLabel($start, $end),
      'start' => $start->format('Y-m-d'),
      'end' => $end->format('Y-m-d'),
      'has_events' => $collected['singles'] !== [] || $collected['runs'] !== [],
      'cache_tags' => $collected['cache_tags'],
    ];
  }

  /**
   * "July 26 – August 1" — the month is written once when it doesn't change.
   */
  protected function rangeLabel(DrupalDateTime $start, DrupalDateTime $end): string {
    return $start->format('F j') . ' – ' . $end->format(
      $start->format('F') === $end->format('F') ? 'j' : 'F j'
    );
  }

  /**
   * Weekday column headings, long and abbreviated.
   */
  public function weekdays(): array {
    return [
      ['long' => 'Sunday', 'short' => 'Sun'],
      ['long' => 'Monday', 'short' => 'Mon'],
      ['long' => 'Tuesday', 'short' => 'Tue'],
      ['long' => 'Wednesday', 'short' => 'Wed'],
      ['long' => 'Thursday', 'short' => 'Thu'],
      ['long' => 'Friday', 'short' => 'Fri'],
      ['long' => 'Saturday', 'short' => 'Sat'],
    ];
  }

  /**
   * Flattens a built grid into a day-by-day agenda.
   *
   * Phone screens can't carry a seven-column grid without either scrolling
   * sideways or hiding events, and hiding events is the thing this calendar
   * exists to stop doing. Below the grid's breakpoint the same data is listed
   * one day at a time instead — every event, same colours, same markers.
   *
   * Multi-day bands are repeated on each day they cover, which is what an
   * agenda reader expects: "what is on today" includes the fair that runs all
   * week.
   *
   * @param array $grid
   *   The return value of build().
   *
   * @return array
   *   In-month days that have something on, each with a list of entries.
   */
  public function agenda(array $grid): array {
    $days = [];

    foreach ($grid['weeks'] as $week) {
      foreach ($week['days'] as $day) {
        if (!$day['in_month'] || $day['blank']) {
          continue;
        }

        $entries = [];
        foreach ($week['bands'] as $band) {
          $covers = $day['column'] >= $band['column_start']
            && $day['column'] < $band['column_start'] + $band['span'];
          if ($covers) {
            $entries[] = [
              'title' => $band['title'],
              'url' => $band['url'],
              'time' => $band['time'],
              'time_label' => $band['time_label'],
              'color' => $band['color'],
              'shape' => $band['shape'],
              'category' => $band['category'],
              'range' => $band['range'],
              'is_band' => TRUE,
              'description' => '',
            ];
          }
        }
        foreach ($day['events'] as $event) {
          $entries[] = $event + ['range' => '', 'is_band' => FALSE];
        }

        if ($entries) {
          $days[] = [
            'date' => $day['date'],
            'label' => $day['label'],
            'is_today' => $day['is_today'],
            'add_url' => $day['add_url'] ?? '',
            'entries' => $entries,
          ];
        }
      }
    }

    return $days;
  }

  /**
   * Loads every event touching the grid and sorts it into singles and runs.
   *
   * @return array
   *   ['singles' => [day index => entries], 'runs' => [...], 'legend' => [...],
   *   'cache_tags' => [...]].
   */
  protected function collect(DrupalDateTime $grid_start, DrupalDateTime $grid_end, int $grid_first_day, int $grid_last_day, \DateTimeZone $tz): array {
    $singles = [];
    $runs = [];
    $legend = [];
    $cache_tags = [];

    foreach ($this->eventContext->findEventsInRange($grid_start->getTimestamp(), $grid_end->getTimestamp()) as $node) {
      $category = $this->eventContext->category($node) ?? $this->eventContext->fallbackCategory();
      if ($category['tid']) {
        $legend[$category['tid']] = [
          'label' => $category['label'],
          'color' => $category['color'],
          'shape' => $category['shape'],
          'weight' => $category['weight'],
        ];
        // A category's colour lives on its term, so recolouring one has to
        // invalidate the calendar too.
        $cache_tags[] = 'taxonomy_term:' . $category['tid'];
      }

      foreach ($this->runsForNode($node, $tz) as $run) {
        if ($run['to'] < $grid_first_day || $run['from'] > $grid_last_day) {
          continue;
        }
        if ($run['length'] >= self::BAND_MIN_DAYS) {
          $runs[] = $this->bandEntry($node, $category, $run, $tz);
          continue;
        }
        $singles[$run['from']][] = $this->chipEntry($node, $category, $run, $tz);
      }
    }

    // All-day first, then by start time, then alphabetically so equal-time
    // events keep a stable order between the screen and the printed sheet.
    foreach ($singles as &$entries) {
      usort($entries, fn($a, $b) => [$a['sort'], $a['title']] <=> [$b['sort'], $b['title']]);
    }
    unset($entries);


    return [
      'singles' => $singles,
      'runs' => $runs,
      'legend' => $legend,
      'cache_tags' => array_values(array_unique($cache_tags)),
    ];
  }

  /**
   * Splits one node's occurrences into maximal runs of consecutive days.
   *
   * Every occurrence on the node is considered, not only the visible ones, so
   * `from`/`to` describe the real extent of a run and the grid can tell a band
   * that starts mid-week from one continuing out of the previous month.
   *
   * @return array
   *   Runs, each ['from', 'to', 'length', 'occurrences', 'first_start_ts',
   *   'all_day', 'same_time'].
   */
  protected function runsForNode(NodeInterface $node, \DateTimeZone $tz): array {
    $claimed = [];
    foreach ($node->get('field_event_date') as $item) {
      $start_ts = (int) $item->value;
      $end_ts = (int) $item->end_value;
      $all_day = $this->eventContext->isAllDay($start_ts, $end_ts, $tz);
      $start = DrupalDateTime::createFromTimestamp($start_ts, $tz);
      $first_day = $this->dayIndex($start->format('Y-m-d'));
      $span = 1 + intdiv(max(0, $end_ts - $start_ts), self::DAY);

      for ($offset = 0; $offset < $span; $offset++) {
        $index = $first_day + $offset;
        // Two occurrences can claim the same day when one runs long; the
        // earlier start is the one the day should be labelled with.
        if (isset($claimed[$index]) && $claimed[$index]['start_ts'] <= $start_ts) {
          continue;
        }
        $claimed[$index] = [
          'start_ts' => $start_ts,
          'end_ts' => $end_ts,
          'all_day' => $all_day,
          'time' => $all_day ? '' : $start->format('H:i'),
        ];
      }
    }
    if (!$claimed) {
      return [];
    }
    ksort($claimed);

    $runs = [];
    $current = NULL;
    foreach ($claimed as $index => $info) {
      if ($current !== NULL && $index === $current['to'] + 1) {
        $current['to'] = $index;
        $current['days'][$index] = $info;
        continue;
      }
      if ($current !== NULL) {
        $runs[] = $current;
      }
      $current = ['from' => $index, 'to' => $index, 'days' => [$index => $info]];
    }
    $runs[] = $current;

    return array_map(function (array $run): array {
      $times = array_column($run['days'], 'time');
      $starts = array_column($run['days'], 'start_ts');
      // The occurrence the run is labelled with: the one that starts earliest.
      $first = $run['days'][$run['from']];
      foreach ($run['days'] as $day) {
        if ($day['start_ts'] < $first['start_ts']) {
          $first = $day;
        }
      }
      return [
        'from' => $run['from'],
        'to' => $run['to'],
        'length' => $run['to'] - $run['from'] + 1,
        'occurrences' => count(array_unique($starts)),
        'first_start_ts' => $first['start_ts'],
        'first_end_ts' => $first['end_ts'],
        'all_day' => !in_array(FALSE, array_column($run['days'], 'all_day'), TRUE),
        'same_time' => count(array_unique($times)) === 1,
      ];
    }, $runs);
  }

  /**
   * A single-day event, as the grid renders it.
   */
  protected function chipEntry(NodeInterface $node, array $category, array $run, \DateTimeZone $tz): array {
    $described = $this->eventContext->describeOccurrence(
      $run['first_start_ts'],
      $run['first_end_ts'],
      $tz
    );
    $start = DrupalDateTime::createFromTimestamp($run['first_start_ts'], $tz);

    return [
      'title' => $node->label(),
      'url' => $node->toUrl()->toString(),
      'time' => $run['all_day'] ? '' : $this->eventContext->shortTime($start),
      'time_label' => $run['all_day'] ? 'All day' : $described['label'],
      'all_day' => $run['all_day'],
      'color' => $category['color'],
      'shape' => $category['shape'],
      'category' => $category['label'],
      'description' => $this->summary($node),
      // Grouping key for the print sheet's condensed layouts, and the sort key
      // that puts all-day events first.
      'slot' => $run['all_day'] ? '' : $start->format('H:i'),
      'sort' => $run['all_day'] ? 0 : $run['first_start_ts'],
      'weekday' => (int) $start->format('w'),
      'day_index' => $run['from'],
      'nid' => (int) $node->id(),
      'standing' => FALSE,
    ];
  }

  /**
   * A multi-day run, before it is cut into per-week segments.
   */
  protected function bandEntry(NodeInterface $node, array $category, array $run, \DateTimeZone $tz): array {
    $start = DrupalDateTime::createFromTimestamp($run['first_start_ts'], $tz);
    $from = new DrupalDateTime($this->dateFromIndex($run['from']) . ' 12:00:00', $tz);
    $to = new DrupalDateTime($this->dateFromIndex($run['to']) . ' 12:00:00', $tz);

    // "6p daily" when every evening of the run starts at the same time — that
    // is what the office writes on the paper calendar. Two days is not "daily",
    // and the band itself already says which two.
    $time = '';
    if (!$run['all_day']) {
      $time = $this->eventContext->shortTime($start);
      if ($run['length'] > 2 && $run['occurrences'] > 1 && $run['same_time']) {
        $time .= ' daily';
      }
    }

    $range = $from->format('M j') . ' – ' . $to->format(
      $from->format('M') === $to->format('M') ? 'j' : 'M j'
    );

    return [
      'title' => $node->label(),
      'url' => $node->toUrl()->toString(),
      'time' => $time,
      'time_label' => $range . ($time ? ' · ' . $time : ''),
      'color' => $category['color'],
      'shape' => $category['shape'],
      'category' => $category['label'],
      'from' => $run['from'],
      'to' => $run['to'],
      'length' => $run['length'],
      'range' => $range,
      'nid' => (int) $node->id(),
    ];
  }

  /**
   * Cuts the runs that overlap one week into positioned segments.
   *
   * Comparisons are all on integer day indices, so nothing here can be tripped
   * up by a daylight-saving boundary mid-month.
   */
  protected function segmentsForWeek(array $runs, int $week_start): array {
    $week_end = $week_start + 6;
    $segments = [];

    foreach ($runs as $run) {
      $from = max($run['from'], $week_start);
      $to = min($run['to'], $week_end);
      if ($from > $to) {
        continue;
      }
      $is_start = $from === $run['from'];
      $continues = $to < $run['to'];
      $segments[] = $run + [
        'column_start' => $from - $week_start + 1,
        'span' => $to - $from + 1,
        'is_start' => $is_start,
        'continues' => $continues,
        // The time is written once, on the segment that starts the run.
        'segment_time' => $is_start ? $run['time'] : '',
      ];
    }

    // Longest-first within a column so wide bands settle into the top lanes.
    usort($segments, fn($a, $b) => [$a['column_start'], -$a['span'], $a['title']]
      <=> [$b['column_start'], -$b['span'], $b['title']]);

    return $segments;
  }

  /**
   * Greedy lane packing: first lane with no column overlap wins.
   */
  protected function packLanes(array $segments): array {
    $lanes = [];
    foreach ($segments as &$segment) {
      $lane = 0;
      while (isset($lanes[$lane]) && $this->laneOccupied($lanes[$lane], $segment)) {
        $lane++;
      }
      $lanes[$lane][] = $segment;
      $segment['lane'] = $lane;
      $segment['row'] = $lane + 2;
    }
    return $segments;
  }

  /**
   * Whether any segment already in a lane overlaps the candidate's columns.
   */
  protected function laneOccupied(array $lane, array $candidate): bool {
    foreach ($lane as $placed) {
      $placed_end = $placed['column_start'] + $placed['span'] - 1;
      $candidate_end = $candidate['column_start'] + $candidate['span'] - 1;
      if ($candidate['column_start'] <= $placed_end && $placed['column_start'] <= $candidate_end) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Buckets a day's events by start time.
   *
   * The print sheet's busy days print the time once per slot rather than once
   * per event, which is what makes a seven-event Sunday fit an inch-wide cell.
   */
  protected function groupByTime(array $events): array {
    $buckets = [];
    foreach ($events as $event) {
      $buckets[$event['slot']][] = $event;
    }
    ksort($buckets);

    $groups = [];
    foreach ($buckets as $slot => $items) {
      $groups[] = [
        'time' => $items[0]['time'],
        'all_day' => $slot === '',
        'items' => $items,
      ];
    }
    return $groups;
  }

  /**
   * Flags the events that repeat every week, for the print sheet's digest.
   *
   * "Standing" means the event lands on the same weekday on every one of that
   * weekday's dates in the month — the Sunday morning schedule, the Wednesday
   * Bible studies. Those are the lines the office already knows by heart and
   * the first candidates to lift out of the grid when a month is tight.
   */
  protected function markStanding(array &$singles, int $year, int $month): void {
    $weekday_dates = array_fill(0, 7, 0);
    $days_in_month = (int) (new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month)))->format('t');
    for ($day = 1; $day <= $days_in_month; $day++) {
      $weekday = (int) (new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day)))->format('w');
      $weekday_dates[$weekday]++;
    }

    // Count in-month appearances of each node on each weekday.
    $seen = [];
    foreach ($singles as $entries) {
      foreach ($entries as $entry) {
        $date = $this->dateFromIndex($entry['day_index']);
        if (substr($date, 0, 7) !== sprintf('%04d-%02d', $year, $month)) {
          continue;
        }
        $key = $entry['nid'] . ':' . $entry['weekday'] . ':' . $entry['slot'];
        $seen[$key] = ($seen[$key] ?? 0) + 1;
      }
    }

    foreach ($singles as &$entries) {
      foreach ($entries as &$entry) {
        $key = $entry['nid'] . ':' . $entry['weekday'] . ':' . $entry['slot'];
        $entry['standing'] = ($seen[$key] ?? 0) >= $weekday_dates[$entry['weekday']]
          && $weekday_dates[$entry['weekday']] > 1;
      }
    }
  }

  /**
   * The "Every week" summary the print sheet's digest layout puts in its header.
   *
   * One clause per weekday, each listing that weekday's standing times and what
   * happens at them.
   *
   * @return array
   *   ['Sundays 8:30a Worship Team Rehearsal · 9:30a …', …]
   */
  protected function standingClauses(array $singles, int $year, int $month): array {
    $by_weekday = [];
    foreach ($singles as $entries) {
      foreach ($entries as $entry) {
        if (!$entry['standing']) {
          continue;
        }
        $by_weekday[$entry['weekday']][$entry['slot']][$entry['nid']] = $entry;
      }
    }
    ksort($by_weekday);

    $weekdays = $this->weekdays();
    $clauses = [];
    foreach ($by_weekday as $weekday => $slots) {
      ksort($slots);
      $parts = [];
      foreach ($slots as $items) {
        $items = array_values($items);
        $titles = array_map(fn($item) => $item['title'], $items);
        $parts[] = trim(($items[0]['time'] ?? '') . ' ' . implode(', ', $titles));
      }
      $clauses[] = $weekdays[$weekday]['long'] . 's ' . implode(' · ', $parts);
    }
    return $clauses;
  }

  /**
   * The tallest week in the month, in printed lines.
   *
   * The print sheet divides a fixed height between the weeks, so this is what
   * tells it how hard it has to squeeze. Returned as raw counts rather than a
   * font size so the decision stays in CSS.
   */
  protected function density(array $weeks): array {
    $worst_lines = 0;
    $worst_lanes = 0;
    $worst_combined = 0;
    foreach ($weeks as $week) {
      $lines = 0;
      foreach ($week['days'] as $day) {
        $lines = max($lines, $day['count']);
      }
      $worst_lines = max($worst_lines, $lines);
      $worst_lanes = max($worst_lanes, $week['lane_count']);
      $worst_combined = max($worst_combined, $lines + $week['lane_count']);
    }
    return [
      'max_events_in_a_day' => $worst_lines,
      'max_lanes' => $worst_lanes,
      'max_rows_in_a_week' => $worst_combined,
    ];
  }

  /**
   * A short plain-text summary of an event, for agenda and detail listings.
   */
  protected function summary(NodeInterface $node): string {
    if (!$node->hasField('field_content') || $node->get('field_content')->isEmpty()) {
      return '';
    }
    $plain = trim(strip_tags((string) $node->get('field_content')->value));
    return $plain === '' ? '' : Unicode::truncate($plain, 160, TRUE, TRUE);
  }

  /**
   * Integer day number for a local `Y-m-d`, used as the grid's coordinate.
   *
   * Deliberately parsed as UTC: these are calendar-day labels being turned into
   * a comparable integer, not moments in time, so no timezone offset should
   * enter into it.
   */
  protected function dayIndex(string $ymd): int {
    return intdiv((int) strtotime($ymd . ' 00:00:00 UTC'), self::DAY);
  }

  /**
   * Inverse of dayIndex().
   */
  protected function dateFromIndex(int $index): string {
    return gmdate('Y-m-d', $index * self::DAY);
  }

}
