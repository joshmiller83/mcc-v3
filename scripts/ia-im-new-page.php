<?php

/**
 * @file
 * Builds the "I'm New" Canvas page at /im-new.
 *
 * Run with:
 *   ddev drush php:script scripts/ia-im-new-page.php
 *
 * Why this exists
 * ---------------
 * The old /im-new was Get Involved content under the wrong name, and was folded
 * away by ia-get-involved-merge.php. That left the nav with no entry aimed at
 * someone who has never been here. This is the real thing: what time to come,
 * what happens on a Sunday, what to do with your kids, how to find the
 * building.
 *
 * Everything asserted below is either on the site already or derived from it:
 *
 *   times      footer-organization menu ("Sunday School 9:30 - Worship 10:30 AM")
 *   address    footer-organization menu
 *   phone      footer-support menu
 *   voice      /about ("a country church that loves people — plainly, and
 *              without much fuss", "You are welcome exactly as you are")
 *   nursery,   ministry nodes 12, 10, 13, 8 under /ministries/worship-service
 *   kids,      and /ministries/youth
 *   youth
 *   streaming  the recurring "FaceBookLive Church Service 10:30a" calendar event
 *
 * Deliberately NOT asserted: where to park, which door to use, how long the
 * service runs, and what communion practice is. None of that is recorded
 * anywhere on this site and none of it is safe to guess about a real
 * congregation. Add them here once the church confirms — see PARKING below.
 *
 * Idempotent: the page is found by its /im-new alias and rebuilt in full from
 * the tree declared here, so re-running restores exactly this state.
 */

use Drupal\redirect\Entity\Redirect;

const ALIAS = '/im-new';
const LABEL = "I'm New";

// Uncomment and fill in once the church confirms, then re-run. It becomes a
// fifth FAQ item. Leaving it commented is deliberate — an invented answer to
// "where do I park" is worse than no answer.
// const PARKING = 'Answer here.';

$MAP_URL = 'https://maps.google.com/?q=650+W.+Horton+Road,+Kirklin,+IN+46050';

/**
 * Component versions, as stored on the other Canvas pages.
 */
const VERSIONS = [
  'section' => '53b3ea832b5dc53e',
  'section-intro' => '74c2e8b544d650c6',
  'section-grid' => '04145a81d752b635',
  'card-icon' => '24cfa587233a1a7e',
  'accordion' => '39f79e0ea8fb1c6b',
  'button' => '1fea841cab710e4e',
];

$rich = fn(string $html) => ['value' => $html, 'format' => 'canvas_html_inline'];
$block = fn(string $html) => ['value' => $html, 'format' => 'canvas_html_block'];

/**
 * A band: one section owning everything inside it.
 *
 * Padding 10 => 5rem (--space-20); marginbottom 0 because the padding already
 * separates the bands and the default 10 would stack margin on top of it.
 */
$band = fn(string $id, string $bg) => [
  'section_id' => $id,
  'backgroundcolor' => $bg,
  'alignment' => 'center',
  'paddingtop' => 10,
  'paddingbottom' => 10,
  'marginbottom' => 0,
  'use_custom_container' => FALSE,
];

$card = fn(string $heading, string $body, ?string $url = NULL, ?string $label = NULL) => array_filter([
  'text' => $heading,
  'description' => $block('<p>' . $body . '</p>'),
  'icon_size' => 'large',
  'icon_align' => 'center',
  'text_align' => 'center',
  'border_radius' => 'medium',
  'cta_url' => $url,
  'cta_label' => $label,
  'cta_variant' => $url ? 'link' : NULL,
], fn($v) => $v !== NULL);

$button = fn(string $href, string $label, string $variant) => [
  'href' => $href,
  'label' => $label,
  'variant' => $variant,
  'size' => 'medium',
  'disabled' => FALSE,
  'icon_first' => FALSE,
  'mobile_width' => TRUE,
];

$faq = fn(string $q, string $a) => [
  'question' => $q,
  'answer' => $a,
  'default_state' => 'collapsed',
];

