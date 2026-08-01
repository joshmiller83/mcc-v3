<?php

/**
 * @file
 * Clears the D7 migration residue out of the ministry bodies, lifts the
 * "Time Commitment" paragraph into its own field, and fixes two pieces of
 * broken data the listing exposes.
 *
 * Run with: ddev drush php:script scripts/ministries-cleanup.php
 *
 * The bodies came out of Word by way of D7, and carry three things that render
 * as real defects rather than as ugly source:
 *
 * - `&nbsp;`-only paragraphs, which are *not* invisible — each one is a real
 *   empty line in the rendered page, and the Women's body ends with five.
 * - `<p>` inside `<li>`, which double-spaces every bullet.
 * - `style` attributes pinning Calibri, 11pt and #000000 onto text the theme is
 *   trying to set in Nunito at the body size, plus `mso-*` properties that mean
 *   nothing outside Word.
 *
 * The "Time Commitment:" section is different in kind: it is good content in
 * the wrong place. It addresses somebody already deciding whether to serve, not
 * somebody deciding whether to visit, so the detail page renders it as a
 * distinct aside — which it can only do once it is its own field.
 *
 * Idempotent. Every step tests for the thing it removes, so a second run is a
 * no-op, and the time-commitment lift only fires while the marker is still in
 * the body — it will not overwrite an edit made afterwards in the UI.
 */

use Drupal\Component\Utility\Html;

/**
 * Ministries whose bodies came through the D7 migration.
 */
const MCC_MINISTRY_BODIES = [8, 10, 11, 12, 13, 31, 35, 38, 40, 45, 46];

/**
 * The heading that separates duties from the time commitment.
 */
const MCC_TIME_COMMITMENT_MARKER = 'time commitment';

/**
 * Strips a node list to a plain array, so it can be walked while mutating.
 */
function mcc_dom_list(\DOMNodeList $list): array {
  return iterator_to_array($list);
}

/**
 * Everything in the document that comes after a node, in reading order.
 *
 * The "Time Commitment:" heading is not reliably a top-level paragraph — the
 * D7 editor left C.A.R.E.'s, Building & Grounds' and Missions' inside the last
 * <li> of the duty list, behind a run of fifty-odd `&nbsp;`. So the split
 * cannot just take following siblings: it has to climb out of whatever the
 * marker is nested in, collecting each level's remainder on the way up.
 * Collected innermost-first, which is already document order.
 *
 * @return \DOMNode[]
 *   The nodes following the marker, excluding the marker itself.
 */
function mcc_dom_nodes_after(\DOMNode $marker): array {
  $tail = [];
  for ($current = $marker; $current && $current->parentNode; $current = $current->parentNode) {
    for ($sibling = $current->nextSibling; $sibling; $sibling = $sibling->nextSibling) {
      $tail[] = $sibling;
    }
    if (strtolower($current->parentNode->nodeName) === 'body') {
      break;
    }
  }
  return $tail;
}

/**
 * Collapses `&nbsp;` and whitespace runs to single spaces, in place.
 *
 * Lifting the time commitment out of a list item leaves the padding that used
 * to separate it — a run of non-breaking spaces is a visible trail of blank
 * space at the end of a bullet, not invisible source noise.
 */
function mcc_dom_normalize_space(\DOMXPath $xpath): void {
  foreach (mcc_dom_list($xpath->query('//body//text()')) as $text) {
    $collapsed = preg_replace('/(?:\xc2\xa0|\s)+/u', ' ', $text->nodeValue);
    if ($collapsed !== $text->nodeValue) {
      $text->nodeValue = $collapsed;
    }
  }
}

/**
 * True when an element carries nothing a reader would see.
 *
 * Text is tested after `&nbsp;` is folded to a space, because a paragraph
 * holding only a non-breaking space is exactly the case this is here for. An
 * element wrapping an image or an iframe is never empty, whatever its text.
 */
