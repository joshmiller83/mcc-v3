<?php

declare(strict_types=1);

namespace Drupal\mcc_migration\EventSubscriber;

use Drupal\mcc_migration\BioDuplicateMerger;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigrateImportEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Re-merges the duplicate bio records after the bios are imported.
 *
 * mcc_bio pins the source node ID, so a re-import recreates and republishes
 * the duplicate record and resets the surviving one to a single leadership
 * group. Running the merge here means the decision survives an import without
 * anyone having to remember a follow-up step.
 */
class BioMergeMigrateSubscriber implements EventSubscriberInterface {

  /**
   * The migration whose completion the merge depends on.
   */
  protected const TRIGGER_MIGRATION = 'mcc_bio';

  public function __construct(
    protected readonly BioDuplicateMerger $merger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      MigrateEvents::POST_IMPORT => ['onPostImport'],
    ];
  }

  /**
   * Folds the duplicate records back together once the bios have landed.
   */
  public function onPostImport(MigrateImportEvent $event): void {
    if ($event->getMigration()->id() !== static::TRIGGER_MIGRATION) {
      return;
    }

    $this->merger->merge();
  }

}
