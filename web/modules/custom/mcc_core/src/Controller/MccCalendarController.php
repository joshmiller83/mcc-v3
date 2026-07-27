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
 * Renders the public monthly calendar at /calendar.
 *
 * Month navigation is driven by `?year=` and `?month=` query parameters so the
 * page stays fully server-rendered and cacheable — no JavaScript is needed to
 * read the calendar, print it, or move between months.
 */
class MccCalendarController extends ControllerBase {

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
   * Builds the calendar page render array.
   */
  public function view(): array {
    $settings = $this->config('mcc_core.calendar.settings');
    [$year, $month] = $this->requestedMonth();

    $grid = $this->calendarMonth->build($year, $month);

    // Editors get a per-day shortcut into the node form. The link opens in a
    // new tab so they don't lose the month they were looking at.
    $can_add = $this->currentUser()->hasPermission('create calendar_event content');
    if ($can_add) {
      $this->addEventLinks($grid);
    }

    $first = new DrupalDateTime(sprintf('%04d-%02d-01', $year, $month), $this->eventContext->timezone());
    $prev = (clone $first)->modify('-1 month');
    $next = (clone $first)->modify('+1 month');

    return [
      '#type' => 'component',
      '#component' => 'mcc_theme:mcc-calendar-month',
      '#props' => [
        'eyebrow' => $settings->get('eyebrow') ?: '',
        'month_label' => $grid['month_label'],
        'weekdays' => $grid['weekdays'],
        'weeks' => $grid['weeks'],
        'agenda' => $this->calendarMonth->agenda($grid),
        'legend' => $settings->get('show_legend') ? $grid['legend'] : [],
        'density' => $settings->get('density') === 'compact' ? 'compact' : 'comfortable',
        'footnote' => $settings->get('footnote') ?: '',
        'can_add' => $can_add,
        'nav' => [
          'prev_url' => $this->monthUrl((int) $prev->format('Y'), (int) $prev->format('n')),
          'prev_label' => $prev->format('F Y'),
          'next_url' => $this->monthUrl((int) $next->format('Y'), (int) $next->format('n')),
          'next_label' => $next->format('F Y'),
          'today_url' => Url::fromRoute('mcc_core.calendar')->toString(),
          'is_current_month' => $grid['is_current_month'],
          'print_url' => Url::fromRoute('mcc_core.print_monthly', [], [
            'query' => ['year' => $year, 'month' => sprintf('%02d', $month)],
          ])->toString(),
        ],
      ],
      '#cache' => [
        'tags' => array_merge([
          'node_list:calendar_event',
          'taxonomy_term_list:mcc_mission_category',
          'config:mcc_core.calendar.settings',
        ], $grid['cache_tags']),
        // The "add an event" affordance and unpublished-event access both
        // depend on who is looking.
        'contexts' => ['url.query_args:year', 'url.query_args:month', 'user.permissions'],
        // "Today" moves at midnight.
        'max-age' => $this->secondsUntilMidnight(),
      ],
    ];
  }

  /**
   * The month asked for, or the current one.
   *
   * Out-of-range input falls back rather than erroring: this is a public URL
   * and a mistyped month should still show a calendar.
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
   * Hangs a prefilled node-add URL off every day cell.
   */
  protected function addEventLinks(array &$grid): void {
    foreach ($grid['weeks'] as &$week) {
      foreach ($week['days'] as &$day) {
        $day['add_url'] = Url::fromRoute('entity.node.add_form', ['node_type' => 'calendar_event'], [
          'query' => ['date' => $day['date']],
        ])->toString();
      }
    }
  }

  /**
   * Builds a URL to the calendar for a given month.
   */
  protected function monthUrl(int $year, int $month): string {
    return Url::fromRoute('mcc_core.calendar', [], [
      'query' => ['year' => $year, 'month' => sprintf('%02d', $month)],
    ])->toString();
  }

  /**
   * How long the rendered month stays accurate, in seconds.
   */
  protected function secondsUntilMidnight(): int {
    $tz = $this->eventContext->timezone();
    $now = new DrupalDateTime('now', $tz);
    $midnight = (new DrupalDateTime('tomorrow', $tz))->setTime(0, 0, 0);
    return max(60, $midnight->getTimestamp() - $now->getTimestamp());
  }

}