function mcc_dom_is_empty(\DOMElement $element): bool {
  foreach (['img', 'iframe', 'hr', 'br', 'input', 'drupal-media'] as $tag) {
    if ($element->getElementsByTagName($tag)->length) {
      return FALSE;
    }
  }
  $text = str_replace("\xc2\xa0", ' ', $element->textContent);
  return trim($text) === '';
}

$node_storage = \Drupal::entityTypeManager()->getStorage('node');

// ---------------------------------------------------------------------------
// 1. Body cleanup + the time-commitment lift.
// ---------------------------------------------------------------------------
foreach (MCC_MINISTRY_BODIES as $nid) {
  $node = $node_storage->load($nid);
  if (!$node || !$node->hasField('field_content') || $node->get('field_content')->isEmpty()) {
    continue;
  }

  $original = $node->get('field_content')->value;
  $format = $node->get('field_content')->format;
  $document = Html::load($original);
  $xpath = new \DOMXPath($document);
  $changes = [];

  // --- Lift the time commitment. ---------------------------------------
  // Everything from the "Time Commitment:" heading to the end of the body is
  // the aside; the heading itself is dropped, because the detail page draws
  // its own label.
  $commitment = '';
  if ($node->hasField('field_time_commitment')) {
    // The innermost element whose text opens with the marker: on three of the
    // four bodies that is a <strong> inside an <li>, not a paragraph.
    $marker = NULL;
    foreach (mcc_dom_list($xpath->query('//body//*[not(*)]')) as $element) {
      $text = strtolower(trim(str_replace("\xc2\xa0", ' ', $element->textContent)));
      if (str_starts_with($text, MCC_TIME_COMMITMENT_MARKER)) {
        $marker = $element;
        break;
      }
    }
    if ($marker) {
      foreach (mcc_dom_nodes_after($marker) as $element) {
        $commitment .= $element->textContent . ' ';
        $element->parentNode->removeChild($element);
      }
      $marker->parentNode->removeChild($marker);

      $commitment = trim(preg_replace('/(?:\xc2\xa0|\s)+/u', ' ', $commitment));
      if ($commitment !== '') {
        $node->set('field_time_commitment', $commitment);
        $changes[] = 'time commitment lifted';
      }
    }
  }

  // --- Drop presentational attributes. ----------------------------------
  // All of them: every style, class and Word `lang`/`dir` on these bodies is
  // migration residue, and the theme is the only thing that should be setting
  // a typeface or a colour on body copy.
  $stripped = 0;
  foreach (mcc_dom_list($xpath->query('//body//*[@style or @class or @lang or @dir or @align]')) as $element) {
    foreach (['style', 'class', 'lang', 'dir', 'align'] as $attribute) {
      if ($element->hasAttribute($attribute)) {
        $element->removeAttribute($attribute);
        $stripped++;
      }
    }
  }
  if ($stripped) {
    $changes[] = "$stripped attribute(s) stripped";
  }

  // --- Unwrap <p> inside <li>. ------------------------------------------
  $unwrapped = 0;
  foreach (mcc_dom_list($xpath->query('//li/p')) as $paragraph) {
    while ($paragraph->firstChild) {
      $paragraph->parentNode->insertBefore($paragraph->firstChild, $paragraph);
    }
    $paragraph->parentNode->removeChild($paragraph);
    $unwrapped++;
  }
  if ($unwrapped) {
    $changes[] = "$unwrapped <p> unwrapped from <li>";
  }

  // --- Unwrap now-bare <span> and <font>. -------------------------------
  // Once the style attributes are gone these carry nothing at all.
  foreach (mcc_dom_list($xpath->query('//body//span[not(@*)] | //body//font[not(@*)]')) as $span) {
    while ($span->firstChild) {
      $span->parentNode->insertBefore($span->firstChild, $span);
    }
    $span->parentNode->removeChild($span);
  }

  // --- Drop empty block elements. ---------------------------------------
  // Innermost first, so a <p> holding only an emptied <span> goes too.
  $removed = 0;
  foreach (array_reverse(mcc_dom_list($xpath->query('//body//p | //body//div | //body//li | //body//strong | //body//em'))) as $element) {
    if ($element->parentNode && mcc_dom_is_empty($element)) {
      $element->parentNode->removeChild($element);
      $removed++;
    }
  }
  if ($removed) {
    $changes[] = "$removed empty element(s) removed";
  }

  mcc_dom_normalize_space($xpath);

  // Serialize, then close the gap a collapsed run leaves against a block edge:
  // "<li> Serve drinks and food … </li>" reads as a stray indent.
  $cleaned = trim(Html::serialize($document));
  $cleaned = preg_replace('#(<(?:p|li|h[1-6]|div|td)\b[^>]*>)\s+#i', '$1', $cleaned);
  $cleaned = preg_replace('#\s+(</(?:p|li|h[1-6]|div|td)>)#i', '$1', $cleaned);
  if ($cleaned !== trim($original)) {
    $node->set('field_content', ['value' => $cleaned, 'format' => $format]);
  }
  elseif (!$changes) {
    printf("%-5d %s — clean already\n", $nid, mb_strimwidth($node->label(), 0, 30, '…'));
    continue;
  }

  $node->save();
  printf("%-5d %-30s %s\n", $nid, mb_strimwidth($node->label(), 0, 30, '…'), implode('; ', $changes) ?: 'whitespace only');
}

