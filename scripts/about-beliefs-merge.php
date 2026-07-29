<?php

/**
 * @file
 * Merges the five separate belief pages (nodes 18-22) into node 17
 * (/about/beliefs) as level-3 sidebar-nav sections, and retires the five old
 * URLs with 301s.
 *
 * Run with:
 *   ddev drush php:script scripts/about-beliefs-merge.php
 *
 * Why this exists
 * ---------------
 * "Our Beliefs" was a landing node (17, the "in essentials unity" statement)
 * plus five standalone doctrine pages (Salvation, Baptism, the Church,
 * Christ, the Bible — nodes 18-22). The design handoff's level-3 template
 * assumes one page with several sidebar-nav sections, the way
 * /about/history now works. This merges the five into node 17's
 * field_content as H2 sections, using each page's own real doctrinal
 * content — condensed where a page was a long apologetic wall of text
 * (node 21, Christ) or a dense verse-by-verse list, but not rewritten in
 * substance. It does not use the design handoff's suggested topic list
 * (communion, elder-led autonomy, the Holy Spirit as separate sections) —
 * there is no existing church content for those, and AGENTS.md is explicit
 * that unverified specifics about a real congregation's doctrine are not
 * safe to invent. Section order is doctrinal: the Bible, who Jesus is,
 * salvation, baptism, then the church itself.
 *
 * Nodes 18-22 are unpublished (not deleted — the content review trail stays),
 * per AGENTS.md's alias-retirement procedure: unpublish and save *first*,
 * then delete the alias, then add the redirect, because pathauto hands an
 * alias straight back to a node that still has one when re-saved, and a live
 * alias silently shadows a redirect (matched only after inbound path
 * processing already resolved it). Redirects point at /node/17, never at the
 * /about/beliefs alias — aliases can change; node ids don't.
 *
 * Idempotent: overwrites node 17's field_content/field_description/
 * field_section_nav in full; unpublish/alias-delete/redirect-create are all
 * no-ops if already done.
 */

use Drupal\redirect\Entity\Redirect;

$node_storage = \Drupal::entityTypeManager()->getStorage('node');
$alias_storage = \Drupal::entityTypeManager()->getStorage('path_alias');

$body = <<<'HTML'
<p>In the essentials, we desire unity. In areas of opinion, we seek liberty. In all things, we pursue love. These statements clarify where we agree; they are not a creed requiring formal agreement. We desire no creed but Christ, and the Bible as our final rule of faith and practice. Mechanicsburg Christian Church is an independent, non-denominational congregation standing in the historic tradition of the church since it began — we do not share every belief of every other congregation, but we believe there is a consensus on most of the essentials of the Christian faith.</p>

<h2>The Bible</h2>
<p>We believe in the authority of Scripture in both the Old and New Testaments. We affirm that Scripture is true and accurate in all matters it addresses, and should be regarded as the final authority on all spiritual matters. God has revealed himself to us through the Bible, and both Testaments tell his story. It alone is our guidebook for life's most important questions.</p>
<p><em>2 Timothy 3:14&ndash;17 &middot; Romans 15:4 &middot; Hebrews 4:12</em></p>

<h2>Who Jesus is</h2>
<p>Scripture is clear that Jesus is God. Old Testament prophecy foretells his birth as the one who is virgin-born &mdash; Isaiah calls him Immanuel, "God with us" (Isaiah 7:14), and later, "Wonderful Counselor, Mighty God, Everlasting Father, Prince of Peace" (Isaiah 9:6). Micah foretells his birth in Bethlehem, explaining that his "origins are from of old, from ancient times" (Micah 5:2). John's gospel opens by describing Jesus as the eternal Word made flesh, present at creation and the source of life for mankind (John 1:1&ndash;18).</p>
<p>Jesus taught this about himself. He claimed to be the Messiah (John 4:26), and used the name God gave himself at the burning bush &mdash; "before Abraham was born, I am" (John 8:58, cf. Exodus 3:14). John records seven distinct "I am" statements: the bread of life, the light of the world, the gate, the good shepherd, the resurrection and the life, the way and the truth and the life, and the true vine (John 6:35, 8:12, 10:9, 10:11, 11:25, 14:6, 15:1). He forgave sin, accepted worship, and allowed Thomas to call him "My Lord and my God" after the resurrection (John 20:28).</p>
<p>The apostle Paul, writing of Jesus' return, calls him "our great God and Savior" (Titus 2:13) &mdash; God in very nature, who did not cling to equality with God but emptied himself for us (Philippians 2:5&ndash;6).</p>

