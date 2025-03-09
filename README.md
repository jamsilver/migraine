# Drupal migrAIne

![migrAIne logo](migraine.jpeg "migrAIne logo")

**Helper scripts that use AI to support the process of developing Drupal 7 → Drupal 10 migrations.**

This project does not, and could never, &ldquo;fully automate&rdquo; the generation of migrations. Instead, it provides a small suite of commands
("tasks") that reduce the developer burden by combining helper scripts and LLM requests to make it fast to gather information about your sites and
generate the "first draft yml" of each migration.

The hope is by removing a chunk of the busywork, developers are able to invest their cognitive and creative energies more intelligently. Y'know, have more fun.

## Installation/requirements

 - [install Mise](https://mise.jdx.dev/getting-started.html)
 - Download the contents of the `mise-tasks` folder into your D10 project folder at e.g. `.mise/tasks`, or perhaps in `$HOME/.config/mise/tasks`, or wherever makes sense for your situation. See https://mise.jdx.dev/tasks/ for more details.
 - Install PHP version 8 (some scripts use PHP)
 - Install [llm](https://github.com/simonw/llm) and set up key(s) of your choice to unlock `migraine:llm:*` tasks.
 - Install [aider](https://github.com/Aider-AI/aider) to unlock `migraine:aider:*` tasks

## The Set Up

These scripts assume you have a working install of both the Drupal 7 and Drupal 10 site locally on your machine. The 
sites should be installed and working, and basic Drush commands that require a database connection should function on 
both.

These scripts create a `.migraine/` folder in in your working directory to store/retrieve (e.g.) site inventory information.
As such, you must run all commands from the same working directory for them to work properly. Most likely this will be your 
Drupal 10 project root.

You may choose to exclude the `.migraine` folder via a line in your `.gitignore` file. Or, you may choose to commit it to
the repo to collaborate with others on migration planning and AI prompt documents.


## The Tasks

The examples here are given assuming you're working within your D10 project root.

### 1. Take an inventory of your site

Export entity type and field information about a Drupal 7 site to `.migraine/d7`

    mise run migraine:inventory:d7 /path/to/d7

Export entity type and field information about a Drupal 10 site to `.migraine/d10`

    mise run migraine:inventory:d10 .

These scripts need to invoke drush in the context of the given site. They do this by `cd`-ing to the path you provide and exec-ing `ddev drush`. If you don't use ddev, this will not work. You must override the drush command-string with one that works via the `--drush` command-line option. For example:

    mise run migraine:inventory:d7 /path/to/d7 --drush "php vendor/bin/drush"


### 2. Work out what migrations you need

The idea is to make a file `.migraine/migrations.json` that states all migrations we need to make.

Run this task to ask AI to look at your site inventories and generate this file for you:

    mise run migraine:llm:guess-migrations

You can change what model is used by passing an `-m`/`--model` option. Run `llm models` to see possible values.

It's possible invalid JSON will be generated, and of course only you know what the right answer is, so check/tweak/fix
this file by hand before moving on to the next step.


### 3. Generate prompt documents for each migration

This task iterates over each migration in `.migraine/migrations.json` and generates a markdown file suitable for
instructing an LLM to make the yml for it:

    mise run migraine:make:prompt \*

You can also generate one at a time:

    mise run migraine:make:prompt node_article

The first time this runs, the template used to generate them is placed in `.migraine/templates/migration-prompt.md`.
You are free to update this and re-run.

This document is a starting point. It's important to complete the ## Field mapping section before moving on.


### 4. Improve the prompt document

This task passes an existing prompt document to an LLM to flesh-out the ## Field mapping section:

    mise run migraine:llm:improve-mapping node_article

It doesn't update the file, it just outputs an updated Field Mapping table to stdout. You can use it to
improve the document, or not. If there are no mappings specified, the AI will try to guess what the
basic mappings should be.


### 5. Generating the YAML

Recommend using [aider](https://github.com/Aider-AI/aider) for this in architect mode. It's very good at automatically updating files based on LLM feedback.
Also, it is _vital_ to have good, representative template migrations you can pass to the AI as an example.

Suggest these steps:

 - Put good quality example migrations in `.migraine/template_migrations`. Can start with some of core's, but as you make your own migrations, include them instead.
 - Configure aider to use architect mode, I like openai/o1 for the main model, and then claude sonnet as the editor model,
 - Start aider specifying `--read`/`read:` as the migration's prompt file and the template migrations directory. For example, via command-line options:

       aider --map-tokens=0 --read=.migraine/prompts/node_article.md --read=.migraine/template_migrations

   Or by creating a `.aider.conf.yml` file in your project root containing:

       map-tokens: 0
       read:
         - .migraine/prompts/node_article.md
         - .migraine/template_migrations

   And executing `aider` to start

 - Once aider has started, try a prompt like this: `Read .migraine/prompts/node_article.md and follow all instructions` to initiate yaml generation.

