<?php

/**
 * @file
 * Designed to be used with drush php:script.
 */

if (php_sapi_name() !== 'cli' || !class_exists('Drupal')) {
    fprintf(STDERR, "This script is designed to be run with drush php:script on a Drupal 9+ site.");
    exit(1);
}

$group = NULL;
$tag = NULL;
$migrationIds = NULL;

while ($arg = trim(array_shift($extra))) {
    switch (TRUE) {
        case str_starts_with($arg, '--group='):
            $group = trim(substr($arg, strlen('--group=')), '" ');
            break;
        case str_starts_with($arg, '--tag='):
            $tag = trim(substr($arg, strlen('--tag=')), '" ');
            break;
        case str_starts_with($arg, '--ids=');
            $migrationIds = trim(substr($arg, strlen('--ids=')), '" ');
            break;
        default:
            fprintf(STDERR, "Unknown argument: %s", $arg);
            exit(1);
    }
}

try {
    /** @var \Drupal\migrate\Plugin\MigratePluginManagerInterface $migrationMluginManager */
    $manager = \Drupal::service('plugin.manager.migration');
}
catch (\Exception $e) {
    fprintf(STDERR, "Need to install migrate module.");
    exit(1);
}

/**
 * Taken from migrate_tools module.
 */
$migrationsList = function($migration_ids = '', array $options = []) use ($manager): array {
    $filter = [];
    // Filter keys must match the migration configuration property name.
    $filter['migration_group'] = $filter['migration_tags'] = [];
    if (!empty($options['group'])) {
        $filter['migration_group'] = explode(',', (string) $options['group']);
    }
    if (!empty($options['tag'])) {
        $filter['migration_tags'] = explode(',', (string) $options['tag']);
    }

    $matched_migrations = [];

    if (empty($migration_ids)) {
        // Get all migrations.
        $plugins = $manager->createInstances([]);
        $matched_migrations = $plugins;
    }
    else {
        // Get the requested migrations.
        $migration_ids = explode(',', mb_strtolower($migration_ids));

        $definitions = $manager->getDefinitions();

        foreach ($migration_ids as $given_migration_id) {
            if (isset($definitions[$given_migration_id])) {
                $matched_migrations[$given_migration_id] = $manager->createInstance($given_migration_id);
            }
            else {
                $error_message = \dt('Migration @id does not exist', ['@id' => $given_migration_id]);
            }

        }
    }

    // Filters the matched migrations if a group or a tag has been input.
    if (!empty($filter['migration_group']) || !empty($filter['migration_tags'])) {
        // Get migrations in any of the specified groups and with any of the
        // specified tags.
        foreach ($filter as $property => $values) {
            if (!empty($values)) {
                $filtered_migrations = [];
                foreach ($values as $search_value) {
                    foreach ($matched_migrations as $id => $migration) {
                        // Cast to array because migration_tags can be an array.
                        $definition = $migration->getPluginDefinition();
                        $configured_values = (array) ($definition[$property] ?? NULL);
                        $configured_id = in_array($search_value, $configured_values, TRUE) ? $search_value : 'default';
                        if (empty($search_value) || $search_value === $configured_id) {
                            if (empty($migration_ids) || in_array(
                                    mb_strtolower($id),
                                    $migration_ids,
                                    TRUE
                                )) {
                                $filtered_migrations[$id] = $migration;
                            }
                        }
                    }
                }
                $matched_migrations = $filtered_migrations;
            }
        }
    }
    return $matched_migrations;
};

/**
 * AI generated this code.
 */
function analyze_migration_entity_types(\Drupal\migrate\Plugin\MigrationInterface $migration) {
    $result = [
        'source_entity_type' => NULL,
        'source_bundle' => NULL,
        'destination_entity_type' => NULL,
        'destination_bundle' => NULL,
    ];
    $result = analyze_migration_destination($migration, $result);
    $result = analyze_migration_source($migration, $result);

    if (isset($result['source_entity_type']) && !isset($result['destination_entity_type'])) {
        $result['destination_entity_type'] = $result['source_entity_type'];
    }
    if (!isset($result['source_entity_type']) && isset($result['destination_entity_type'])) {
        $result['source_entity_type'] = $result['destination_entity_type'];
    }
    if (isset($result['destination_bundle']) && !isset($result['source_bundle'])) {
        $result['source_bundle'] = $result['destination_bundle'];
    }
    if (isset($result['source_bundle']) && !isset($result['destination_bundle'])) {
        $result['destination_bundle'] = $result['source_bundle'];
    }
    if (isset($result['source_entity_type']) && !isset($result['source_bundle'])) {
        $result['source_bundle'] = $result['source_entity_type'];
    }
    if (isset($result['destination_entity_type']) && !isset($result['destination_bundle'])) {
        $result['destination_bundle'] = $result['destination_entity_type'];
    }

    return array_values($result);
}

/**
 * AI generated this code.
 */
