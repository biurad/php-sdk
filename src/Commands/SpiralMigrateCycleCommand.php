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

use Biurad\Framework\CycleGenerators\CycleShowChanges;
use Biurad\Framework\SpiralMigrator;
use Cycle\Schema\Compiler;
use Cycle\Schema\Registry;
use Spiral\Migrations\Config\MigrationConfig;
use Spiral\Migrations\State;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Cycle\Migrations\GenerateMigrations;

final class SpiralMigrateCycleCommand extends SpiralMigrateCommand
{
    protected static $defaultName   = 'spiral:migrate:cycle';

    /** @var Registry */
    private $registry;

    /** @var GenerateMigrations */
    private $migrations;

    /** @var GeneratorInterface[] */
    private $generators;

    public function __construct(SpiralMigrator $migrator, MigrationConfig $config, Registry $registry, GenerateMigrations $migrations, array $generators)
    {
        $this->registry   = $registry;
        $this->migrations = $migrations;
        $this->generators = $generators;

        parent::__construct($migrator, $config);
    }

    /**
     * {@inheritdoc}
     */
    protected function defineDescription(): string
    {
        return 'Generate ORM schema migrations';
    }

    /**
     * {@inheritdoc}
     */
    protected function defineOption(): array
    {
        return [new InputOption('run', 'r', InputOption::VALUE_NONE, 'Automatically run generated migration.')];
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->verifyConfigured($output)) {
            return 1;
        }

        foreach ($this->migrator->getMigrations() as $migration) {
            if ($migration->getState()->getStatus() !== State::STATUS_EXECUTED) {
                $output->writeln('<fg=red>Outstanding migrations found, run `migrate` first.</fg=red>');

                return 1;
            }
        }

        $show = new CycleShowChanges($output);
        (new Compiler())->compile($this->registry, \array_merge($this->generators, [$show]));

        if ($show->hasChanges()) {
            (new Compiler())->compile($this->registry, [$this->migrations]);

            if ($input->getOption('run')) {
                return $this->getApplication()->find('spiral:migrate:start')
                    ->run($input, $output);
            }
        }

        return 0;
    }
}
