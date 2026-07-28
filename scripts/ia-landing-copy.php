<?php

/**
 * @file
 * Replaces the CareSphere demo copy on the repurposed landing pages.
 *
 * Run with: ddev drush php:script scripts/ia-landing-copy.php
 *
 * Idempotent — it matches components by uuid and overwrites their inputs, so
 * re-running is harmless. Canvas pages are not produced by any migration, so a
 * re-import never touches this; the script exists so the copy is reviewable in
 * version control rather than living only in the database.
 *
 * Source material is the migrated D7 text: node 3 "Who We Are" for /about and
 * node 29 "Ministries" for /ministries.
 */

/**
 * Canvas page id => [component uuid => the fields to overwrite].
 */
const COPY = [
  // /about
  4 => [
    'd33d6365-f737-410c-80d6-328a9faf9ebe' => [
      'tagline' => 'Who We Are',
      'heading' => 'A church designed by God himself',
      'description' => 'Mechanicsburg Christian Church has met on this corner of Horton Road since 1840. We are a country church that loves people — plainly, and without much fuss.',
    ],
    '37383960-0719-4d29-93af-8f1c9b1540a5' => [
      'tagline' => 'History',
      'heading' => 'In 1840, a small group of men cut logs and built a church in a thick woods. We have been here ever since.',
    ],
    'bc4ab332-64db-4f86-a870-bbdcddcf7fd3' => [
      'tagline' => 'Mission',
      'heading' => 'To be known for worship services of celebration and praise, and to be a church where gifts are developed and used for the glory of God.',
    ],
    '8f59e5b5-927f-494b-93dc-cb49d62ca7c4' => [
      'tagline' => 'Vision',
      'heading' => 'A place where people are lifted and encouraged through teaching and prayer, and where a diversity of ministries meets real needs.',
    ],
    'c49c6f49-5065-4d62-9500-03e653078a6b' => [
      'tagline' => 'What We Believe',
      'heading' => 'In the essentials, unity. In opinion, liberty. In all things, love.',
      'description' => 'These statements clarify where we agree. They are not a creed requiring formal agreement — we desire no creed but Christ.',
    ],
    '5e54b0cf-880b-4625-a8da-061a44140126' => [
      'tagline' => 'Leadership',
      'heading' => 'Our elders, deacons and ministry leaders',
      'description' => 'If you are not sure who to ask about something, start here.',
    ],
    '49a01840-0367-4ec7-afe5-c9a976c4f105' => [
      'heading' => 'Come and visit',
      'description' => 'Sunday School at 9:30, Worship at 10:30. You are welcome exactly as you are.',
    ],
    'e2f80f95-6dca-4cf2-a949-acca5c1b11e1' => [
      'heading' => 'Find your place here',
      'description' => 'There is a seat for you on Sunday morning, and a place to serve during the week.',
    ],
  ],

  // /ministries
  8 => [
    '0b7e5079-560b-436d-b27e-77205eb9d56d' => [
      'tagline' => 'Serve',
      'heading' => 'Our ministries',
      'description' => 'Every Christian should be serving the Lord in ministry. God has given each of us different abilities and interests, and it is only appropriate that we use them for him as best we can.',
    ],
    'f7e3e605-abc9-4e14-adc3-750b3cf6b1d3' => [
      'tagline' => 'Ministries',
      'heading' => 'Where people serve at MCC',
      'description' => 'Each of these is run by people in this congregation. If one of them fits how God has gifted you, say so on a Sunday morning.',
    ],
    '50b6934b-45b4-49ab-b56b-654f95660270' => [
      'tagline' => 'Ephesians 4:11-13',
      'heading' => 'To prepare God\'s people for works of service, so that the body of Christ may be built up',
      'description' => 'Until we all reach unity in the faith and in the knowledge of the Son of God, and become mature.',
    ],
    '292425c4-634a-491c-a85e-5ad6a27a8754' => [
      'heading' => 'Find where you fit',
      'description' => 'Talk to any ministry leader on a Sunday morning, or reach out to the church office.',
    ],
  ],

  // /give — the fabricated $19/$29/$49 donation tiers were deleted outright
  // (see scripts note in CONTENT_LOG.md). What remains needs MCC's real giving
  // arrangements, which we do not have yet.
  1 => [
    '2c03273a-1e29-4420-b88e-91dba17cd019' => [
      'tagline' => 'Give',
      'heading' => 'Giving to MCC',
      'description' => 'Your giving supports the ministries of this church, the missions we send out, and the neighbours we help. Thank you.',
    ],
    'e12948a2-a8f3-4f4a-9ae1-e3e4cd6c8f2d' => [
      'tagline' => 'Ways to give',
      'heading' => 'How to give',
      'description' => 'Offering is received during Sunday worship. To give another way, or to designate a gift toward missions, contact the church office.',
    ],
    '74ddb88b-9985-4789-84d1-f66a4b634a75' => [
      'heading' => 'Questions about giving?',
      'description' => 'The church office is glad to help.',
    ],
  ],
];

$storage = \Drupal::entityTypeManager()->getStorage('canvas_page');

foreach (COPY as $page_id => $changes) {
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
      $inputs = json_decode($value['inputs'], TRUE) ?: [];
      foreach ($changes[$value['uuid']] as $field => $text) {
        // `heading` is a rich-text prop with its own format; the others are
        // plain strings. Writing a bare string into heading fails validation.
        $inputs[$field] = $field === 'heading'
          ? ['value' => $text, 'format' => 'canvas_html_inline']
          : $text;
      }
      $value['inputs'] = json_encode($inputs);
      $touched++;
    }
    $items[] = $value;
  }

  $page->set('components', $items);
  $page->setRevisionLogMessage('Replaced CareSphere demo copy with MCC content.');
  $page->save();
  printf("canvas_page %d (%s): rewrote %d component%s\n",
    $page_id, $page->label(), $touched, $touched === 1 ? '' : 's');
}
