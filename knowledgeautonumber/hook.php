<?php

/**
 * Knowledge AutoNumber - GLPI plugin
 *
 * @license GPLv3 https://www.gnu.org/licenses/gpl-3.0.html
 * @link    https://github.com/MagSec-fur/GLPI_knowlegde_base_number_plugin
 */

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Knowledgeautonumber\Numbering;
use GlpiPlugin\Knowledgeautonumber\Schema;

/**
 * Install or upgrade the plugin.
 *
 * Articles that do not have a number yet get the next numbers of the sequence.
 * Existing numbers are never changed.
 *
 * @return boolean
 */
function plugin_knowledgeautonumber_install()
{
    try {
        Schema::install();
    } catch (Throwable $e) {
        Numbering::log('Installation failed: ' . $e->getMessage());
        Session::addMessageAfterRedirect(
            htmlspecialchars(sprintf(__('Installation failed: %s', 'knowledgeautonumber'), $e->getMessage())),
            false,
            ERROR
        );
        return false;
    }

    try {
        Numbering::fillMissing();
    } catch (Throwable $e) {
        // Tables are fine: numbering can be completed later with the console command.
        Numbering::log('Numbering existing articles failed: ' . $e->getMessage());
    }

    return true;
}

/**
 * Uninstall the plugin: all numbers are deleted.
 *
 * @return boolean
 */
function plugin_knowledgeautonumber_uninstall()
{
    Schema::uninstall();

    return true;
}

/**
 * `item_add` hook: number a knowledge article once it has been created.
 *
 * @param CommonDBTM $item
 *
 * @return void
 */
function plugin_knowledgeautonumber_item_add($item)
{
    if (!($item instanceof KnowbaseItem) || $item->isNewItem()) {
        return;
    }

    try {
        Numbering::assignToNewItem((int) $item->getID());
    } catch (Throwable $e) {
        Numbering::log(sprintf(
            'Unable to number knowledge article #%d: %s',
            $item->getID(),
            $e->getMessage()
        ));
    }
}

/**
 * `item_purge` hook: forget the number of a deleted knowledge article.
 *
 * @param CommonDBTM $item
 *
 * @return void
 */
function plugin_knowledgeautonumber_item_purge($item)
{
    if (!($item instanceof KnowbaseItem)) {
        return;
    }

    try {
        Numbering::forget((int) $item->getID());
    } catch (Throwable $e) {
        Numbering::log(sprintf(
            'Unable to remove the number of knowledge article #%d: %s',
            $item->getID(),
            $e->getMessage()
        ));
    }
}

/**
 * `post_item_form` hook: show the number in the knowledge article form.
 *
 * Read-only: this hook is called for every form of every item type and must never
 * write to the database.
 *
 * @param array $params ['item' => CommonDBTM, 'options' => array]
 *
 * @return void
 */
function plugin_knowledgeautonumber_post_item_form($params)
{
    $item = is_array($params) && isset($params['item']) ? $params['item'] : $params;
    if (!($item instanceof KnowbaseItem)) {
        return;
    }

    $kb_number = $item->isNewItem() ? null : Numbering::getNumber((int) $item->getID());

    TemplateRenderer::getInstance()->display('@knowledgeautonumber/form_field.html.twig', [
        'kb_number' => $kb_number,
        'is_new'    => $item->isNewItem(),
    ]);
}

/**
 * `pre_show_tab` hook: show the number above the article content.
 *
 * Read-only, like plugin_knowledgeautonumber_post_item_form().
 *
 * @param array $params ['item' => CommonGLPI, 'options' => ['itemtype' => string, 'tabnum' => int]]
 *
 * @return void
 */
function plugin_knowledgeautonumber_pre_show_tab($params)
{
    $item    = $params['item'] ?? null;
    $options = $params['options'] ?? [];

    if (
        !($item instanceof KnowbaseItem)
        || $item->isNewItem()
        || ($options['itemtype'] ?? null) !== KnowbaseItem::class
        || (int) ($options['tabnum'] ?? 0) !== 1 // "Knowledge base" tab: the article itself
    ) {
        return;
    }

    $kb_number = Numbering::getNumber((int) $item->getID());
    if ($kb_number === null) {
        return;
    }

    TemplateRenderer::getInstance()->display('@knowledgeautonumber/article_number.html.twig', [
        'kb_number' => $kb_number,
    ]);
}

/**
 * Add the "Knowledge Item Number" column to the knowledge base search engine.
 *
 * @param string $itemtype
 *
 * @return array
 */
function plugin_knowledgeautonumber_getAddSearchOptionsNew($itemtype)
{
    if ($itemtype !== KnowbaseItem::class) {
        return [];
    }

    return [
        [
            'id'            => PLUGIN_KNOWLEDGEAUTONUMBER_SEARCH_OPTION_ID,
            'table'         => Numbering::NUMBERS_TABLE,
            'field'         => 'kb_number',
            'name'          => __('Knowledge Item Number', 'knowledgeautonumber'),
            'datatype'      => 'string',
            'massiveaction' => false,
            'joinparams'    => [
                'jointype'  => 'child',
                'linkfield' => 'item_id',
            ],
        ],
    ];
}
