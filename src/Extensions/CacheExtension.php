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

use Biurad\Cache\AdapterFactory;
use Biurad\Cache\CacheItemPool;
use Biurad\Cache\SimpleCache;
use Biurad\Cache\TagAwareCache;
use Biurad\DependencyInjection\Extension;
use Nette;
use Nette\DI\Definitions\Statement;
use Cache\Adapter\Doctrine\DoctrineCachePool;
use Doctrine\Common\Cache\Cache as DoctrineCache;

class CacheExtension extends Extension
{
    /**
     * {@inheritDoc}
     */
    public function getConfigSchema(): Nette\Schema\Schema
    {
        return Nette\Schema\Expect::structure([
            'driver' => Nette\Schema\Expect::string(),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function loadConfiguration(): void
    {
        $container = $this->getContainerBuilder();
        $frameworkDriver = $this->getExtensionConfig(FrameworkExtension::class, 'cache_driver');

        if (\class_exists(SimpleCache::class)) {
            $container->register(
                $this->prefix('doctrine'),
                new Statement(
                    [AdapterFactory::class, 'createHandler'],
                    [$this->config->driver ?? $frameworkDriver]
                )
            )->setType(DoctrineCache::class);

            if (\class_exists(DoctrineCachePool::class)) {
                $container->register($this->prefix('psr6'), TagAwareCache::class);
            } else {
                $container->register($this->prefix('psr6'), CacheItemPool::class);
            }

            $container->register($this->prefix('psr16'), SimpleCache::class);
        }

        $container->addAlias('cache', $this->prefix('psr16'));
    }
}
