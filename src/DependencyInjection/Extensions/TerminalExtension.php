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

use Biurad\Framework\Commands\AboutCommand;
use Biurad\Framework\Commands\CacheCleanCommand;
<<<<<<< HEAD
use Biurad\Framework\ConsoleApp;
use Biurad\Framework\DependencyInjection\Extension;
use Doctrine\Common\Cache\Cache as DoctrineCache;
=======
use Biurad\Framework\Commands\ServerRunCommand;
use Biurad\Framework\Commands\ServerStartCommand;
use Biurad\Framework\Commands\ServerStopCommand;
use Biurad\Framework\ConsoleApp;
use Biurad\Framework\DependencyInjection\Extension;
>>>>>>> master
use Nette;
use Nette\DI\Definitions\Reference;
use Nette\DI\Definitions\Statement;
use Nette\Schema\Expect;
use Symfony\Component\Console\CommandLoader\ContainerCommandLoader;
use Symfony\Component\Console\EventListener\ErrorListener;

class TerminalExtension extends Extension
{
    /**
     * {@inheritDoc}
     */
    public function getConfigSchema(): Nette\Schema\Schema
    {
<<<<<<< HEAD
        return Nette\Schema\Expect::structure([
            'server_root'   => Nette\Schema\Expect::string()->default('public'),
=======
        $webRoot = $this->getContainerBuilder()->getParameter('wwwDir');

        return Nette\Schema\Expect::structure([
            'server_root'   => Nette\Schema\Expect::string()->default($webRoot),
>>>>>>> master
            'commands'      => Nette\Schema\Expect::arrayOf(
                Expect::structure([
                    'class' => Nette\Schema\Expect::string()->assert('class_exists'),
                    'tags'  => Nette\Schema\Expect::array()->before(function ($value) {
                        return \is_string($value) ? [$value => 'none'] : $value;
                    }),
                ])->castTo('array')
            ),
        ])->before(function ($value) {
            if (isset($value['commands'])) {
                $new = [];

                foreach ($value['commands'] as $index => $attrs) {
                    $new['command.' . $index] = $attrs;
                }
                unset($value['commands']);
                $value['commands'] = $new;
            }

            return $value;
        });
    }

    /**
     * {@inheritDoc}
     */
    public function loadConfiguration(): void
    {
        $container = $this->getContainerBuilder();

        if (!$container->getParameter('consoleMode')) {
            return;
        }

        $this->loadDefinitionsFromConfig($this->config->commands ?? []);
        $commands = $this->addCommands([
            new Statement(
                CacheCleanCommand::class,
                [
<<<<<<< HEAD
                    new Reference(DoctrineCache::class),
                    $container->getParameter('tempDir') . '/cache',
                    $container->getParameter('tempDir') . '/logs',
                ]
            ),
=======
                    1 => $container->getParameter('tempDir') . '/cache',
                    2 => $container->getParameter('tempDir') . '/logs',
                ]
            ),
            new Statement(ServerRunCommand::class, [$this->config->server_root, $container->getParameter('envMode')]),
            new Statement(ServerStartCommand::class, [$this->config->server_root, $container->getParameter('envMode')]),
            ServerStopCommand::class,
>>>>>>> master
            AboutCommand::class,
        ]);

        $container->register($this->prefix('error_listener'), ErrorListener::class);
        $container->register($this->prefix('app'), ConsoleApp::class)
            ->addSetup('setCommandLoader')
            ->addSetup('addCommands', [$commands]);
    }

    /**
     * {@inheritdoc}
     */
    public function beforeCompile(): void
    {
        $container = $this->getContainerBuilder();

        $commandServices = $container->findByTag($this->prefix('command'));
        $lazyCommandMap  = [];
        $serviceIds      = [];

        foreach ($commandServices as $id => $commandName) {
            $definition = $container->getDefinition($id);

            if (\in_array($commandName, [null, true, 'none'], true)) {
                $serviceIds[] = new Reference($id);

                continue;
            }

            $lazyCommandMap[$commandName] = $id;
            $definition->addSetup('setName', [$commandName]);
        }

        if (!empty($serviceIds)) {
            $container->getDefinitionByType(ConsoleApp::class)
                ->addSetup('addCommands', [$serviceIds]);
        }

        $container->register($this->prefix('command_loader'), ContainerCommandLoader::class)
            ->setArgument('commandMap', $lazyCommandMap);
    }

    protected function addCommands(array $commands): array
    {
        $results = [];

        foreach ($commands as $command) {
            $results[] = $command instanceof Statement ? $command : new Statement($command);
        }

        return $results;
    }
}
