<?php

/**
 * @file
 * Seeds the Ministry Groups vocabulary — the three editorial groups the
 * /ministries listing is built from.
 *
 * Run with: ddev drush php:script scripts/ministry-groups.php
 *
 * Nothing about a group is hardcoded in Twig, CSS or PHP. The listing is two
 * views, the same shape /about/leadership uses:
 *
 * - name                 — the group's <h2>.
 * - weight               — the order the groups appear in. This is the
 *   vocabulary's own term order, so dragging terms at
 *   /admin/structure/taxonomy/manage/mcc_ministry_groups/overview reorders the
 *   page, and adding a fourth group makes a fourth section appear with no
 *   developer involved.
 * - description          — the blurb to the right of the heading.
 * - field_group_eyebrow  — the small uppercase line above the heading.
 *
 * A group with no published ministries in it is skipped rather than rendered
 * as a heading over a hole.
 *
 * Idempotent, but it does overwrite copy edited in the UI — the copy below came
 * from the design handoff as a draft and the church is expected to rewrite it.
 * Once they have, delete the corresponding entry here rather than letting this
 * script stomp their words on the next migration re-import.
 */

use Drupal\taxonomy\Entity\Term;

/**
 * Group name => [weight, eyebrow, blurb].
 */
const MCC_MINISTRY_GROUPS = [
  'Worship & Discipleship' => [
    0,
    'Sunday mornings',
    'Everything that happens when the church gathers — for every age in the building.',
  ],
  'Care & Community' => [
    1,
    'Serving one another',
    'Meals, benevolence, and the practical work of looking after this church and this county.',
  ],
  'Missions & Outreach' => [
    2,
    'Beyond our walls',
    'Support for gospel work here in Boone County and around the world.',
  ],
];

$storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

foreach (MCC_MINISTRY_GROUPS as $name => [$weight, $eyebrow, $blurb]) {
  $existing = $storage->loadByProperties([
    'vid' => 'mcc_ministry_groups',
    'name' => $name,
  ]);
  $term = $existing ? reset($existing) : Term::create([
    'vid' => 'mcc_ministry_groups',
    'name' => $name,
  ]);

  $term->setWeight($weight);
  $term->set('field_group_eyebrow', $eyebrow);
  $term->set('description', [
    'value' => $blurb,
    'format' => 'content_format',
  ]);
  $term->save();

  printf("%-22s weight=%d  %s\n", $name, $weight, $eyebrow);
}

print "Done.\n";
