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

namespace Biurad\Framework\DependencyInjection\Extensions;

use Biurad\Framework\DependencyInjection\Extension;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Annotations\CachedReader;
use Doctrine\Common\Cache\Cache as DoctrineCache;
use Nette;
use Nette\DI\Definitions\Definition;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpLiteral;

class AnnotationsExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function getConfigSchema(): Nette\Schema\Schema
    {
        return Nette\Schema\Expect::structure([
            'debug'  => Nette\Schema\Expect::bool(false),
            'ignore' => Nette\Schema\Expect::listOf('string')->default([
                'persistent',
                'serializationVersion',
            ]),
            'cache'  => Nette\Schema\Expect::anyOf(
                Nette\Schema\Expect::string(),
                Nette\Schema\Expect::array(),
                Nette\Schema\Expect::type(Statement::class)
            )->default('@Doctrine\Common\Cache\Cache'),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function loadConfiguration(): void
    {
        $container        = $this->getContainerBuilder();
        $readerDefinition = $container->register($this->prefix('delegated'), AnnotationReader::class);

        foreach ($this->config->ignore as $annotationName) {
            $readerDefinition->addSetup('addGlobalIgnoredName', [$annotationName]);
            AnnotationReader::addGlobalIgnoredName($annotationName);
        }

        AnnotationRegistry::registerUniqueLoader('class_exists');
    }

    /**
     * {@inheritdoc}
     */
    public function beforeCompile(): void
    {
        $container        = $this->getContainerBuilder();
        $config           = $this->config;
        $readerDefinition = $container->getDefinition($this->prefix('delegated'));

        if ($container->getByType(DoctrineCache::class)) {
            $readerDefinition->setAutowired(false);
            $cacheName       = $this->prefix('cache');
            $cacheDefinition = $this->getDefinitionFromConfig($config->cache, $cacheName);

            // If service is extension specific, then disable autowiring
            if ($cacheDefinition instanceof Definition && $cacheName === $cacheDefinition->getName()) {
                $cacheDefinition->setAutowired(false);
            }

            $container->register($this->prefix('reader'), CachedReader::class)
                ->setArguments([$readerDefinition, $cacheDefinition, $config->debug]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function afterCompile(ClassType $classType): void
    {
        $initialize = $this->initialization;
        $initialize->setBody('?::registerUniqueLoader("class_exists");', [new PhpLiteral(AnnotationRegistry::class)]);
    }
}