// ---------------------------------------------------------------------------
// 2. The Youth calendar event points at a node in the trash.
//
// Node 1223 is a duplicate Youth ministry that was trashed without moving its
// one calendar event across, so Youth showed no upcoming dates while its event
// sat on a deleted node. Repoint first, then purge — in that order, so the
// event is never briefly orphaned.
// ---------------------------------------------------------------------------
const MCC_TRASHED_YOUTH = 1223;
const MCC_YOUTH = 8;

/** @var \Drupal\trash\TrashManagerInterface $trash */
$trash = \Drupal::service('trash.manager');

$repointed = $trash->executeInTrashContext('ignore', function () use ($node_storage) {
  $events = $node_storage->loadByProperties([
    'type' => 'calendar_event',
    'field_related_ministry' => MCC_TRASHED_YOUTH,
  ]);
  foreach ($events as $event) {
    $targets = [];
    foreach ($event->get('field_related_ministry') as $item) {
      $target = (int) $item->target_id === MCC_TRASHED_YOUTH ? MCC_YOUTH : (int) $item->target_id;
      $targets[$target] = ['target_id' => $target];
    }
    $event->set('field_related_ministry', array_values($targets));
    $event->save();
    printf("repointed event %d (%s) to node %d\n", $event->id(), $event->label(), MCC_YOUTH);
  }
  return count($events);
});

if (!$repointed) {
  print "youth event: nothing to repoint\n";
}

$trashed = $trash->executeInTrashContext('ignore', function () use ($node_storage) {
  return $node_storage->load(MCC_TRASHED_YOUTH);
});
if ($trashed) {
  $trash->executeInTrashContext('ignore', function () use ($trashed) {
    $trashed->delete();
  });
  printf("purged trashed duplicate node %d\n", MCC_TRASHED_YOUTH);
}

// ---------------------------------------------------------------------------
// 3. Duplicate aliases.
//
// Nodes 8 and 11 each carry a bare D7 alias alongside the /ministries one.
// Two live aliases for one node means the canonical URL is whichever the alias
// manager happens to pick, which is not a thing to leave to chance on the two
// ministries the rest of the site links to most.
//
// mcc_retire_stale_aliases() is the same helper scripts/ia-page-slugs.php uses:
// it deletes the extra alias *before* writing the 301, because a live alias is
// resolved during inbound path processing and so silently shadows a redirect.
// ---------------------------------------------------------------------------
require_once __DIR__ . '/ia-page-slugs.inc.php';

foreach ([8 => '/ministries/youth', 11 => '/ministries/worship-service'] as $nid => $keep) {
  mcc_retire_stale_aliases($nid, $keep);
}

print "Done.\n";
