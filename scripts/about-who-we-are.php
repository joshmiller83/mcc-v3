<?php

/**
 * @file
 * Applies the level-3 band/typography treatment to /about/who-we-are
 * (node 3), without inventing content it doesn't have.
 *
 * Run with:
 *   ddev drush php:script scripts/about-who-we-are.php
 *
 * Why this exists
 * ---------------
 * Node 3 has one real paragraph (already duplicated as the vision statement
 * on the About landing itself) — not the five-section "rural congregation /
 * a Sunday here / how we're governed / who we serve / how to connect"
 * structure the design handoff suggests. That structure has no source
 * content behind it, and AGENTS.md says not to invent specifics about a real
 * congregation. So this only cleans the existing paragraph into proper
 * markup and turns on field_section_nav for the title band and body
 * typography; mcc-page-body.js hides the sidebar itself when it finds fewer
 * than two `<h2>`s, so a single-section page here doesn't render a
 * pointless one-item "On this page" nav. Richer sections are a follow-up
 * once the church has more to say.
 *
 * Idempotent: overwrites field_content/field_description/field_section_nav
 * in full.
 */

$body = <<<'HTML'
<p>The vision of Mechanicsburg Christian Church is to be a church designed by God himself. We are known for worship services of celebration and praise, dedicated to being a church where special talents and gifts are developed and used for the glory of God, and a place where people are lifted and encouraged through teaching and prayer.</p>
<p>We strive to maintain a diversity of ministries that move believers into the world to serve Christ and each other in everyday relationships. Mechanicsburg Christian Church pursues the expansion of God's kingdom, both in membership and in facilities &mdash; honoring what our predecessors have accomplished, and building toward what future generations can aspire to.</p>
HTML;

$node = \Drupal::entityTypeManager()->getStorage('node')->load(3);
if (!$node) {
  print "no node 3 (/about/who-we-are)\n";
  return;
}

$node->set('field_content', ['value' => $body, 'format' => 'content_format']);
$node->set('field_description', "Who Mechanicsburg Christian Church is, and the vision we hold for worship, gifts, and service.");
$node->set('field_section_nav', TRUE);
$node->save();

print "updated node 3 (/about/who-we-are): section_nav on\n";
