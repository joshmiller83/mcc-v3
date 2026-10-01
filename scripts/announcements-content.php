<?php

/**
 * @file
 * The five announcements that came over from D7 with a flyer: each one's
 * picture cropped out of the flyer, and the flyer's text typed into the body.
 *
 * Run with:
 *   ddev drush php:script scripts/announcements-content.php
 * and on the Pantheon sandbox, once it is reachable:
 *   ddev exec terminus remote:drush mcc2026.dev -- php:script scripts/announcements-content.php
 *
 * Why this exists
 * ---------------
 * D7's "Homepage Teaser" image field held 1275x1650 flyers that are really a
 * page of Word text with a picture in one corner — a book cover, a road sign,
 * a photo of the Shalom House sign — and lots of white space. On the front
 * page card the picture was a thumbnail and the text was unreadable. The
 * church asked, once, for each announcement to be rebuilt the obvious way:
 * the picture as the announcement's image and the flyer's words as its body.
 *
 * This is a one-time content fix, not a migration rule. It is a script rather
 * than a hand edit for the same reason every other content change here is:
 * content lives in the database and does not deploy with code, and a
 * mcc_announcement --update would hand the flyers and the empty bodies
 * straight back. Re-running this after that restores the five.
 *
 * The crops ship in the theme (images/announcements/) so they travel with the
 * code; this copies them into the files directory and mints one media entity
 * each, keyed by name, so a re-run reuses them. The original flyer media are
 * left in the media library untouched. Nodes are found through the migrate
 * map by their D7 id, with the title as a guard — the same guard
 * BioDuplicateMerger uses — so a record someone has since repurposed is left
 * alone rather than overwritten.
 *
 * Wording is the flyers' own. Capitalisation is sentence case where a flyer
 * shouted, "@" is "at", and the longer texts are split into paragraphs.
 */

use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;

// Keyed by D7 nid.
$announcements = [
  1624 => [
    'slug' => 'adult-sunday-school',
    'title' => 'Adult Sunday School Class',
    'media_name' => 'Announcement — Adult Sunday School Class',
    'alt' => 'Join us for Bible Study: All the Books of the Bible. One story, one message, one God.',
    'body' => '<p><strong>New Adult Sunday School class: All the Books of the Bible</strong> begins September 13. Who wrote each book? Why? What is the basic content? We\'ll answer these and other questions as we explore the key themes and people, and important ideas.</p>'
      . '<p>Bring your Bible and a notebook with at least 66 pages for taking notes. As people of the Bible it is important that we have a firm understanding of what it is and what is in it.</p>',
  ],
  1621 => [
    'slug' => 'womens-bible-study',
    'title' => "Women's Bible Study",
    'media_name' => "Announcement — Women's Bible Study",
    'alt' => 'Song of Songs: The Love We Long For, a Bible study guide by Lisa Harper.',
    'body' => '<p>Discover the divine love story written for us. What if the greatest love story ever told was about the beautifully intimate bond we can enjoy with God? In this Bible study of the Song of Songs, Bible teacher Lisa Harper unveils an eye-opening truth: this ancient, often-misunderstood book that sometimes reads like a racy romance novel is actually a passionate declaration of our Creator Redeemer\'s relentless love for His people. With her signature blend of humor, deep biblical insight, and raw vulnerability, Lisa peels back the layers of history and allegory to reveal Jesus as the Divine Bridegroom who didn\'t just come to deliver us — He delights in us! You are the object of God\'s affection!</p>'
      . '<p>Join us Wednesdays at 6:45 PM for prayers, joys and concerns. The lesson begins at 7. Come in through the brown office door; we meet in the back office.</p>',
  ],
  1611 => [
    'slug' => 'young-adult-bible-study',
    'title' => 'Young Adult Bible Study',
    'media_name' => 'Announcement — Young Adult Bible Study',
    'alt' => 'Young Adult Bible Study: an open Bible beneath a cross.',
    'body' => '<p>Young Adult Bible Study meets on Wednesdays at 7 PM at the home of Josh &amp; Elizabeth Miller. This is an open group, so anyone is welcome! See Rod Nielson if you have questions.</p>',
  ],
  1208 => [
    'slug' => 'shalom-meals',
    'title' => 'Shalom Meals',
    'media_name' => 'Announcement — Shalom Meals',
    'alt' => 'The carved wooden Shalom House sign, established 2005.',
    'body' => '<p>MCC serves a meal at Shalom House on the Tuesdays we are scheduled, from 3:30 to 7 PM. If you would like to be involved, contact Jon and Paula Culbertson for details and to sign up.</p>',
  ],
  1156 => [
    'slug' => 'highway-52-youth-group',
    'title' => 'Highway 52 Youth Group',
    'media_name' => 'Announcement — Highway 52 Youth Group',
    'alt' => 'A US Highway 52 route shield.',
    'body' => '<p>Highway 52 Youth Group meets on the second Sunday of each month, immediately following church. Lunch will be provided. Contact Maria Weidman if you have any questions.</p>',
  ],
];

