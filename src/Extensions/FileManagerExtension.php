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

use Biurad\DependencyInjection\Extension;
use Biurad\FileManager\ConnectionFactory;
use Biurad\FileManager\Plugin;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Cached\CachedAdapter;
use League\Flysystem\Cached\Storage\Psr6Cache;
use League\Flysystem\Filesystem;
use League\Flysystem\MountManager;
use Nette;
use Nette\DI\Definitions\Definition;
use Nette\DI\Definitions\Statement;
use Psr\Cache\CacheItemPoolInterface;

class FileManagerExtension extends Extension
{
    /**
     * {@inheritDoc}
     */
    public function getConfigSchema(): Nette\Schema\Schema
    {
        return Nette\Schema\Expect::structure([
            'default'           => Nette\Schema\Expect::string()->default('array'),
            'caching'           => Nette\Schema\Expect::structure([
                'enable' => Nette\Schema\Expect::bool(false),
                'key'    => Nette\Schema\Expect::string()->default('flysystem'),
                'ttl'    => Nette\Schema\Expect::int()->nullable(),
            ])->castTo('array'),
            'connections'       => Nette\Schema\Expect::arrayOf(
                Nette\Schema\Expect::structure([
                    'visibility' => Nette\Schema\Expect::anyOf('public', 'private')->default('public'),
                    'pirate'     => Nette\Schema\Expect::scalar()->default(false),
                ])->otherItems()->castTo('array')
            ),
        ])->castTo('array');
    }

    /**
     * {@inheritDoc}
     */
    public function loadConfiguration(): void
    {
        $container   = $this->getContainerBuilder();

        if (!\class_exists(ConnectionFactory::class)) {
            return;
        }

        if (!empty($this->config['connections'])) {
            $adapters = ['awss3', 'azure', 'dropbox', 'ftp', 'gcs', 'gridfs', 'local', 'rackspace', 'sftp', 'webdav', 'zip'];

            foreach ($adapters as $adapter) {
                $container->register($this->prefix($adapter), Filesystem::class)
                    ->setArguments([$this->getFlyAdapter($adapter, $adapter), $this->getFlyConfig($adapter)]);
            }
        }

        $container->register($this->prefix('app'), Filesystem::class);

        $container->addAlias('flysystem', $this->prefix('app'));
    }

    /**
     * {@inheritdoc}
     */
    public function beforeCompile(): void
    {
        $container  = $this->getContainerBuilder();
        $default    = $this->getFromConfig('default');
        $adapters   = $filesystems = [];

        if (!\class_exists(ConnectionFactory::class)) {
            return;
        }

        foreach ($container->findByType(AdapterInterface::class) as $adapter) {
            $tags = $adapter->getTags();

            if (isset($tags[ConnectionFactory::FLY_ADAPTER_TAG])) {
                $name            = $tags[ConnectionFactory::FLY_ADAPTER_TAG];
                $adapters[$name] = $connection = $this->getFlyAdapter($name, $adapter);

                $container->register($this->prefix($name), Filesystem::class)
                    ->setArguments([$connection, $this->getFlyConfig($name)]);
            }
        }

        $container->getDefinition($this->prefix('app'))
            ->setArgument(0, $adapters[$default] ?? $this->getFlyAdapter($default, $default));

        foreach ($container->findByType(Filesystem::class) as $id => $filesystem) {
            foreach ([
                Plugin\AppendContent::class,
                Plugin\CheckDirectory::class,
                Plugin\CheckFile::class,
                Plugin\CreateStream::class,
                Plugin\CreateSymlink::class,
                Plugin\FilePath::class,
                Plugin\FilterByType::class,
                Plugin\FlushCache::class,
                Plugin\PrependContent::class,
            ] as $plugin) {
                $filesystem->addSetup('addPlugin', [new Statement($plugin)]);
            }

            if ($id !== $this->prefix('app')) {
                $filesystems[substr($id, strlen($this->prefix('')))] = $filesystem;
            }
        }

        $container->register($this->prefix('map'), MountManager::class)
            ->setArguments([$filesystems]);
    }

    /**
     * @param string           $name
     * @param Definition|string $adapter
     *
     * @return Statement
     */
    private function getFlyAdapter(string $name, $adapter): Statement
    {
        $container = $this->getContainerBuilder();
        $cache     = $this->getFromConfig('caching');
        $adapter   =  new Statement([ConnectionFactory::class, 'makeAdapter'], [$this->setFlyConfig($name, $adapter)]);

        if ($cache['enable'] && \class_exists(Psr6Cache::class) && $container->getByType(CacheItemPoolInterface::class)) {
            $adapter = new Statement(
                CachedAdapter::class,
                [$adapter, new Statement(Psr6Cache::class, [1 => $cache['key'], 2 => $cache['ttl']])]
            );
        }

        return $adapter;
    }

    /**
     * @param string           $name
     * @param Statement|string $adapter
     *
     * @return array
     */
    private function setFlyConfig(string $name, $adapter): array
    {
        $adapterConfig = \array_filter(
            $this->config['connections'][$name] ?? [],
            function (string $key) {
                return !\in_array($key, ['visibility', 'pirate'], true);
            },
            \ARRAY_FILTER_USE_KEY
        );

        return [
            'default'     => $adapter,
            'connection'  => $adapterConfig,
        ];
    }

    /**
     * @param string $name
     *
     * @return mixed[]
     */
    private function getFlyConfig(string $name): array
    {
        $options = [];
        $config  = $this->config['connections'][$name] ?? [];

        if (isset($config['visibility'])) {
            $options['visibility'] = $config['visibility'];
        }

        if (isset($config['pirate'])) {
            $options['disable_asserts'] = $config['pirate'];
        }

        return $options;
    }
}
