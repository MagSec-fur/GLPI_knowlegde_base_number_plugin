<?php

/**
 * Knowledge AutoNumber - GLPI plugin
 *
 * @license GPLv3 https://www.gnu.org/licenses/gpl-3.0.html
 * @link    https://github.com/MagSec-fur/GLPI_knowlegde_base_number_plugin
 */

namespace GlpiPlugin\Knowledgeautonumber\Console;

use Glpi\Console\AbstractCommand;
use GlpiPlugin\Knowledgeautonumber\Numbering;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * php bin/console plugins:knowledgeautonumber:fill-missing [--dry-run]
 *
 * Gives the next numbers to knowledge articles that do not have one yet
 * (for example articles created while the plugin was disabled).
 * Existing numbers are never changed and the sequence is never reset.
 */
class FillMissingCommand extends AbstractCommand
{
    protected function configure(): void
    {
        parent::configure();

        $this->setName('plugins:knowledgeautonumber:fill-missing');
        $this->setDescription(
            'Number the knowledge articles that do not have a number yet. Existing numbers are never changed.'
        );
        $this->addOption(
            'dry-run',
            null,
            InputOption::VALUE_NONE,
            'Only report how many articles have no number, without changing anything'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $missing = Numbering::countMissing();
        if ($missing === 0) {
            $output->writeln('<info>All knowledge articles already have a number.</info>');
            return 0;
        }

        if ($input->getOption('dry-run')) {
            $output->writeln(sprintf(
                '%d knowledge article(s) have no number. The next number is %s.',
                $missing,
                Numbering::format(max(Numbering::getSequenceValue(), Numbering::getHighestNumber()) + 1)
            ));
            return 0;
        }

        try {
            $assigned = Numbering::fillMissing(
                static function (int $items_id, string $kb_number) use ($output): void {
                    $output->writeln(
                        sprintf('Knowledge article #%d: %s', $items_id, $kb_number),
                        OutputInterface::VERBOSITY_VERBOSE
                    );
                }
            );
        } catch (Throwable $e) {
            Numbering::log('fill-missing failed: ' . $e->getMessage());
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return 1;
        }

        $output->writeln(sprintf('<info>%d knowledge article(s) numbered.</info>', $assigned));
        return 0;
    }
}