<h2>Salvation</h2>
<p>There is no hope of salvation outside of Jesus Christ &mdash; he is the way, the truth and the life, and no one comes to the Father except through him (John 14:6; 1 Timothy 2:5). We believe man must respond to his broken relationship with God by accepting what Jesus has done for us, surrendering our lives to him as Lord. God's plan of salvation is made available through the gospel of Jesus' life, death on the cross, and resurrection (1 Peter 1:18&ndash;19; 1 John 1:7).</p>
<p>That response has a shape Scripture is consistent about:</p>
<ul>
<li><strong>Believe</strong> &mdash; in one God who created everything, and in his son Jesus Christ, who lived a sinless life as both fully God and fully man, was killed and buried, and was raised on the third day (John 3:16; Mark 16:16).</li>
<li><strong>Confess</strong> &mdash; stating what you believe, and acknowledging that you are a sinner in need of a Savior (Romans 10:9; 1 Timothy 1:15&ndash;16).</li>
<li><strong>Repent</strong> &mdash; turning away from worldly behavior and toward Christ (Acts 3:19; Luke 5:31&ndash;32).</li>
<li><strong>Obey</strong> &mdash; making Jesus not only Savior, but Lord of your life (Matthew 7:21; John 14:23).</li>
<li><strong>Baptism</strong> &mdash; the point at which we identify with the death and burial of Jesus Christ, and so will also live with him (Romans 6:3&ndash;8; Acts 2:38).</li>
</ul>

<h2>Baptism</h2>
<p><strong>Why do we baptize?</strong> To follow Christ's own example (Mark 1:9) and obey his command to make disciples, baptizing them (Matthew 28:18&ndash;20). Every conversion recorded in Acts includes baptism, and Scripture connects it directly to the Holy Spirit and the forgiveness of sins (Acts 2:38).</p>
<p><strong>How do we baptize?</strong> By immersion. Jesus himself was immersed (Matthew 3:16), every recorded baptism in Scripture was by immersion (Acts 8:38&ndash;39), and the word itself means "to dip under water" &mdash; picturing burial and resurrection with Christ (Colossians 2:11&ndash;12).</p>
<p><strong>Who should be baptized, and when?</strong> Anyone who believes (Mark 16:16; Acts 2:41; 8:12), as soon as they believe &mdash; Scripture records no waiting period (Acts 8:36&ndash;38; 22:16). We hold that baptism as a believer, by immersion, is a once-only step; someone baptized before believing, or not by immersion, is welcome to be baptized by immersion now (Ephesians 4:4&ndash;6).</p>

<h2>The church</h2>
<p>We do not believe we are the only Christians, but we desire only to be labeled "Christians" &mdash; simply followers of Jesus (1 Peter 4:16; Acts 11:26). We have elders in the local body who are responsible for the spiritual well-being of the congregation and provide guidance for the body as needed (Acts 14:23; Titus 1:5).</p>
HTML;

$node_17 = $node_storage->load(17);
if (!$node_17) {
  print "no node 17 (/about/beliefs)\n";
  return;
}

$node_17->set('field_content', ['value' => $body, 'format' => 'content_format']);
$node_17->set('field_description', "What Mechanicsburg Christian Church believes about Scripture, Jesus, salvation, baptism and the church — no creed but Christ.");
$node_17->set('field_section_nav', TRUE);
$node_17->save();
print "updated node 17 (/about/beliefs): 5 sections, section_nav on\n";

// Retire the five standalone belief pages.
foreach ([18, 19, 20, 21, 22] as $nid) {
  $node = $node_storage->load($nid);
  if (!$node) {
    print "  node $nid: not found, skipping\n";
    continue;
  }

  if ($node->isPublished()) {
    $node->setUnpublished()->save();
  }

  $aliases = $alias_storage->loadByProperties(['path' => '/node/' . $nid]);
  foreach ($aliases as $alias_entity) {
    $old = $alias_entity->getAlias();
    $alias_entity->delete();

    $source = ltrim($old, '/');
    $already = \Drupal::entityTypeManager()->getStorage('redirect')
      ->loadByProperties(['redirect_source__path' => $source]);
    if ($already) {
      print "  $old -> redirect already exists\n";
      continue;
    }

    Redirect::create([
      'redirect_source' => ['path' => $source, 'query' => []],
      'redirect_redirect' => ['uri' => 'internal:/node/17'],
      'status_code' => 301,
      'language' => 'und',
    ])->save();
    print "  301: $old -> /node/17\n";
  }
}
