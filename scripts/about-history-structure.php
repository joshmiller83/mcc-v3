<?php

/**
 * @file
 * Restructures /about/history (node 101) into the level-3 sidebar-nav
 * template.
 *
 * Run with:
 *   ddev drush php:script scripts/about-history-structure.php
 *
 * Why this exists
 * ---------------
 * Node 101's field_content already carries the church's real history — every
 * building, cost and date below comes from that field, not invented for this
 * pass. It was stored as one un-sectioned blob (a bold-ish opening line, then
 * chronological paragraphs, ending in a preachers list) with no headings at
 * all. This breaks it into H2 sections so the level-3 template's
 * client-side table of contents (mcc-page-body.js, scanning for `<h2>`) has
 * something to build a sidebar from.
 *
 * Two deliberate departures from the design handoff's suggested section
 * list, both because AGENTS.md says not to invent unverified specifics about
 * a real congregation:
 * - The handoff's timeline skips the 1880 brick building and suggests a
 *   "$324,000 land expansion 2006" stewardship figure. Neither is in the
 *   source text, so the timeline here has 8 rows (not 7) and the
 *   stewardship band has 2 stat cards (the two dollar figures actually on
 *   record), not 4.
 * - The handoff's "aside card" is titled "Saved from the old church" (implying
 *   fire salvage). The source only documents a communion set restored by the
 *   youth group in 1973-74, not fire salvage specifically, so the card here
 *   describes what is actually recorded.
 *
 * The two photo placeholders (`history-gathering`, `history-quilts`) are
 * created as media and embedded via <drupal-media> — an already-allowed tag
 * in content_format (media_embed), so no filter change is needed.
 *
 * Idempotent: overwrites field_content and field_description in full, and
 * looks up media by name before creating it.
 */

use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;

const NODE_ID = 101;

$file_system = \Drupal::service('file_system');
$theme_path = \Drupal::service('extension.list.theme')->getPath('mcc_theme');
$media_storage = \Drupal::entityTypeManager()->getStorage('media');

$photos = [
  'history-gathering' => [
    'name' => 'Placeholder — history: the Gathering Place shelter',
    'alt' => 'Placeholder: the Gathering Place shelter, ideally in use — a meal, reunion or youth event.',
  ],
  'history-quilts' => [
    'name' => 'Placeholder — history: congregation quilts on the sanctuary wall',
    'alt' => 'Placeholder: the congregation quilts on the stone wall behind the pulpit.',
  ],
];

$media_uuids = [];
foreach ($photos as $slug => $info) {
  $existing = $media_storage->loadByProperties(['name' => $info['name']]);
  if ($existing) {
    $media = reset($existing);
  }
  else {
    $source = "$theme_path/images/placeholders/$slug.jpg";
    $directory = 'public://placeholders';
    $file_system->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
    $destination = "$directory/$slug.jpg";
    $uri = $file_system->copy($source, $destination, FileSystemInterface::EXISTS_REPLACE);
    $file = File::create(['uri' => $uri]);
    $file->setPermanent();
    $file->save();
    $media = Media::create([
      'bundle' => 'image',
      'name' => $info['name'],
      'field_media_image' => [
        'target_id' => $file->id(),
        'alt' => $info['alt'],
      ],
      'status' => 1,
    ]);
    $media->save();
  }
  $media_uuids[$slug] = $media->uuid();
  printf("%s: media %d (%s)\n", $slug, $media->id(), $media->uuid());
}

$embed = fn(string $slug, string $alt) => sprintf(
  '<drupal-media data-entity-type="media" data-entity-uuid="%s" data-view-mode="medium" alt="%s"></drupal-media>',
  $media_uuids[$slug],
  htmlspecialchars($alt, ENT_QUOTES)
);

$body = <<<HTML
<h2>1840 &mdash; Cut from this land</h2>
<p>In 1840, a small group of men organized a Christian Church, cut logs, and built a log church on a plot of ground given to them to be used only as a church &mdash; a thick woods, on or near the same ground the congregation still meets on today. That first building served for thirteen years before the congregation outgrew it.</p>
<blockquote>It has been said that Mechanicsburg is just a speck on the map. When the ground was dedicated, it was dedicated for church use only. It has stood the test for over 180 years.</blockquote>

