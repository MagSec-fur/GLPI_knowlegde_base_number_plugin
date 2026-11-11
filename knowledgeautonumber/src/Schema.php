<?php

/**
 * Knowledge AutoNumber - GLPI plugin
 *
 * @license GPLv3 https://www.gnu.org/licenses/gpl-3.0.html
 * @link    https://github.com/MagSec-fur/GLPI_knowlegde_base_number_plugin
 */

namespace GlpiPlugin\Knowledgeautonumber;

use DBConnection;
use RuntimeException;

/**
 * Database schema install / upgrade / uninstall.
 *
 * Only the GLPI database API is used (no DBmysql::query(), which GLPI 11 refuses),
 * and the plugin never alters GLPI core tables.
 */
final class Schema
{
    /**
     * Create the plugin tables, or upgrade them from a previous version.
     *
     * Safe to run several times (GLPI runs it again on every plugin update).
     */
    public static function install(): void
    {
        global $DB;

        $charset   = DBConnection::getDefaultCharset();
        $collation = DBConnection::getDefaultCollation();
        $sign      = DBConnection::getDefaultPrimaryKeySignOption();
        $options   = "ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation} ROW_FORMAT=DYNAMIC";

        if (!$DB->tableExists(Numbering::SEQUENCE_TABLE)) {
            self::query(sprintf(
                'CREATE TABLE `%s` (
                    `id` int %s NOT NULL,
                    `last_number` int unsigned NOT NULL DEFAULT 0,
                    PRIMARY KEY (`id`)
                ) %s',
                Numbering::SEQUENCE_TABLE,
                $sign,
                $options
            ));
        }

        if (!$DB->tableExists(Numbering::NUMBERS_TABLE)) {
            self::query(sprintf(
                'CREATE TABLE `%s` (
                    `id` int %s NOT NULL AUTO_INCREMENT,
                    `item_id` int %s NOT NULL,
                    `kb_number` varchar(10) NOT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `item_id` (`item_id`),
                    UNIQUE KEY `kb_number` (`kb_number`)
                ) %s',
                Numbering::NUMBERS_TABLE,
                $sign,
                $sign,
                $options
            ));
        } else {
            self::upgradeNumbersTable();
        }

        Numbering::syncSequence();
    }

    /**
     * Drop the plugin tables and display preferences.
     */
    public static function uninstall(): void
    {
        global $DB;

        foreach ([Numbering::NUMBERS_TABLE, Numbering::SEQUENCE_TABLE] as $table) {
            if ($DB->tableExists($table)) {
                self::query(sprintf('DROP TABLE `%s`', $table));
            }
        }

        $DB->delete('glpi_displaypreferences', [
            'itemtype' => 'KnowbaseItem',
            'num'      => PLUGIN_KNOWLEDGEAUTONUMBER_SEARCH_OPTION_ID,
        ]);
    }

    /**
     * Upgrade a numbers table created by plugin versions <= 1.0.19.
     */
    private static function upgradeNumbersTable(): void
    {
        global $DB;

        // Versions <= 1.0.19 created a foreign key on the GLPI core table `glpi_knowbaseitems`.
        // Plugins must not constrain core tables (it can break GLPI upgrades, and fails on
        // databases still using signed keys); purged articles are now cleaned up by the
        // `item_purge` hook and by Numbering::removeOrphans() instead.
        $constraints = $DB->request([
            'SELECT' => ['CONSTRAINT_NAME'],
            'FROM'   => 'information_schema.REFERENTIAL_CONSTRAINTS',
            'WHERE'  => [
                'CONSTRAINT_SCHEMA' => $DB->dbdefault,
                'TABLE_NAME'        => Numbering::NUMBERS_TABLE,
            ],
        ]);
        $constraint_names = [];
        foreach ($constraints as $constraint) {
            $constraint_names[] = $constraint['CONSTRAINT_NAME'];
        }
        foreach ($constraint_names as $constraint_name) {
            self::query(sprintf(
                'ALTER TABLE `%s` DROP FOREIGN KEY `%s`',
                Numbering::NUMBERS_TABLE,
                str_replace('`', '``', $constraint_name)
            ));
        }

        // Let the database guarantee that a number is never used twice.
        if (!self::indexExists(Numbering::NUMBERS_TABLE, 'kb_number')) {
            $duplicates = self::findDuplicateNumbers();
            if (count($duplicates) === 0) {
                self::query(sprintf(
                    'ALTER TABLE `%s` ADD UNIQUE KEY `kb_number` (`kb_number`)',
                    Numbering::NUMBERS_TABLE
                ));
            } else {
                Numbering::log(sprintf(
                    'Unique index on numbers not created: these numbers are used by several articles: %s',
                    implode(', ', $duplicates)
                ));
            }
        }
    }

    private static function indexExists(string $table, string $index): bool
    {
        global $DB;

        return count($DB->request([
            'SELECT' => ['INDEX_NAME'],
            'FROM'   => 'information_schema.STATISTICS',
            'WHERE'  => [
                'TABLE_SCHEMA' => $DB->dbdefault,
                'TABLE_NAME'   => $table,
                'INDEX_NAME'   => $index,
            ],
            'LIMIT'  => 1,
        ])) > 0;
    }

    /**
     * @return string[]
     */
    private static function findDuplicateNumbers(): array
    {
        global $DB;

        $seen       = [];
        $duplicates = [];
        foreach ($DB->request(['SELECT' => ['kb_number'], 'FROM' => Numbering::NUMBERS_TABLE]) as $row) {
            $kb_number = (string) $row['kb_number'];
            if (isset($seen[$kb_number])) {
                $duplicates[$kb_number] = $kb_number;
            }
            $seen[$kb_number] = true;
        }

        return array_values($duplicates);
    }

    /**
     * Run a schema (DDL) statement.
     *
     * GLPI 11 forbids DBmysql::query(); DBmysql::doQuery() exists since GLPI 10.0.
     */
    private static function query(string $sql): void
    {
        global $DB;

        $result = method_exists($DB, 'doQuery') ? $DB->doQuery($sql) : $DB->query($sql);
        if ($result === false) {
            throw new RuntimeException(sprintf('SQL error: %s in "%s"', $DB->error(), $sql));
        }
    }
}
