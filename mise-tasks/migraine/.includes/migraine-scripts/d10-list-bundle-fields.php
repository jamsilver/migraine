<?php

/**
 * @file
 * Designed to be used with drush php:script.
 */

if (php_sapi_name() !== 'cli' || !class_exists('Drupal')) {
    fprintf(STDERR, "This script is designed to be run with drush php:script on a Drupal 9+ site.");
    exit(1);
}

if (!isset($extra[0]) || !is_string($extra[0]) || strlen($extra[0]) === 0 || !preg_match('/^[a-zA-Z0-9_-]+$/', $extra[0])) {
    fprintf(STDERR, "This script expects a valid entity type ID to be passed as the 1st argument.\n");
    exit(1);
}

if (!isset($extra[1]) || !is_string($extra[1]) || strlen($extra[1]) === 0 || !preg_match('/^[a-zA-Z0-9_-]+$/', $extra[1])) {
    fprintf(STDERR, "This script expects a valid bundle ID to be passed as the 2nd argument.\n");
    exit(1);
}

$entityTypeID = $extra[0];
$bundle = $extra[1];

$entityTypeManager = \Drupal::entityTypeManager();
$entityFieldManager = \Drupal::service('entity_field.manager');
$entityTypeBundleInfo = \Drupal::service('entity_type.bundle.info');
$entityRepository = \Drupal::service('entity.repository');

// Validation.

$entityType = $entityTypeManager->getDefinition($entityTypeID, FALSE);

if (!$entityType) {
    fprintf(STDERR, 'Unrecognised entity type "%s".' . "\n", $entityTypeID);
    exit(1);
}

$bundles = $entityTypeBundleInfo->getBundleInfo($entityTypeID);

if (!isset($bundles[$bundle])) {
    fprintf(STDERR, 'Unrecognised bundle "%s.%s".' . "\n", $entityTypeID, $bundle);
    exit(1);
}

// Get field definitions for the bundle
$fieldDefinitions = $entityFieldManager->getFieldDefinitions($entityTypeID, $bundle);

if (empty($fieldDefinitions)) {
    fprintf(STDERR, 'No fields found for bundle "%s.%s".' . "\n", $entityTypeID, $bundle);
    exit(1);
}

$fields = [];

foreach ($fieldDefinitions as $fieldName => $field) {
    if ($field->isComputed()) {
        continue;
    }

    $fieldType = $field->getType();
    $storage = $field->getFieldStorageDefinition();
    $handlerSettings = $field->getSetting('handler_settings');

    $fields[] = [
        'field_name' => $field->getName(),
        'field_type' => $field->getType(),
        'cardinality' => $storage->getCardinality(),
        'allowed_values' => function_exists('options_allowed_values')
            ? options_allowed_values($storage)
            : $storage->getSetting('allowed_values'),
        'reference_target_type' => $field->getSetting('target_type')
            ?? match($fieldType) {
                'media' => 'media',
                'file' => 'file',
                default => NULL,
            },
        'reference_target_bundles' => $handlerSettings['target_bundles']
            ?? match($fieldType) {
                'file' => 'file',
                default => NULL,
            },
    ];
}

return json_encode($fields);
