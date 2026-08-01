<?php

/**
 * @file
 * Puts all eleven ministries and two mission partners into the shape the
 * /ministries listing and the ministry detail page read.
 *
 * Run with: ddev drush php:script scripts/ministries-content.php
 *
 * Two jobs:
 *
 * 1. **Bundle conversion.** Five ministries were modelled as `page` nodes in
 *    D7 — Kids Worship, Nursery, Sunday School, Prayer & Outreach and the
 *    Emergency Response Team. The split is an implementation detail nobody
 *    visiting the site can see, and it was the reason the listing showed six of
 *    eleven ministries. They become `ministry` nodes here. Aliases are left
 *    alone: scripts/ia-page-slugs.php pins them with PathautoState::SKIP, so
 *    the URLs survive both the conversion and any later editor save.
 *
 * 2. **Field values.** Every card and detail page is built from fields, not
 *    from prose parsing, so each ministry needs its group, icon, order, summary
 *    and — where the church has recorded one — a schedule, a leader and a
 *    theme scripture.
 *
 * Idempotent: the whole desired state is declared below and written over
 * whatever is stored, so re-running after a migration re-import restores it.
 *
 * **The summaries are draft copy and the church has not approved them.** They
 * were written from the ministries' own bodies and the design handoff. Nothing
 * here asserts a fact the site did not already state — service times come from
 * the Worship Service body, the Kids Worship age range from its own body, the
 * nursery ages from the nursery's. Prayer & Outreach deliberately ships with no
 * summary: the card renders a visible "a description is on the way" state, so
 * the gap prompts a fix instead of hiding.
 */

use Drupal\pathauto\PathautoState;

/**
 * Ministries that arrived as `page` nodes and are really ministries.
 */
const MCC_CONVERT_TO_MINISTRY = [10, 12, 13, 1098, 1100];

/**
 * Group term names, in vocabulary order. Resolved to tids at run time.
 */
const MCC_GROUP_WORSHIP = 'Worship & Discipleship';
const MCC_GROUP_CARE = 'Care & Community';
const MCC_GROUP_MISSIONS = 'Missions & Outreach';

/**
 * The whole listing, in the order it renders.
 *
 * nid => [
 *   group, weight, icon, display_title, subtitle, schedule, summary,
 *   leader bio nid, [[verse text, reference], …],
 * ]
 *
 * A NULL means "this ministry genuinely has no value for that field" — every
 * one of them is a real gap in the church's own records, and the card and
 * detail page both omit rather than invent. Partner nodes take no icon,
 * schedule, leader or verse; they render the `partner` card variant instead.
 */
