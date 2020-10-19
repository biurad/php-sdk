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

namespace Biurad\Framework\DependencyInjection\Extensions;

use Biurad\Framework\Commands\SpiralDbListCommand;
use Biurad\Framework\Commands\SpiralDbTableCommand;
use Biurad\Framework\Commands\SpiralMigrateCycleCommand;
use Biurad\Framework\Commands\SpiralMigrateInitCommand;
use Biurad\Framework\Commands\SpiralMigrateReplayCommand;
use Biurad\Framework\Commands\SpiralMigrateRollbackCommand;
use Biurad\Framework\Commands\SpiralMigrateStartCommand;
use Biurad\Framework\Commands\SpiralMigrateStatusCommand;
use Biurad\Framework\Commands\SpiralMigrateSyncCommand;
use Biurad\Framework\CycleFactory;
use Biurad\Framework\DependencyInjection\Extension;
use Biurad\Framework\SpiralDatabase;
use Biurad\Framework\SpiralMigrator;
use Cycle\Annotated;
use Cycle\Migrations\GenerateMigrations;
use Cycle\ORM;
use Cycle\Schema\Compiler;
use Cycle\Schema\Generator;
use Cycle\Schema\GeneratorInterface;
use Cycle\Schema\Registry;
use Nette;
use Nette\DI\ContainerBuilder;
use Nette\DI\Definitions\Reference;
use Nette\DI\Definitions\Statement;
use Nette\PhpGenerator\PhpLiteral;
use Nette\Schema\Expect;
use Spiral\Database\Config\DatabaseConfig;
use Spiral\Database\DatabaseInterface;
use Spiral\Database\DatabaseProviderInterface;
use Spiral\Migrations\Config\MigrationConfig;
use Spiral\Migrations\FileRepository;
use Spiral\Migrations\MigrationInterface;
use Spiral\Tokenizer\ClassLocator;
use Symfony\Component\Finder\Finder;

class SpiralDatabaseExtension extends Extension
{
    /** @var string */
    private $migrationDir;

    /** @var string */
    private $appDir;

