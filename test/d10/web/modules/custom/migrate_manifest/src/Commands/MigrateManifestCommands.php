<?php

namespace Drupal\migrate_manifest\Commands;

use Drupal\migrate_manifest\MigrateManifest;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use Consolidation\SiteAlias\SiteAliasManagerAwareInterface;
use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;
use Psr\Log\LoggerInterface;

/**
 * Drush 9 Migrate manifest command.
 *
 * In addition to a commandfile like this one, you need a drush.services.yml
 * in root of your module.
 *
 * See these files for an example of injecting Drupal services:
 *   - http://cgit.drupalcode.org/devel/tree/src/Commands/DevelCommands.php
 *   - http://cgit.drupalcode.org/devel/tree/drush.services.yml
 */
class MigrateManifestCommands extends DrushCommands implements SiteAliasManagerAwareInterface {

  use SiteAliasManagerAwareTrait;

  /**
   * Execute the migrations as specified in a manifest file.
   *
   * @command migrate:manifest
   * @param $manifest The path to the manifest file
   * @option legacy-db-url A database url to use for migrations.
   * @option legacy-db-prefix A prefix to use for tables through the migration connection.
   * @option legacy-db-key A database connection key from settings.php. Use as an alternative to legacy-db-url
   * @option update In addition to processing unprocessed items from the source, update previously-imported items with the current data
   * @option force Force an operation to run, even if all dependencies are not satisfied
   * @validate-module-enabled migrate_manifest
   * @aliases migrate-manifest2,mm,migrate-manifest
   */
  public function manifest($manifest, $options = [
    'legacy-db-url' => NULL,
    'legacy-db-prefix' => NULL,
    'legacy-db-key' => NULL,
    'update' => NULL,
    'force' => NULL,
  ]) {
    try {
      $migration_manager = \Drupal::service('plugin.manager.migration');
      $manifest_runner = new MigrateManifest($migration_manager, $this->logger(), $options['force'], $options['update']);
      MigrateManifest::setDbState($options['legacy-db-key'], $options['legacy-db-url'], $options['legacy-db-prefix']);
      $manifest_runner->import($manifest);
    }
    catch (\Exception $e) {
      $this->logger()->error($e->getMessage());
    }

    $process = Drush::drush($this->siteAliasManager()->getSelf(), 'cache-rebuild');
    $process->mustRun();
  }

  /**
   * Lists migration templates available to run.
   *
   * @command migrate:template:list
   * @option tag Template tag
   * @option as-yaml Create output as yaml that can be used as a manifest.
   * @validate-module-enabled migrate_manifest
   * @aliases migrate-template-list,mml
   */
  public function templateList($options = ['tag' => NULL, 'as-yaml' => NULL]) {
    $tag = $options['tag'];
    $as_yaml = $options['as-yaml'];

    /** @var \Drupal\migrate_manifest\MigrateTemplateStorageInterface $template_storage */
    $template_storage = \Drupal::service('migrate_manifest.template_storage');
    if ($tag) {
      $templates = $template_storage->findTemplatesByTag($tag);
    }
    else {
      $templates = $template_storage->getAllTemplates();
    }

    foreach ($templates as $template) {
      $this->output()->writeln(($as_yaml ? '- ' : '') . $template['id']);
    }
  }

}
