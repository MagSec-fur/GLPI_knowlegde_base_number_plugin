# Knowledge AutoNumber

This GLPI plugin gives every knowledge base article a unique, permanent number in the format
`KI-0001`, `KI-0002`, …

Made by Destiny_fur from MagSec.

## Features

- An article gets its number when it is created (web interface, REST API, imports, …).
- Numbers never change. Viewing or editing an article never renumbers anything.
- Numbers are unique, even when several articles are created at the same time.
- The number of a deleted article is not reused.
- The number is shown above the article content and in the article edit form.
- "Knowledge Item Number" is available as a search criterion and column in GLPI's search engine
  and in the REST API search.
- A console command numbers articles that do not have a number yet.
- Translated into 14 languages.

## Requirements

| Plugin | GLPI                 | PHP                                              |
|--------|----------------------|--------------------------------------------------|
| 1.1.0  | 10.0.x and 11.0.x    | the version required by your GLPI (7.4+ / 8.2+)  |

## Installation

The plugin directory must be named `knowledgeautonumber`.

### From a release archive

1. Download the archive from the [releases page](https://github.com/MagSec-fur/GLPI_knowlegde_base_number_plugin/releases).
2. Extract it into the `plugins` directory of your GLPI installation, so that you get
   `plugins/knowledgeautonumber/setup.php`.
3. In GLPI, go to **Setup > Plugins**, install the plugin, then enable it.

### With git

```bash
cd /path/to/glpi/plugins
git clone https://github.com/MagSec-fur/GLPI_knowlegde_base_number_plugin.git
mv GLPI_knowlegde_base_number_plugin/knowledgeautonumber .
rm -rf GLPI_knowlegde_base_number_plugin
```

Then install and enable the plugin in **Setup > Plugins**, or from the GLPI directory:

```bash
php bin/console plugin:install knowledgeautonumber -u <admin login>
php bin/console plugin:activate knowledgeautonumber
```

During installation, existing articles get a number, oldest article first.

## Upgrading from 1.0.x

1. Replace the `knowledgeautonumber` directory with the new version.
2. In **Setup > Plugins**, click **Update** (or run
   `php bin/console plugin:install knowledgeautonumber -u <admin login>`), then enable the plugin again.

The update:

- keeps every existing number exactly as it is;
- numbers the articles that have no number, continuing after the highest existing number;
- removes the foreign key that versions up to 1.0.19 created on the GLPI table `glpi_knowbaseitems`,
  and adds a unique index on the numbers.

Versions up to 1.0.19 renumbered every article from `KI-0001` each time a form was displayed
(issue [#6](https://github.com/MagSec-fur/GLPI_knowlegde_base_number_plugin/issues/6)). The numbers
in place when you update are the ones that are kept from then on. Those versions could also fill
`files/_log/knowledgeautonumber.log` with tens of megabytes of renumbering messages; that file
can be deleted after the update.

## Usage

- Create a knowledge base article: it gets the next number when it is saved.
- The number is shown above the article content, and in the **Edit** tab as a read-only
  "Knowledge Item Number" field.
- Search for an article by number with the "Knowledge Item Number" search criterion.

### Console command

Articles created while the plugin was disabled have no number. Number them with:

```bash
# Only report how many articles have no number
php bin/console plugins:knowledgeautonumber:fill-missing --dry-run

# Number them (add -v to list each article)
php bin/console plugins:knowledgeautonumber:fill-missing
```

This command never changes existing numbers and never restarts the sequence.

## How numbering works

- `glpi_plugin_knowledgeautonumber_sequence` holds the last number used. When an article is created,
  it is incremented atomically inside a database transaction, so two articles can never get the
  same number.
- `glpi_plugin_knowledgeautonumber_numbers` links each article to its number. Both the article and
  the number are unique.
- When an article is deleted permanently, its number is removed and is not reused.
- Numbers have at least 4 digits: `KI-9999` is followed by `KI-10000`.
- Errors are written to `files/_log/knowledgeautonumber.log`.

## Uninstalling

Uninstalling the plugin deletes all numbers. If you install it again, articles are numbered again
from `KI-0001`. To keep the numbers, disable the plugin instead of uninstalling it.

## Translations

Translations are gettext files in `knowledgeautonumber/locales/`. To add a language, copy
`knowledgeautonumber.pot` to `xx_XX.po`, translate it, and compile it with
`msgfmt xx_XX.po -o xx_XX.mo`.

## Contributing

* Open a ticket for each bug/feature so it can be discussed
* Follow [development guidelines](http://glpi-developer-documentation.readthedocs.io/en/latest/plugins/index.html)
* Refer to [GitFlow](http://git-flow.readthedocs.io/) process for branching
* Work on a new branch on your own fork
* Open a PR that will be reviewed by a developer
