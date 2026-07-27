<?php

namespace Drupal\mcc_core\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\mcc_core\CalendarMonth;
use Drupal\mcc_core\EventContext;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The front page's "This week at MCC" panel.
 *
 * A block rather than a controller or a hard-coded region so it stays
 * placeable: the front page is a Drupal Canvas page, and Canvas offers block
 * plugins as components, so an editor can move this panel, drop it on another
 * page, or take it off the front page without touching code.
 *
 * It carries no settings on purpose. Where the week appears is the editor's
 * decision and belongs in Canvas; what a category looks like is the taxonomy's
 * and belongs on the term. Neither is a block setting, and adding one would
 * give the same answer two places to live.
 */
#[Block(
  id: 'mcc_this_week',
  admin_label: new TranslatableMarkup('This week at MCC'),
  category: new TranslatableMarkup('MCC'),
)]
final class ThisWeekBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected CalendarMonth $calendarMonth,
    protected EventContext $eventContext,
    protected ConfigFactoryInterface $configFactory,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('mcc_core.calendar_month'),
      $container->get('mcc_core.event_context'),
      $container->get('config.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $settings = $this->configFactory->get('mcc_core.calendar.settings');
    $week = $this->calendarMonth->week();

    return [
      '#type' => 'component',
      '#component' => 'mcc_theme:mcc-this-week',
      '#props' => [
        'week' => $week['week'],
        'weekdays' => $week['weekdays'],
        'agenda' => $week['agenda'],
        'legend' => $settings->get('show_legend') ? $week['legend'] : [],
        'range_label' => $week['range_label'],
        'calendar_url' => Url::fromRoute('mcc_core.calendar')->toString(),
      ],
      '#cache' => [
        'tags' => array_merge([
          'node_list:calendar_event',
          'taxonomy_term_list:mcc_mission_category',
          'config:mcc_core.calendar.settings',
        ], $week['cache_tags']),
        // Unpublished events are visible to editors, so the panel is not the
        // same page for everyone.
        'contexts' => ['user.permissions'],
        // Which day is "Today", and which week is "this week", both move at
        // midnight.
        'max-age' => $this->secondsUntilMidnight(),
      ],
    ];
  }

  /**
   * How long the rendered week stays accurate, in seconds.
   */
  protected function secondsUntilMidnight(): int {
    $tz = $this->eventContext->timezone();
    $now = new DrupalDateTime('now', $tz);
    $midnight = (new DrupalDateTime('tomorrow', $tz))->setTime(0, 0, 0);
    return max(60, $midnight->getTimestamp() - $now->getTimestamp());
  }

}