<h2>1840&ndash;1985 &mdash; Five buildings, one congregation</h2>
<p>Every building the congregation has raised has stood on the same ground.</p>
<table>
<thead><tr><th>Year</th><th>What was built</th><th>Detail</th></tr></thead>
<tbody>
<tr><td>1840</td><td>Original log structure</td><td>Cut and raised by the congregation's founders; used 13 years.</td></tr>
<tr><td>1852</td><td>Timber frame building</td><td>Native lumber, faced east, two doors; used 27 years, later sold and used as a post office and blacksmith shop.</td></tr>
<tr><td>1880</td><td>Brick building</td><td>Faced south, used 32 years. A communion set of two pitchers, two cups and two plates was purchased in this period.</td></tr>
<tr><td>1912</td><td>Brick and frame meetinghouse</td><td>Built for \$4,500; used 40 years, until destroyed by fire in September 1961.</td></tr>
<tr><td>1962</td><td>Rebuilt sanctuary</td><td>Built for \$45,000 on newly purchased ground, dedicated August 19, 1962. Stands a little west of the 1912 building.</td></tr>
<tr><td>1974</td><td>Educational wing</td><td>Added to the north of the 1962 sanctuary for \$33,500.</td></tr>
<tr><td>1985</td><td>Worship center</td><td>Built for \$340,000; first service held February 24, 1985, dedicated June 30, 1985.</td></tr>
<tr><td>2015</td><td>"The Gathering Place" shelter</td><td>Built for the congregation's 175th anniversary.</td></tr>
</tbody>
</table>

<h2>1961&ndash;1962 &mdash; Fire, and eleven months</h2>
<p>In September 1961, fire destroyed the fourth church building &mdash; the brick and frame meetinghouse the congregation had used for forty years. Within a year, the congregation had purchased new ground from Ed and Rena Virtue and built again: the fifth building was dedicated August 19, 1962, a little west of where the old one stood.</p>
<blockquote>Communion history: the round communion tray and glass cups bought in the 1912 building's years were later replaced with silver cups when they proved too easily broken. In 1973&ndash;74, the church's youth surprised the congregation by presenting an earlier communion service they had restored, and it is still kept on display.</blockquote>

<h2>1974 &mdash; Room to teach, room to gather</h2>
<p>An educational wing was added to the north of the sanctuary in 1973&ndash;74, at a cost of \$33,500 &mdash; the first expansion after the 1962 rebuild, and the beginning of the additions that shaped the building the congregation still worships in.</p>

<h2>2006&ndash;2025 &mdash; The Gathering Place</h2>
<p>The congregation built "The Gathering Place" picnic shelter in 2015 to mark its 175th anniversary &mdash; built, as the church put it, "on faith with a promise of a bright future." In 2017, a new bus was purchased to continue the church's bus ministry with safe, reliable transportation. In 2021, electricity was added to the shelter, with light fixtures inside and out.</p>
\$embed_gathering
<p>The Gathering Place, built for the congregation's 175th anniversary in 2015.</p>

<h2>2006&ndash;2022 &mdash; Debt-free by design</h2>
<p>In March 2020, as the church closed its building during the Covid-19 pandemic and moved worship to Facebook Live, the congregation paid off its land loan in full &mdash; \$40,000, from savings, with no general offering used. By January 2022, the congregation had paid itself back, restoring that \$40,000 in savings entirely through designated giving.</p>
<table>
<thead><tr><th>Amount</th><th>What it did</th></tr></thead>
<tbody>
<tr><td>\$40,000</td><td>Land loan paid off, March 2020, from savings, no general offering used.</td></tr>
<tr><td>\$40,000</td><td>Reserve restored, January 2022, through designated giving alone.</td></tr>
</tbody>
</table>

<h2>Today &mdash; What we have kept</h2>
<p>The congregation still worships in the 1985 worship center, on the ground first dedicated in 1840. Quilts made by members of the congregation hang in the sanctuary today &mdash; a small, ordinary sign of the hands that have kept this church running for over 180 years.</p>
\$embed_quilts
<p>Congregation quilts in the sanctuary.</p>
HTML;

$body = str_replace(
  ['$embed_gathering', '$embed_quilts'],
  [
    $embed('history-gathering', 'The Gathering Place shelter'),
    $embed('history-quilts', 'Congregation quilts in the sanctuary'),
  ],
  $body
);

$node = \Drupal::entityTypeManager()->getStorage('node')->load(NODE_ID);
if (!$node) {
  print "no node " . NODE_ID . " (/about/history)\n";
  return;
}

$node->set('field_content', ['value' => $body, 'format' => 'content_format']);
$node->set('field_description', "185 years of Mechanicsburg Christian Church, from the log church cut in 1840 to the sanctuary that stands today.");
$node->set('field_section_nav', TRUE);
$node->save();

print "updated node " . NODE_ID . " (/about/history): 8 sections, section_nav on\n";
