<?php

declare(strict_types=1);

namespace Drupal\mcc_migration;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\node\NodeInterface;
use Drupal\path_alias\AliasManagerInterface;

/**
 * Folds the legacy site's duplicate bio records into one person each.
 *
 * Two people were entered twice on the D7 site — once under each board they
 * serve on — rather than once with both roles. Gary Allen exists as a deacon
 * record and a trustee record; Jon Culbertson as a trustee record and a deacon
 * record. They are one person apiece, and the leadership page is built to show
 * one person in two groups, so the pair should be one node with two terms.
 *
 * This cannot be expressed as a migration process plugin. The two rows are
 * separate source nodes and a process plugin only ever sees one row at a time,
 * so there is no point at which the trustee row can add its term to the deacon
 * node. Skipping the duplicate row instead would silently drop the second
 * term, which is the one piece of information the duplicate actually carries.
 * So the merge runs after mcc_bio finishes, in the same way the focal point
 * conversion runs after mcc_files.
 *
 * The pairs are listed explicitly rather than detected by matching titles. A
 * congregation can have two people with the same name, and merging two real
 * people is not something to risk on a string comparison — each pair below is
 * a decision somebody made by looking at both records.
 *
 * Safe to re-run. A re-import recreates the duplicate and republishes it, so
 * re-running this is how the merge survives one.
 */
class BioDuplicateMerger {

  /**
   * Duplicate node ID => [surviving node ID, the person's name].
   *
   * The survivor is the older record in both cases, which is also the one
   * holding the clean path alias and, for Jon Culbertson, the one nine
   * calendar events already point at.
   *
   * The name is what both records are checked against before anything is
   * merged. It is not there to identify them — the node IDs do that — but to
   * refuse the merge if either record has become somebody else since this list
   * was written.
   */
  protected const PAIRS = [
    // Gary Allen: trustee record (2026) folds into the deacon record (2022).
    1607 => [1214, 'Gary Allen'],
    // Jon Culbertson: deacon record (2026) folds into the trustee record
    // (2014). The deacon record is where the "Finance" role came from, so the
    // merge carries it across.
    1604 => [346, 'Jon Culbertson'],
  ];

