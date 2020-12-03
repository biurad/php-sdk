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

use ArrayAccess;
use Biurad;
use Biurad\DependencyInjection\Loader;
use Biurad\DependencyInjection\XmlAdapter;
use Closure;
use Composer\Autoload\ClassLoader;
use Contributte;
use Countable;
use Iterator;
use IteratorAggregate;
use JsonSerializable;
use Nette;
use Nette\Configurator;
use Nette\DI;
use Nette\DI\Config\Adapters\NeonAdapter;
use Nette\Neon;
use Nette\Utils\FileSystem;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use Serializable;
use SplDoublyLinkedList;
use SplStack;
use stdClass;
use Tracy;
use Traversable;

class ContainerLoader extends Configurator
{
    /** @var array */
    public $defaultExtensions = [
        'extensions'    => Nette\DI\Extensions\ExtensionsExtension::class,
        'php'           => Nette\DI\Extensions\PhpExtension::class,
        'constants'     => Nette\DI\Extensions\ConstantsExtension::class,
        'di'            => [Biurad\Framework\Extensions\DIExtension::class, ['%debugMode%']],
        'decorator'     => Nette\DI\Extensions\DecoratorExtension::class,
        'inject'        => Nette\DI\Extensions\InjectExtension::class,
        'search'        => [Nette\DI\Extensions\SearchExtension::class, ['%tempDir%/cache/nette.searches']],
        'aware'         => Contributte\DI\Extension\ContainerAwareExtension::class,
        'autoload'      => Contributte\DI\Extension\ResourceExtension::class,
        'callable'      => Biurad\Framework\Extensions\InvokerExtension::class,
        'cache'         => Biurad\Framework\Extensions\CacheExtension::class,
        'annotation'    => Biurad\Framework\Extensions\AnnotationsExtension::class,
        'events'        => [Biurad\Framework\Extensions\EventDispatcherExtension::class, ['%appDir%']],
        'http'          => [Biurad\Framework\Extensions\HttpExtension::class, ['%tempDir%/session']],
        'routing'       => Biurad\Framework\Extensions\RouterExtension::class,
        'filesystem'    => Biurad\Framework\Extensions\FileManagerExtension::class,
        'templating'    => Biurad\Framework\Extensions\TemplatingExtension::class,
        'leanmapper'    => [Biurad\Framework\Extensions\LeanMapperExtension::class, ['%appDir%']],
        'cycle'         => [Biurad\Framework\Extensions\SpiralDatabaseExtension::class, ['%appDir%', '%tempDir%/migrations']],
        'console'       => [Biurad\Framework\Extensions\TerminalExtension::class, ['%appDir%']],
        'framework'     => Biurad\Framework\Extensions\FrameworkExtension::class,
        'tracy'         => [Tracy\Bridges\Nette\TracyExtension::class, ['%debugMode%', '%consoleMode%']],
    ];

    /** @var string[] of classes which shouldn't be autowired */
    public $autowireExcludedClasses = [
        ArrayAccess::class,
        Countable::class,
        IteratorAggregate::class,
        SplDoublyLinkedList::class,
        stdClass::class,
        SplStack::class,
        Iterator::class,
        Traversable::class,
        Serializable::class,
        JsonSerializable::class,
    ];

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
     * Loads system DI container class and returns its name.
     *
     * @return string
     */
    public function loadContainer(): string
    {
        $loader = new Loader(
            $this->getCacheDirectory() . '/nette.configurator',
            $this->parameters['debugMode']
        );

        return $loader->load(
            Closure::fromCallable([$this, 'generateContainer']),
            [
                $this->parameters,
                \array_keys($this->dynamicParameters),
                $this->configs,
                \PHP_VERSION_ID - \PHP_RELEASE_VERSION, // minor PHP version
                \class_exists(ClassLoader::class) ? \filemtime((new ReflectionClass(ClassLoader::class))->getFilename()) : null, // composer update
            ]
        );
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
