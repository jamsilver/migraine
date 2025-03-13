<?php

/**
 * @file
 * Designed to be used with drush php:script.
 */

if (php_sapi_name() !== 'cli' || !class_exists('Drupal')) {
    fprintf(STDERR, "This script is designed to be run with drush php:script on a Drupal 9+ site.");
    exit(1);
}

$result = [];

/** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager */
$entityTypeManager = \Drupal::entityTypeManager();

foreach ($entityTypeManager->getDefinitions() as $entityTypeId => $entityType) {
    if (!$entityType instanceof \Drupal\Core\Entity\ContentEntityTypeInterface) {
        continue;
    }

    $storage = $entityTypeManager->getStorage($entityTypeId);

    $isSQLable = $storage instanceof \Drupal\Core\Entity\Sql\SqlEntityStorageInterface;

    $result[$entityTypeId] = [
        'bundles' => [],
        'schema' => [
            'is_sqlable' => $isSQLable,
            'is_revisionable' => $entityType->isRevisionable(),
        ],
    ];

    if ($isSQLable) {
        $result[$entityTypeId]['schema'] += [
            'base_table' => $storage->getBaseTable(),
            'revision_table' => $storage->getRevisionTable(),
            'data_table' => $storage->getDataTable(),
            'revision_data_table' => $storage->getRevisionDataTable(),
        ];
    }

    $bundleEntityTypeId = $entityType->getBundleEntityType();

    if (empty($bundleEntityTypeId)) {
        $result[$entityTypeId]['bundles'] = [$entityTypeId => $entityTypeId];
        continue;
    }

    $bundleStorage = \Drupal::entityTypeManager()->getStorage($bundleEntityTypeId);

    foreach ($bundleStorage->loadMultiple() as $bundle) {
        $result[$entityTypeId]['bundles'][$bundle->id()] = $bundle->id();
    }
}

return json_encode($result);
