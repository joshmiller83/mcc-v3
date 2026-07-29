<?php

/**
 * @file
 * Reshapes the footer menus into the four columns the design handoff asks for.
 *
 * Run with: ddev drush php:script scripts/footer-menus.php
 *
 * The handoff's footer is a brand cell plus four link columns — Visit,
 * Ministries, Connect, About — and mcc_theme_preprocess_page() reads one menu
 * per column. Two of those menus did not exist, and the fifth menu that did
 * ("Footer Contact", an email address and a phone number) is not a column in
 * the design: those two are contact details, so the phone joins Visit and the
 * email joins Connect, and the menu is retired.
 *
 * Idempotent: the four menus below are declared in full, matched by link title,
 * and anything not declared is deleted. Re-running is a no-op. Every URL here
 * points at something already on the site — no page is invented.
 *
 * The menu's **label is the footer column's heading**, so renaming a menu at
 * /admin/structure/menu renames the column. That makes the label editable
 * content, which a declarative script must not stomp: labels are written on
 * create, and on an existing menu only when it still carries the pre-design
 * name listed in `legacy_labels`. Rename a column in the admin UI and
 * re-running this script leaves your rename alone.
 */

use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\system\Entity\Menu;

/**
 * Menu id => [label, description, links].
 *
 * A link is [title, uri]. `route:<nolink>` is a plain line of text, not a
 * target — the street address and the service times are facts, not somewhere
 * to click, and wrapping them in an anchor to nowhere was the old behaviour.
 */
const FOOTER_MENUS = [
  'footer-organization' => [
    'label' => 'Visit',
    'legacy_labels' => ['Footer Visit'],
    'description' => 'Footer column 1 — where and when to find us. The menu name is the column heading.',
    'links' => [
      ['650 W. Horton Road, Kirklin, IN 46050', 'route:<nolink>'],
      ['Sunday School 9:30 - Worship 10:30 AM', 'route:<nolink>'],
      ['(765) 325-2772', 'tel:+17653252772'],
      ['Contact', 'internal:/contact'],
    ],
  ],
  'footer-ministries' => [
    'label' => 'Ministries',
    'legacy_labels' => ['Footer Ministries'],
    'description' => 'Footer column 2 — the ministry pages. The menu name is the column heading.',
    'links' => [
      ['Worship Service', 'internal:/ministries/worship-service'],
      ["Women's", 'internal:/ministries/womens'],
      ['Youth', 'internal:/ministries/youth'],
      ['Missions', 'internal:/ministries/missions'],
      ['C.A.R.E.', 'internal:/ministries/care-christians-are-reaching-everyone-ministry'],
      ['Building & Grounds', 'internal:/ministries/building-grounds'],
    ],
  ],
  'footer-connect' => [
    'label' => 'Connect',
    'legacy_labels' => ['Footer Connect'],
    'description' => 'Footer column 3 — ways to take part. The menu name is the column heading.',
    'links' => [
      ['Sermons', 'internal:/sermons'],
      ['Calendar', 'internal:/calendar'],
      ['News', 'internal:/news'],
      ['Get Involved', 'internal:/get-involved'],
      ['Give', 'internal:/give'],
      ['Email us', 'mailto:mechanicsburgsecretary@gmail.com'],
    ],
  ],
  'footer-about' => [
    'label' => 'About',
    'legacy_labels' => ['Footer About'],
    'description' => 'Footer column 4 — who the church is. The menu name is the column heading.',
    'links' => [
      ['Who we are', 'internal:/about/who-we-are'],
      ['Our beliefs', 'internal:/about/beliefs'],
      ['Our history', 'internal:/about/history'],
      ['Our leadership', 'internal:/about/leadership'],
    ],
  ],
];

/**
 * Menus the footer no longer reads, emptied and deleted.
 */
const RETIRED_MENUS = ['footer-support'];

$storage = \Drupal::entityTypeManager()->getStorage('menu_link_content');

foreach (FOOTER_MENUS as $menu_id => $spec) {
  $menu = Menu::load($menu_id);
  if (!$menu) {
    $menu = Menu::create([
      'id' => $menu_id,
      'label' => $spec['label'],
      'description' => $spec['description'],
    ]);
    $menu->save();
    echo "created menu $menu_id (\"{$spec['label']}\")\n";
  }
  elseif (in_array($menu->label(), $spec['legacy_labels'], TRUE)) {
    // A one-time rename off the pre-design name. Anything else in the label is
    // an editor's own column heading and is left alone.
    echo "renamed $menu_id: \"{$menu->label()}\" -> \"{$spec['label']}\"\n";
    $menu->set('label', $spec['label']);
    $menu->set('description', $spec['description']);
    $menu->save();
  }

  $existing = $storage->loadByProperties(['menu_name' => $menu_id]);
  $by_title = [];
  foreach ($existing as $link) {
    $by_title[$link->getTitle()] = $link;
  }

  foreach (array_values($spec['links']) as $weight => [$title, $uri]) {
    $link = $by_title[$title] ?? MenuLinkContent::create([
      'menu_name' => $menu_id,
      'title' => $title,
    ]);
    unset($by_title[$title]);
    $link->set('link', ['uri' => $uri]);
    $link->set('weight', $weight);
    $link->set('expanded', FALSE);
    $link->set('enabled', TRUE);
    $link->save();
  }

  // Anything left over is not in the declaration, so it goes.
  foreach ($by_title as $title => $link) {
    $link->delete();
    echo "removed \"$title\" from $menu_id\n";
  }

  echo "$menu_id: " . count($spec['links']) . " links\n";
}

foreach (RETIRED_MENUS as $menu_id) {
  foreach ($storage->loadByProperties(['menu_name' => $menu_id]) as $link) {
    $link->delete();
  }
  $menu = Menu::load($menu_id);
  if ($menu) {
    $menu->delete();
    echo "retired menu $menu_id\n";
  }
}

\Drupal::service('plugin.manager.menu.link')->rebuild();
echo "done\n";
