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

use Nette\Configurator;
use Nette\SmartObject;
use Psr\Container\ContainerInterface;
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
     * @param bool      $return       If set to true, container instance will return
     *
     * @throws Throwable
     *
     * @return null|ContainerInterface
     */
    abstract public static function boot(Directory $directories, bool $handleErrors = true, bool $return = false);

    /**
     * Initiate application core.
     *
     * @param Directory $directories  directory map, "root", "tempDir", and "configDir" is required
     * @param bool      $handleErrors enable global error handling
     * @param bool      $return       If set to true, container instance will return
     *
     * @throws Throwable
     *
     * @return null|ContainerInterface
     */
    protected static function initializeApp(Directory $directories, bool $handleErrors = true, bool $return = false)
    {
        $loader = new ContainerLoader(); // Boot the CoreKenel for processes to begin...
        $loader->setTempDirectory($directories['tempDir']);

        // Let's enable our debugger our exceptions first.
        if (false !== $handleErrors) {
            //$loader->setDebugMode('23.75.345.200'); // enable for your remote IP
            //$loader->setDebugMode(false); // uncomment to start in production mode
            $loader->enableDebugger($directories['logDir'] ?? null);
        }

        $loader->initializeBundles($directories['bundles'] ?? []);
        $container = static::initializeContainer($directories, $loader);

        foreach ($loader->getBundles() as $bundle) {
            $bundle->setContainer($container);

            if (isset($_ENV['PHPUNIT_TESTING']) && false !== $_ENV['PHPUNIT_TESTING']) {
                break;
            }
            $bundle->boot();
        }

        return $return ? $container : null;
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