// The tree, depth first: [uuid, component, slot, inputs, children].
$tree = [
  // Hero.
  ['0d4196cd-2211-4036-bb7f-ebd1cc186593', 'section', NULL, $band('im-new-hero', 'black'), [
    ['471f79b6-0bfb-4b63-97cf-536ed78f6a76', 'section-intro', 'content', [
      'tagline' => 'Visiting',
      'heading' => $rich('Your first Sunday at MCC'),
      'description' => 'We are a small country church on the corner of Horton Road. Here is what a Sunday morning actually looks like, so none of your first visit has to be a guess.',
      'heading_level' => 1,
      'textcolor' => 'light',
    ], []],
  ]],

  // When we gather.
  ['d9aa7af5-93c1-4bfa-90dc-c366186c7282', 'section', NULL, $band('im-new-sunday', 'white'), [
    ['34eb9f93-9fa9-4200-802b-4dd798f396ac', 'section-intro', 'content', [
      'tagline' => 'Sunday Mornings',
      'heading' => $rich('When we gather'),
      'description' => 'Two things happen every Sunday. You are welcome at both, and you are welcome at just one.',
      'heading_level' => 2,
    ], []],
    ['f0e67864-1276-4a68-b4ab-f748d0a95247', 'section-grid', 'content', [
      'grid_layout' => '3_column',
      'column_1_layout' => 'stacked',
      'column_2_layout' => 'stacked',
      'column_3_layout' => 'stacked',
    ], [
      ['460e246f-e2ab-4748-8832-8497e31333e2', 'card-icon', 'slot_1',
        $card('Sunday School — 9:30', 'Classes for adults and for young people before the service. A good place to start if you would rather meet a few people than a room full.', '/ministries/worship-service/sunday-school', 'Sunday School'), []],
      ['95b5c111-7d68-43a8-a439-b2b8e51f1fae', 'card-icon', 'slot_2',
        $card('Worship — 10:30', 'Singing, prayer and teaching from scripture. This is the main gathering of the week, and it is also streamed on Facebook Live if you would rather start from home.', '/sermons', 'Recent sermons'), []],
      ['a390d83f-137b-41ce-90fa-dc77d4c6a843', 'card-icon', 'slot_3',
        $card('Children and youth', 'We have a nursery for the littlest ones, Kids Worship for younger children, and a youth ministry for older kids.', '/ministries/worship-service', 'Kids at MCC'), []],
    ]],
  ]],

  // Come as you are.
  ['75e20ca2-53bb-41ee-9e06-59c7b814bb03', 'section', NULL, $band('im-new-expect', 'gray-light'), [
    ['46978d81-d1f0-40b6-bcfa-1cbc9ec5abb9', 'section-intro', 'content', [
      'tagline' => 'What to Expect',
      'heading' => $rich('Come as you are'),
      'description' => 'You are welcome exactly as you are. We are a country church that loves people — plainly, and without much fuss. Come in, find a seat, and say hello to whoever is nearest.',
      'heading_level' => 2,
    ], [
      ['07f0aaeb-9aa8-45df-b286-29cc4898e573', 'button', 'ctas', $button($MAP_URL, 'Get directions', 'primary'), []],
      ['76fe0032-dd73-4dc8-8421-c52b6ab4b596', 'button', 'ctas', $button('/contact', 'Ask us anything', 'secondary-dark'), []],
    ]],
  ]],

  // Questions.
  ['239a93db-f74e-411c-bdbc-0432ad687d0d', 'section', NULL, $band('im-new-faq', 'white'), [
    ['7a91e7de-af9e-47ef-8a9b-03ba1803523a', 'section-intro', 'content', [
      'heading' => $rich('Before you come'),
      'description' => 'The things people usually want to know before a first visit.',
      'heading_level' => 2,
    ], []],
    ['40408110-b497-4ddc-b4ed-c247c580a25d', 'section-grid', 'content', [
      'grid_layout' => '1_column',
      'grid_row_gap' => 2,
      'column_1_layout' => 'stacked',
    ], [
      ['8f718d03-42e2-4e39-b280-319bedc837f6', 'accordion', 'slot_1',
        $faq('What time should I arrive?', 'Sunday School starts at 9:30 and worship at 10:30. If you are coming for worship, a few minutes before 10:30 gives you time to find a seat.'), []],
      ['ef01de16-32f3-4eb8-bb55-c92ceacdc016', 'accordion', 'slot_1',
        $faq('What should I wear?', 'Whatever you are comfortable in. You are welcome exactly as you are.'), []],
      ['e4be954a-2df4-4118-a37a-d27bfe801d48', 'accordion', 'slot_1',
        $faq('Is there anything for my kids?', 'Yes. There is a nursery for the littlest ones and Kids Worship for younger children during the morning, plus Sunday School classes and a youth ministry for older kids.'), []],
      ['eef665d6-392f-43f6-b50f-22bba772de24', 'accordion', 'slot_1',
        $faq('Can I watch online first?', 'Yes. The 10:30 service is streamed on Facebook Live, and plenty of people start there before visiting in person.'), []],
    ]],
  ]],

  // Come and see.
  ['c04ed3c5-0459-4900-bcc7-a4497eb467ff', 'section', NULL, $band('im-new-visit', 'dark-gray'), [
    ['5c4b6a13-7f2e-4d55-9a0c-1e8b0f2d7a64', 'section-intro', 'content', [
      'heading' => $rich('We would love to meet you'),
      'description' => '650 W. Horton Road, Kirklin, IN 46050. Sunday School at 9:30, worship at 10:30. If it is easier to ask a person, call the office at (765) 325-2772.',
      'heading_level' => 2,
      'textcolor' => 'light',
    ], [
      ['9d3f8e21-4c07-4b6a-8f31-2a5c9e0b7d18', 'button', 'ctas', $button('/contact', 'Get in touch', 'primary'), []],
    ]],
  ]],
];

