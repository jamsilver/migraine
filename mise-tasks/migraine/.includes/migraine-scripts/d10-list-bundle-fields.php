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

/** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager */
$entityTypeManager = \Drupal::entityTypeManager();
/** @var \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager */
$entityFieldManager = \Drupal::service('entity_field.manager');
/** @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entityTypeBundleInfo */
$entityTypeBundleInfo = \Drupal::service('entity_type.bundle.info');
/** @var \Drupal\Core\Entity\EntityRepositoryInterface $entityRepository */
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

$entityStorage = $entityTypeManager->getStorage($entityTypeID);

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

    $info = [
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

    $isSQLable = $entityStorage instanceof \Drupal\Core\Entity\Sql\SqlEntityStorageInterface
        && !$storage->hasCustomStorage()
        && !$storage->isDeleted();

    $schema = [
        'is_sqlable' => $isSQLable,
        'is_deleted' => $storage->isDeleted(),
        'is_translatable' => $storage->isTranslatable(),
        'is_revisionable' => $storage->isRevisionable(),
        'keys' => [
            'langcode' => 'langcode',
        ],
        'extra_join_conditions' => [],
    ];

    if ($isSQLable) {
        $mapping = $entityStorage->getTableMapping();
        $schema['columns'] = [];
        foreach ($storage->getColumns() as $column_name => $data) {
            $schema['columns'][$column_name] = $mapping->getFieldColumnName($storage, $column_name);
        }
        if ($mapping->requiresDedicatedTableStorage($storage)) {
            $schema['is_shared'] = FALSE;
            $schema['data_table'] = $mapping->getDedicatedDataTableName($storage, $storage->isDeleted());
            $schema['revision_table'] = !$entityType->isRevisionable() ? NULL : $mapping->getDedicatedRevisionTableName($storage, $storage->isDeleted());
        } elseif ($mapping->allowsSharedTableStorage($storage)) {
            $schema['is_shared'] = TRUE;
            $schema['data_table'] = $mapping->getFieldTableName($fieldName);
            $schema['revision_table'] = $mapping->getRevisionDataTable();
        }
    }

    $fields[] = [
        'info' => $info,
        'schema' => $schema,
    ];
}

return json_encode($fields);
