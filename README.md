# Drupal migrAIne

![migrAIne logo](migraine.jpeg "migrAIne logo")

**Use AI to support the process of developing Drupal → Drupal migrations.**

A small suite of commands that reduce the developer burden involved in information gathering and migration yml
creation. LLMs are used where they make sense and good old-fashioned helper scripts are used where they don&apos;t.

These are rough-and-ready scripts that I made to test if AI could add value for writing migrations. There's lots that needs to be improved!


## TL;DR

Install in your destination Drupal project folder and then:

    # Gather entity type/field structure data about your source and destination sites.
    mise run mig:register source /path/to/d7
    mise run mig:register dest .

    # Make JSON list of all needed migrations.
    mise run mig:llm:guess-migrations

      # Hand-fix/tweak .migraine/migrations.json.

    # Generate an AI prompt file for each migration.
    mise run mig:prompt \*

    # Use aider to generate a first-draft migration yml file.
    mise run mig:aider:migrate node_article

      # Hand-fix/tweak config/sync/migrate_plus.migration.<MIGRATION_ID>.yml.

    # Pass an existing yml to aider for improvement.
    mise run mig:aider:migrate node_article config/sync/migrate_plus.migration.node_article.yml

    # Generate mysql queries that list all entity field values in source and destination.
    mise run mig:sql source node article | pbcopy
    mise run mig:sql dest node article | pbcopy

    # Update the destination site inventory following some field schema changes.
    mise run mig:inventory dest

    # Dump markdown files for every entity type in source into ~/.migraine/document
    mise run mig:document source


### Drupal 6/7 upgrades

