# Knowledgebaseautonumber

This GLPI plugin adds a knowledgbase ID for existing and new knowledgebase items.
Made by Destiny_fur from MagSec


## Installation when u have access to GLPI plugin folder

1. download the plugin

2. Copy the `knowledgeautonumber` directory to the `plugins` directory of your GLPI installation

3. Install and Activate the plugin from the GLPI plugins management interface.

## install via CLI

1. cd `your glpi plugins folder`

2. git clone https://github.com/MagSec-fur/GLPI_knowlegde_base_number_plugin.git

3. cd GLPI_knowlegde_base_number_plugin

4. mv knowledgebasenumber ..

5. cd ..

6. rm -rf GLPI_knowledge_base_number_plugin

7. Install and Activate the plugin from the GLPI plugins management interface.

## Usage

When creating or editing a knowledgebase item, it will genarate a knowledgebase item ID in the forrmat `KI-#### `

## Contributing

* Open a ticket for each bug/feature so it can be discussed
* Follow [development guidelines](http://glpi-developer-documentation.readthedocs.io/en/latest/plugins/index.html)
* Refer to [GitFlow](http://git-flow.readthedocs.io/) process for branching
* Work on a new branch on your own fork
* Open a PR that will be reviewed by a developer
