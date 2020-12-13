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

namespace Biurad\Framework\Extensions;

use Biurad\Cycle\Commands\Database\ListCommand;
use Biurad\Cycle\Commands\Database\TableCommand;
use Biurad\Cycle\Commands\Migrations\CycleCommand;
use Biurad\Cycle\Commands\Migrations\InitCommand;
use Biurad\Cycle\Commands\Migrations\ReplayCommand;
use Biurad\Cycle\Commands\Migrations\RollbackCommand;
use Biurad\Cycle\Commands\Migrations\StartCommand;
use Biurad\Cycle\Commands\Migrations\StatusCommand;
use Biurad\Cycle\Commands\Migrations\SyncCommand;
use Biurad\Cycle\Compiler;
use Biurad\Cycle\Database;
use Biurad\Cycle\Factory;
use Biurad\Cycle\Migrator;
use Biurad\DependencyInjection\Extension;
use Biurad\Framework\Kernel;
use Cycle\Annotated;
use Cycle\Annotated\Configurator;
use Cycle\Migrations\GenerateMigrations;
use Cycle\ORM;
use Cycle\Schema\Generator;
use Cycle\Schema\GeneratorInterface;
use Cycle\Schema\Registry;
use Nette;
use Nette\DI\Definitions\Reference;
use Nette\DI\Definitions\Statement;
use Nette\Loaders\RobotLoader;
use Nette\PhpGenerator\PhpLiteral;
use Nette\Schema\Expect;
use Spiral\Database\Config\DatabaseConfig;
use Spiral\Database\DatabaseInterface;
use Spiral\Database\DatabaseProviderInterface;
use Spiral\Migrations\Config\MigrationConfig;
use Spiral\Migrations\FileRepository;
use Spiral\Migrations\MigrationInterface;

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

        if (!\interface_exists(DatabaseProviderInterface::class)) {
            return;
        }

        $container->register($this->prefix('config'), DatabaseConfig::class)
            ->setArguments([\array_intersect_key(
                $this->config,
                \array_flip(['default', 'aliases', 'databases', 'connections'])
            )]);

        $container->register($this->prefix('factory'), Database::class)
            ->addSetup('setLogger');

        $container->register($this->prefix('dbal'), new Statement([new Reference(DatabaseProviderInterface::class), 'database']))
            ->setType(DatabaseInterface::class);

        $container->addAlias('spiraldb', $this->prefix('dbal'));

        if (\interface_exists(MigrationInterface::class)) {
            $container->register($this->prefix('migration.config'), MigrationConfig::class)
                ->setArguments([$this->config['migration']]);

            $container->register($this->prefix('migration'), Migrator::class)
                ->setArgument('dbal', $container->getDefinition($this->prefix('factory')));

            $container->register($this->prefix('migration.repository'), FileRepository::class);

            // Migrations
            if ($container->getParameter('consoleMode')) {
                $container->register($this->prefix('spiral_migrate_command.init'), InitCommand::class)
                    ->addTag('console.command', 'migrations:init');

                $container->register($this->prefix('spiral_migrate_command.start'), StartCommand::class)
                    ->addTag('console.command', 'migrations:start');

                $container->register($this->prefix('spiral_migrate_command.replay'), ReplayCommand::class)
                    ->addTag('console.command', 'migrations:replay');

                $container->register($this->prefix('spiral_migrate_command.rollback'), RollbackCommand::class)
                    ->addTag('console.command', 'migrations:rollback');

                $container->register($this->prefix('spiral_migrate_command.status'), StatusCommand::class)
                    ->addTag('console.command', 'migrations:status');
            }
        }

        if (\interface_exists(ORM\ORMInterface::class)) {
            $container->register($this->prefix('orm.factory'), Factory::class)
                ->setArgument('dbal', $container->getDefinition($this->prefix('factory'))->setAutowired(false))
                ->setArgument('config', new Statement([ORM\Config\RelationConfig::class, 'getDefault']));

            $container->register($this->prefix('orm.transaction'), ORM\Transaction::class);

            if (\class_exists(Compiler::class)) {
                $container->register($this->prefix('orm.schema.registry'), Registry::class);
                $container->register($this->prefix('orm.schema.generate_relations'), Generator\GenerateRelations::class);

                $container->register($this->prefix('orm.schema.compiler'), Compiler::class)
                    ->setArgument('generators', $this->getGenerators());

                if ($container->getParameter('consoleMode')) {
                    $container->register($this->prefix('cycle_command.sync'), SyncCommand::class)
                        ->addTag('console.command', 'migrations:sync');

                    if (\class_exists(GenerateMigrations::class)) {
                        $container->register($this->prefix('orm.schema.generate_migrations'), GenerateMigrations::class);

                        $container->register($this->prefix('cycle_command.migrate'), CycleCommand::class)
                            ->addTag('console.command', 'migrations:cycle');
                    }
                }

                $container->register($this->prefix('orm'), ORM\ORM::class)
                   ->setArgument('schema', new Statement(
                       ORM\Schema::class,
                       [new Statement([new Reference(Compiler::class), 'compile'])]
                   ));

                $container->addAlias('cycleorm', $this->prefix('orm'));
            }
        }

        if (!$container->getParameter('consoleMode')) {
            return;
        }

        // Commands
        $container->register($this->prefix('spiral_db_command.list'), ListCommand::class)
            ->addTag('console.command', 'database:list');

        $container->register($this->prefix('spiral_db_command.table'), TableCommand::class)
            ->addTag('console.command', 'database:table');
    }

    /**
     * @return PhpLiteral[]
     */
    public function getClassLocator(): array
    {
        $robot   = $this->createRobotLoader();
        $classes = [];

        foreach (\array_unique(\array_keys($robot->getIndexedClasses())) as $class) {
            // Skip not existing class
            if (!\class_exists($class)) {
                continue;
            }

            // Remove `Biurad\Framework\Kernel` class
            if (\is_subclass_of($class, Kernel::class)) {
                continue;
            }

            $classes[] = new PhpLiteral($class . '::class');
        }

        return $classes;
    }

    protected function createRobotLoader(): RobotLoader
    {
        $robot = new RobotLoader();
        $robot->addDirectory($this->getFromConfig('orm.entities'));
        $robot->acceptFiles = ['*.php'];
        $robot->rebuild();

        return $robot;
    }

    /**
     * @return GeneratorInterface[]
     */
    private function getGenerators(): array
    {
        $generators = [new Statement(Generator\ResetTables::class)]; # re-declared table schemas (remove columns)

        if (\class_exists(\Cycle\Annotated\Configurator::class)) {
            $generators = \array_merge([
                new Statement(\Biurad\Cycle\Annotated\Embeddings::class, [$this->getClassLocator()]), # register embeddable entities
                new Statement(\Biurad\Cycle\Annotated\Entities::class, [$this->getClassLocator()]), # register annotated entities
                new Statement(Annotated\MergeColumns::class), # copy column declarations from all related classes (@Table annotation)
            ], $generators);
        }

        $generators = \array_merge($generators, [
            new Reference(Generator\GenerateRelations::class), # generate entity relations
            new Statement(Generator\ValidateEntities::class), # make sure all entity schemas are correct
            new Statement(Generator\RenderTables::class), # declare table schemas
            new Statement(Generator\RenderRelations::class), # declare relation keys and indexes
            new Statement(Generator\GenerateTypecast::class), # typecast non string columns
            class_exists(Configurator::class) ? new Statement(Annotated\MergeIndexes::class) : null, # copy index declarations from all related classes (@Table annotation)
        ]);

        return array_filter($generators);
    }
}
