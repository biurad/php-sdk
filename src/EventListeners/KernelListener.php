<?php

declare(strict_types=1);

/*
 * This file is part of BiuradPHP opensource projects.
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

namespace BiuradPHP\MVC\EventListeners;

use BiuradPHP\DependencyInjection\Interfaces\FactoryInterface;
use BiuradPHP\Events\Interfaces\EventSubscriberInterface;
use BiuradPHP\MVC\Events\FinishRequestEvent;
use BiuradPHP\MVC\KernelEvents;
use BiuradPHP\Routing\Events\ControllerArgumentsEvent;
use BiuradPHP\Routing\Events\ControllerEvent;

/**
 * Let's broaacast framework as event and listen to it.
 */
class KernelListener implements EventSubscriberInterface
{
    private $container;

    public function __construct(FactoryInterface $container)
    {
        $this->container = $container;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::CONTROLLER            => ['onCurrentController', -100],
            KernelEvents::CONTROLLER_ARGUMENTS  => 'onControllerArguments',
            KernelEvents::FINISH_REQUEST        => ['onKernelFinishRequest', 0],
        ];
    }

    /**
     * 'listens' to the 'ControllerEvent' event, which is
     * triggered whenever a controller is executed in the application.
     *
     * @param ControllerEvent $event
     */
    public function onCurrentController(ControllerEvent $event): void
    {
    }

    /**
     * 'listens' to the 'ControllerArgumentsEvent' event on controller,
     *
     * @param ControllerArgumentsEvent $event
     */
    public function onControllerArguments(ControllerArgumentsEvent $event): void
    {
    }

    /**
     * After a sub-request is done, we need to reset the routing context to the parent request so that the URL generator
     * operates on the correct context again.
     *
     * @param FinishRequestEvent $event
     */
    public function onKernelFinishRequest(FinishRequestEvent $event): void
    {
    }
}
