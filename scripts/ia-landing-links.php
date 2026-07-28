<?php

/**
 * @file
 * Repoints the CTA buttons on the landing pages at the new IA.
 *
 * Run with: ddev drush php:script scripts/ia-landing-links.php
 *
 * Idempotent — matches components by uuid and overwrites href/label.
 *
 * The demo pages shipped with buttons pointing at:
 *   - https://easebuzz.in/demo/ — a third-party payment demo, on four buttons
 *     labelled "Donate". Nobody's giving should ever have gone there.
 *   - /impact — a page that no longer exists.
 *   - /canvas_page/2 — an internal entity path rather than a URL.
 *   - /who-we-are/our-leadership — the pre-IA leadership path.
 *
 * NOTE: the Give buttons now point at /contact, because MCC's real online
 * giving provider is not known yet. Once it is, change GIVING_URL below.
 */

const GIVING_URL = '/contact';

/**
 * Canvas page id => [button uuid => [href, label|NULL to keep]].
 */
const LINKS = [
  // /about
  4 => [
    '8624a074-dac8-4c53-a53d-e57bbe1c67f6' => ['/give', 'Give'],
    '0573c9a4-fa1f-4343-a8eb-e9032f9e80eb' => ['/get-involved', 'Get involved'],
    '51b1ce01-8dc2-446c-86f8-ba6d23bba54a' => ['/about/history', 'Our history'],
    '04613a26-f4f1-43dd-a0a5-90e06557c75c' => ['/about/beliefs', 'What we believe'],
    '5531b61a-1760-49bb-b39b-e6c41c3d68ce' => ['/ministries', 'Our ministries'],
    '2ebef10f-4ec2-4a90-8fe7-6c424286cfb8' => ['/im-new', "I'm new"],
    '740b2187-f69b-4c8b-979f-18d01919a488' => ['/about/leadership', 'Our leadership'],
    'a0df5edc-d3ab-4c93-a07f-4e97ae6a57cb' => ['/about/leadership', 'Meet our leaders'],
    'c49eaeb8-0e35-4e51-ad6b-bc08feb0e37c' => ['/give', 'Give'],
    '1600d41a-42c3-4934-a77c-d42ac8ce06bb' => ['/get-involved', 'Get involved'],
  ],

  // /ministries
  8 => [
    '570fb1f1-3668-4286-999b-6f0b9797333a' => ['/get-involved', 'Find your place'],
    '508b6cd8-2e84-4d6d-85c8-c10a6e727d68' => ['/give', 'Give'],
    '9af1c0b0-b70d-4f5a-84a3-ffbafff19a06' => ['/get-involved', 'Get involved'],
  ],

  // /im-new
  11 => [
    '6ddbd8aa-88d4-4460-a813-f296fc180308' => ['/ministries', 'Browse ministries'],
    '767bd10a-1fbb-4133-b370-28c7118787ff' => ['/about/leadership', 'Meet our leaders'],
    '4a5c504e-7036-40e3-834d-88f6c1120e69' => ['/contact', 'Contact us'],
  ],

  // /give — no real giving provider yet, so these go to /contact rather than
  // an external demo site.
  1 => [
    '6712a527-3c2d-4a8e-adb9-f6fdfb1a0030' => [GIVING_URL, 'Ask about giving'],
    '9bee2204-04f1-43f7-9f15-79d6921935b6' => ['/about', 'About MCC'],
    'f40b3d82-2710-43e1-86c8-460cd070aaab' => [GIVING_URL, 'Ask about giving'],
    // "Get invloved" — the demo content shipped with the typo.
    'fb1bd662-e742-4c16-ae9c-e12b17ff5a25' => ['/get-involved', 'Get involved'],
  ],
];

$storage = \Drupal::entityTypeManager()->getStorage('canvas_page');

foreach (LINKS as $page_id => $changes) {
  $page = $storage->load($page_id);
  if (!$page) {
    print "no canvas_page $page_id\n";
    continue;
  }

  $items = [];
  $touched = 0;
  foreach ($page->get('components') as $item) {
    $value = $item->getValue();
    if (isset($changes[$value['uuid']])) {
      [$href, $label] = $changes[$value['uuid']];
      $inputs = json_decode($value['inputs'], TRUE) ?: [];
      $inputs['href'] = $href;
      if ($label !== NULL) {
        $inputs['label'] = $label;
      }
      $value['inputs'] = json_encode($inputs);
      $touched++;
    }
    $items[] = $value;
  }

  $page->set('components', $items);
  $page->setRevisionLogMessage('Repointed CTA buttons at the new IA.');
  $page->save();
  printf("canvas_page %d (%s): %d button%s\n", $page_id, $page->label(), $touched, $touched === 1 ? '' : 's');
}
