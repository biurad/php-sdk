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

namespace Biurad\Framework\Debug\Route;

use DivineNii\Invoker\CallableResolver;
use Flight\Routing\Exceptions\MethodNotAllowedException;
use Flight\Routing\Exceptions\RouteNotFoundException;
use Flight\Routing\Route;
use Flight\Routing\Router;
use Nette;
use Nette\Utils\Callback;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use Tracy;
use Tracy\Dumper;

/**
 * Routing debugger for Debug Bar.
 */
final class RoutesPanel implements Tracy\IBarPanel
{
    use Nette\SmartObject;

    /** @var Router */
    private $router;

    /** @var ServerRequestInterface */
    private $httpRequest;

    /** @var array */
    private $routers = [];

    /** @var null|array */
    private $matched;

    /** @var ReflectionClass|ReflectionFunction|ReflectionMethod|string */
    private $source;

    public function __construct(Router $router, ServerRequestInterface $request)
    {
        $this->router      = $router;
        $this->httpRequest = $request;
    }

    /**
     * Renders tab.
     */
    public function getTab(): string
    {
        $this->analyse($this->router);

        return Nette\Utils\Helpers::capture(function (): void {
            require __DIR__ . '/templates/RoutingPanel.tab.phtml';
        });
    }

    /**
     * Renders panel.
     */
    public function getPanel(): string
    {
        $uri = $this->httpRequest->getUri();

        return Nette\Utils\Helpers::capture(function () use ($uri): void {
            $matched = $this->matched;
            $routers = $this->routers;
            $source = $this->source;
            $url = $uri->getScheme() . '://' . $uri->getAuthority() . $uri->getPath() . $uri->getQuery();
            $method = $this->httpRequest->getMethod();

            require __DIR__ . '/templates/RoutingPanel.panel.phtml';
        });
    }

    /**
     * Analyses simple route.
     */
    private function analyse(Router $router): void
    {
        $matched = 'no';
        $route   = null;
        $request = $this->httpRequest;

        foreach ($router->getRoutes() as $route) {

        }

        try {
            $router->match($request);
        } catch (MethodNotAllowedException $e) {
            $this->routers = $e->getMessage();

            return;
        } catch (RouteNotFoundException $e) {
            return;
        }

        /** @var Route $route */
        $route = $request->getAttribute(Route::class);
        $controller = $route->getController();

        if ($controller instanceof RequestHandlerInterface) {
            $controller = \get_class($controller) . '@' . 'handle';
        } elseif (is_string($controller) && \function_exists($controller)) {
            $controller = $controller;
        } elseif (\is_callable($controller) && !$controller instanceof \Closure) {
            $controller = (\is_object($controller[0]) ? \get_class($controller[0]) : $controller[0]) . '@' . $controller[1];
        }

        $params              = $route->getArguments();
        $params['presenter'] = $controller;
        $matched             = 'may';

        if (null === $this->matched) {
            $this->matched = $params;
            $this->findSource();
            $matched = 'yes';
        }

        $this->routers[] = [
            'matched' => $matched,
            'route'   => $route,
            'name'    => $route->getName(),
            'params'  => $params,
        ];
    }

    private function findSource(): void
    {
        $params    = $this->matched;
        $presenter = $params['presenter'] ?? '';
        [$class, $method] = [$presenter, null];

        if (\is_string($presenter) && 1 === \preg_match(CallableResolver::CALLABLE_PATTERN, $presenter, $matches)) {
            var_dump($matches);
            [, $class, $method] = $matches;
        }

        $rc           = \is_callable($presenter) ? Callback::toReflection($presenter) : new ReflectionClass($class);
        $this->source = isset($method) && $rc->hasMethod($method) ? $rc->getMethod($method) : $rc;
    }
}
