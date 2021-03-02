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
use Nette\Bootstrap\Configurator;
use Nette\SmartObject;
use Throwable;

/**
 * The Kernel is the heart of the Biurad system.
 * It manages an environment made of bundles and compilers.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
abstract class Kernel
{
    use SmartObject;

    /**
     * Load application config in a pre-defined order in such a way that local settings
     *  overwrite global settings. (Loaded as first to last):
     *    - `parameters.{php,xml,yaml,yml,neon}`
     *    - `global.{php,xml,yaml,yml,neon}`
     *    - `services.{php,xml,yaml,yml,neon}`
     *    - `packages/*.{php,xml,yaml,yml,neon}`
     */
    public const LOAD_CONFIG = '%s/{{parameters,services,global},{packages/}}*%s';

    /**
     * @todo Make this constant public in version 1.0.0
     */
    private const CONFIG_EXTS = '.{php,xml,yaml,yml,neon}';

    /**
     * Boot the application from self::initializeApp.
     *
     * @param Directory $directories  directory map, "root", "tempDir", and "configDir" is required
     * @param bool      $handleErrors enable global error handling
     *
     * @throws \Throwable
     *
     * @return FactoryInterface
     */
    abstract public static function boot(Directory $directories, bool $handleErrors = true);

    /**
     * Initiate application core.
     *
     * @param Directory $directories  directory map, "root", "tempDir", and "configDir" is required
     * @param bool      $handleErrors enable global error handling
     *
     * @throws \Throwable
     *
     * @return null|FactoryInterface
     */
    protected static function initializeApp(Directory $directories, bool $handleErrors = true)
    {
        $loader = new Loaders\ContainerLoader(); // Boot the CoreKenel for processes to begin...
        $loader->setTempDirectory($directories['tempDir']);

        // Let's enable our debugger our exceptions first.
        if (false !== $handleErrors) {
            //$loader->setDebugMode('23.75.345.200'); // enable for your remote IP

            // If this exist in Heroku server, serve debug mode then ...
            if (isset($_SERVER['HEROKU_SERVER_MODE'])) {
                $loader->setDebugMode(true);
            }

            $loader->enableDebugger($directories['logDir'] ?? null);
        }

        return static::initializeContainer($directories, $loader);
    }

    /**
     * Initializes the service container.
     *
     * @param Directory $directories
     *
     * @return \Nette\DI\Container
     */
    protected static function initializeContainer(Directory $directories, Configurator $loader)
    {
        $config  = \sprintf(static::LOAD_CONFIG, $directories['configDir'], self::CONFIG_EXTS);
        $envMode = $loader->isDebugMode() ? 'dev' : ('cli' === \PHP_SAPI ? 'cli' : 'prod');

        //Load the environmental file.
        if (isset($directories['envFile'])) {
            $_SERVER['APP_DEBUG'] = $loader->isDebugMode();
            $_SERVER['APP_ENV']   = $envMode;

            static::initializeEnv($directories['envFile']);
            $loader->addDynamicParameters(['env' => $_ENV]);
        }

        $loader->addParameters([
            'envMode'   => $envMode,
            'rootDir'   => $directories['root'],
            'configDir' => $directories['configDir'],
            'logDir'    => $directories['logDir'],
        ]);

        foreach (\glob($config, \GLOB_BRACE) as $configPath) {
            $loader->addConfig($configPath);
        }

        return $loader->createContainer();
    }

    /**
     * This function should be used to load .env variables into framework.
     *
     * @param string $envFile
     */
    protected static function initializeEnv(string $envFile): void
    {
        // Try loading $envFile ...
    }
}
