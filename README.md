# Drupal MigrAIne

Helper scripts for using AI to speed up the process of writing D7->D10 Drupal migrations. There
are two parts to this repo:

 - The helper scripts (in `mise-tasks`) defined as [Mise-en-place tasks](https://mise.jdx.dev/tasks/).
 - A couple simple ddev D7 and D10 sites for testing and development.

## Installation/requirements

 - [install Mise](https://mise.jdx.dev/getting-started.html)
 - Download/copy the contents of mise-tasks into your D10 project folder at e.g. `.mise/tasks`, or perhaps in `$HOME/.config/mise/tasks`, or wherever makes sense for your situation. See https://mise.jdx.dev/tasks/ for more details.
 - Install PHP version 8 (some scripts use PHP)
 - Install [llm](https://github.com/simonw/llm), configure it with a key of your choice (recommend openai).

## The Set Up

These scripts assume you have a working install of both the Drupal 7 and Drupal 10 site locally on your machine. The 
sites should be installed and working, and basic Drush commands that require a database connection should function on 
both.

These scripts read/write files in `.migraine/` in the directory you run the command from, so stay in the same working
directory when you run them.

The scripts assume both sites are being served with DDEV, but this can be worked-around (see below).


## The Scripts

The examples here are given assuming you're working within your D10 project root.

### 1. Take an inventory of your site

Export entity type and field information about a Drupal 7 site to `.migraine/d7`

    mise run migraine:inventory:d7 /path/to/d7

Export entity type and field information about a Drupal 10 site to `.migraine/d10`

    mise run migraine:inventory:d10 .

If your site isn't being served with ddev, then override the command you need to run to invoke drush from the webroot like this:

    mise run migraine:inventory:d7 /path/to/d7 --drush "../vendor/bin/drush"


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

