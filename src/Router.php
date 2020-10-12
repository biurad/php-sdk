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

namespace Biurad\Framework;

use Biurad\Framework\Event\ControllerArgumentsEvent;
use Biurad\Framework\Event\ControllerEvent;
use Biurad\Framework\Interfaces\HttpKernelInterface;
use Biurad\Http\Interfaces\Psr17Interface;
use DivineNii\Invoker\Interfaces\InvokerInterface;
use DivineNii\Invoker\Invoker;
use Flight\Routing\Interfaces\RouteInterface;
use Flight\Routing\Interfaces\RouteMatcherInterface;
use Flight\Routing\Router as FlightRouter;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class Router extends FlightRouter
{
    /** @var InvokerInterface */
    private $resolver;

    /** @var HttpKernelInterface */
    private $kernel;

    public function __construct(
        Psr17Interface $factory,
        HttpKernelInterface $kernel,
        ?RouteMatcherInterface $matcher = null,
        ?InvokerInterface $resolver = null
    ) {
        $this->kernel   = $kernel;
        $this->resolver = $resolver ?? new Invoker();

        parent::__construct($factory, $factory, $matcher, $this->resolver, $kernel->getContainer());
    }

    /**
     * {@inheritdoc}
     */
    protected function resolveController(ServerRequestInterface $request, RouteInterface &$route)
    {
        // Disable or enable HTTP request method prefix for action.
        if (
            \is_array($controller = $route->getController()) &&
            false !== \strpos($route->getName(), '__restful')
        ) {
            $controller[1] = \strtolower($request->getMethod()) . \ucfirst($controller[1]);
        }

        $handler   = $this->resolveNamespace($controller);
        $arguments = $route->getArguments();

        if ('phpinfo' === $handler) {
            $arguments['what'] = -1;
        }

        // For a class that implements RequestHandlerInterface, we will call handle()
        // if no method has been specified explicitly
        if (\is_string($handler) && \is_a($handler, RequestHandlerInterface::class, true)) {
            $handler = [$handler, 'handle'];
        }

        // Get new $handler and $parameters from events on reference.
        $this->handleEvent($handler, $arguments, $request);
        $route->setArguments($arguments);

        // If controller is instance of RequestHandlerInterface
        if ($handler instanceof RequestHandlerInterface) {
            return $handler->handle($request);
        }

        return $handler;
    }

    /**
     * @param mixed                  &$controller
     * @param mixed                  &$arguments
     * @param ServerRequestInterface $request
     */
    private function handleEvent(&$controller, &$arguments, ServerRequestInterface $request): void
    {
        $dispatcher = $this->kernel->getEventDisptacher();

        $event = new ControllerEvent($this->kernel, $controller, $request);
        $dispatcher->dispatch($event, KernelEvents::CONTROLLER);

        $event = new ControllerArgumentsEvent($this->kernel, $event->getController(), $arguments, $request);
        $dispatcher->dispatch($event, KernelEvents::CONTROLLER_ARGUMENTS);

        // Set the new arguments and controller.
        $controller = $event->getController();
        $arguments  = $event->getArguments();
    }
}
