# Drupal migrAIne

![migrAIne logo](migraine.jpeg "migrAIne logo")

**Use AI to support the process of developing Drupal → Drupal migrations.**

A small suite of commands ("tasks") that reduce the developer burden involved in information gathering and migration yml 
creation. LLMs are used where they make sense and good old-fashioned helper scripts are used where they don&apos;t.

This project does not, and could never, &ldquo;fully automate&rdquo; the generation of migrations. Instead, the hope is 
by removing a chunk of the busywork, developers are able to invest their cognitive and creative energies more 
intelligently. Y&apos;know. Have more fun 🚀 🎉 😀.


## TL;DR

Install everything (see below) and then:

    # 1. Gather entity type/field structure data about the source site.
    mise run migraine:inventory:d7 /path/to/d7

    # Gather entity type/field structure data about the destination site.
    mise run migraine:inventory:d10 .

    # 2. Make JSON list of all needed migrations.
    mise run migraine:llm:guess-migrations

      # Hand-fix/tweak .migraine/migrations.json.

    # 3. Generate an AI prompt file for each migration.
    mise run migraine:make:prompt \*

    # 4. Generate a migration yml file.
    mise run migraine:llm:improve-mapping <MIGRATION_ID>
    mise run migraine:aider:migrate <MIGRATION_ID>

      # Hand-fix/tweak config/sync/migrate_plus.migration.<MIGRATION_ID>.yml.
      # Test.
      # Commit.
      # Repeat.

For more details about each task and how you might use it, keep reading.

## Installation/requirements

 - [Install mise](https://mise.jdx.dev/getting-started.html),
 - Download the contents of this repo's `mise-tasks` folder into your D10 project folder at e.g. `.mise/tasks`, or perhaps in `$HOME/.config/mise/tasks` (see [mise task docs](https://mise.jdx.dev/tasks/) for more information),
 - Install PHP version 8 (some tasks require PHP),
 - Install and configure [llm](https://github.com/simonw/llm) to unlock `migraine:llm:*` tasks,
 - Install and configure [aider](https://github.com/Aider-AI/aider) to unlock `migraine:aider:*` tasks. Recommend `--architect` mode,

This tool _invokes_ `llm` and `aider` but does not configure them. It is up to you to register with your preferred AI 
provider, generate API keys, and configure `llm` and `aider`the way you like it. If you like, you can look at 
[example aider configuration below](#example-aider-configuration). This closely resembles my own set-up, so should work
well with migraine.


## The Set Up

It is assumed you have a working installation of both the Drupal 7 and Drupal 10 site locally on your machine. The 
sites should be "upped" and functional. You must be able to run Drush commands that require a full bootstrap and
database connection.

These tasks create a `.migraine/` folder in your working directory to store/retrieve (e.g.) site inventory and 
prompt information. As such, you must remain in the same working directory when running migraine tasks. Most likely this 
will be your Drupal 10 project root.

You may choose to exclude the `.migraine/` folder via a line in your `.gitignore` file. Or, you may choose to commit it 
to your repo to collaborate with others on migration planning and AI prompt documents.


## The Tasks

The examples here assume your working directory is a [ddev](https://github.com/ddev/ddev) project root of your Drupal 10 site.


### 1. Take an inventory of your site

Export entity type and field information about a Drupal 7 site to `.migraine/d7`

    mise run migraine:inventory:d7 /path/to/d7

Export entity type and field information about a Drupal 10 site to `.migraine/d10`

    mise run migraine:inventory:d10 .

#### Non-ddev drush support

This task needs to invoke drush in the context of the given site. They do this by `cd`-ing to the path you provide and exec-ing `ddev drush`. If you don't use ddev, this will not work. You must override the drush command-string with one that works via the `--drush` command-line option. For example:

    mise run migraine:inventory:d7 /path/to/d7 --drush "php vendor/bin/drush"


### 2. Work out what migrations you need

The idea is to have a file `.migraine/migrations.json` that lists all needed migrations.

Run this task to ask AI to look at your site inventories and generate this file for you:

    mise run migraine:llm:guess-migrations

You can change what model is used by passing an `-m`/`--model` option. Run `llm models` to see possible values.

It's possible invalid JSON will be generated, and of course only you know what the right answer is, so check/tweak/fix
this file by hand before moving on to the next step.


### 3. Generate an AI prompt file for each migration

This task iterates over each migration in `.migraine/migrations.json` and generates a suitable markdown file:

    mise run migraine:make:prompt \*

You can also generate one at a time:

    mise run migraine:make:prompt node_article

The first time this runs, the template used to generate them is placed in `.migraine/templates/migration-prompt.md`.
You are free to update this and re-run.

These prompt files are only a starting point. 

Complete the ## Field mapping section to describe how source files are mapped to destination files in whatever format
makes sense to you and is clear and non-ambiguous for LLMs to understand.


### 4. Improve the prompt document

This task passes an existing prompt document to an LLM to flesh-out the ## Field mapping section:

    mise run migraine:llm:improve-mapping node_article

It doesn't update the file, it just outputs an updated Field Mapping table to stdout. You can use it to
improve the document, or not. If there are no mappings specified, the AI will try to guess what the
basic mappings should be.


### 5. Generate the yml file for a migration

This task passes your carefully-crafted prompt file to `aider` to generate a migration yml. For example:

    mise run migraine:aider:migrate node_article

For good results it's vital you put high-quality example migrations that exhibit the patterns you wish the AI to use in 
`.migraine/template_migrations`.


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
