<?php

namespace Drupal\mcc_core;

/**
 * The level-3 page families, keyed by owning node id.
 *
 * A "family" is a section landing page's set of children — currently just
 * About's four. `/about/leadership` is a Views page, not a node, so it can
 * never be the *current* page here, but it is still a valid sibling target
 * (a string key rather than a nid).
 *
 * Adding a ministry program family later is a matter of adding another
 * top-level array here; nothing that reads this needs to change.
 */
final class SectionFamilies {

  public const array FAMILIES = [
    'about' => [
      'label' => 'About',
      'url' => '/about',
      'members' => [
        3 => ['label' => 'Who we are', 'url' => '/about/who-we-are'],
        17 => ['label' => 'Our beliefs', 'url' => '/about/beliefs'],
        101 => ['label' => 'Our history', 'url' => '/about/history'],
        'leadership' => ['label' => 'Our leadership', 'url' => '/about/leadership'],
      ],
    ],
  ];

  /**
   * The family a node belongs to, or NULL if it isn't in one.
   */
  public static function forNode(int $nid): ?array {
    foreach (self::FAMILIES as $family) {
      if (isset($family['members'][$nid])) {
        return $family;
      }
    }
    return NULL;
  }

}
