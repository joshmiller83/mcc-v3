<?php

namespace Drupal\mcc_core\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\mcc_core\SectionFamilies;

/**
 * The level-3 page's closing prev/next row.
 *
 * "Previous" is always the section's own landing page ("Back to About"), not
 * a sibling — see the design handoff. "Next" is the following member in
 * SectionFamilies' declared order, or absent on the family's last page.
 */
#[Block(
  id: 'mcc_section_prevnext',
  admin_label: new TranslatableMarkup('Section prev/next'),
  category: new TranslatableMarkup('MCC'),
)]
final class SectionPrevNextBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected RouteMatchInterface $routeMatch,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
    );
  }

  public function build(): array {
    $node = $this->routeMatch->getParameter('node');
    $nid = $node ? (int) $node->id() : NULL;
    $family = $nid ? SectionFamilies::forNode($nid) : NULL;

    if (!$family) {
      return [];
    }

    $keys = array_keys($family['members']);
    $position = array_search($nid, $keys, TRUE);
    $next_key = $keys[$position + 1] ?? NULL;
    $next = $next_key !== NULL ? $family['members'][$next_key] : NULL;

    return [
      '#type' => 'component',
      '#component' => 'mcc_theme:mcc-section-prevnext',
      '#props' => [
        'prev_label' => 'Back to ' . $family['label'],
        'prev_url' => $family['url'],
        'next_label' => $next['label'] ?? NULL,
        'next_url' => $next['url'] ?? NULL,
      ],
      '#cache' => [
        'contexts' => ['route'],
      ],
    ];
  }

}