const MCC_MINISTRIES = [
  // --- Worship & Discipleship ------------------------------------------
  11 => [
    MCC_GROUP_WORSHIP, 0, 'lucide:church', NULL, NULL, 'Sundays 9:30 & 10:30',
    'Sunday School at 9:30 and worship at 10:30 — blended music, communion every week, and plain preaching.',
    NULL, [],
  ],
  8 => [
    MCC_GROUP_WORSHIP, 1, 'lucide:users', 'Youth Ministry', 'Preschool through high school', NULL,
    'Sunday classes, monthly Freedom Rides, camp at Hanging Rock, and VBS each summer.',
    1603,
    // Lifted out of the body, where it was already doing a theme verse's job.
    [['Train up a child in the way he should go; even when he is old he will not depart from it', 'Proverbs 22:6']],
  ],
  13 => [
    MCC_GROUP_WORSHIP, 2, 'lucide:book-open', NULL, NULL, 'Sundays 9:30',
    'School of the Word: classes for every age and stage, infant through adult.',
    NULL, [],
  ],
  10 => [
    MCC_GROUP_WORSHIP, 3, 'lucide:music', NULL, 'Ages 4–12', 'During the sermon',
    'Children head downstairs during the sermon for songs, prayer, and an age-appropriate lesson.',
    NULL, [],
  ],
  12 => [
    MCC_GROUP_WORSHIP, 4, 'lucide:baby', NULL, 'Infants to 4 years', 'Sunday school and worship',
    'Two nurseries during worship — one for toddlers, one for 18 months and younger.',
    NULL,
    [['Let the little children come to Me, and do not hinder them, for the Kingdom of God belongs to such as these.', 'Luke 18:16']],
  ],

  // --- Care & Community -------------------------------------------------
  38 => [
    MCC_GROUP_CARE, 0, 'lucide:heart-handshake', "Women's Ministry", NULL, 'Wednesday evenings',
    'Bible study on Wednesday evenings, retreats, and friendship for women of the church and community.',
    36,
    [
      ['Charm is deceptive, and beauty is fleeting; but a woman who fears the Lord is to be praised.', 'Proverbs 31:29-30'],
      ['Likewise, teach the older women to be reverent in the way they live, not to be slanderers or addicted to much wine, but to teach what is good. Then they can train the younger women to love their husbands and children, to be self-controlled and pure, to be busy at home, to be kind and to be subject to their husbands, so that no one will malign the work of God.', 'Titus 2:3-5'],
    ],
  ],
  35 => [
    MCC_GROUP_CARE, 1, 'lucide:utensils', 'C.A.R.E. Ministry', 'Christians Are Reaching Everyone', 'Meets quarterly',
    'Funeral dinners, meals for families after an illness or a new baby, and our Veterans Day event.',
    34,
    [['Take heed to yourselves and to all the flock, in which the Holy Spirit has made you overseers, to care for the church of God, which He obtained with the blood of His own Son.', 'Acts 20:28']],
  ],
  31 => [
    MCC_GROUP_CARE, 2, 'lucide:hammer', NULL, NULL, NULL,
    'Work days, mowing, repairs, and the upkeep that keeps our buildings and grounds in good order.',
    1215,
    [['Whatever you do, work at it with all your heart, as working for the Lord, not for men.', 'Colossians 3:23']],
  ],
  1100 => [
    MCC_GROUP_CARE, 3, 'lucide:siren', 'Emergency Response Team', NULL, NULL,
    'A trained team ready to help neighbors after storms, fires, and other emergencies in the county.',
    25, [],
  ],
  // No summary on purpose — the card's empty state is the ask for one.
  1098 => [
    MCC_GROUP_CARE, 4, 'lucide:hand-heart', NULL, NULL, NULL,
    NULL,
    503, [],
  ],

  // --- Missions & Outreach ----------------------------------------------
  40 => [
    MCC_GROUP_MISSIONS, 0, 'lucide:globe', NULL, NULL, NULL,
    'Hanging Rock camps, Love INC, Purdue Campus House, and benevolence for neighbors in need.',
    1605,
    [['But you will receive power when the Holy Spirit comes on you; and you will be my witnesses in Jerusalem, and in all Judea and Samaria, and the ends of the earth.', 'Acts 1:8']],
  ],
  46 => [
    MCC_GROUP_MISSIONS, 1, NULL, NULL, NULL, NULL,
    'Camps and retreats that help individuals, families and churches strengthen their relationship with Christ.',
    NULL, [],
  ],
  45 => [
    MCC_GROUP_MISSIONS, 2, NULL, NULL, NULL, NULL,
    'A network of Boone County churches and agencies meeting neighbors’ practical needs in hard seasons.',
    NULL, [],
  ],
];

// ---------------------------------------------------------------------------
// 1. Bundle conversion.
//
// Drupal has no API for moving a node between bundles, so this is deliberate
// table surgery. It stays safe because the five nodes carry exactly one
// fielded value between them (field_content, on three of them) and that field
// exists on `ministry` too — there is no data to strand. The guard below
// re-checks that rather than trusting this comment.
// ---------------------------------------------------------------------------
$db = \Drupal::database();
$entity_field_manager = \Drupal::service('entity_field.manager');
$ministry_fields = array_keys($entity_field_manager->getFieldDefinitions('node', 'ministry'));

$to_convert = $db->select('node_field_data', 'n')
  ->fields('n', ['nid'])
  ->condition('nid', MCC_CONVERT_TO_MINISTRY, 'IN')
  ->condition('type', 'ministry', '<>')
  ->execute()
  ->fetchCol();

