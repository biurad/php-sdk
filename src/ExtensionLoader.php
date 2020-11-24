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
use Biurad\DependencyInjection\Builder as ContainerBuilder;
use Composer\Autoload\ClassLoader;
use Composer\InstalledVersions;
use Contributte;
use Countable;
use Iterator;
use IteratorAggregate;
use JsonSerializable;
use Nette;
use Nette\DI\Compiler;
use Nette\DI\CompilerExtension;
use Nette\DI\Helpers;
use Nette\InvalidArgumentException;
use Nette\NotSupportedException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionObject;
use RuntimeException;
use Serializable;
use SplDoublyLinkedList;
use SplFileInfo;
use SplStack;
use stdClass;
use Tracy;
use Traversable;

class ExtensionLoader
{
    /** @var string[] of classes which shouldn't be autowired */
    private const EXCLUDED_CLASSES = [
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

    /** @var Compiler */
    private $compiler;

    /** @var array */
    private $parameters;

    /** @var array [id => CompilerExtension] */
    private $extensions = [
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
        'annotation'    => Biurad\Framework\Extensions\AnnotationsExtension::class,
        'events'        => [Biurad\Framework\Extensions\EventDispatcherExtension::class, ['%appDir%']],
        'http'          => [Biurad\Framework\Extensions\HttpExtension::class, ['%tempDir%/session']],
        'routing'       => Biurad\Framework\Extensions\RouterExtension::class,
        'console'       => [Biurad\Framework\Extensions\TerminalExtension::class, ['%appDir%']],
        'filesystem'    => Biurad\Framework\Extensions\FileManagerExtension::class,
        'templating'    => Biurad\Framework\Extensions\TemplatingExtension::class,
        'leanmapper'    => [Biurad\Framework\Extensions\LeanMapperExtension::class, ['%appDir%']],
        'spiraldb'      => [Biurad\Framework\Extensions\SpiralDatabaseExtension::class, ['%appDir%', '%tempDir%/migrations']],
        'tracy'         => [Tracy\Bridges\Nette\TracyExtension::class, ['%debugMode%', '%consoleMode%']],
    ];

    public function __construct(array $extensions = [], array $parameters = [])
    {
        $this->parameters = $parameters;
        $this->extensions = \array_merge($this->extensions, $extensions);
    }

    /**
     * Get the Container Compiler
     */
    public function getCompiler(): ?Compiler
    {
        return $this->compiler;
    }

    /**
     * Set the Container Compiler
     *
     * @param Compiler $compiler
     */
    public function setCompiler(Compiler $compiler)
    {
        $new           = clone $this;
        $new->compiler = $compiler;

        return $new;
    }

    /**
     * You can modify the container here before it is dumped to PHP code.
     *
     * @param ContainerBuilder $container
     */
    public function load(ContainerBuilder $container): void
    {
        $container->addExcludedClasses(self::EXCLUDED_CLASSES);

        foreach ($this->extensions as $name => $extension) {
            [$class, $args] = \is_string($extension) ? [$extension, []] : $extension;

            if (\class_exists($class)) {
                $args = Helpers::expand($args, $this->parameters, true);
                $this->getCompiler()->addExtension($name, (new ReflectionClass($class))->newInstanceArgs($args));
            }
        }
    }

    /**
     * Returns the file path for a given compiler extension resource.
     *
     * A Resource can be a file or a directory.
     *
     * The resource name must follow the following pattern:
     *
     *     "@CompilerExtension/path/to/a/file.something"
     *
     * where CompilerExtension is the name of the nette-di extension
     * and the remaining part is the relative path in the class.
     *
     * @param CompilerExtension $extension
     * @param string            $name
     * @param bool              $throw
     *
     * @throws InvalidArgumentException if the file cannot be found or the name is not valid
     * @throws RuntimeException         if the name contains invalid/unsafe characters
     * @throws NotSupportedException    if the $name doesn't match in $extension
     *
     * @return string The absolute path of the resource
     */
    public static function getLocation(CompilerExtension $extension, string $name, bool $throw = true)
    {
        if ('@' !== $name[0] && $throw) {
            throw new InvalidArgumentException(\sprintf('A resource name must start with @ ("%s" given).', $name));
        }

        if (false !== \strpos($name, '..')) {
            throw new RuntimeException(\sprintf('File name "%s" contains invalid characters (..).', $name));
        }

        $path = '';

        if (false !== \strpos($bundleName = \substr($name, 1), '/')) {
            [$bundleName, $path] = \explode('/', $bundleName, 2);
        }

        if (false === \strpos(\get_class($extension), $bundleName)) {
            throw new NotSupportedException(\sprintf('Resource path is not supported for %s', $bundleName));
        }

        /** @var RecursiveIteratorIterator|SplFileInfo[] $iterator */
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($bundlePath = self::findComposerDirectory($extension)),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if (\strlen($file->getPathname()) === \strlen($bundlePath . $path) && \file_exists($bundlePath . $path)) {
                return \strtr($file->getPathname(), ['\\' => '/']);
            }
        }

        throw new InvalidArgumentException(\sprintf('Unable to find file "%s".', $name));
    }

    /**
     * @param CompilerExtension $extension
     *
     * @return string
     */
    private static function findComposerDirectory(CompilerExtension $extension): string
    {
        $path      = \dirname((new ReflectionClass(ClassLoader::class))->getFileName());
        $directory = \dirname((new ReflectionObject($extension))->getFileName());

        $packagist = \class_exists(InstalledVersions::class)
            ? InstalledVersions::getRawData()
            : \json_decode(\file_get_contents($path . '/installed.json'), true);

        foreach ($packagist as $package) {
            $packagePath = \str_replace(['\\', '/'], \DIRECTORY_SEPARATOR, \dirname($path, 1) . '/' . $package['name']);

            if (!str_starts_with($directory, $packagePath)) {
                continue;
            }

            $pathPrefix = \current($package['autoload']['psr-4']
                ?? $package['autoload']['psr-0']
                ?? $package['autoload']['classmap']);

            return \sprintf('%s/%s/', $packagePath, \rtrim($pathPrefix, '/'));
        }

        return \dirname($directory, 1) . '/';
    }
}
