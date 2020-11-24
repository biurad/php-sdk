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

use Biurad\Framework\Commands\AboutCommand;
use Biurad\Framework\Commands\CacheCleanCommand;
use Biurad\Framework\Commands\RouteListCommand;
use Biurad\Framework\Commands\ServerRunCommand;
use Biurad\Framework\Commands\ServerStartCommand;
use Biurad\Framework\Commands\ServerStopCommand;
use Biurad\DependencyInjection\Extension;
use Biurad\Framework\Kernels\ConsoleKernel;
use Flight\Routing\Interfaces\RouteCollectorInterface;
use Flight\Routing\RouteLoader;
use Nette;
use Nette\DI\Definitions\Reference;
use Nette\DI\Definitions\Statement;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\CommandLoader\ContainerCommandLoader;
use Symfony\Component\Console\EventListener\ErrorListener;

class TerminalExtension extends Extension
{
    /** @var string|string[] */
    private $scanDirs;

    /**
     * @param string|string[] $scanDirs
     */
    public function __construct($scanDirs = [])
    {
        $this->scanDirs = $scanDirs;
    }

    /**
     * {@inheritDoc}
     */
    public function getConfigSchema(): Nette\Schema\Schema
    {
        $webRoot = $this->getContainerBuilder()->getParameter('wwwDir');

        return Nette\Schema\Expect::structure([
            'server_root' => Nette\Schema\Expect::string()->default($webRoot),
            'scanDirs'    => Nette\Schema\Expect::list()->default([$this->scanDirs])->before(function ($value) {
                return \is_string($value) ? [$value] : $value;
            }),
        ])->castTo('array');
    }

    /**
     * {@inheritDoc}
     */
    public function loadConfiguration(): void
    {
        $container = $this->getContainerBuilder();
        $index     = 1;

        if (!$container->getParameter('consoleMode')) {
            return;
        }

        $commands = $this->addCommands([
            new Statement(
                CacheCleanCommand::class,
                [
                    1 => $container->getParameter('tempDir') . '/cache',
                    2 => $container->getParameter('tempDir') . '/logs',
                ]
            ),
            new Statement(
                RouteListCommand::class,
                [
                    $container->getByType(RouteLoader::class)
                        ? new Reference(RouteLoader::class)
                        : new Reference(RouteCollectorInterface::class),
                ]
            ),
            new Statement(ServerRunCommand::class, [$this->getFromConfig('server_root'), $container->getParameter('envMode')]),
            new Statement(ServerStartCommand::class, [$this->getFromConfig('server_root'), $container->getParameter('envMode')]),
            ServerStopCommand::class,
            AboutCommand::class,
        ]);

        $container->register($this->prefix('error_listener'), ErrorListener::class);
        $container->register($this->prefix('app'), ConsoleKernel::class)
            ->addSetup('setCommandLoader')
            ->addSetup('addCommands', [$commands]);

        $commands = $this->findClasses($this->getFromConfig('scanDirs'), Command::class);

        foreach ($commands as $command) {
            $container->register($this->prefix('command.' . $index++), $command)
                ->addTag('console.command', $command::getDefaultName());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function beforeCompile(): void
    {
        $container = $this->getContainerBuilder();

        if (!$container->getParameter('consoleMode')) {
            return;
        }

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
            $container->getDefinitionByType(ConsoleKernel::class)
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
