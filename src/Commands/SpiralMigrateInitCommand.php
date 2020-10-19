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
use Symfony\Component\Console\Output\OutputInterface;

final class SpiralMigrateInitCommand extends SpiralMigrateCommand
{
    protected static $defaultName = 'spiral:migrate:init';

    /**
     * {@inheritdoc}
     */
    protected function defineDescription(): string
    {
        return 'Init Databse migrations (create migrations table)';
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->migrator->configure();
        $output->writeln('');
        $output->writeln('<info>Migrations table were successfully created</info>');

        return 0;
    }
}
