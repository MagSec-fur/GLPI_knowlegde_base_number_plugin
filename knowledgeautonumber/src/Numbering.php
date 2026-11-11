<?php

/**
 * Knowledge AutoNumber - GLPI plugin
 *
 * @license GPLv3 https://www.gnu.org/licenses/gpl-3.0.html
 * @link    https://github.com/MagSec-fur/GLPI_knowlegde_base_number_plugin
 */

namespace GlpiPlugin\Knowledgeautonumber;

use RuntimeException;
use Throwable;
use Toolbox;

/**
 * Knowledge article numbering (KI-0001, KI-0002, ...).
 *
 * Rules:
 * - an article gets its number once, when it is created, and keeps it forever;
 * - numbers come from a single sequence row that is incremented atomically inside a
 *   transaction, so articles created at the same time never share a number;
 * - the sequence only moves forward: the number of a purged article is never reused;
 * - articles that have no number yet (e.g. created while the plugin was disabled) are
 *   only numbered by an explicit job (plugin install/update or the console command),
 *   which continues the sequence and never touches numbers that already exist;
 * - displaying an article or a form never writes anything.
 */
final class Numbering
{
    public const NUMBERS_TABLE  = 'glpi_plugin_knowledgeautonumber_numbers';
    public const SEQUENCE_TABLE = 'glpi_plugin_knowledgeautonumber_sequence';

    public const PREFIX = 'KI-';
    public const DIGITS = 4;

    private const SEQUENCE_ID = 1;
    private const BATCH_SIZE  = 500;
    private const LOG_FILE    = 'knowledgeautonumber';

