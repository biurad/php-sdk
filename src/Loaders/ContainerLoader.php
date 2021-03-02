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

namespace Biurad\Framework\Loaders;

use Biurad;
use Biurad\DependencyInjection\Adapters;
use Biurad\DependencyInjection\Loader;
use Composer\Autoload\ClassLoader;
use Contributte;
use Nette;
use Nette\Bootstrap\Configurator;
use Nette\DI;
use Psr\Container\ContainerInterface;
use Tracy;

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
        'events'        => Biurad\Framework\Extensions\EventDispatcherExtension::class,
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
        \ArrayAccess::class,
        \Countable::class,
        \IteratorAggregate::class,
        \SplDoublyLinkedList::class,
        \stdClass::class,
        \SplStack::class,
        \Iterator::class,
        \Traversable::class,
        \Serializable::class,
        \JsonSerializable::class,
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
            $this->staticParameters['debugMode']
        );

        return $loader->load(
            \Closure::fromCallable([$this, 'generateContainer']),
            [
                $this->staticParameters,
                \array_keys($this->dynamicParameters),
                $this->configs,
                \PHP_VERSION_ID - \PHP_RELEASE_VERSION, // minor PHP version
                \class_exists(ClassLoader::class) ? \filemtime((new \ReflectionClass(ClassLoader::class))->getFilename()) : null, // composer update
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
        $defaults  = parent::getDefaultParameters();

        return array_replace($defaults, [
            'appDir' => isset($trace[2]['file']) ? \dirname($trace[2]['file']) : null,
            'wwwDir' => isset($last['file']) ? \dirname($last['file']) : null,
        ]);
    }

    /**
     * Get the loader
     */
    protected function createLoader(): DI\Config\Loader
    {
        $loader = parent::createLoader();

        $loader->addAdapter('yaml', Adapters\YamlAdapter::class);
        $loader->addAdapter('yml', Adapters\YamlAdapter::class);
        $loader->addAdapter('xml', Adapters\XmlAdapter::class);

        return $loader;
    }
}