function analyze_migration_destination(\Drupal\migrate\Plugin\MigrationInterface $migration, array $result) {
    $destination = $migration->getDestinationPlugin();
    $destinationPluginId = $destination->getPluginId();
    $configuration = $migration->getDestinationConfiguration();

    // Direct entity destination plugins
    if (preg_match('/^entity:(.+)$/', $destinationPluginId, $matches)) {
        $result['destination_entity_type'] = $matches[1];

        // Check for default_bundle setting
        if (!empty($configuration['default_bundle'])) {
            $result['destination_bundle'] = $configuration['default_bundle'];
        }
    }
    // Entity reference revisions destination
    elseif (preg_match('/^entity_reference_revisions:(.+)$/', $destinationPluginId, $matches)) {
        $result['destination_entity_type'] = $matches[1];

        if (!empty($configuration['default_bundle'])) {
            $result['destination_bundle'] = $configuration['default_bundle'];
        }
    }
    // Field storage/instance
    elseif (in_array($destinationPluginId, ['entity:field_storage_config', 'entity:field_config'])) {
        $result['destination_entity_type'] = 'field';

        // Process plugin might contain entity_type as target
        $process = $migration->getProcess();
        if (isset($process['entity_type']) && !empty($process['entity_type'][0]['value'])) {
            $result['destination_bundle'] = $process['entity_type'][0]['value'];
        }
    }

    return $result;
}

/**
 * AI generated this code.
 */
function analyze_migration_source(\Drupal\migrate\Plugin\MigrationInterface $migration, array $result) {
    $source = $migration->getSourcePlugin();
    $sourcePluginId = $source->getPluginId();
    $configuration = $migration->getSourceConfiguration();

    // Basic Drupal source plugins directly indicate entity type
    if (preg_match('/^d[678]_(.+)$/', $sourcePluginId, $matches)) {
        $entityTypeHint = $matches[1];

        // Handle different source plugin naming conventions
        if ($entityTypeHint === 'node') {
            $result['source_entity_type'] = 'node';

            // Check for node type/bundle in configuration
            if (!empty($configuration['node_type'])) {
                $result['source_bundle'] = $configuration['node_type'];
            }
        }
        elseif ($entityTypeHint === 'term') {
            $result['source_entity_type'] = 'taxonomy_term';

            // Check for vocabulary in configuration
            if (!empty($configuration['bundle'])) {
                $result['source_bundle'] = $configuration['bundle'];
            }
            elseif (!empty($configuration['vocabulary'])) {
                $result['source_bundle'] = $configuration['vocabulary'];
            }
        }
        elseif (in_array($entityTypeHint, ['user', 'file', 'comment', 'block_content'])) {
            $result['source_entity_type'] = $entityTypeHint;

            // Check for bundle in configuration
            if (!empty($configuration['bundle'])) {
                $result['source_bundle'] = $configuration['bundle'];
            }
        }
        elseif ($entityTypeHint === 'entity_field') {
            $result['source_entity_type'] = 'field';
        }
    }

    // Handle SQL-based sources
    elseif ($sourcePluginId === 'sql' || strpos($sourcePluginId, 'sqlbase') !== false) {
        // Examine table name for hints
        if (!empty($configuration['table_name'])) {
            $tableName = $configuration['table_name'];

            // Common patterns in migration table names
            if (strpos($tableName, 'node') === 0) {
                $result['source_entity_type'] = 'node';

                // Check if specific content type is in the name
                if (preg_match('/node_(.+)$/', $tableName, $matches)) {
                    $result['source_bundle'] = $matches[1];
                }
            }
            elseif (strpos($tableName, 'term') === 0 || strpos($tableName, 'taxonomy') === 0) {
                $result['source_entity_type'] = 'taxonomy_term';
            }
            elseif (strpos($tableName, 'user') === 0) {
                $result['source_entity_type'] = 'user';
            }
        }

        // If no luck with table name, examine key query columns
        if (!$result['source_entity_type'] && !empty($configuration['key_schema'])) {
            $keyField = key($configuration['key_schema']);

            // Infer entity type from primary key field naming conventions
            if ($keyField === 'nid') {
                $result['source_entity_type'] = 'node';
            }
            elseif ($keyField === 'tid') {
                $result['source_entity_type'] = 'taxonomy_term';
            }
            elseif ($keyField === 'uid') {
                $result['source_entity_type'] = 'user';
            }
        }
    }

    // Try to infer bundle from process pipeline if not found earlier
    if (!empty($result['destination_entity_type']) && empty($result['destination_bundle'])) {
        $process = $migration->getProcess();

        // Node bundle is usually mapped to 'type'
        if ($result['destination_entity_type'] === 'node' && isset($process['type'])) {
            if (!empty($process['type'][0]['value'])) {
                $result['destination_bundle'] = $process['type'][0]['value'];
            } elseif (isset($process['type'][0]['map'])) {
                // There's a map, we could take the first "to" value as a guess
                $map = $process['type'][0]['map'];
                $firstValue = reset($map);
                if ($firstValue) {
                    $result['destination_bundle'] = $firstValue;
                }
            }
        }

        // Other entity types like media use 'bundle'
        if (empty($result['destination_bundle']) && isset($process['bundle'])) {
            if (!empty($process['bundle'][0]['value'])) {
                $result['destination_bundle'] = $process['bundle'][0]['value'];
            }
        }
    }

    return $result;
}

$return = [];

foreach ($migrationsList($migrationIds, ['group' => $group, 'tag' => $tag]) as $migrationId => $migration) {
    $return[$migrationId] = analyze_migration_entity_types($migration);
}

return json_encode($return);