$database = \Drupal::database();
$file_system = \Drupal::service('file_system');
$theme_path = \Drupal::service('extension.list.theme')->getPath('mcc_theme');
$media_storage = \Drupal::entityTypeManager()->getStorage('media');

if (!$database->schema()->tableExists('migrate_map_mcc_announcement')) {
  print "no migrate_map_mcc_announcement table — run migrate:import mcc_announcement first\n";
  return;
}

foreach ($announcements as $source_nid => $a) {
  $nid = $database->select('migrate_map_mcc_announcement', 'm')
    ->fields('m', ['destid1'])
    ->condition('sourceid1', $source_nid)
    ->execute()
    ->fetchField();
  $node = $nid ? Node::load($nid) : NULL;
  if (!$node) {
    printf("  ! D7 node %d (%s) has not been imported — skipping\n", $source_nid, $a['title']);
    continue;
  }
  if ($node->bundle() !== 'announcement' || $node->label() !== $a['title']) {
    printf("  ! node %d is not the announcement '%s' (it is '%s' %s) — skipping\n", $node->id(), $a['title'], $node->label(), $node->bundle());
    continue;
  }

  // The picture: look the media up by name so a re-run reuses it.
  $existing = $media_storage->loadByProperties(['bundle' => 'image', 'name' => $a['media_name']]);
  $source = sprintf('%s/images/announcements/%s.jpg', $theme_path, $a['slug']);
  if (!file_exists($source)) {
    printf("  ! missing %s — skipping %s\n", $source, $a['title']);
    continue;
  }
  $directory = 'public://announcements';
  if ($existing) {
    $media = reset($existing);
    // A database dump carries the media entity to another environment but
    // not the file on disk. Put the picture back from the theme if it is
    // missing, so the dump plus this script is a complete hand-off.
    $file = $media->get('field_media_image')->entity;
    if ($file && !file_exists($file->getFileUri())) {
      $file_system->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
      $file_system->copy($source, $file->getFileUri(), FileSystemInterface::EXISTS_REPLACE);
      printf("  restored %s from the theme\n", $file->getFileUri());
    }
  }
  else {
    $file_system->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
    $uri = $file_system->copy($source, sprintf('%s/%s.jpg', $directory, $a['slug']), FileSystemInterface::EXISTS_REPLACE);

    $file = File::create(['uri' => $uri]);
    $file->setPermanent();
    $file->save();

    $media = Media::create([
      'bundle' => 'image',
      'name' => $a['media_name'],
      'field_media_image' => [
        'target_id' => $file->id(),
        'alt' => $a['alt'],
      ],
      'status' => 1,
    ]);
    $media->save();
    printf("  created media '%s' (id %d)\n", $a['media_name'], $media->id());
  }

  $changed = [];
  if ((int) $node->get('field_featured_image')->target_id !== (int) $media->id()) {
    $node->set('field_featured_image', ['target_id' => $media->id()]);
    $changed[] = 'picture';
  }
  $body = $node->get('body');
  if ($body->value !== $a['body'] || $body->format !== 'content_format') {
    $node->set('body', ['value' => $a['body'], 'format' => 'content_format']);
    $changed[] = 'body';
  }

  if ($changed) {
    $node->setNewRevision(TRUE);
    $node->setRevisionLogMessage('Picture cropped out of the D7 flyer and its text moved into the body (scripts/announcements-content.php).');
    $node->save();
    printf("  %s (node %d): updated %s\n", $node->label(), $node->id(), implode(' and ', $changed));
  }
  else {
    printf("  %s (node %d): unchanged\n", $node->label(), $node->id());
  }
}
