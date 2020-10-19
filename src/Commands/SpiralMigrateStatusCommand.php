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

use Spiral\Migrations\State;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class SpiralMigrateStatusCommand extends SpiralMigrateCommand
{
    protected const PENDING = '<fg=red>not executed yet</fg=red>';

    protected static $defaultName = 'spiral:migrate:status';

    /**
     * {@inheritdoc}
     */
    protected function defineDescription(): string
    {
        return 'Get list of all available migrations and their statuses';
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->verifyConfigured($output)) {
            //Making sure migration is configured.
            return 1;
        }

        if (empty($this->migrator->getMigrations())) {
            $output->writeln('<comment>No migrations were found.</comment>');

            return 1;
        }

        $table = new Table($output);
        $table = $table->setHeaders(['Migration', 'Created at', 'Executed at']);

        foreach ($this->migrator->getMigrations() as $migration) {
            $state = $migration->getState();

            $table->addRow([
                $state->getName(),
                $state->getTimeCreated()->format('Y-m-d H:i:s'),
                $state->getStatus() === State::STATUS_PENDING
                    ? self::PENDING
                    : '<info>' . $state->getTimeExecuted()->format('Y-m-d H:i:s') . '</info>',
            ]);
        }

        $table->render();

        return 0;
    }
}