    /**
     * Format a sequence value as a knowledge item number, e.g. 42 => "KI-0042".
     */
    public static function format(int $number): string
    {
        return self::PREFIX . str_pad((string) $number, self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * Extract the sequence value from a knowledge item number, e.g. "KI-0042" => 42.
     * Returns null if the value does not use the plugin format.
     */
    public static function parse(string $kb_number): ?int
    {
        if (preg_match('/^' . preg_quote(self::PREFIX, '/') . '(\d+)$/', trim($kb_number), $matches) !== 1) {
            return null;
        }
        return (int) $matches[1];
    }

    /**
     * Get the number of an article, or null if it has none.
     */
    public static function getNumber(int $items_id): ?string
    {
        global $DB;

        if ($items_id <= 0) {
            return null;
        }

        $row = $DB->request([
            'SELECT' => ['kb_number'],
            'FROM'   => self::NUMBERS_TABLE,
            'WHERE'  => ['item_id' => $items_id],
            'LIMIT'  => 1,
        ])->current();

        return !empty($row['kb_number']) ? (string) $row['kb_number'] : null;
    }

    /**
     * Give an article the next free number.
     *
     * Does nothing (and returns the current number) if the article already has one:
     * existing numbers are never changed.
     *
     * @return string The article number
     */
    public static function assign(int $items_id): string
    {
        global $DB;

        if ($items_id <= 0) {
            throw new RuntimeException(sprintf('Invalid knowledge article id "%d".', $items_id));
        }

        return self::transactional(function () use ($DB, $items_id): string {
            $existing = self::getNumber($items_id);
            if ($existing !== null) {
                return $existing;
            }

            // The sequence row stays locked until the end of the transaction, so concurrent
            // assignments are serialized. Skip any number that is already used (e.g. the
            // sequence was edited manually) instead of creating a duplicate.
            do {
                $kb_number = self::format(self::nextSequenceValue());
            } while (self::isNumberUsed($kb_number));

            $result = $DB->insert(self::NUMBERS_TABLE, [
                'item_id'   => $items_id,
                'kb_number' => $kb_number,
            ]);
            if ($result === false) {
                throw new RuntimeException(sprintf(
                    'Unable to store number %s for knowledge article #%d: %s',
                    $kb_number,
                    $items_id,
                    $DB->error()
                ));
            }

            return $kb_number;
        });
    }

    /**
     * Remove the number of a purged article. The number itself is not reused.
     */
    public static function forget(int $items_id): void
    {
        global $DB;

        if ($items_id <= 0) {
            return;
        }

        $DB->delete(self::NUMBERS_TABLE, ['item_id' => $items_id]);
    }

    /**
     * Number a freshly created article.
     *
     * A brand new article cannot legitimately have a number yet, so any row already stored
     * for its id belongs to a purged article whose id was reused by the database (possible
     * if it was purged while the plugin was disabled): drop it before numbering.
     */
    public static function assignToNewItem(int $items_id): string
    {
        self::forget($items_id);
        return self::assign($items_id);
    }

    /**
     * Remove numbers that belong to articles which no longer exist (e.g. purged while the
     * plugin was disabled). Their numbers are not reused.
     *
     * @return int Number of removed rows
     */
    public static function removeOrphans(): int
    {
        global $DB;

        $iterator = $DB->request([
            'SELECT'    => [self::NUMBERS_TABLE . '.item_id'],
            'FROM'      => self::NUMBERS_TABLE,
            'LEFT JOIN' => [
                'glpi_knowbaseitems' => [
                    'FKEY' => [
                        self::NUMBERS_TABLE  => 'item_id',
                        'glpi_knowbaseitems' => 'id',
                    ],
                ],
            ],
            'WHERE'     => ['glpi_knowbaseitems.id' => null],
        ]);

        $orphans = [];
        foreach ($iterator as $row) {
            $orphans[] = (int) $row['item_id'];
        }
        foreach (array_chunk($orphans, self::BATCH_SIZE) as $chunk) {
            $DB->delete(self::NUMBERS_TABLE, ['item_id' => $chunk]);
        }

        return count($orphans);
    }

    /**
     * Count articles that have no number yet.
     */
    public static function countMissing(): int
    {
        global $DB;

        $row = $DB->request([
            'COUNT'     => 'cpt',
            'FROM'      => 'glpi_knowbaseitems',
            'LEFT JOIN' => self::missingNumberJoin(),
            'WHERE'     => [self::NUMBERS_TABLE . '.item_id' => null],
        ])->current();

        return (int) ($row['cpt'] ?? 0);
    }

    /**
     * Give a number to every article that does not have one yet, oldest article first.
     *
     * Existing numbers are never modified and the sequence is continued (never reset).
     * Each article is numbered in its own short transaction, so articles created in the
     * meantime are not blocked and cannot get the same number.
     *
     * @param callable|null $on_assign Called with (int $items_id, string $kb_number) after each assignment
     *
     * @return int Number of articles that received a number
     */
    public static function fillMissing(?callable $on_assign = null): int
    {
        global $DB;

        // Sync first: numbers of removed orphans must stay "used" so they are never reissued.
        self::syncSequence();
        self::removeOrphans();

        $assigned = 0;
        $last_id  = 0;
        do {
            $iterator = $DB->request([
                'SELECT'    => ['glpi_knowbaseitems.id'],
                'FROM'      => 'glpi_knowbaseitems',
                'LEFT JOIN' => self::missingNumberJoin(),
                'WHERE'     => [
                    self::NUMBERS_TABLE . '.item_id' => null,
                    'glpi_knowbaseitems.id'          => ['>', $last_id],
                ],
                'ORDER'     => ['glpi_knowbaseitems.id ASC'],
                'LIMIT'     => self::BATCH_SIZE,
            ]);

            $ids = [];
            foreach ($iterator as $row) {
                $ids[] = (int) $row['id'];
            }

            foreach ($ids as $items_id) {
                $kb_number = self::assign($items_id);
                $assigned++;
                $last_id = $items_id;
                if ($on_assign !== null) {
                    $on_assign($items_id, $kb_number);
                }
            }
        } while (count($ids) === self::BATCH_SIZE);

        if ($assigned > 0) {
            self::log(sprintf('Assigned a number to %d knowledge article(s) that had none.', $assigned));
        }

        return $assigned;
    }

    /**
     * Make sure the sequence row exists and is not behind the highest number in use,
     * so that new numbers can never collide with existing ones. Never moves backwards.
     *
     * @return int The current sequence value
     */
    public static function syncSequence(): int
    {
        global $DB;

        $highest = self::getHighestNumber();

        $sequence_row = $DB->request([
            'SELECT' => ['id'],
            'FROM'   => self::SEQUENCE_TABLE,
            'WHERE'  => ['id' => self::SEQUENCE_ID],
        ]);

        if (count($sequence_row) === 0) {
            $DB->insert(self::SEQUENCE_TABLE, ['id' => self::SEQUENCE_ID, 'last_number' => $highest]);
        } else {
            $greatest = sprintf('GREATEST(%s, %d)', $DB->quoteName('last_number'), $highest);
            $DB->update(
                self::SEQUENCE_TABLE,
                ['last_number' => self::expression($greatest)],
                ['id' => self::SEQUENCE_ID]
            );
        }

        return self::getSequenceValue();
    }

    /**
     * Last value handed out by the sequence.
     */
    public static function getSequenceValue(): int
    {
        global $DB;

        $row = $DB->request([
            'SELECT' => ['last_number'],
            'FROM'   => self::SEQUENCE_TABLE,
            'WHERE'  => ['id' => self::SEQUENCE_ID],
            'LIMIT'  => 1,
        ])->current();

        return (int) ($row['last_number'] ?? 0);
    }

    /**
     * Highest number currently assigned to an article (0 if none).
     */
    public static function getHighestNumber(): int
    {
        global $DB;

        $highest = 0;
        foreach ($DB->request(['SELECT' => ['kb_number'], 'FROM' => self::NUMBERS_TABLE]) as $row) {
            $number = self::parse((string) $row['kb_number']);
            if ($number !== null && $number > $highest) {
                $highest = $number;
            }
        }

        return $highest;
    }

    /**
     * Write a line in GLPI_LOG_DIR/knowledgeautonumber.log.
     */
    public static function log(string $message): void
    {
        Toolbox::logInFile(self::LOG_FILE, $message . "\n", true);
    }

    /**
     * Atomically increment the sequence and return the new value.
     *
     * Must be called inside a transaction: the UPDATE locks the sequence row until commit.
     */
    private static function nextSequenceValue(bool $recreate_if_missing = true): int
    {
        global $DB;

        $result = $DB->update(
            self::SEQUENCE_TABLE,
            ['last_number' => self::expression($DB->quoteName('last_number') . ' + 1')],
            ['id' => self::SEQUENCE_ID]
        );
        if ($result === false) {
            throw new RuntimeException('Unable to increment the knowledge article number sequence: ' . $DB->error());
        }

        if ($DB->affectedRows() === 0) {
            if (!$recreate_if_missing) {
                throw new RuntimeException('The knowledge article number sequence is missing.');
            }
            // Sequence row was removed (e.g. manually): recreate it, then retry once.
            self::syncSequence();
            return self::nextSequenceValue(false);
        }

        return self::getSequenceValue();
    }

    private static function isNumberUsed(string $kb_number): bool
    {
        global $DB;

        return count($DB->request([
            'SELECT' => ['id'],
            'FROM'   => self::NUMBERS_TABLE,
            'WHERE'  => ['kb_number' => $kb_number],
            'LIMIT'  => 1,
        ])) > 0;
    }

    private static function missingNumberJoin(): array
    {
        return [
            self::NUMBERS_TABLE => [
                'FKEY' => [
                    'glpi_knowbaseitems' => 'id',
                    self::NUMBERS_TABLE  => 'item_id',
                ],
            ],
        ];
    }

    /**
     * Run a callback in a database transaction.
     *
     * GLPI 11 nests transactions using savepoints. GLPI 10 cannot nest them, so when a
     * transaction is already open there the callback simply runs inside it.
     *
     * @return mixed The callback result
     */
    private static function transactional(callable $callback)
    {
        global $DB;

        $owns_transaction = !(method_exists($DB, 'inTransaction') && $DB->inTransaction());
        if ($owns_transaction) {
            $DB->beginTransaction();
        }

        try {
            $result = $callback();
            if ($owns_transaction) {
                $DB->commit();
            }
            return $result;
        } catch (Throwable $e) {
            if ($owns_transaction) {
                $DB->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Build a raw SQL expression with the class available in the running GLPI version.
     *
     * @return \Glpi\DBAL\QueryExpression|\QueryExpression
     */
    private static function expression(string $sql)
    {
        if (class_exists('Glpi\\DBAL\\QueryExpression')) {
            return new \Glpi\DBAL\QueryExpression($sql); // GLPI >= 11.0
        }
        return new \QueryExpression($sql); // GLPI 10.0
    }
}