  /**
   * Fields the survivor takes from the duplicate only if it has none itself.
   *
   * Never an overwrite: the surviving record is the one somebody has been
   * maintaining, and the duplicate is a partial copy of the same person.
   */
  protected const BACKFILL_FIELDS = [
    'field_role',
    'field_bio_pic',
    'field_email',
    'field_phone_number',
    'field_topics',
    'field_attachments',
    'body',
  ];

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly AliasManagerInterface $aliasManager,
    protected readonly TimeInterface $time,
    protected readonly LoggerChannelInterface $logger,
  ) {}

  /**
   * Merges every configured pair.
   *
   * @return array
   *   Report keyed by outcome: merged, already_merged, skipped.
   */
  public function merge(): array {
    $report = ['merged' => [], 'already_merged' => [], 'skipped' => []];
    $storage = $this->entityTypeManager->getStorage('node');

    foreach (static::PAIRS as $duplicate_id => [$survivor_id, $name]) {
      $duplicate = $storage->load($duplicate_id);
      $survivor = $storage->load($survivor_id);

      $problem = $this->check($duplicate, $survivor, $duplicate_id, $survivor_id, $name);
      if ($problem !== NULL) {
        $report['skipped'][] = $problem;
        $this->logger->warning('Skipped merging bio @dup into @keep: @why', [
          '@dup' => $duplicate_id,
          '@keep' => $survivor_id,
          '@why' => $problem,
        ]);
        continue;
      }

      $changed = $this->mergeTerms($duplicate, $survivor);
      $changed = $this->backfill($duplicate, $survivor) || $changed;
      $changed = $this->repointReferences($duplicate, $survivor) || $changed;

      if ($changed) {
        $survivor->setNewRevision(TRUE);
        $survivor->setRevisionLogMessage(sprintf(
          'Merged the duplicate bio record for this person (node %d).',
          $duplicate_id
        ));
        $survivor->setRevisionCreationTime($this->time->getRequestTime());
        $survivor->save();
      }

      if ($duplicate->isPublished()) {
        // Unpublished rather than deleted: reversible, and it keeps the
        // migration's record of the source node intact so a re-import still
        // has somewhere to land.
        $duplicate->setUnpublished();
        $duplicate->setNewRevision(TRUE);
        $duplicate->setRevisionLogMessage(sprintf(
          'Unpublished as a duplicate; merged into node %d.',
          $survivor_id
        ));
        $duplicate->setRevisionCreationTime($this->time->getRequestTime());
        $duplicate->save();
        $report['merged'][] = sprintf('%s (node %d into %d)', $survivor->label(), $duplicate_id, $survivor_id);
      }
      else {
        $report['already_merged'][] = sprintf('%s (node %d)', $survivor->label(), $duplicate_id);
      }

      // Last, and in this order: saving the duplicate above regenerates its
      // alias, because the "menu_path" pathauto pattern applies to every
      // bundle. Retiring the alias before that save would simply hand it
      // straight back. Both steps run on every pass — an alias can return on
      // any save, and a rename can leave a fresh redirect aimed at the record
      // being retired.
      $this->retireAlias($duplicate, $survivor);
      $this->repointRedirects($duplicate, $survivor);
    }

    if ($report['merged']) {
      $this->logger->notice('Merged @count duplicate bio record(s): @list', [
        '@count' => count($report['merged']),
        '@list' => implode('; ', $report['merged']),
      ]);
    }

    return $report;
  }

  /**
   * Refuses the merge unless both records are still the expected person.
   *
   * Both titles are checked against the name rather than against each other,
   * because the two are not equal at every point in the pipeline. Straight
   * after an import the records still carry the legacy site's mixed
   * "Name - Role" titles — "Jon Culbertson - Finance" alongside plain "Jon
   * Culbertson" — and this runs before scripts/mcc_split_bio_name_role.php
   * has separated them. Requiring each title to *begin* with the person's name
   * accepts both forms and still refuses a record that has become somebody
   * else.
   *
   * @return string|null
   *   The reason to skip, or NULL when the pair is safe to merge.
   */
  protected function check(?NodeInterface $duplicate, ?NodeInterface $survivor, int $duplicate_id, int $survivor_id, string $name): ?string {
    if (!$duplicate) {
      return sprintf('node %d no longer exists', $duplicate_id);
    }
    if (!$survivor) {
      return sprintf('node %d no longer exists', $survivor_id);
    }
    if ($duplicate->bundle() !== 'bio' || $survivor->bundle() !== 'bio') {
      return 'one of the pair is not a bio';
    }

    $normalise = static fn(string $text): string => mb_strtolower(preg_replace('/\s+/', ' ', trim($text)));
    $expected = $normalise($name);

    foreach (['duplicate' => $duplicate, 'surviving' => $survivor] as $which => $node) {
      if (!str_starts_with($normalise($node->label()), $expected)) {
        return sprintf('the %s record is titled "%s", not %s', $which, $node->label(), $name);
      }
    }

    return NULL;
  }

  /**
   * Gives the survivor every leadership group either record was in.
   */
  protected function mergeTerms(NodeInterface $duplicate, NodeInterface $survivor): bool {
    $existing = array_column($survivor->get('field_ministry_structure')->getValue(), 'target_id');
    $incoming = array_column($duplicate->get('field_ministry_structure')->getValue(), 'target_id');

    $merged = array_values(array_unique(array_merge($existing, $incoming)));
    if ($merged === array_values($existing)) {
      return FALSE;
    }

    $survivor->set('field_ministry_structure', array_map(
      static fn($tid) => ['target_id' => $tid],
      $merged
    ));

    return TRUE;
  }

  /**
   * Fills the survivor's empty fields from the duplicate.
   */
  protected function backfill(NodeInterface $duplicate, NodeInterface $survivor): bool {
    $changed = FALSE;

    foreach (static::BACKFILL_FIELDS as $field) {
      if (!$survivor->hasField($field) || !$duplicate->hasField($field)) {
        continue;
      }
      if (!$survivor->get($field)->isEmpty() || $duplicate->get($field)->isEmpty()) {
        continue;
      }
      $survivor->set($field, $duplicate->get($field)->getValue());
      $changed = TRUE;
    }

    return $changed;
  }

  /**
   * Points anything referencing the duplicate at the survivor instead.
   *
   * Events and ministries name a bio as their contact; those links have to
   * follow the person, not the record being retired.
   */
  protected function repointReferences(NodeInterface $duplicate, NodeInterface $survivor): bool {
    $storage = $this->entityTypeManager->getStorage('node');
    $referencing = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('field_bio_reference', $duplicate->id())
      ->execute();

    if (!$referencing) {
      return FALSE;
    }

    foreach ($storage->loadMultiple($referencing) as $node) {
      $values = [];
      foreach ($node->get('field_bio_reference')->getValue() as $item) {
        $target = (int) $item['target_id'] === (int) $duplicate->id()
          ? (int) $survivor->id()
          : (int) $item['target_id'];
        // Both records could be referenced by the same event; keep one.
        $values[$target] = ['target_id' => $target];
      }
      $node->set('field_bio_reference', array_values($values));
      $node->setNewRevision(TRUE);
      $node->setRevisionLogMessage(sprintf(
        'Repointed a bio reference from the duplicate node %d to node %d.',
        $duplicate->id(),
        $survivor->id()
      ));
      $node->save();
    }

    $this->logger->notice('Repointed @count reference(s) from node @dup to node @keep.', [
      '@count' => count($referencing),
      '@dup' => $duplicate->id(),
      '@keep' => $survivor->id(),
    ]);

    // The survivor itself was not edited by this.
    return FALSE;
  }

  /**
   * Sends the duplicate's old URL to the surviving person's page.
   *
   * The alias has to be handed over, not just redirected past. Redirects are
   * matched *after* inbound path processing, so while "/gary-allen-0" is still
   * a live alias it resolves to "/node/1607" before the redirect is looked up
   * and the redirect never fires — the visitor gets the retired record's 404
   * instead. Deleting the alias is what lets the redirect take effect.
   *
   * It is deleted on every run rather than once, because the pathauto pattern
   * "menu_path" has no bundle criteria and so regenerates an alias for any
   * node that gets saved, including a re-imported duplicate.
   */
  protected function retireAlias(NodeInterface $duplicate, NodeInterface $survivor): void {
    $system_path = '/node/' . $duplicate->id();
    $alias = $this->aliasManager->getAliasByPath($system_path);
    if ($alias === $system_path) {
      // No alias of its own, so there is nothing to hand over. Any redirect
      // from a previous run still stands.
      return;
    }

    $this->addRedirect(ltrim($alias, '/'), $survivor);

    $alias_storage = $this->entityTypeManager->getStorage('path_alias');
    $aliases = $alias_storage->loadByProperties(['path' => $system_path]);
    $alias_storage->delete($aliases);
    $this->aliasManager->cacheClear($system_path);

    $this->logger->notice('Retired the alias @alias from node @dup; it now redirects to node @keep.', [
      '@alias' => $alias,
      '@dup' => $duplicate->id(),
      '@keep' => $survivor->id(),
    ]);
  }

  /**
   * Adds a 301 from a path to the survivor, unless one is already there.
   */
  protected function addRedirect(string $source, NodeInterface $survivor): void {
    // Matched on the stored source path rather than through
    // RedirectRepository::findMatchingRedirect(), which hashes the language
    // into its lookup: a redirect saved in English is invisible to a search
    // for an undefined-language one, so this would insert a second row every
    // time it ran until the unique hash index refused it.
    $storage = $this->entityTypeManager->getStorage('redirect');
    $existing = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('redirect_source.path', $source)
      ->range(0, 1)
      ->execute();
    if ($existing) {
      return;
    }

    $redirect = $storage->create([]);
    $redirect->setSource($source);
    $redirect->setRedirect('/node/' . $survivor->id());
    $redirect->setStatusCode(301);
    $redirect->save();
  }

  /**
   * Points redirects that aimed at the retired record at the person instead.
   *
   * Renaming a bio leaves a redirect behind from its previous alias, so the
   * duplicate can already be the target of one. Left alone those become a
   * chain ending on an unpublished node — a 301 to a 404.
   */
  protected function repointRedirects(NodeInterface $duplicate, NodeInterface $survivor): void {
    $storage = $this->entityTypeManager->getStorage('redirect');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('redirect_redirect.uri', 'internal:/node/' . $duplicate->id())
      ->execute();

    if (!$ids) {
      return;
    }

    foreach ($storage->loadMultiple($ids) as $redirect) {
      $redirect->setRedirect('/node/' . $survivor->id());
      $redirect->save();
    }

    $this->logger->notice('Repointed @count redirect(s) from node @dup to node @keep.', [
      '@count' => count($ids),
      '@dup' => $duplicate->id(),
      '@keep' => $survivor->id(),
    ]);
  }

}
