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

use Biurad\Events\LazyEventDispatcher;
use Biurad\Events\TraceableEventDispatcher;
use Biurad\Framework\Debug\Event\EventsPanel;
use Biurad\Framework\DependencyInjection\Extension;
use Nette;
use Nette\DI\Definitions\Reference;
use Nette\DI\Definitions\Statement;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class EventDispatcherExtension extends Extension
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
        return Nette\Schema\Expect::structure([
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

        $events = new Statement(LazyEventDispatcher::class);

        $events = $container->register(
            $this->prefix('dispatcher'),
            $container->getParameter('debugMode') ? new Statement(TraceableEventDispatcher::class, [$events]) : $events
        );

        if ($container->getParameter('debugMode')) {
            $events->addSetup([new Reference('Tracy\Bar'), 'addPanel'], [new Statement(EventsPanel::class, [$events])]);
        }

        $events = $this->findClasses($this->getFromConfig('scanDirs'), EventSubscriberInterface::class);

        foreach ($events as $eventClass) {
            $container->register($this->prefix('subscriber.' . $index++), $eventClass);
        }

        $container->addAlias('events', $this->prefix('dispatcher'));
    }

    /**
     * {@inheritdoc}
     */
    public function beforeCompile(): void
    {
        $container  = $this->getContainerBuilder();
        $type       = $container->findByType(EventSubscriberInterface::class);
        $dispatcher = $container->getDefinitionByType(EventDispatcherInterface::class);

        // Register as services
        foreach ($this->getServiceDefinitionsFromDefinitions($type) as $definition) {
            $dispatcher->addSetup('addSubscriber', [$definition]);
        }
    }
}
