# Migrate Manifest

This project provides tools for running Drupal 8 migrations using the manifest
format.

## Usage

### Running migrations

Migrations run with a drush command with a single argument which is a path to a manifest file. The path can be either relative to the Drupal root or an absolute path but it must be accessible to the drush process.

This provides several benefits.

1) You can commit multiple migrations to your source control. For example in a `/migrations` folder
2) You can develop multiple migrations in a source controlled setup. For example if you have multple sources or multple stages to your migration.

### Connecting to databases

By default, migrate will use the `migrate` database connection for database migrations. This works with Migrate Manifest as well but it also provides some options for overriding this.

#### Key
You can specify the database key for a different connection to use for the migration.
```shell
drush migrate-manifest --legacy-db-key=wordpress manifest.yml
```

#### Database Url
You can specify the entire database url as an option. This is very convenient, but it may not be the most secure. Use with caution.
```shell
drush migrate-manifest --legacy-db-url=mysql://d6user:d6pass@localhost/drupal_6 manifest.yml
```

### Creating manifests
Manifests are a list of migrations in a YAML file. Each entry is a migrations are "templates" in core migrate terms and an example of a simple migration may look like this:

````yaml
- d6_action_settings
- d6_aggregator_feed
````

You can also override migrations for both source and the destination. An example as such:

````yaml
- d6_file:
  source:
    conf_path: sites/assets
  destination:
    source_base_path: destination/base/path
    destination_path_property: uri
- d6_action_settings
````

If you want to list the available templates available in your site you can list them with the included drush command `migrate:template:list (mml)`.


### Custom Migrations

If you need to write a custom migration you can take a couple of approaches depending on your needs and preferences.

#### Custom Module
If you're developing a custom module for your site and have quite a bit of customizations you might consider committing all your templates to your module and just referencing the templates in your manifest. This keeps your manifest easier to read and avoids some complexities in customizting templates with manifests.

#### Customize manifests
If your migrations are more tame you can probably get away with just providing overides for existing templates or code migrations directly into the manifest. You don't need a module but it might get more complicated.

# Author

- Author:: Mike Ryan
- Author:: James Gilliland (<jgilliland@apqc.org>)
