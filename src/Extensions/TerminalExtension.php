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
use Biurad\Framework\Commands\AboutCommand;
use Biurad\Framework\Commands\Cache\CleanCommand;
use Biurad\Framework\Commands\Cache\FlushCommand;
use Biurad\Framework\Commands\Server\RunCommand;
use Biurad\Framework\Commands\Server\StartCommand;
use Biurad\Framework\Commands\Server\StopCommand;
use Biurad\Framework\Kernels\ConsoleKernel;
use Doctrine\Common\Cache\Cache as DoctrineCache;
use Nette;
use Nette\DI\Definitions\Definition;
use Nette\DI\Definitions\Statement;
use Nette\DI\ServiceCreationException;
use Nette\Schema\Expect;
use Nette\Schema\ValidationException;
use Nette\Utils\Arrays;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\CommandLoader\ContainerCommandLoader;
use Symfony\Component\Console\EventListener\ErrorListener;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class TerminalExtension extends Extension
{
    public const COMMAND_TAG = 'console.command';

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
     * {@inheritdoc}
     */
    public function getConfigSchema(): Nette\Schema\Schema
    {
        $webRoot = $this->getContainerBuilder()->getParameter('wwwDir');

        return Nette\Schema\Expect::structure([
            'server_root'     => Nette\Schema\Expect::string($webRoot),
            'name'            => Nette\Schema\Expect::string(),
            'version'         => Nette\Schema\Expect::anyOf(Expect::string(), Expect::int(), Expect::float()),
            'catchExceptions' => Nette\Schema\Expect::bool(),
            'autoExit'        => Nette\Schema\Expect::bool(),
            'helperSet'       => Nette\Schema\Expect::anyOf(Expect::string(), Expect::type(Statement::class))
                ->assert(function ($helperSet) {
                    if ($helperSet === null) {
                        throw new ValidationException('helperSet cannot be null');
                    }

                    return true;
                }),
            'helpers'         => Nette\Schema\Expect::arrayOf(
                Expect::anyOf(Expect::string(), Expect::array(), Expect::type(Statement::class))
            ),
            'lazy'            => Nette\Schema\Expect::bool(true),
            'scanDirs'        => Nette\Schema\Expect::list()->default([$this->scanDirs])
                ->before(function ($value) {
                    return \is_string($value) ? [$value] : $value;
                }),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function loadConfiguration(): void
    {
        $container = $this->getContainerBuilder();
        $config    = $this->getConfig();
        $index     = 1;

        // Skip if isn't CLI
        if (!$container->getParameter('consoleMode')) {
            return;
        }

        $commands = [
            new Statement(RunCommand::class, [$config->server_root, $container->getParameter('envMode')]),
            new Statement(StartCommand::class, [$config->server_root, $container->getParameter('envMode')]),
            new Statement(CleanCommand::class, [$container->getParameter('tempDir') . '/cache', $container->getParameter('logDir')]),
            new Statement(StopCommand::class),
            new Statement(AboutCommand::class),
        ];

        if ($container->getByType(DoctrineCache::class)) {
            $commands[] = new Statement(FlushCommand::class);
        }

        $applicationDef = $container->register($this->prefix('app'), ConsoleKernel::class)
            ->addSetup('addCommands', [$commands]);

        if (null !== $config->name) {
            $applicationDef->addSetup('setName', [$config->name]);
        }

        // Setup console version
        if (null !== $config->version) {
            $applicationDef->addSetup('setVersion', [(string) $config->version]);
        }

        // Catch or populate exceptions
        if (null !== $config->catchExceptions) {
            $applicationDef->addSetup('setCatchExceptions', [$config->catchExceptions]);
        }

        // Call die() or not
        if (null !== $config->autoExit) {
            $applicationDef->addSetup('setAutoExit', [$config->autoExit]);
        }

        // Register given or default HelperSet
        if (null !== $config->helperSet) {
            $applicationDef->addSetup('setHelperSet', [
                $this->getHelper()->getDefinitionFromConfig($config->helperSet, $this->prefix('helperSet')),
            ]);
        }

        // Register extra helpers
        foreach ($config->helpers as $helperName => $helperConfig) {
            $helperPrefix = $this->prefix('helper.' . $helperName);
            $helperDef    = $this->getHelper()->getDefinitionFromConfig($helperConfig, $helperPrefix);

            if ($helperDef instanceof Definition) {
                $helperDef->setAutowired(false);
            }

            $applicationDef->addSetup('?->getHelperSet()->set(?)', ['@self', $helperDef]);
        }

        foreach ($this->findClasses($config->scanDirs, Command::class) as $command) {
            $container->register($this->prefix('command.' . $index++), $command)
                ->addTag('console.command', $command::getDefaultName());
        }

        if ($container->getByType(EventDispatcherInterface::class)) {
            $container->register($this->prefix('error_listener'), ErrorListener::class);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function beforeCompile(): void
    {
        $container = $this->getContainerBuilder();

        // Skip if isn't CLI
        if (!$container->getParameter('consoleMode')) {
            return;
        }

        // Register all commands (if they are not lazy-loaded)
        // otherwise build a command map for command loader
        $commandServices = $container->findByType(Command::class);
        $applicationDef  = $container->getDefinition($this->prefix('app'));
        $lazyCommandMap  = [];

        if (!$this->config->lazy) {
            // Add all commands to console
            $applicationDef->addSetup('addCommands', [\array_values($commandServices)]);

            return;
        }

        // Iterate over all commands and build commandMap
        foreach ($commandServices as $serviceName => $service) {
            $tags  = $service->getTags();
            $entry = ['name' => null, 'alias' => null];

            if (isset($tags[self::COMMAND_TAG])) {
                // Parse tag's name attribute
                if (\is_string($tags[self::COMMAND_TAG])) {
                    $entry['name'] = $tags[self::COMMAND_TAG];
                } elseif (\is_array($tags[self::COMMAND_TAG])) {
                    $entry['name'] = Arrays::get($tags[self::COMMAND_TAG], 'name');
                }
            } else {
                // Parse it from static property
                $entry['name'] = \call_user_func([$service->getType(), 'getDefaultName']);
            }

            // Validate command name
            if (!isset($entry['name'])) {
                throw new ServiceCreationException(
                    \sprintf(
                        'Command "%s" missing tag "%s[name]" or variable "$defaultName".',
                        $service->getType(),
                        self::COMMAND_TAG
                    )
                );
            }

            // Append service to command map
            $lazyCommandMap[$entry['name']] = $serviceName;
        }

        $container->register($this->prefix('command_loader'), ContainerCommandLoader::class)
            ->setArgument('commandMap', $lazyCommandMap);
        $applicationDef->addSetup('setCommandLoader');
    }
}