    public function __construct(string $appDir, string $migrationDir)
    {
        $this->appDir       = $appDir;
        $this->migrationDir = $migrationDir;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigSchema(): Nette\Schema\Schema
    {
        return Nette\Schema\Expect::structure([
            'default'   => Nette\Schema\Expect::string()->default('default'),
            'aliases'   => Nette\Schema\Expect::array()->default([]),
            'databases' => Nette\Schema\Expect::arrayOf(Expect::structure([
                'connection'        => Nette\Schema\Expect::string()->nullable(),
                'tablePrefix'       => Nette\Schema\Expect::string()->nullable(),
                'readConnection'    => Nette\Schema\Expect::string()->nullable(),
            ])->castTo('array')),
            'connections'   => Nette\Schema\Expect::arrayOf(Expect::structure([
                'class'     => Expect::string()->assert('class_exists'),
                'options'   => Expect::structure([
                    'reconnect'      => Nette\Schema\Expect::bool(true),
                    'timezone'       => Nette\Schema\Expect::string('UTC'),
                    'connection'     => Nette\Schema\Expect::string(),
                    'username'       => Nette\Schema\Expect::string()->nullable(),
                    'password'       => Nette\Schema\Expect::string()->nullable(),
                    'options'        => Nette\Schema\Expect::array(),
                    'queryCache'     => Nette\Schema\Expect::bool(true),
                    'readonlySchema' => Nette\Schema\Expect::bool(false),
                ])->castTo('array'),
            ])->castTo('array')),
            'migration' => Nette\Schema\Expect::structure([
                'directory'     => Nette\Schema\Expect::string()->default($this->migrationDir),
                'table'         => Nette\Schema\Expect::string()->default('migrations'),
                'safe'          => Nette\Schema\Expect::bool()->default(true),
                'namespace'     => Nette\Schema\Expect::string()->default('Migration'),
            ])->castTo('array'),
            'orm' => Nette\Schema\Expect::structure([
                'entities'      => Nette\Schema\Expect::string()->assert('is_dir')->default($this->appDir),
            ])->castTo('array'),
        ])->castTo('array');
    }

    /**
     * {@inheritdoc}
     */
    public function loadConfiguration(): void
    {
        $container = $this->getContainerBuilder();

        $container->register($this->prefix('config'), DatabaseConfig::class)
            ->setArguments([\array_intersect_key(
                $this->config,
                \array_flip(['default', 'aliases', 'databases', 'connections'])
            )]);

        $container->register($this->prefix('factory'), SpiralDatabase::class)
            ->addSetup('setLogger');

        $container->register($this->prefix('dbal'), new Statement([new Reference(DatabaseProviderInterface::class), 'database']))
            ->setType(DatabaseInterface::class);

        $container->addAlias('spiraldb', $this->prefix('dbal'));

        if (\interface_exists(MigrationInterface::class)) {
            $container->register($this->prefix('migration.config'), MigrationConfig::class)
                ->setArguments([$this->config['migration']]);

            $container->register($this->prefix('migration'), SpiralMigrator::class)
                ->setArgument('dbal', $container->getDefinition($this->prefix('factory')));

            $container->register($this->prefix('migration.repository'), FileRepository::class);

            // Migrations
            if ($container->getParameter('consoleMode')) {
                $container->register($this->prefix('spiral_migrate_command.init'), SpiralMigrateInitCommand::class)
                ->addTag('console.command', 'spiral:migrate:init');

                $container->register($this->prefix('spiral_migrate_command.start'), SpiralMigrateStartCommand::class)
                ->addTag('console.command', 'spiral:migrate:start');

                $container->register($this->prefix('spiral_migrate_command.replay'), SpiralMigrateReplayCommand::class)
                ->addTag('console.command', 'spiral:migrate:replay');

                $container->register($this->prefix('spiral_migrate_command.rollback'), SpiralMigrateRollbackCommand::class)
                ->addTag('console.command', 'spiral:migrate:rollback');

                $container->register($this->prefix('spiral_migrate_command.status'), SpiralMigrateStatusCommand::class)
                ->addTag('console.command', 'spiral:migrate:status');
            }
        }

        if (\interface_exists(ORM\ORMInterface::class)) {
            $container->getDefinition($this->prefix('factory'))
                ->setAutowired(false);

            $container->register($this->prefix('orm.factory'), CycleFactory::class)
                ->setArgument('dbal', $container->getDefinition($this->prefix('factory')));

            $container->register($this->prefix('orm.transaction'), ORM\Transaction::class);

            if (\class_exists(Compiler::class)) {
                $container->register($this->prefix('orm.schema.registry'), Registry::class);

                $schema = new Statement(
                    [new Statement(Compiler::class), 'compile'],
                    [1 => $this->getGenerators()]
                );

                if ($container->getParameter('consoleMode')) {
                    $container->register($this->prefix('cycle_command.sync'), SpiralMigrateSyncCommand::class)
                        ->setArgument('generators', $schema)
                        ->addTag('console.command', 'spiral:migrate:sync');

                    if (\class_exists(GenerateMigrations::class)) {
                        $container->register($this->prefix('orm.schema.generate_migrations'), GenerateMigrations::class);

                        $container->register($this->prefix('cycle_command.migrate'), SpiralMigrateCycleCommand::class)
                            ->setArgument('generators', $schema)
                            ->addTag('console.command', 'spiral:migrate:cycle');
                    }
                }

                $container->register($this->prefix('orm.schema'), new Statement(ORM\Schema::class, [$schema]));
                $container->register($this->prefix('orm.schema.generate_relations'), Generator\GenerateRelations::class);
            }

            $container->register($this->prefix('orm'), ORM\ORM::class);
            $container->addAlias('cycleorm', $this->prefix('orm'));
        }

        if (!$container->getParameter('consoleMode')) {
            return;
        }

        // Commands
        $container->register($this->prefix('spiral_db_command.list'), SpiralDbListCommand::class)
            ->addTag('console.command', 'spiral:db:list');

        $container->register($this->prefix('spiral_db_command.table'), SpiralDbTableCommand::class)
            ->addTag('console.command', 'spiral:db:table');
    }

    /**
     * @param string                $directory
     * @param null|ContainerBuilder $container
     */
    public function getClassLocator(string $directory, ?ContainerBuilder $container = null)
    {
        $container = $container ?? $this->getContainerBuilder();

        return new Statement(
            ClassLocator::class,
            [
                new PhpLiteral(
                    $container->formatPhp(
                        '(?)->files()->in(?)',
                        [new Statement(Finder::class), $directory]
                    )
                ),
            ]
        );
    }

    /**
     * @return GeneratorInterface[]
     */
    private function getGenerators(): array
    {
        $generators = [];

        if (\class_exists(\Cycle\Annotated\Configurator::class)) {
            $generators = \array_merge($generators, [
                new Statement(Annotated\Embeddings::class, [$this->getClassLocator($this->getFromConfig('orm.entities'))]), # register embeddable entities
                new Statement(Annotated\Entities::class, [$this->getClassLocator($this->getFromConfig('orm.entities'))]), # register annotated entities
                new Statement(Annotated\MergeColumns::class), # copy column declarations from all related classes (@Table annotation)
                new Statement(Annotated\MergeIndexes::class), # copy index declarations from all related classes (@Table annotation)
            ]);
        }

        $generators = \array_merge($generators, [
            new Statement(Generator\ResetTables::class), # re-declared table schemas (remove columns)
            new Reference(Generator\GenerateRelations::class), # generate entity relations
            new Statement(Generator\ValidateEntities::class), # make sure all entity schemas are correct
            new Statement(Generator\RenderTables::class), # declare table schemas
            new Statement(Generator\RenderRelations::class), # declare relation keys and indexes
            new Statement(Generator\GenerateTypecast::class), # typecast non string columns
        ]);

        return $generators;
    }
}
