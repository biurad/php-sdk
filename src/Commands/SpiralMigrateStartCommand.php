<?php

declare(strict_types=1);

/*
 * This file is part of Biurad opensource projects.
 *
 * PHP version 7.2 and above required
 *
 * @author    Divine Niiquaye Ibok <divineibok@gmail.com>
 * @copyright 2019 Biurad Group (https://biurad.com/)
 * @license   https://opensource.org/licenses/BSD-3-Clause License
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Biurad\Framework\Commands;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class SpiralMigrateStartCommand extends SpiralMigrateCommand
{
    protected static $defaultName = 'spiral:migrate:start';

    /**
     * {@inheritdoc}
     */
    protected function defineDescription(): string
    {
        return 'Perform one or all outstanding migrations';
    }

    /**
     * {@inheritdoc}
     */
    protected function defineOption(): array
    {
        return [new InputOption('one', 'o', InputOption::VALUE_NONE, 'Execute only one (first) migration')];
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->verifyConfigured($output) || !$this->verifyEnvironment($input, $io)) {
            return 1;
        }

        $found = false;
        $count = $input->getOption('one') ? 1 : \PHP_INT_MAX;

        while ($count > 0 && ($migration = $this->migrator->run())) {
            $found = true;
            $count--;

            $io->newLine();
            $output->write(
                \sprintf(
                    "<info>Migration <comment>%s</comment> was successfully executed.</info>\n",
                    $migration->getState()->getName()
                )
            );
        }

        if (!$found) {
            $io->error('No outstanding migrations were found');
        }

        return 0;
    }
}
