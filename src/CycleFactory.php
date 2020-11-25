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

namespace Biurad\Framework;

use Biurad\DependencyInjection\FactoryInterface as NetteFactoryInterface;
use Closure;
use Cycle\ORM\Config\RelationConfig;
use Cycle\ORM\Exception\TypecastException;
use Cycle\ORM\FactoryInterface;
use Cycle\ORM\Mapper\Mapper;
use Cycle\ORM\MapperInterface;
use Cycle\ORM\ORMInterface;
use Cycle\ORM\Relation;
use Cycle\ORM\Relation\RelationInterface;
use Cycle\ORM\RepositoryInterface;
use Cycle\ORM\Schema;
use Cycle\ORM\SchemaInterface;
use Cycle\ORM\Select;
use Cycle\ORM\Select\ConstrainInterface;
use Cycle\ORM\Select\LoaderInterface;
use Cycle\ORM\Select\Repository;
use Cycle\ORM\Select\Source;
use Cycle\ORM\Select\SourceInterface;
use Spiral\Core\Container\Autowire;
use Spiral\Database\DatabaseInterface;
use Spiral\Database\DatabaseProviderInterface;

final class CycleFactory implements FactoryInterface
{
    /** @var RelationConfig */
    private $config;

    /** @var NetteFactoryInterface */
    private $factory;

    /** @var DatabaseProviderInterface */
    private $dbal;

    /** @var array<string, string> */
    private $defaults = [
        Schema::REPOSITORY => Repository::class,
        Schema::SOURCE     => Source::class,
        Schema::MAPPER     => Mapper::class,
        Schema::CONSTRAIN  => null,
    ];

    /**
     * @param DatabaseProviderInterface $dbal
     * @param RelationConfig            $config
     * @param NetteFactoryInterface     $factory
     */
    public function __construct(
        DatabaseProviderInterface $dbal,
        RelationConfig $config = null,
        NetteFactoryInterface $factory = null
    ) {
        $this->dbal    = $dbal;
        $this->factory = $factory;
        $this->config  = $config ?? RelationConfig::getDefault();
    }

    /**
     * @inheritdoc
     */
    public function make(string $alias, array $parameters = [])
    {
        return $this->factory->createInstance($alias, $parameters);
    }

    /**
     * @inheritdoc
     */
    public function mapper(
        ORMInterface $orm,
        SchemaInterface $schema,
        string $role
    ): MapperInterface {
        $class = $schema->define($role, Schema::MAPPER) ?? $this->defaults[Schema::MAPPER];

        if (!\is_subclass_of($class, MapperInterface::class)) {
            throw new TypecastException($class . ' does not implement ' . MapperInterface::class);
        }

        return $this->factory->createInstance(
            $class,
            [
                'orm'    => $orm,
                'role'   => $role,
                'schema' => $schema->define($role, Schema::SCHEMA),
            ]
        );
    }

    /**
     * @inheritdoc
     */
    public function loader(
        ORMInterface $orm,
        SchemaInterface $schema,
        string $role,
        string $relation
    ): LoaderInterface {
        $schema  = $schema->defineRelation($role, $relation);
        $factory = clone $this->factory;

        $loader = Closure::bind(
            static function (Autowire $autowire) use ($factory, $orm, $relation, $schema): LoaderInterface {
                return $factory->createInstance(
                    $autowire->alias,
                    [
                        'orm'    => $orm,
                        'name'   => $relation,
                        'target' => $schema[Relation::TARGET],
                        'schema' => $schema[Relation::SCHEMA],
                    ]
                );
            },
            null,
            Autowire::class
        );

        return $loader($this->config->getLoader($schema[Relation::TYPE]));
    }

    /**
     * @inheritdoc
     */
    public function relation(
        ORMInterface $orm,
        SchemaInterface $schema,
        string $role,
        string $relation
    ): RelationInterface {
        $relSchema = $schema->defineRelation($role, $relation);
        $type      = $relSchema[Relation::TYPE];
        $factory   = clone $this->factory;

        $relation = Closure::bind(
            static function (Autowire $autowire) use ($factory, $orm, $relation, $relSchema): RelationInterface {
                return $factory->createInstance(
                    $autowire->alias,
                    [
                        'orm'    => $orm,
                        'name'   => $relation,
                        'target' => $relSchema[Relation::TARGET],
                        'schema' => $relSchema[Relation::SCHEMA] + [Relation::LOAD => $relSchema[Relation::LOAD] ?? null],
                    ]
                );
            },
            null,
            Autowire::class
        );

        return $relation($this->config->getRelation($type));
    }

    /**
     * @inheritdoc
     */
    public function database(string $database = null): DatabaseInterface
    {
        return $this->dbal->database($database);
    }

    /**
     * @inheritDoc
     */
    public function repository(
        ORMInterface $orm,
        SchemaInterface $schema,
        string $role,
        ?Select $select
    ): RepositoryInterface {
        $class = $schema->define($role, Schema::REPOSITORY) ?? $this->defaults[Schema::REPOSITORY];

        if (!\is_subclass_of($class, RepositoryInterface::class)) {
            throw new TypecastException($class . ' does not implement ' . RepositoryInterface::class);
        }

        return $this->factory->createInstance(
            $class,
            [
                'select' => $select,
                'orm'    => $orm,
                'role'   => $role,
            ]
        );
    }

    /**
     * @inheritDoc
     */
    public function source(
        ORMInterface $orm,
        SchemaInterface $schema,
        string $role
    ): SourceInterface {
        $source = $schema->define($role, Schema::SOURCE) ?? $this->defaults[Schema::SOURCE];

        if (!\is_subclass_of($source, SourceInterface::class)) {
            throw new TypecastException($source . ' does not implement ' . SourceInterface::class);
        }

        if ($source !== Source::class) {
            return $this->factory->make($source, ['orm' => $orm, 'role' => $role]);
        }

        $source = new Source(
            $this->database($schema->define($role, Schema::DATABASE)),
            $schema->define($role, Schema::TABLE)
        );

        $constrain = $schema->define($role, Schema::CONSTRAIN) ?? $this->defaults[Schema::CONSTRAIN];

        if (null === $constrain) {
            return $source;
        }

        if (!\is_subclass_of($constrain, ConstrainInterface::class)) {
            throw new TypecastException($constrain . ' does not implement ' . ConstrainInterface::class);
        }

        return $source->withConstrain(\is_object($constrain) ? $constrain : $this->factory->make($constrain));
    }

    /**
     * Add default classes for resolve
     *
     * @param array $defaults
     *
     * @return FactoryInterface
     */
    public function withDefaultSchemaClasses(array $defaults): FactoryInterface
    {
        $clone = clone $this;

        $clone->defaults = $defaults + $this->defaults;

        return $clone;
    }
}
