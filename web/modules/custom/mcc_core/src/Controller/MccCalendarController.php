<?php

namespace Drupal\mcc_core\Controller;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
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
   * Maps Mission Category term names to accent slugs used by the component CSS.
   *
   * @var array<string, string>
   */
  protected const CATEGORY_ACCENTS = [
    'worship' => 'worship',
    'serve' => 'serve',
    'fellowship' => 'fellowship',
    'youth' => 'youth',
    'equip' => 'equip',
    'lead' => 'lead',
  ];

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs the calendar controller.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, RequestStack $request_stack) {
    $this->entityTypeManager = $entity_type_manager;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('request_stack')
    );
  }

  /**
   * Builds the calendar page render array.
   */
  public function view() {
    $tz = new \DateTimeZone(\Drupal::config('system.date')->get('timezone.default') ?: 'America/New_York');
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
    // Show the legend in a stable, meaningful order.
    $order = array_keys(self::CATEGORY_ACCENTS);
    uksort($legend, fn($a, $b) => array_search($a, $order, TRUE) <=> array_search($b, $order, TRUE));

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
        'tags' => ['node_list:calendar_event'],
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
   *   accent slug: ['worship' => ['accent' => 'worship', 'label' => 'Worship']].
   *
   * @return array
   *   Map of 'Y-m-d' => list of event entries, each already sorted by start.
   */
  protected function collectOccurrences(DrupalDateTime $grid_start, DrupalDateTime $grid_end, \DateTimeZone $tz, array &$legend): array {
    $start_ts = $grid_start->getTimestamp();
    $end_ts = $grid_end->getTimestamp();

    $node_storage = $this->entityTypeManager->getStorage('node');
    $nids = $node_storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'calendar_event')
      ->condition('status', 1)
      ->condition('field_event_date.value', $end_ts, '<=')
      ->condition('field_event_date.end_value', $start_ts, '>=')
      ->execute();

    $by_date = [];
    if (empty($nids)) {
      return $by_date;
    }

    foreach ($node_storage->loadMultiple($nids) as $node) {
      $url = $node->toUrl()->toString();

      // Derive the accent + legend entry from the event's Mission Category.
      $accent = 'default';
      if (!$node->get('field_mission_category')->isEmpty()) {
        $term = $node->get('field_mission_category')->entity;
        if ($term) {
          $slug = strtolower(trim($term->label()));
          if (isset(self::CATEGORY_ACCENTS[$slug])) {
            $accent = self::CATEGORY_ACCENTS[$slug];
            $legend[$accent] = ['accent' => $accent, 'label' => $term->label()];
          }
        }
      }

      $description = '';
      if ($node->hasField('body') && !$node->get('body')->isEmpty()) {
        $plain = trim(strip_tags((string) $node->get('body')->value));
        $description = Unicode::truncate($plain, 160, TRUE, TRUE);
      }

      foreach ($node->get('field_event_date') as $item) {
        $occ_start_ts = (int) $item->value;
        $occ_end_ts = (int) $item->end_value;
        // Skip occurrences that fall entirely outside the visible grid.
        if ($occ_start_ts > $end_ts || $occ_end_ts < $start_ts) {
          continue;
        }

        $occ_start = DrupalDateTime::createFromTimestamp($occ_start_ts, $tz);
        $occ_end = DrupalDateTime::createFromTimestamp($occ_end_ts, $tz);
        // An event marked "all day" spans a full day with no meaningful time.
        $all_day = $occ_start->format('H:i') === '00:00' && $occ_end->format('H:i') === '23:59';
        $multi_day = $occ_start->format('Y-m-d') !== $occ_end->format('Y-m-d');

        if ($all_day) {
          $time_label = 'All day';
        }
        elseif ($multi_day) {
          $time_label = $occ_start->format('M j, g:i A') . ' – ' . $occ_end->format('M j, g:i A');
        }
        else {
          $time_label = $occ_start->format('g:i A');
        }

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
            'all_day' => $all_day,
            'is_start' => $is_start,
            'chip_time' => $all_day ? '' : ($is_start ? $occ_start->format('g:i A') : ''),
            'time_label' => $time_label,
            'description' => $description,
            'sort' => $all_day ? 0 : $occ_start_ts,
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
