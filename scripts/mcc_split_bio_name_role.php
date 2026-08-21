<?php

/**
 * @file
 * Splits each bio's mixed "Name - Role" title into a name and a field_role.
 *
 * The legacy Drupal 7 site kept both in the node title, inconsistently:
 * "Brian Hoffman -  Emergency Response Team" has a delimiter and a double
 * space, "Alan Martin Finance " has neither, "Jim Garland " is a bare name with
 * trailing whitespace. Both designed surfaces render the name and the role as
 * separate typographic elements, so they have to be separate values.
 *
 * A regex cannot do this — nothing in "Alan Martin Finance" says where the name
 * ends — so the split is an explicit table, keyed by node ID and checked
 * against the title we expect to find. If a title has since been edited by
 * hand, that row is skipped rather than silently overwritten.
 *
 * Bios are aliased by Pathauto (bio_path: /about/leadership/[node:title]), so
 * renaming a bio regenerates its alias on save and the redirect module keeps
 * the old URL working. Nothing here touches an alias directly.
 *
 * Re-runnable, and worth re-running after a migration re-import: mcc_bio maps
 * the D7 title straight through, so an import puts the mixed titles back.
 *
 * Run with: ddev drush php:script scripts/mcc_split_bio_name_role.php
 */

$node_storage = \Drupal::entityTypeManager()->getStorage('node');

// nid => [legacy title as migrated, person's name, role]
//
// An empty role means the legacy title carried no role at all. Those cards
// show the name and the group chip and nothing else, which is the intended
// empty state — do not invent a role to fill the gap.
$bios = [
  25 => ['Brian Hoffman -  Emergency Response Team', 'Brian Hoffman', 'Emergency Response Team'],
  26 => ['Mike Smith', 'Mike Smith', ''],
  33 => ['Jim Garland ', 'Jim Garland', ''],
  34 => ['Jane Mohler - CARE', 'Jane Mohler', 'CARE'],
  36 => ["Pat Garland - Communications & Women's", 'Pat Garland', "Communications & Women's"],
  // Not a person — a standing notice that the pulpit is vacant. It is the only
  // member of the Senior Minister group, so it renders as the featured card.
  342 => ['Minister Search Underway', 'Minister Search Underway', ''],
  346 => ['Jon Culbertson', 'Jon Culbertson', ''],
  503 => ['Aaron Lucas - Prayer/Outreach', 'Aaron Lucas', 'Prayer & Outreach'],
  504 => ['Alan Martin Finance ', 'Alan Martin', 'Finance'],
  1214 => ['Gary Allen', 'Gary Allen', ''],
  // The migration now emits every group name into this title ("Deacons &
  // Buildings & Grounds"), not just the one the legacy site carried, so the
  // expected-title guard has to match that or the row is skipped.
  1215 => ['John Weidman Deacons & Buildings & Grounds', 'John Weidman', 'Buildings & Grounds'],
  1602 => ['Bob Kline', 'Bob Kline', ''],
  1603 => ['Maria Weidman - Youth', 'Maria Weidman', 'Youth'],
  1604 => ['Jon Culbertson - Finance', 'Jon Culbertson', 'Finance'],
  1605 => ['Kerry Dull - Missions & Treasurer', 'Kerry Dull', 'Missions & Treasurer'],
  1606 => ['Ellen Bailey - Education', 'Ellen Bailey', 'Education'],
  1607 => ['Gary Allen', 'Gary Allen', ''],
  1608 => ['Jody Durham - Guest Services', 'Jody Durham', 'Guest Services'],
  // "Monthly" is what the legacy title said. It is almost certainly shorthand
  // for something ("Monthly newsletter"?) — left verbatim rather than guessed
  // at, for the office to correct.
  1610 => ['Myrtle Cox - Monthly', 'Myrtle Cox', 'Monthly'],
  1609 => ['Robin Lynn - Music & Worship', 'Robin Lynn', 'Music & Worship'],
];

$changed = 0;
$skipped = 0;

foreach ($bios as $nid => [$legacy_title, $name, $role]) {
  $node = $node_storage->load($nid);
  if (!$node || $node->bundle() !== 'bio') {
    print sprintf("SKIP %-5d no bio node\n", $nid);
    $skipped++;
    continue;
  }

  $title = $node->label();
  $already_done = $title === $name && (string) $node->get('field_role')->value === $role;
  if ($already_done) {
    continue;
  }

  // Anything other than the title we migrated means a human has been here
  // since. Leave their edit alone.
  if ($title !== $legacy_title && $title !== $name) {
    print sprintf("SKIP %-5d title is now %s\n", $nid, var_export($title, TRUE));
    $skipped++;
    continue;
  }

  $node->setTitle($name);
  $node->set('field_role', $role === '' ? NULL : $role);
  $node->setNewRevision(TRUE);
  $node->setRevisionLogMessage('Split the migrated "Name - Role" title into the title and field_role.');
  $node->setRevisionCreationTime(\Drupal::time()->getRequestTime());
  $node->save();

  print sprintf("%-5d %-22s %s\n", $nid, $name, $role);
  $changed++;
}

print sprintf("Done. %d updated, %d skipped.\n", $changed, $skipped);
