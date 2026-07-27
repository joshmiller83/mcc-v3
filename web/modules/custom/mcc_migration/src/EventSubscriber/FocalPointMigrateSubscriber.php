<?php

declare(strict_types=1);

namespace Drupal\mcc_migration\EventSubscriber;

use Drupal\mcc_migration\ManualCropToFocalPoint;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigrateImportEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Re-applies legacy Manual Crop data as focal points after files are imported.
 *
 * Hooks the file migration rather than the media one because crop entities are
 * keyed on file URI, so they only become meaningful once the file entities
 * exist. Running it here means a full re-import picks the focal points back up
 * without anyone remembering to run a separate script.
 */
class FocalPointMigrateSubscriber implements EventSubscriberInterface {

  /**
   * The migration whose completion the crop data depends on.
   */
  protected const TRIGGER_MIGRATION = 'mcc_files';

  public function __construct(
    protected readonly ManualCropToFocalPoint $converter,
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
   * Converts legacy crops once the file migration has finished.
   */
  public function onPostImport(MigrateImportEvent $event): void {
    if ($event->getMigration()->id() !== static::TRIGGER_MIGRATION) {
      return;
    }

    $this->converter->convert();
  }

}