// Optional fifth FAQ, only if the church has confirmed an answer.
if (defined('PARKING')) {
  $tree[3][4][1][4][] = ['b7c1a904-63de-4f2a-9c85-0d41e7f3a2b6', 'accordion', 'slot_1',
    $faq('Where should I park?', PARKING), []];
}

$page_storage = \Drupal::entityTypeManager()->getStorage('canvas_page');
$alias_storage = \Drupal::entityTypeManager()->getStorage('path_alias');

// Flatten the tree into the field's depth-first item list.
$items = [];
$walk = function (array $nodes, ?string $parent) use (&$walk, &$items) {
  foreach ($nodes as [$uuid, $component, $slot, $inputs, $children]) {
    $items[] = [
      'uuid' => $uuid,
      'component_id' => 'sdc.caresphere_theme.' . $component,
      'component_version' => VERSIONS[$component],
      'parent_uuid' => $parent,
      'slot' => $slot,
      'inputs' => json_encode($inputs),
      'label' => NULL,
    ];
    if ($children) {
      $walk($children, $uuid);
    }
  }
};
$walk($tree, NULL);

// Find an existing page behind the alias so re-runs update rather than duplicate.
$existing_path = \Drupal::service('path_alias.manager')->getPathByAlias(ALIAS);
$page = preg_match('#^/page/(\d+)$#', $existing_path, $m) ? $page_storage->load($m[1]) : NULL;

if ($page) {
  printf("updating existing canvas_page %d\n", $page->id());
}
else {
  // /im-new 301s to Get Involved from the merge. That redirect has to go, or it
  // shadows the new page — inbound path processing resolves the alias only
  // after the redirect has already fired.
  foreach (\Drupal::entityTypeManager()->getStorage('redirect')
    ->loadByProperties(['redirect_source__path' => ltrim(ALIAS, '/')]) as $redirect) {
    $redirect->delete();
    print "removed the /im-new -> Get Involved redirect\n";
  }
  $page = $page_storage->create(['type' => 'page']);
  print "creating a new canvas_page\n";
}

$page->set('title', LABEL);
$page->set('components', $items);
$page->setPublished();
$page->save();

// Canvas inserts a second alias rather than updating in place, so sweep first.
foreach ($alias_storage->loadByProperties(['path' => '/page/' . $page->id()]) as $alias) {
  if ($alias->getAlias() !== ALIAS) {
    $alias->delete();
  }
}
if (!$alias_storage->loadByProperties(['path' => '/page/' . $page->id(), 'alias' => ALIAS])) {
  $alias_storage->create([
    'path' => '/page/' . $page->id(),
    'alias' => ALIAS,
    'langcode' => 'en',
  ])->save();
}

printf("page %d \"%s\" at %s — %d components in 5 bands\n", $page->id(), LABEL, ALIAS, count($items));

// Put it back in the nav, ahead of About.
$link_storage = \Drupal::entityTypeManager()->getStorage('menu_link_content');
$existing_link = $link_storage->loadByProperties([
  'menu_name' => 'main',
  'link__uri' => 'internal:' . ALIAS,
]);
if ($existing_link) {
  print "menu: already in main\n";
}
else {
  $link_storage->create([
    'title' => LABEL,
    'link' => ['uri' => 'internal:' . ALIAS],
    'menu_name' => 'main',
    'weight' => -10,
    'expanded' => FALSE,
  ])->save();
  print "menu: added to main ahead of About\n";
}

\Drupal::service('cache_tags.invalidator')->invalidateTags(['canvas_page_list', 'menu_link_content_list']);
