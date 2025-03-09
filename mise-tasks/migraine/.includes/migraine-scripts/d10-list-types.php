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

foreach (\Drupal::entityTypeManager()->getDefinitions() as $entityTypeId => $entityType) {
    if (!$entityType instanceof \Drupal\Core\Entity\ContentEntityTypeInterface) {
        continue;
    }

    $result[$entityTypeId] = [];

    $bundleEntityTypeId = $entityType->getBundleEntityType();

    if (empty($bundleEntityTypeId)) {
        $result[$entityTypeId] = [$entityTypeId => $entityTypeId];
        continue;
    }

    $bundleStorage = \Drupal::entityTypeManager()->getStorage($bundleEntityTypeId);

    foreach ($bundleStorage->loadMultiple() as $bundle) {
        $result[$entityTypeId][$bundle->id()] = $bundle->id();
    }
}

return json_encode($result);