You can use Drupal core + [Migrate Upgrade](https://www.drupal.org/project/migrate_upgrade) to generate the first
version of all migrations and then pass it to aider for improvement:

    # Get Drupal to generate the first version of your migrations.
    ddev drush migrate:upgrade --configure-only --legacy-db-key=migrate

    # Get migraine to guess its migrations.json from the generated migrations.
    mise run mig:set-migrations --group="drupal_7" --tag="Content"

    # Migraine's attempt to guess migrations.json values from the config sucks, improve with AI.
    mise run mig:llm:guess-migrations --improve

      # Hand-fix/tweak .migraine/migrations.json.

    # Re-generate prompts from the new migrations.json.
    mise run mig:prompt \* --force

    # Export the generated migrations and use aider to improve one.
    ddev drush config:export -y
    mise run mig:aider:migrate upgrade_d7_node_article config/sync/migrate_plus.migration.upgrade_d7_node_article.yml


## More task tips

For more details about any task, run it with the `--help` flag, e.g.:

    mise run mig:inventory --help

Most commands can be accessed via their initialism, and the same for "source" and "dest", e.g.:

    # Take inventory of the source
    mise run mig:i s

    # Set migration from config
    mise run mig:sm


## Installation/requirements

 - [Install mise](https://mise.jdx.dev/getting-started.html),
 - Install PHP version >= 8.2,
 - Install and configure [llm](https://github.com/simonw/llm) to unlock `migraine:llm:*` tasks,
 - Install and configure [aider](https://github.com/Aider-AI/aider) to unlock `migraine:aider:*` tasks. Recommend `--architect` mode,
 - Download the contents of this repo's `mise-tasks` folder a Drupal project folder at `.mise/tasks`,
   - To make this globally available, copy it to `$HOME/.config/mise/tasks` (see [mise task docs](https://mise.jdx.dev/tasks/) for more information),
 - Each command-file must be made executable. Execute the `install` command to do this automatically:

       chmod u+x .mise/tasks/migraine/install && mise run migraine:install

(If you installed it somewhere else, modify the above command to use the right path).

This tool _invokes_ `llm` and `aider` but does not configure them. It is up to you to register with your preferred AI 
provider, generate API keys, and configure `llm` and `aider`the way you like it. If you like, you can look at 
[example aider configuration below](#example-aider-configuration). This closely resembles my own set-up, so should work
well with migraine.


## The Set Up

It is assumed you have a working installation of both Drupal sites locally on your machine. The sites should be "upped"
and functional. You must be able to run Drush commands that require a full bootstrap and database connection.

These tasks create a `.migraine/` folder in your working directory to store/retrieve (e.g.) site inventory and 
prompt information. As such, you must remain in the same working directory when running migraine tasks. Most likely this 
will be your Drupal 10 project root.

You may choose to exclude the `.migraine/` folder via a line in your `.gitignore` file. Or, you may choose to commit it 
to your repo to collaborate with others on migration planning and AI prompt documents.


## The Tasks

The examples here assume your working directory is a [ddev](https://github.com/ddev/ddev) project root of your Drupal 10 site.


### 1. Register/unregister sites.

Tells migraine how to find your source and/or destination sites:

    mise run migraine:register source /path/to/site
    mise run migraine:register dest ../path/to/another/site

Key facts about your sites are stored in `.migraine/migraine.yml`. Migraine does
some basic validation of the Drupal root and detects the version of Drupal.

Un-register a site by passing a `--delete` or `-D` flag:

    mise run migraine:register source /path/to/site --delete

By default an immediate inventory is taken on a newly registered site. To skip
this for some reason, pass the `--no-inventory` options:

    mise run migraine:register s /path/to/site --no-inventory


### 2. List all registered sites.

Prints a table of all registered sites, their Drupal version, and status:

    mise run migraine:status

This command also checks drush can be executed against the site, and verifies
drush can connect to the database.


### 3. Take an inventory of your site

Discovers entity type and field information about your source site:

    mise run migraine:inventory source

Discovers entity type and field information about your destination site:

    mise run migraine:inventory dest

This information is stored in JSON files inside `.migraine/inventory`. It is
used by other commands to use to, e.g., generate prompts.

An inventory is taken automatically when a site is first registered.

#### Non-ddev drush support

This task needs to invoke drush in the context of the given site. It does this
by `cd`-ing to the Drupal webroot and exec-ing `ddev drush`. If you don't use
ddev, this will not work. You must override the drush command-string with one
that works via the `--drush` command-line option. For example:

    mise run migraine:inventory source --drush "php vendor/bin/drush"


### 4. Work out what migrations you need

The idea is to have a file `.migraine/migrations.json` that lists all needed migrations.

Run this task to ask AI to look at your site inventories and generate this file for you:

    mise run migraine:llm:guess-migrations

You can change what model is used by passing an `-m`/`--model` option. Run `llm models` to see possible values.

It's possible invalid JSON will be generated, and of course only you know what the right answer is, so check/tweak/fix
this file by hand before moving on to the next step.


### 5. Generate an AI prompt file for each migration

This task iterates over each migration in `.migraine/migrations.json` and generates a suitable markdown file:

    mise run migraine:prompt \*

You can also generate one at a time:

    mise run migraine:prompt node_article

The first time this runs, the template used to generate them is placed in `.migraine/templates/prompts/migration-prompt.md`.
You are free to update this and re-run.

These prompt files are only a starting point. 

Complete the ## Field mapping section to describe how source files are mapped to destination files in whatever format
makes sense to you and is clear and non-ambiguous for LLMs to understand.


### 6. Improve the prompt document

This task passes an existing prompt document to an LLM to flesh-out the ## Field mapping section:

    mise run migraine:llm:improve-mapping node_article

It doesn't update the file, it just outputs an updated Field Mapping table to stdout. You can use it to
improve the document, or not. If there are no mappings specified, the AI will try to guess what the
basic mappings should be.


### 7. Generate the yml file for a migration

This task passes your carefully-crafted prompt file to `aider` to generate a migration yml. For example:

    mise run migraine:aider:migrate node_article

For good results it's vital you put high-quality example migrations that exhibit the patterns you wish the AI to use in 
`.migraine/templates/migrations`.


## Example aider configuration

At the time of writing I've experienced good results with openai's `o3*` or `o1` models for planning/reasoning, and anthropic's
claude `sonnet` for code generation/editing.

My personal `~/.aider.conf.yml` file looks something like this:

    model: openai/o1
    openai-api-key: ...
    anthropic-api-key: ...
    architect: true
    editor-model: sonnet
    map-tokens: 0          # Never want to accidentally send codebase details up to the cloud,
    dark-mode: true
    auto-commits: false    # I want to control my own commits thank you,
    analytics-disable: true
