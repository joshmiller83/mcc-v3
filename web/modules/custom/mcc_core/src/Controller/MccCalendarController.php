<?php

namespace Drupal\mcc_core\Controller;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Url;
use Drupal\mcc_core\EventContext;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Renders the public monthly calendar at /calendar.
 *
 * Builds a Sunday-start month grid from `calendar_event` nodes, reading
 * occurrences from the Smart Date `field_event_date` field. Month navigation
 * is driven by `?year=` and `?month=` query parameters so the page stays
 * fully server-rendered and cacheable.
 */
class MccCalendarController extends ControllerBase {

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Shared event lookups (categories, date-range queries, time labels).
   *
   * @var \Drupal\mcc_core\EventContext
   */
  protected $eventContext;

  /**
   * Constructs the calendar controller.
   */
  public function __construct(RequestStack $request_stack, EventContext $event_context) {
    $this->requestStack = $request_stack;
    $this->eventContext = $event_context;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('mcc_core.event_context')
    );
  }

  /**
   * Builds the calendar page render array.
   */
  public function view() {
    $tz = $this->eventContext->timezone();
    $request = $this->requestStack->getCurrentRequest();
    $today = new DrupalDateTime('now', $tz);

    // Resolve the requested month, falling back to the current month. Guard
    // against out-of-range input so the grid maths never break.
    $year = (int) $request->query->get('year', $today->format('Y'));
    $month = (int) $request->query->get('month', $today->format('n'));
    if ($month < 1 || $month > 12) {
      $month = (int) $today->format('n');
    }
    if ($year < 1970 || $year > 2200) {
      $year = (int) $today->format('Y');
    }

    // Anchor dates for the visible month.
    $first_of_month = new DrupalDateTime(sprintf('%04d-%02d-01 00:00:00', $year, $month), $tz);
    $last_of_month = (clone $first_of_month)->modify('last day of this month')->setTime(23, 59, 59);

    // Expand to a Sunday-start / Saturday-end grid so leading and trailing
    // days from adjacent months are shown (and their events too).
    $grid_start = (clone $first_of_month)->modify('-' . (int) $first_of_month->format('w') . ' days');
    $grid_end = (clone $last_of_month)->modify('+' . (6 - (int) $last_of_month->format('w')) . ' days')->setTime(23, 59, 59);

    // Bucket every occurrence that touches the visible grid by calendar day.
    $legend = [];
    $by_date = $this->collectOccurrences($grid_start, $grid_end, $tz, $legend);
    // Order the legend the way editors ordered the vocabulary itself, so the
    // sequence stays meaningful as categories are added or renamed.
    uasort($legend, fn($a, $b) => [$a['weight'], $a['label']] <=> [$b['weight'], $b['label']]);
    $legend = array_map(
      fn($entry) => ['accent' => $entry['accent'], 'label' => $entry['label']],
      array_values($legend)
    );

    // Build the week rows.
    $today_key = $today->format('Y-m-d');
    $weeks = [];
    $cursor = clone $grid_start;
    while ($cursor <= $grid_end) {
      $week = [];
      for ($i = 0; $i < 7; $i++) {
        $key = $cursor->format('Y-m-d');
        $day_events = $by_date[$key] ?? [];
        $week[] = [
          'day' => (int) $cursor->format('j'),
          'date' => $key,
          'in_month' => (int) $cursor->format('n') === $month,
          'is_today' => $key === $today_key,
          'is_weekend' => in_array((int) $cursor->format('w'), [0, 6], TRUE),
          'events' => array_slice($day_events, 0, 3),
          'more' => max(0, count($day_events) - 3),
          'count' => count($day_events),
        ];
        $cursor->modify('+1 day');
      }
      $weeks[] = $week;
    }

    // Flat, chronological list of every grid day that has events, used to
    // render the expandable detail panels. Adjacent-month days are included so
    // their (clickable) cells have a panel to open too.
    $day_details = [];
    foreach ($by_date as $key => $events) {
      $dt = new DrupalDateTime($key . ' 12:00:00', $tz);
      $day_details[] = [
        'date' => $key,
        'label' => $dt->format('l, F j'),
        'events' => $events,
      ];
    }
    usort($day_details, fn($a, $b) => strcmp($a['date'], $b['date']));

    // Navigation targets.
    $prev = (clone $first_of_month)->modify('-1 month');
    $next = (clone $first_of_month)->modify('+1 month');

    return [
      '#type' => 'component',
      '#component' => 'mcc_theme:mcc-calendar-month',
      '#props' => [
        'month_label' => $first_of_month->format('F Y'),
        'weekdays' => ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
        'weeks' => $weeks,
        'day_details' => $day_details,
        'legend' => array_values($legend),
        'is_current_month' => (int) $today->format('n') === $month && (int) $today->format('Y') === $year,
        'nav' => [
          'prev_url' => $this->monthUrl((int) $prev->format('Y'), (int) $prev->format('n')),
          'prev_label' => $prev->format('F Y'),
          'next_url' => $this->monthUrl((int) $next->format('Y'), (int) $next->format('n')),
          'next_label' => $next->format('F Y'),
          'today_url' => Url::fromRoute('mcc_core.calendar')->toString(),
        ],
        'print_url' => Url::fromRoute('mcc_core.print_monthly', [], [
          'query' => ['year' => $year, 'month' => sprintf('%02d', $month)],
        ])->toString(),
      ],
      '#cache' => [
        // Category colours/labels are read from the vocabulary, so editing a
        // term has to invalidate the calendar too.
        'tags' => ['node_list:calendar_event', 'taxonomy_term_list:mcc_mission_category'],
        'contexts' => ['url.query_args:year', 'url.query_args:month'],
      ],
    ];
  }

  /**
   * Loads and buckets event occurrences overlapping the visible grid.
   *
   * @param \Drupal\Core\Datetime\DrupalDateTime $grid_start
   *   First day shown (00:00, site timezone).
   * @param \Drupal\Core\Datetime\DrupalDateTime $grid_end
   *   Last day shown (23:59, site timezone).
   * @param \DateTimeZone $tz
   *   Site timezone used for day bucketing.
   * @param array $legend
   *   Populated by reference with the categories actually present, keyed by
   *   term id: [7 => ['accent' => 'forest', 'label' => 'Worship', 'weight' => 0]].
   *
   * @return array
   *   Map of 'Y-m-d' => list of event entries, each already sorted by start.
   */
  protected function collectOccurrences(DrupalDateTime $grid_start, DrupalDateTime $grid_end, \DateTimeZone $tz, array &$legend): array {
    $start_ts = $grid_start->getTimestamp();
    $end_ts = $grid_end->getTimestamp();

    $by_date = [];

    foreach ($this->eventContext->findEventsInRange($start_ts, $end_ts) as $node) {
      $url = $node->toUrl()->toString();

      // The category carries its own colour, so adding or recolouring one is
      // an editorial change rather than a code change.
      $category = $this->eventContext->category($node);
      $accent = $category['accent'] ?? 'default';
      if ($category) {
        $legend[$category['tid']] = [
          'accent' => $category['accent'],
          'label' => $category['label'],
          'weight' => $category['weight'],
        ];
      }

      $description = '';
      if ($node->hasField('field_content') && !$node->get('field_content')->isEmpty()) {
        $plain = trim(strip_tags((string) $node->get('field_content')->value));
        $description = Unicode::truncate($plain, 160, TRUE, TRUE);
      }

      foreach ($node->get('field_event_date') as $item) {
        $occ_start_ts = (int) $item->value;
        $occ_end_ts = (int) $item->end_value;
        // Skip occurrences that fall entirely outside the visible grid.
        if ($occ_start_ts > $end_ts || $occ_end_ts < $start_ts) {
          continue;
        }

        $occurrence = $this->eventContext->describeOccurrence($occ_start_ts, $occ_end_ts, $tz);
        $occ_start = DrupalDateTime::createFromTimestamp($occ_start_ts, $tz);

        // Add the event to each visible day it covers.
        $day = DrupalDateTime::createFromTimestamp(max($occ_start_ts, $start_ts), $tz)->setTime(12, 0, 0);
        $last_day = DrupalDateTime::createFromTimestamp(min($occ_end_ts, $end_ts), $tz)->setTime(12, 0, 0);
        while ($day <= $last_day) {
          $key = $day->format('Y-m-d');
          $is_start = $key === $occ_start->format('Y-m-d');
          $by_date[$key][] = [
            'title' => $node->label(),
            'url' => $url,
            'accent' => $accent,
            'all_day' => $occurrence['all_day'],
            'is_start' => $is_start,
            'chip_time' => $is_start ? $occurrence['short'] : '',
            'time_label' => $occurrence['label'],
            'description' => $description,
            'sort' => $occurrence['all_day'] ? 0 : $occ_start_ts,
          ];
          $day->modify('+1 day');
        }
      }
    }

    // Sort each day's events: all-day first, then by start time, then title.
    foreach ($by_date as &$events) {
      usort($events, function ($a, $b) {
        return [$a['sort'], $a['title']] <=> [$b['sort'], $b['title']];
      });
    }

    return $by_date;
  }

  /**
   * Builds a URL to the calendar for a given month.
   */
  protected function monthUrl(int $year, int $month): string {
    return Url::fromRoute('mcc_core.calendar', [], [
      'query' => ['year' => $year, 'month' => sprintf('%02d', $month)],
    ])->toString();
  }

}
