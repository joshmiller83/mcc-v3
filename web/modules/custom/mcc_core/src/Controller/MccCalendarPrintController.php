<?php

namespace Drupal\mcc_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Url;
use Drupal\mcc_core\CalendarMonth;
use Drupal\mcc_core\EventContext;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Renders the one-page printable calendar at /calendar/print.
 *
 * The office prints one of these per month and hands it out, so "fits on a
 * single sheet of Letter paper with nothing cut off" is a hard requirement,
 * not a nicety. Three things enforce it together:
 *
 * 1. The sheet is a fixed 8.5in x 11in box with `@page { margin: 0 }`, and the
 *    week rows share its height as `1fr` each, so a 5-week and a 6-week month
 *    both fill the page exactly.
 * 2. This controller sizes the type down when a month is unusually busy, using
 *    the grid's own line and lane counts (see printScale()).
 * 3. The component's small script measures the rendered sheet and keeps
 *    shrinking until nothing overflows, which covers whatever the estimate in
 *    (2) got wrong.
 *
 * scripts/calendar-compare.mjs asserts the result: exactly one PDF page, and
 * no element inside the sheet scrolled or clipped.
 */
class MccCalendarPrintController extends ControllerBase {

  /**
   * Height available to the week rows once the sheet's chrome is deducted.
   *
   * Inches. The sheet is 11in tall, less its 0.52in of vertical padding, the
   * header block, the weekday band and the footer line.
   */
  const GRID_HEIGHT_IN = 9.4;

  /**
   * Rough printed height of the pieces of a week row, in inches at full size.
   */
  const ROW_DAY_NUMBER_IN = 0.16;
  const ROW_LANE_IN = 0.135;
  const ROW_LINE_IN = 0.118;

  /**
   * How small the type is allowed to get before we stop shrinking.
   *
   * Below about 60% of 7pt the sheet stops being readable across a room, and a
   * month that busy is better served by the "weekly digest" layout.
   */
  const MIN_SCALE = 0.6;

  public function __construct(
    protected RequestStack $requestStack,
    protected CalendarMonth $calendarMonth,
    protected EventContext $eventContext,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('mcc_core.calendar_month'),
      $container->get('mcc_core.event_context')
    );
  }

  /**
   * Builds the print sheet render array.
   */
  public function view(): array {
    $settings = $this->config('mcc_core.calendar.settings');
    $style = $settings->get('print.busy_day_style') ?: 'grouped';
    [$year, $month] = $this->requestedMonth();

    $grid = $this->calendarMonth->build($year, $month, [
      'show_adjacent_days' => (bool) $settings->get('print.show_adjacent_days'),
      'busy_threshold' => (int) ($settings->get('print.busy_threshold') ?: 4),
    ]);

    // The digest layout lifts the standing weekly schedule out of the grid and
    // states it once, under the header.
    $standing = [];
    if ($style === 'digest') {
      $standing = $grid['standing'];
      $this->liftStandingEvents($grid);
    }

    $first = new DrupalDateTime(sprintf('%04d-%02d-01', $year, $month), $this->eventContext->timezone());
    $prev = (clone $first)->modify('-1 month');
    $next = (clone $first)->modify('+1 month');

    return [
      '#type' => 'component',
      '#component' => 'mcc_theme:mcc-calendar-print',
      '#props' => [
        'month_label' => $grid['month_label'],
        'tagline' => $settings->get('print.tagline') ?: '',
        'weekdays' => $grid['weekdays'],
        'weeks' => $grid['weeks'],
        'week_count' => $grid['week_count'],
        'legend' => $grid['legend'],
        'busy_day_style' => $style,
        'standing' => $standing,
        'scale' => $this->printScale($grid, $style),
        'footer_left' => $settings->get('print.footer_left') ?: '',
        'footer_right' => $settings->get('print.footer_right') ?: '',
        'toolbar' => [
          'calendar_url' => Url::fromRoute('mcc_core.calendar', [], [
            'query' => ['year' => $year, 'month' => sprintf('%02d', $month)],
          ])->toString(),
          'prev_url' => $this->printUrl((int) $prev->format('Y'), (int) $prev->format('n')),
          'prev_label' => $prev->format('F Y'),
          'next_url' => $this->printUrl((int) $next->format('Y'), (int) $next->format('n')),
          'next_label' => $next->format('F Y'),
        ],
      ],
      '#cache' => [
        'tags' => array_merge([
          'node_list:calendar_event',
          'taxonomy_term_list:mcc_mission_category',
          'config:mcc_core.calendar.settings',
        ], $grid['cache_tags']),
        'contexts' => ['url.query_args:year', 'url.query_args:month'],
      ],
    ];
  }

  /**
   * The month asked for, or the current one.
   */
  protected function requestedMonth(): array {
    $today = new DrupalDateTime('now', $this->eventContext->timezone());
    $query = $this->requestStack->getCurrentRequest()->query;

    $year = (int) $query->get('year', $today->format('Y'));
    $month = (int) $query->get('month', $today->format('n'));
    if ($month < 1 || $month > 12) {
      $month = (int) $today->format('n');
    }
    if ($year < 1970 || $year > 2200) {
      $year = (int) $today->format('Y');
    }

    return [$year, $month];
  }

  /**
   * Removes the standing weekly events from the day cells.
   *
   * Days that lose something get a note pointing at the digest band, so a
   * reader glancing at a Sunday doesn't conclude nothing happens.
   */
  protected function liftStandingEvents(array &$grid): void {
    foreach ($grid['weeks'] as &$week) {
      foreach ($week['days'] as &$day) {
        $kept = array_values(array_filter($day['events'], fn($event) => !$event['standing']));
        $day['lifted'] = count($day['events']) - count($kept);
        $day['events'] = $kept;
        $day['count'] = count($kept);
        $day['is_busy'] = FALSE;
      }
    }
  }

  /**
   * How far the sheet's type has to shrink for this month to fit.
   *
   * Each week row gets an equal share of a fixed height, so the busiest week
   * decides. Returned as a multiplier the component applies to its base type
   * size; 1 means the month fits at full size, which most do.
   */
  protected function printScale(array $grid, string $style): float {
    $week_height = self::GRID_HEIGHT_IN / max(1, $grid['week_count']);
    $scale = 1.0;

    foreach ($grid['weeks'] as $week) {
      $lines = 0;
      foreach ($week['days'] as $day) {
        $lines = max($lines, $this->printedLines($day, $style));
      }
      $needed = self::ROW_DAY_NUMBER_IN
        + $week['lane_count'] * self::ROW_LANE_IN
        + $lines * self::ROW_LINE_IN;
      if ($needed > 0) {
        $scale = min($scale, $week_height / $needed);
      }
    }

    return round(max(self::MIN_SCALE, min(1.0, $scale)), 2);
  }

  /**
   * How many lines a day cell prints under the chosen busy-day layout.
   */
  protected function printedLines(array $day, string $style): int {
    if ($style === 'runs' && $day['is_busy']) {
      // One flowing line per time slot, plus a little slack for wrapping.
      return count($day['groups']) + 1;
    }
    return $day['count'] + ($day['lifted'] ?? 0 ? 1 : 0);
  }

  /**
   * Builds a URL to the print sheet for a given month.
   */
  protected function printUrl(int $year, int $month): string {
    return Url::fromRoute('mcc_core.print_monthly', [], [
      'query' => ['year' => $year, 'month' => sprintf('%02d', $month)],
    ])->toString();
  }

}
