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

use Biurad\Annotations\AnnotationLoader;
use Biurad\Annotations\ListenerInterface;
use Biurad\Annotations\LoaderInterface;
use Biurad\DependencyInjection\Extension;
use Biurad\Framework\Kernel;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Annotations\CachedReader;
use Doctrine\Common\Cache\Cache as DoctrineCache;
use Nette;
use Nette\DI\Definitions\Definition;
use Nette\DI\Definitions\Statement;
use Nette\Loaders\RobotLoader;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpLiteral;
use Spiral\Attributes\AnnotationReader as DoctrineReader;
use Spiral\Attributes\AttributeReader;
use Spiral\Attributes\Composite\MergeReader;

class AnnotationsExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function getConfigSchema(): Nette\Schema\Schema
    {
        $debugMode = $this->getContainerBuilder()->getParameter('debugMode');

        return Nette\Schema\Expect::structure([
            'resources' => Nette\Schema\Expect::list()->before(function ($value) {
                return \is_string($value) ? [$value] : $value;
            }),
            'debug'     => Nette\Schema\Expect::bool($debugMode),
            'ignore'    => Nette\Schema\Expect::listOf('string')->default([
                'persistent',
                'serializationVersion',
            ]),
            'cache'     => Nette\Schema\Expect::anyOf(
                Nette\Schema\Expect::string(),
                Nette\Schema\Expect::array(),
                Nette\Schema\Expect::type(Statement::class)
            ),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function loadConfiguration(): void
    {
        $container = $this->getContainerBuilder();
        $doctrine  = \class_exists(AnnotationReader::class) ? DoctrineReader::class : null;

        if (\class_exists(AnnotationLoader::class)) {
            $reader = new Statement(AttributeReader::class);

            if (null !== $doctrine) {
                $reader =  new Statement(MergeReader::class, [[$reader, new Statement(DoctrineReader::class)]]);
            }

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

            $container->register($this->prefix('loader'), AnnotationLoader::class)
                ->setArgument('reader', $reader)
                ->addSetup('?->attach(...?)', ['@self', $classes]);
        }

        if (null === $doctrine) {
            return;
        }

        $readerDefinition = $container->register($this->prefix('delegated'), AnnotationReader::class);

        foreach ($this->config->ignore as $annotationName) {
            $readerDefinition->addSetup('addGlobalIgnoredName', [$annotationName]);
            AnnotationReader::addGlobalIgnoredName($annotationName);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function beforeCompile(): void
    {
        $container        = $this->getContainerBuilder();
        $config           = $this->config;

        if ($container->getByType(DoctrineCache::class)) {
            $readerDefinition = $container->getDefinition($this->prefix('delegated'));
            $readerDefinition->setAutowired(false);

            $cacheName       = $this->prefix('cache');
            $cacheDefinition = $this->getHelper()
                ->getDefinitionFromConfig($config->cache ?? '@Doctrine\Common\Cache\Cache', $cacheName);

            // If service is extension specific, then disable autowiring
            if ($cacheDefinition instanceof Definition && $cacheName === $cacheDefinition->getName()) {
                $cacheDefinition->setAutowired(false);
            }

            $container->register($this->prefix('reader'), CachedReader::class)
                ->setArguments([$readerDefinition, $cacheDefinition, $config->debug]);
        }

        // Load annotation listeners ...
        if ($container->getByType(LoaderInterface::class)) {
            $listeners = $container->findByType(ListenerInterface::class);
            $container->getDefinitionByType(LoaderInterface::class)
            ->addSetup(
                '?->attachListener(...?)',
                ['@self', $this->getHelper()->getServiceDefinitionsFromDefinitions($listeners)]
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function afterCompile(ClassType $classType): void
    {
        $initialize = $this->initialization;

        if (!\class_exists(AnnotationReader::class)) {
            return;
        }

        // doctrine/annotations ^1.0 compatibility.
        if (\method_exists(AnnotationRegistry::class, 'registerLoader')) {
            $initialize->setBody('?::registerUniqueLoader("\\class_exists");', [new PhpLiteral(AnnotationRegistry::class)]);
        }
    }

    protected function createRobotLoader(): RobotLoader
    {
        $robot = new RobotLoader();
        $robot->addDirectory(...$this->config->resources);
        $robot->acceptFiles = ['*.php'];
		$robot->rebuild();

        return $robot;
    }
}
