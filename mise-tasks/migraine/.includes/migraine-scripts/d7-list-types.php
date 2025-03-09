<?php

/**
 * @file
 * Designed to be used with drush php:script.
 */

if (php_sapi_name() !== 'cli' || !isset($args) || !function_exists('entity_get_info')) {
    fprintf(STDERR, "This script is designed to be run with drush php:script on a Drupal 7 site.");
    exit(1);
}

$result = [];

$entityInfo = entity_get_info();

if (empty($entityInfo)) {
    fprintf(STDERR, "Error loading entity type information. Maybe clear caches?");
    exit(1);
}

$types = array();

foreach (array_keys($entityInfo) as $entityTypeId) {
    $types[$entityTypeId] = [];

    if (!isset($entityInfo[$entityTypeId]['bundles'])) {
        $types[$entityTypeId] = [$entityTypeId => $entityTypeId];
        continue;
    }

    foreach (array_keys($entityInfo[$entityTypeId]['bundles']) as $bundleId) {
        $types[$entityTypeId][$bundleId] = $bundleId;
    }
}

return json_encode($types);
