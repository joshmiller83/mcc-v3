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
 * The level-3 sidebar's "Elsewhere in [section]" list.
 *
 * A block, not a hard-coded part of the page template, for the same reason
 * as ThisWeekBlock: it needs to be a component the shared content template
 * can place, and its output depends on which node is being rendered — which
 * a Canvas prop (static, or sourced from that same node's own fields) can't
 * express. See SectionFamilies for the sibling data.
 */
#[Block(
  id: 'mcc_section_related',
  admin_label: new TranslatableMarkup('Section related links'),
  category: new TranslatableMarkup('MCC'),
)]
final class SectionRelatedBlock extends BlockBase implements ContainerFactoryPluginInterface {

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

    $links = [];
    foreach ($family['members'] as $member_nid => $member) {
      if ($member_nid === $nid) {
        continue;
      }
      $links[] = $member;
    }

    $props = ['heading' => 'Elsewhere in ' . $family['label']];
    foreach (array_slice($links, 0, 3) as $i => $link) {
      $n = $i + 1;
      $props["link_{$n}_label"] = $link['label'];
      $props["link_{$n}_url"] = $link['url'];
    }

    return [
      '#type' => 'component',
      '#component' => 'mcc_theme:mcc-section-related',
      '#props' => $props,
      '#cache' => [
        'contexts' => ['route'],
      ],
    ];
  }

}