if ($to_convert) {
  // Any field table holding data for these nodes has to name a field the
  // ministry bundle also has, or converting would orphan it.
  $stranded = [];
  foreach ($entity_field_manager->getFieldDefinitions('node', 'page') as $name => $definition) {
    $storage = $definition->getFieldStorageDefinition();
    if ($storage->isBaseField() || in_array($name, $ministry_fields, TRUE)) {
      continue;
    }
    $table = 'node__' . $name;
    if (!$db->schema()->tableExists($table)) {
      continue;
    }
    $rows = (int) $db->select($table, 't')
      ->condition('entity_id', $to_convert, 'IN')
      ->countQuery()->execute()->fetchField();
    if ($rows) {
      $stranded[$name] = $rows;
    }
  }
  if ($stranded) {
    print "ABORTING — these fields hold data that the ministry bundle cannot keep:\n";
    foreach ($stranded as $name => $rows) {
      print "  $name ($rows rows)\n";
    }
    return;
  }

  $db->update('node')->fields(['type' => 'ministry'])
    ->condition('nid', $to_convert, 'IN')->execute();
  $db->update('node_field_data')->fields(['type' => 'ministry'])
    ->condition('nid', $to_convert, 'IN')->execute();

  // Every field table's `bundle` column has to follow, current and revision.
  foreach ($ministry_fields as $name) {
    foreach (['node__' . $name, 'node_revision__' . $name] as $table) {
      if (!$db->schema()->tableExists($table)) {
        continue;
      }
      $db->update($table)->fields(['bundle' => 'ministry'])
        ->condition('entity_id', $to_convert, 'IN')->execute();
    }
  }

  \Drupal::entityTypeManager()->getStorage('node')->resetCache($to_convert);
  printf("converted %d node(s) to the ministry bundle: %s\n", count($to_convert), implode(', ', $to_convert));
}
else {
  print "bundle conversion: nothing to do\n";
}

// ---------------------------------------------------------------------------
// 2. Field values.
// ---------------------------------------------------------------------------
$term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
$node_storage = \Drupal::entityTypeManager()->getStorage('node');

$tids = [];
foreach ($term_storage->loadByProperties(['vid' => 'mcc_ministry_groups']) as $term) {
  $tids[$term->label()] = (int) $term->id();
}

foreach (MCC_MINISTRIES as $nid => $values) {
  [$group, $weight, $icon, $display_title, $subtitle, $schedule, $summary, $leader, $verses] = $values;

  $node = $node_storage->load($nid);
  if (!$node) {
    print "SKIP: no node $nid\n";
    continue;
  }
  if (!isset($tids[$group])) {
    print "SKIP: no '$group' term — run scripts/ministry-groups.php first\n";
    continue;
  }

  $node->set('field_ministry_group', ['target_id' => $tids[$group]]);
  $node->set('field_weight', $weight);
  $node->set('field_summary', $summary);
  $node->set('field_subtitle', $subtitle);

  // The two mission partners share only the fields the card needs.
  if ($node->bundle() === 'ministry') {
    $node->set('field_display_title', $display_title);
    $node->set('field_schedule', $schedule);
    // field_icon is core's Icon API type: the value is a `pack:icon` id, and
    // the widget is a searchable picker over the whole pack.
    $node->set('field_icon', $icon ? ['target_id' => $icon] : NULL);
    if ($leader) {
      $node->set('field_bio_reference', ['target_id' => $leader]);
    }
    // Paired by position: the reference at delta N belongs to the verse at
    // delta N. Written together so they cannot drift apart.
    $node->set('field_verse_text', array_column($verses, 0));
    $node->set('field_verse_reference', array_column($verses, 1));
  }

  // The aliases are pinned in scripts/ia-page-slugs.php; saving must not let
  // pathauto regenerate them from /ministries/[node:title].
  if ($node->hasField('path')) {
    $node->get('path')->pathauto = PathautoState::SKIP;
  }
  $node->save();

  printf(
    "%-5d %-28s %-22s w=%d %s%s%s\n",
    $nid,
    mb_strimwidth($display_title ?: $node->label(), 0, 28, '…'),
    $group,
    $weight,
    $icon ? "icon=$icon " : '',
    $leader ? 'leader ' : '',
    $verses ? count($verses) . ' verse(s)' : ''
  );
}

print "Done.\n";
