<?php

namespace Drupal\mcc_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Controller for printing monthly events.
 */
class MccCalendarPrintController extends ControllerBase {

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new MccCalendarPrintController object.
   */
  public function __construct(DateFormatterInterface $date_formatter, EntityTypeManagerInterface $entity_type_manager) {
    $this->dateFormatter = $date_formatter;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('date.formatter'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Renders the printable monthly events list using SDC.
   */
  public function printMonthly() {
    // Determine the current month window in the site's configured default timezone
    $timezone = \Drupal::config('system.date')->get('timezone.default') ?: 'America/New_York';
    $date = new \DateTime('now', new \DateTimeZone($timezone));
    
    // Check if a specific month/year is requested via query params
    $request = \Drupal::request();
    $year = $request->query->get('year', $date->format('Y'));
    $month = $request->query->get('month', $date->format('m'));
    
    // Set start and end range
    $start_date = new \DateTime("$year-$month-01 00:00:00", new \DateTimeZone($timezone));
    $start_timestamp = $start_date->getTimestamp();
    
    // Clone and modify to get the last day of the month
    $end_date = clone $start_date;
    $end_date->modify('last day of this month 23:59:59');
    $end_timestamp = $end_date->getTimestamp();

    $month_label = $start_date->format('F Y');

    // Query calendar_event nodes that have occurrences in this range
    $node_storage = $this->entityTypeManager->getStorage('node');
    $query = $node_storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'calendar_event')
      ->condition('status', 1)
      ->condition('field_event_date.value', $end_timestamp, '<=')
      ->condition('field_event_date.end_value', $start_timestamp, '>=');
    
    $nids = $query->execute();

    $events = [];
    if (!empty($nids)) {
      $nodes = $node_storage->loadMultiple($nids);
      foreach ($nodes as $node) {
        foreach ($node->field_event_date as $item) {
          $start = (int) $item->value;
          $end = (int) $item->end_value;
          // Only collect occurrences in the targeted month
          if ($start <= $end_timestamp && $end >= $start_timestamp) {
            $events[] = [
              'timestamp' => $start,
              'date' => $this->dateFormatter->format($start, 'custom', 'l, F j'),
              'time' => $this->dateFormatter->format($start, 'custom', 'g:i A'),
              'title' => $node->label(),
              'description' => $node->body->value ?? '',
            ];
          }
        }
      }

      // Sort chronological
      usort($events, function($a, $b) {
        return $a['timestamp'] <=> $b['timestamp'];
      });
    }

    // Build the render array using the custom Single Directory Component (SDC)
    return [
      '#type' => 'component',
      '#component' => 'mcc_core:mcc-monthly-events-print',
      '#props' => [
        'month_label' => $month_label,
        'events' => $events,
      ],
      '#cache' => [
        'tags' => ['node_list:calendar_event'],
      ],
    ];
  }

}
