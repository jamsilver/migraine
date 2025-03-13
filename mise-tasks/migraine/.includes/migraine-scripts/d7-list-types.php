<?php

/**
 * @file
 * Designed to be used with drush php:script.
 */

if (php_sapi_name() !== 'cli' || !isset($args) || !function_exists('entity_get_info')) {
    fprintf(STDERR, "This script is designed to be run with drush php:script on a Drupal 7 site.\n");
    exit(1);
}

$result = [];

$entitiesInfo = entity_get_info();

if (empty($entitiesInfo)) {
    fprintf(STDERR, "Error loading entity type information. Maybe clear caches?\n");
    exit(1);
}

$result = array();

foreach ($entitiesInfo as $entityTypeId => $entityInfo) {

    $isSQLable = isset($entityInfo['controller class'])
        && class_exists($entityInfo['controller class'])
        && ($entityInfo['controller class'] === DrupalDefaultEntityController::class
            || is_subclass_of($entityInfo['controller class'], DrupalDefaultEntityController::class));

    $result[$entityTypeId] = [
        'bundles' => [],
        'schema' => [
            'is_sqlable' => $isSQLable,
            'is_revisionable' => !empty($entityInfo['revision table']) && !empty($entityInfo['entity keys']['revision']),
        ],
    ];

    if ($isSQLable) {
        $result[$entityTypeId]['schema'] += [
            'base_table' => $entityInfo['base table'],
            'revision_table' => $entityInfo['revision table'] ?? NULL,
        ];
    }

    if (!isset($entityInfo['bundles'])) {
        $result[$entityTypeId]['bundles'] = [$entityTypeId => $entityTypeId];
        continue;
    }

    foreach (array_keys($entityInfo['bundles']) as $bundleId) {
        $result[$entityTypeId]['bundles'][$bundleId] = $bundleId;
    }
}

return json_encode($result);
