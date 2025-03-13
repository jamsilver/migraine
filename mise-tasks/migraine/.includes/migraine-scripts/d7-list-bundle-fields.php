<?php

/**
 * @file
 * Designed to be used with drush php:script.
 */

if (php_sapi_name() !== 'cli' || !isset($args) || !function_exists('entity_get_info')) {
    fprintf(STDERR, "This script is designed to be run with drush php:script on a Drupal 7 site.\n");
    exit(1);
}

if (!isset($args[1]) || !is_string($args[1]) || strlen($args[1]) === 0 || !preg_match('/^[a-zA-Z0-9_-]+$/', $args[1])) {
    fprintf(STDERR, "This script expects a valid entity type ID to be passed as the 1st argument.\n");
    exit(1);
}

if (!isset($args[2]) || !is_string($args[2]) || strlen($args[2]) === 0 || !preg_match('/^[a-zA-Z0-9_-]+$/', $args[2])) {
    fprintf(STDERR, "This script expects a valid bundle ID to be passed as the 2nd argument.\n");
    exit(1);
}

$entityTypeID = $args[1];
$bundle = $args[2];

$entityInfo = entity_get_info($entityTypeID);

if (empty($entityInfo)) {
    fprintf(STDERR, 'Unrecognised entity type "%s".' . "\n", $entityTypeID);
    exit(1);
}

if (isset($bundle) && !isset($entityInfo['bundles'][$bundle])) {
    fprintf(STDERR, 'Unrecognised bundle "%s.%s".' . "\n", $entityTypeID, $bundle);
    exit(1);
}

$fieldInstances = field_info_instances($entityTypeID, $bundle);

if (!is_array($fieldInstances)) {
    fprintf(STDERR, 'Unrecognised bundle "%s.%s".' . "\n", $entityTypeID, $bundle);
    exit(1);
}

$fields = [];

foreach ($fieldInstances as $fieldName => $instanceInfo) {
    $fieldInfo = field_info_field($fieldName);

    $info = [
        'field_name' => $fieldName,
        'field_type' => $fieldInfo['type'],
        'cardinality' => $fieldInfo['cardinality'],
        'allowed_values' => strpos($fieldInfo['type'], 'list') !== FALSE && (isset($fieldInfo['settings']['allowed_values']) || isset($fieldInfo['settings']['allowed_values_function'])) && function_exists('list_allowed_values')
            ? array_keys(@list_allowed_values($fieldInfo, $instanceInfo, $entityTypeID))
            : NULL,
        'reference_target_type' => $fieldInfo['type'] === 'taxonomy_term_reference' ? 'taxonomy_term'
            : ($fieldInfo['settings']['target_type'] ?? NULL),
        'reference_target_bundles' => $fieldInfo['type'] === 'taxonomy_term_reference'
            ? (isset($fieldInfo["settings"]["allowed_values"][0]["vocabulary"]) ? [$fieldInfo["settings"]["allowed_values"][0]["vocabulary"]] : NULL)
            : ($fieldInfo['settings']['handler_settings']['target_bundles'] ?? NULL)
    ];

    $isDeleted = (bool) $fieldInfo['deleted'] ?? FALSE;

    $isSQLable = $fieldInfo['storage']['module'] === 'field_sql_storage'
        && $fieldInfo['storage']['type'] === 'field_sql_storage'
        && !$isDeleted;

    $schema = [
        'is_sqlable' => $isSQLable,
        'is_deleted' => $isDeleted,
        'is_translatable' => (bool) ($fieldInfo['translatable'] ?? FALSE),
        'is_revisionable' => TRUE,
        'keys' => [
            'langcode' => 'language',
        ],
        'extra_join_conditions' => [
            'entity_type' => $entityTypeID,
        ],
    ];

    if ($isSQLable) {
        $schema['columns'] = [];
        foreach (array_keys($fieldInfo['columns']) as $column_name) {
            $schema['columns'][$column_name] = _field_sql_storage_columnname($fieldName, $column_name);
        }
        $schema['is_shared'] = FALSE;
        $schema['data_table'] = _field_sql_storage_tablename($fieldInfo);
        $schema['revision_table'] = _field_sql_storage_revision_tablename($fieldInfo);
    }

    $fields[] = [
        'info' => $info,
        'schema' => $schema,
    ];
}

return json_encode($fields, JSON_PRETTY_PRINT);
