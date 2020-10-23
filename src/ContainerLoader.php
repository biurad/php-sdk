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

use Biurad\Framework\DependencyInjection\Extensions\BundlesExtension;
use Biurad\Framework\DependencyInjection\Loader;
use Biurad\Framework\DependencyInjection\XmlAdapter;
use Biurad\Framework\Interfaces\BundleInterface;
use Composer\Autoload\ClassLoader;
use LogicException;
use Nette\Configurator;
use Nette\DI;
use Nette\DI\Config\Adapters\NeonAdapter;
use Nette\Neon;
use Nette\PhpGenerator\Helpers;
use Nette\Utils\FileSystem;
use Psr\Container\ContainerInterface;
use ReflectionClass;

class ContainerLoader extends Configurator
{
    /** @var BundleInterface[] */
    protected $bundles = [];

    /**
     * Initializes bundles.
     *
     * @param BundleInterface[]|string[]
     *
     * @throws LogicException if two bundles share a common name
     */
    public function initializeBundles(array $bundles): void
    {
        // init bundles
        $this->bundles = [];

        foreach ($bundles as $bundle) {
            $name = \md5($bundle);

            if (isset($this->bundles[$name])) {
                throw new LogicException(\sprintf('Trying to register two bundles with the same name "%s".', $name));
            }

            $this->bundles[$name] = \is_object($bundle) ? $bundle : new $bundle();
        }
    }

    /**
     * Gets the registered bundle instances.
     *
     * @return BundleInterface[] — An array of registered bundle instances
     */
    public function getBundles(): array
    {
        return $this->bundles;
    }

    /**
     * Returns system DI container.
     *
     * @return ContainerInterface
     */
    public function createContainer(): DI\Container
    {
        return parent::createContainer();
    }

    /**
     * Prepares the ContainerBuilder before it is compiled.
     *
     * @internal
     */
    public function generateContainer(DI\Compiler $compiler): void
    {
        $loader = $this->createLoader();
        $loader->setParameters($this->parameters);

        foreach ($this->configs as $config) {
            if (\is_string($config)) {
                $compiler->loadConfig($config, $loader);
            } else {
                $compiler->addConfig($config);
            }
        }

        $compiler->addConfig(['parameters' => $this->parameters]);
        $compiler->setDynamicParameterNames(\array_keys($this->dynamicParameters));

        $extensions           = [];
        $extensions['bundle'] = [BundlesExtension::class, [$this->bundles]];

        foreach ($this->bundles as $bundle) {
            if (null !== $extension = $bundle->getContainerExtension()) {
                $name = \strtolower(Helpers::extractShortName($extension));
                $extensions[\substr($name, 0, -9)] = $extension;
            }
        }

        $defaultExtensions = (new ExtensionLoader($extensions, $this->parameters))->setCompiler($compiler);
        $defaultExtensions->load($compiler->getContainerBuilder());

        $this->onCompile($this, $compiler);
    }

    /**
     * Loads system DI container class and returns its name.
     */
    public function loadContainer(): string
    {
        $loader = new Loader(
            $this->getCacheDirectory() . '/nette.configurator',
            $this->parameters['debugMode']
        );

        $class = $loader->load(
            [$this, 'generateContainer'],
            [
                $this->parameters,
                \array_keys($this->dynamicParameters),
                $this->configs,
                \PHP_VERSION_ID - \PHP_RELEASE_VERSION, // minor PHP version
                \class_exists(ClassLoader::class) ? \filemtime((new ReflectionClass(ClassLoader::class))->getFilename()) : null, // composer update
            ]
        );

        return $class;
    }

    /**
     * @return array<string,mixed>
     */
    protected function getDefaultParameters(): array
    {
        $trace     = \debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS);
        $last      = \end($trace);
        $debugMode = static::detectDebugMode();
        $loaderRc  = \class_exists(ClassLoader::class) ? new ReflectionClass(ClassLoader::class) : null;

        return [
            'appDir'         => isset($trace[2]['file']) ? \dirname($trace[2]['file']) : null,
            'wwwDir'         => isset($last['file']) ? \dirname($last['file']) : null,
            'vendorDir'      => $loaderRc ? \dirname($loaderRc->getFileName(), 2) : null,
            'debugMode'      => $debugMode,
            'productionMode' => !$debugMode,
            'consoleMode'    => \PHP_SAPI === 'cli',
        ];
    }

    /**
     * Get the loader
     */
    protected function createLoader(): DI\Config\Loader
    {
        $yamlAdapter = new class() implements Di\Config\Adapter {
            /**
             * {@inheritdoc}
             */
            public function load(string $file): array
            {
                // So yaml syntax could work properly
                $contents = \str_replace(
                    ['~', '\'false\'', '\'true\'', '"false"', '"true"'],
                    ['null', 'false', 'true', 'false', 'true'],
                    FileSystem::read($file)
                );

                return (new NeonAdapter())->process((array) Neon\Neon::decode($contents));
            }

            /**
             * Generates configuration in NEON format.
             */
            public function dump(array $data): string
            {
                return (new NeonAdapter())->dump($data);
            }
        };

        $loader = new DI\Config\Loader();
        $loader->addAdapter('yaml', $yamlAdapter);
        $loader->addAdapter('yml', $yamlAdapter);
        $loader->addAdapter('xml', XmlAdapter::class);

        return $loader;
    }
}
