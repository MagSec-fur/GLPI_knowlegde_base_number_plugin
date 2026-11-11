<?php

/**
 * Knowledge AutoNumber - GLPI plugin
 *
 * Gives every knowledge base article a stable, unique number (KI-0001, KI-0002, ...).
 *
 * @license GPLv3 https://www.gnu.org/licenses/gpl-3.0.html
 * @link    https://github.com/MagSec-fur/GLPI_knowlegde_base_number_plugin
 */

define('PLUGIN_KNOWLEDGEAUTONUMBER_VERSION', '1.1.0');

// Minimal GLPI version, inclusive
define('PLUGIN_KNOWLEDGEAUTONUMBER_MIN_GLPI_VERSION', '10.0.0');
// Maximum GLPI version, exclusive
define('PLUGIN_KNOWLEDGEAUTONUMBER_MAX_GLPI_VERSION', '11.0.99');

// Id of the "Knowledge Item Number" column in the knowledge base search engine
define('PLUGIN_KNOWLEDGEAUTONUMBER_SEARCH_OPTION_ID', 91750);

/**
 * Plugin description and requirements.
 *
 * @return array
 */
function plugin_version_knowledgeautonumber()
{
    return [
        'name'         => 'Knowledge AutoNumber',
        'version'      => PLUGIN_KNOWLEDGEAUTONUMBER_VERSION,
        'author'       => 'Destiny_fur from MagSec',
        'license'      => 'GPLv3',
        'homepage'     => 'https://github.com/MagSec-fur/GLPI_knowlegde_base_number_plugin',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_KNOWLEDGEAUTONUMBER_MIN_GLPI_VERSION,
                'max' => PLUGIN_KNOWLEDGEAUTONUMBER_MAX_GLPI_VERSION,
            ],
            'php'  => [
                'min' => '7.4',
            ],
        ],
    ];
}

/**
 * Register the plugin hooks.
 *
 * @return void
 */
function plugin_init_knowledgeautonumber()
{
    global $PLUGIN_HOOKS;

    // Deprecated (and always on) since GLPI 11.0.
    if (version_compare(GLPI_VERSION, '11.0.0', '<')) {
        $PLUGIN_HOOKS['csrf_compliant']['knowledgeautonumber'] = true;
    }

    // Assign the next number once the article has really been created.
    $PLUGIN_HOOKS['item_add']['knowledgeautonumber'] = [
        'KnowbaseItem' => 'plugin_knowledgeautonumber_item_add',
    ];

    // Forget the number of an article that is purged. Numbers are never reused.
    $PLUGIN_HOOKS['item_purge']['knowledgeautonumber'] = [
        'KnowbaseItem' => 'plugin_knowledgeautonumber_item_purge',
    ];

    // Display only: these hooks never write to the database.
    $PLUGIN_HOOKS['post_item_form']['knowledgeautonumber'] = 'plugin_knowledgeautonumber_post_item_form';
    $PLUGIN_HOOKS['pre_show_tab']['knowledgeautonumber']   = 'plugin_knowledgeautonumber_pre_show_tab';
}

/**
 * Check prerequisites before install.
 *
 * Version requirements are declared in plugin_version_knowledgeautonumber().
 *
 * @return boolean
 */
function plugin_knowledgeautonumber_check_prerequisites()
{
    return true;
}

/**
 * Check configuration process.
 *
 * @param boolean $verbose Whether to display message on failure. Defaults to false
 *
 * @return boolean
 */
function plugin_knowledgeautonumber_check_config($verbose = false)
{
    return true;
}
