<?php

/**
 * Knowledge AutoNumber - GLPI plugin
 *
 * @license GPLv3 https://www.gnu.org/licenses/gpl-3.0.html
 * @link    https://github.com/MagSec-fur/GLPI_knowlegde_base_number_plugin
 */

namespace GlpiPlugin\Knowledgeautonumber;

use CommonDBTM;

/**
 * Number of a knowledge article (table `glpi_plugin_knowledgeautonumber_numbers`).
 *
 * GLPI's search engine needs an item class for every table used by a search option
 * (required since GLPI 11). Numbers are managed by Numbering, not through this class.
 */
class Number extends CommonDBTM
{
    public static $rightname = 'knowbase';

    public static function getTypeName($nb = 0)
    {
        return __('Knowledge Item Number', 'knowledgeautonumber');
    }
}
