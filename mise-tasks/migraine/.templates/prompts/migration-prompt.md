# INSTRUCTIONS

Write a Drupal $SOURCE_ENV_TYPE to Drupal $DEST_ENV_TYPE migrate_plus migration yml into config/sync/ for the migration specified in this
file. Use the File template section below as a starting point. Fill out the rest of the migration yml in accordance with
the Mapping, and Field mapping sections below. Do not insert comments or blank lines into the yml file.

The migration ID should be: $MIGRATION_ID

Where relevant, repeat patterns from the template migrations in .migraine/templates/migrations/*.yml

## File template

langcode: en
status: true
dependencies: {  }
id: '$MIGRATION_ID'
class: null
field_plugin_method: null
cck_plugin_method: null
migration_tags:
- 'Drupal 7'
- '$DEST_TYPE'
  migration_group: <SameGroupAsAllTheOthers>
  label: 'Migrate <HumanNameLowerCase> items'

## Entity Mapping

Source entity type: $SOURCE_TYPE ($SOURCE_BUNDLE).
Destination entity type: $DEST_TYPE ($DEST_BUNDLE).

## Source Field Details

The usual entity props, plus the following fields:

$SOURCE_FIELD_TABLE

## Destination Field Details

$DEST_FIELD_TABLE

## Field mapping

The usual relevant entity type props, plus the following field mappings:

