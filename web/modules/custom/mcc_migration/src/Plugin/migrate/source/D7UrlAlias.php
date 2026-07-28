<?php

declare(strict_types=1);

namespace Drupal\mcc_migration\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SqlBase;

/**
 * Every node URL alias from the D7 site.
 *
 * These are the addresses the live site has been publishing for years — 979 of
 * the 1,055 aliases sit under `content/`, which is what Google has indexed and
 * what is in people's bookmarks. None of them exist on this site, so
 * redirect.module's auto_redirect can't help: nothing was renamed here, so
 * there is no alias update to hook. They have to be created outright.
 *
 * Taxonomy and user aliases are excluded — the new site has no equivalent
 * destination for `itunes-category/*` (a podcast vocabulary that no longer
 * exists) or `users/*`.
 *
 * @MigrateSource(
 *   id = "mcc_d7_url_alias",
 *   source_module = "path"
 * )
 */
final class D7UrlAlias extends SqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    return $this->select('url_alias', 'ua')
      ->fields('ua', ['pid', 'source', 'alias'])
      ->condition('ua.source', 'node/%', 'LIKE')
      ->orderBy('ua.pid');
  }

  /**
   * {@inheritdoc}
   */
  public function fields(): array {
    return [
      'pid' => $this->t('The D7 alias id.'),
      'source' => $this->t('The internal path, always node/N here.'),
      'alias' => $this->t('The published D7 URL.'),
      'nid' => $this->t('The node id parsed out of source.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds(): array {
    return [
      'pid' => [
        'type' => 'integer',
        'alias' => 'ua',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(\Drupal\migrate\Row $row): bool {
    $source = (string) $row->getSourceProperty('source');
    $nid = (int) substr($source, strlen('node/'));
    if ($nid <= 0) {
      return FALSE;
    }
    $row->setSourceProperty('nid', $nid);
    return parent::prepareRow($row);
  }

}
