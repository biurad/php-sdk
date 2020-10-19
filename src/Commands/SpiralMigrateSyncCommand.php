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
use Cycle\Schema\Generator\SyncTables;
use Cycle\Schema\GeneratorInterface;
use Cycle\Schema\Registry;
use Spiral\Migrations\Config\MigrationConfig;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class SpiralMigrateSyncCommand extends SpiralMigrateCommand
{
    protected static $defaultName   = 'spiral:migrate:sync';

    /** @var Registry */
    private $registry;

    /** @var GeneratorInterface[] */
    private $generators;

    public function __construct(SpiralMigrator $migrator, MigrationConfig $config, Registry $registry, array $generators)
    {
        $this->registry   = $registry;
        $this->generators = $generators;

        parent::__construct($migrator, $config);
    }

    /**
     * {@inheritdoc}
     */
    protected function defineDescription(): string
    {
        return 'Sync Cycle ORM schema with database without intermediate migration (risk operation)';
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->verifyConfigured($output)) {
            return 1;
        }

        $show = new CycleShowChanges($output);
        (new Compiler())->compile($this->registry, \array_merge($this->generators, [$show, new SyncTables()]));

        if ($show->hasChanges()) {
            $output->writeln("\n<info>ORM Schema has been synchronized</info>");
        }

        return 0;
    }
}
