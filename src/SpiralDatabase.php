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

use Biurad\DependencyInjection\FactoryInterface;
use Closure;
use Psr\Container\ContainerExceptionInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Spiral\Core\Container\Autowire;
use Spiral\Database\Config\DatabaseConfig;
use Spiral\Database\Config\DatabasePartial;
use Spiral\Database\Database;
use Spiral\Database\DatabaseInterface;
use Spiral\Database\DatabaseProviderInterface;
use Spiral\Database\Driver\DriverInterface;
use Spiral\Database\Exception\DBALException;

/**
 * Automatic factory and configurator for Drivers and Databases.
 *
 * Example:
 * $config = [
 *  'default'     => 'default',
 *  'aliases'     => [
 *      'default'  => 'primary',
 *      'database' => 'primary',
 *      'db'       => 'primary',
 *  ],
 *  'databases'   => [
 *      'primary'   => [
 *          'connection'  => 'mysql',
 *          'tablePrefix' => 'db_'
 *      ],
 *      'secondary' => [
 *          'connection'  => 'postgres',
 *          'tablePrefix' => '',
 *      ],
 *  ],
 *  'connections' => [
 *      'mysql'     => [
 *          'driver'     => Driver\MySQL\MySQLDriver::class,
 *          'options' => [
 *              'connection' => 'mysql:host=127.0.0.1;dbname=database',
 *              'username'   => 'mysql',
 *              'password'   => 'mysql',
 *           ],
 *      ],
 *      'postgres'  => [
 *          'driver'     => Driver\Postgres\PostgresDriver::class,
 *          'options' => [
 *              'connection' => 'pgsql:host=127.0.0.1;dbname=database',
 *              'username'   => 'postgres',
 *              'password'   => 'postgres',
 *           ],
 *      ],
 *      'runtime'   => [
 *          'driver'     => Driver\SQLite\SQLiteDriver::class,
 *          'options' => [
 *              'connection' => 'sqlite:' . directory('runtime') . 'runtime.db',
 *              'username'   => 'sqlite',
 *           ],
 *      ],
 *      'sqlServer' => [
 *          'driver'     => Driver\SQLServer\SQLServerDriver::class,
 *          'options' => [
 *              'connection' => 'sqlsrv:Server=OWNER;Database=DATABASE',
 *              'username'   => 'sqlServer',
 *              'password'   => 'sqlServer',
 *           ],
 *      ],
 *   ]
 * ];
 *
 * $manager = new DatabaseManager(new DatabaseConfig($config));
 *
 * echo $manager->database('runtime')->select()->from('users')->count();
 */
final class SpiralDatabase implements DatabaseProviderInterface
{
    use LoggerAwareTrait;

    /** @var DatabaseConfig */
    private $config;

    /** @var FactoryInterface */
    private $factory;

    /** @var Database[] */
    private $databases = [];

    /** @var DriverInterface[] */
    private $drivers = [];

    /**
     * @param DatabaseConfig   $config
     * @param FactoryInterface $factory
     */
    public function __construct(DatabaseConfig $config, FactoryInterface $factory)
    {
        $this->config  = $config;
        $this->factory = $factory;
    }

    /**
     * Get all databases.
     *
     * @throws DatabaseException
     *
     * @return Database[]
     */
    public function getDatabases(): array
    {
        $names = \array_unique(
            \array_merge(
                \array_keys($this->databases),
                \array_keys($this->config->getDatabases())
            )
        );

        $result = [];

        foreach ($names as $name) {
            $result[] = $this->database($name);
        }

        return $result;
    }

    /**
     * Get Database associated with a given database alias or automatically created one.
     *
     * @param null|string $database
     *
     * @throws DBALException
     *
     * @return Database|DatabaseInterface
     */
    public function database(string $database = null): DatabaseInterface
    {
        if ($database === null) {
            $database = $this->config->getDefaultDatabase();
        }

        //Spiral support ability to link multiple virtual databases together using aliases
        $database = $this->config->resolveAlias($database);

        if (isset($this->databases[$database])) {
            return $this->databases[$database];
        }

        if (!$this->config->hasDatabase($database)) {
            throw new DBALException(
                "Unable to create Database, no presets for '{$database}' found"
            );
        }

        return $this->databases[$database] = $this->makeDatabase(
            $this->config->getDatabase($database)
        );
    }

    /**
     * Add new database.
     *
     * @param Database $database
     *
     * @throws DBALException
     */
    public function addDatabase(Database $database): void
    {
        if (isset($this->databases[$database->getName()])) {
            throw new DBALException("Database '{$database->getName()}' already exists");
        }

        $this->databases[$database->getName()] = $database;
    }

    /**
     * Get instance of every available driver/connection.
     *
     * @throws DBALException
     *
     * @return Driver[]
     */
    public function getDrivers(): array
    {
        $names = \array_unique(
            \array_merge(
                \array_keys($this->drivers),
                \array_keys($this->config->getDrivers())
            )
        );

        $result = [];

        foreach ($names as $name) {
            $result[] = $this->driver($name);
        }

        return $result;
    }

    /**
     * Get driver instance by it's name or automatically create one.
     *
     * @param string $driver
     *
     * @throws DBALException
     *
     * @return DriverInterface
     */
    public function driver(string $driver): DriverInterface
    {
        if (isset($this->drivers[$driver])) {
            return $this->drivers[$driver];
        }

        $factory = clone $this->factory;

        try {
            $driverObject = Closure::bind(
                static function (Autowire $autowire) use ($factory): DriverInterface {
                    return $factory->createInstance($autowire->alias, $autowire->parameters);
                },
                null,
                Autowire::class
            );
            $driverObject = $driverObject($this->config->getDriver($driver));

            $this->drivers[$driver] = $driverObject;

            if ($driverObject instanceof LoggerAwareInterface) {
                if (!$this->logger instanceof NullLogger) {
                    $driverObject->setLogger($this->logger);
                }
            }

            return $this->drivers[$driver];
        } catch (ContainerExceptionInterface $e) {
            throw new DBALException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Manually set connection instance.
     *
     * @param string          $name
     * @param DriverInterface $driver
     *
     * @throws DBALException
     *
     * @return self
     */
    public function addDriver(string $name, DriverInterface $driver): SpiralDatabase
    {
        if (isset($this->drivers[$name])) {
            throw new DBALException("Connection '{$name}' already exists");
        }

        $this->drivers[$name] = $driver;

        return $this;
    }

    /**
     * @param DatabasePartial $database
     *
     * @throws DBALException
     *
     * @return Database
     */
    protected function makeDatabase(DatabasePartial $database): Database
    {
        return new Database(
            $database->getName(),
            $database->getPrefix(),
            $this->driver($database->getDriver()),
            $database->getReadDriver() ? $this->driver($database->getReadDriver()) : null
        );
    }
}
