<?php

/**
 * @file
 * everything.features.taxonomy.inc
 */

/**
 * Implements hook_taxonomy_default_vocabularies().
 */
function everything_taxonomy_default_vocabularies() {
  return array(
    'cheese_regions' => array(
      'name' => 'Cheese regions',
      'machine_name' => 'cheese_regions',
      'description' => '',
      'hierarchy' => 0,
      'module' => 'taxonomy',
      'weight' => 0,
    ),
  );
}
