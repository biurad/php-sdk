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
use Nette\DirectoryNotFoundException;
use Nette\SmartObject;
use Psr\Container\ContainerInterface;
use Symfony\Component\Dotenv\Dotenv;
use Throwable;

/**
 * The Kernel is the heart of the Biurad system.
 *
 * It manages an environment made of bundles and compilers.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class Kernel
{
    use SmartObject;

    /**
     * @todo Make this constant public in version 1.0.0
     */
    private const CONFIG_EXTS = '.{php,xml,yaml,yml,neon}';

    /**
     * Initiate application core.
     *
     * @param array<string,mixed> $directories  directory map, "root", "tempDir", and "configDir" is required
     * @param bool                $handleErrors enable global error handling
     * @param bool                $return       If set to true, container instance will return
     *
     * @return null|ContainerInterface
     *
     * @throws Throwable
     */
    public static function boot(array $directories, bool $handleErrors = true, bool $return = false)
    {
        if (!isset($directories['root'], $directories['tempDir'], $directories['configDir'])) {
            throw new DirectoryNotFoundException('Unable to locate [root, tempDir, configDir] directories');
        }

        $directories = self::resolveDirectories($directories);
        $loader      = new ContainerLoader(); // Boot the CoreKenel for processes to begin...

        $loader->initializeBundles(require $directories['configDir'] . '/bundles.php');
        $container = self::initializeContainer($directories, $handleErrors, $loader);

        foreach ($loader->getBundles() as $bundle) {
            $bundle->setContainer($container);
            $bundle->boot();
        }

        return $return ? $container : null;
    }

    /**
     * Initializes the service container.
     *
     * @param array<string,mixed> $directories
     *
     * @return ContainerInterface
     */
    protected static function initializeContainer(array $directories, bool $handleErrors, Configurator $loader)
    {
        // Load application config in a pre-defined order in such a way that local settings
        // overwrite global settings. (Loaded as first to last):
        //   - `parameters.{php,xml,yaml,yml,neon}`
        //   - `global.{php,xml,yaml,yml,neon}`
        //   - `services.{php,xml,yaml,yml,neon}`
        //   - `packages/*.{php,xml,yaml,yml,neon}`
        $config = \sprintf(
            '%s/{{parameters,services,global},{packages/}}*%s',
            $directories['configDir'],
            self::CONFIG_EXTS
        );

        //Load the environmental file.
        if (\file_exists($envPath = $directories['root'] . \DIRECTORY_SEPARATOR . '.env')) {
            $_SERVER['APP_DEBUG'] = $loader->isDebugMode() || 'cli' === \PHP_SAPI;

            (new Dotenv())->loadEnv($envPath);
            $loader->addDynamicParameters(['env' => $_ENV]);
        }

        // Let's enable our debugger our exceptions first.
        if (false !== $handleErrors) {
            $errorEmail = $_ENV['APP_ERROR_MAIL'] ?? null;

            //$loader->setDebugMode('23.75.345.200'); // enable for your remote IP
            //$loader->setDebugMode(false); // uncomment to start in production mode
            $loader->enableDebugger($directories['tempDir'] . \DIRECTORY_SEPARATOR . 'logs', $errorEmail);
        }

        $loader->addParameters([
            'envMode'   => $loader->isDebugMode() ? 'dev' : ('cli' === \PHP_SAPI ? 'cli' : 'prod'),
            'rootDir'   => $directories['root'],
            'configDir' => $directories['configDir'],
        ]);
        $loader->setTempDirectory($directories['tempDir']);

        foreach (\glob($config, \GLOB_BRACE) as $configPath) {
            $loader->addConfig($configPath);
        }

        return $loader->createContainer();
    }

    /**
     * @param array<string,mixed> $directories
     *
     * @return array<string,mixed>
     */
    private static function resolveDirectories(array $directories): array
    {
        $newDirectoris = [];
        $rootPath      = \rtrim($directories['root'], '\\/');

        // Remove root directory for $directories and set new
        unset($directories['root']);
        $newDirectoris['root'] = $rootPath;

        foreach ($directories as $name => $path) {
            $newDirectoris[$name] = $rootPath . \DIRECTORY_SEPARATOR . \trim($path, '\\/');
        }

        return $newDirectoris;
    }
}
