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

namespace Biurad\Framework\Listeners;

use Biurad\Framework\Event\ControllerArgumentsEvent;
use Biurad\Framework\Event\ControllerEvent;
use Biurad\Framework\Interfaces\KernelInterface;
use Biurad\Framework\KernelEvents;
use Flight\Routing\Interfaces\RouteInterface;
use Flight\Routing\Interfaces\RouteListenerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class EventRouteListener implements RouteListenerInterface
{
    /** @var EventDispatcherInterface */
    private $dispatcher;

    /** @var KernelInterface */
    private $kernel;

    /**
     * @param EventDispatcherInterface $dispatcher
     */
    public function __construct(EventDispatcherInterface $dispatcher, KernelInterface $kernel)
    {
        $this->kernel     = $kernel;
        $this->dispatcher = $dispatcher;
    }

    /**
     * {@inheritdoc}
     */
    public function onRoute(ServerRequestInterface $request, RouteInterface &$route): void
    {
        $event = new ControllerEvent($this->kernel, $route->getController(), $request);
        $this->dispatcher->dispatch($event, KernelEvents::CONTROLLER);

        $event = new ControllerArgumentsEvent(
            $this->kernel,
            $event->getController(),
            $route->getArguments(),
            $request
        );
        $this->dispatcher->dispatch($event, KernelEvents::CONTROLLER_ARGUMENTS);
    }
}
