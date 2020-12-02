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

use Biurad\Annotations\AnnotationLoader;
use Biurad\DependencyInjection\Extension;
use Biurad\Framework\Commands\Debug\RouteCommand;
use Biurad\Framework\Debug\Route\RoutesPanel;
use Biurad\Framework\DependencyInjection\XmlAdapter;
use Biurad\Framework\ExtensionLoader;
use Biurad\Framework\Listeners\EventRouteListener;
use Biurad\Http\Middlewares\AccessControlMiddleware;
use Biurad\Http\Middlewares\CacheControlMiddleware;
use Biurad\Http\Middlewares\ContentSecurityPolicyMiddleware;
use Biurad\Http\Middlewares\CookiesMiddleware;
use Biurad\Http\Middlewares\ErrorHandlerMiddleware;
use Biurad\Http\Middlewares\HttpMiddleware;
use Biurad\Http\Middlewares\SessionMiddleware;
use Flight\Routing\Annotation\Listener;
use Flight\Routing\Interfaces\RouteListenerInterface;
use Flight\Routing\Middlewares\PathMiddleware;
use Flight\Routing\RouteCollector;
use Flight\Routing\RoutePipeline;
use Flight\Routing\Router as FlightRouter;
use Nette;
use Nette\DI\Config\Adapters\NeonAdapter;
use Nette\DI\Definitions\Reference;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\DI\Definitions\Statement;
use Nette\PhpGenerator\PhpLiteral;
use Nette\Schema\Expect;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class RouterExtension extends Extension
{
    /**
     * {@inheritDoc}
     */
    public function getConfigSchema(): Nette\Schema\Schema
    {
        return Nette\Schema\Expect::structure([
            'namespace'             => Nette\Schema\Expect::string()->default(null),
            'defaults'              => Nette\Schema\Expect::array()->before(function ($value) {
                return \is_string($value) ? [$value] : $value;
            }),
            'requirements'          => Nette\Schema\Expect::array()->before(function ($value) {
                return \is_string($value) ? [$value] : $value;
            }),
            'middlewares'           => Expect::array()->before(function ($value) {
                return \is_string($value) ? [$value] : $value;
            }),
            'shortcut'              => Expect::arrayOf(
                Expect::structure([
                    'path'          => Nette\Schema\Expect::string(),
                    'name'          => Nette\Schema\Expect::string(),
                    'methods'       => Expect::array()->before(function ($methods) {
                        return \is_string($methods) ? [$methods] : $methods;
                    }),
                    'mode'          => Nette\Schema\Expect::anyOf('DEPLOY-MODE', 'DEBUG-MODE', null),
                    'controller'    => Expect::anyOf(Expect::string(), Expect::array(), Expect::object()),
                    'domain'        => Nette\Schema\Expect::string(),
                    'requirements'  => Nette\Schema\Expect::anyOf(Expect::string(), Expect::array()),
                    'defaults'      => Nette\Schema\Expect::anyOf(Expect::string(), Expect::array()),
                    'arguments'     => Nette\Schema\Expect::anyOf(Expect::string(), Expect::array()),
                    'middlewares'   => Expect::anyOf(Expect::array(), Expect::string()),
                ])
            )->before(function ($values) {
                if (!isset($values[0])) {
                    $values = [$values];
                }

                foreach ($values ?? [] as $key => $value) {
                    if ('imports' === $key) {
                        $files = \array_map([$this, 'loadFromFile'], (array) $value);
                        unset($values[$key]);

                        foreach ($files as $file) {
                            $values = \array_merge($values, $file);
                        }
                    }
                }

                return $values;
            }),
        ])->castTo('array');
    }

    /**
     * {@inheritDoc}
     */
    public function loadConfiguration(): void
    {
        $container = $this->getContainerBuilder();

        $this->addRoute(
            $container->register($this->prefix('collector'), RouteCollector::class),
            $this->getFromConfig('shortcut')
        );

        if ($container->getByType(EventDispatcherInterface::class)) {
            $container->register($this->prefix('route_listener'), EventRouteListener::class);
        }

        $router = $container->register($this->prefix('factory'), FlightRouter::class)
            ->setArgument('profileRoutes', $container->getParameter('debugMode'))
            ->addSetup('addParameters', [$this->getFromConfig('requirements')])
            ->addSetup('addParameters', [
                $this->getFromConfig('defaults'),
                new PhpLiteral('Flight\Routing\Router::TYPE_DEFAULT'),
            ]);

        if (null !== $this->getFromConfig('namespace')) {
            $router->addSetup('setNamespace', [$this->getFromConfig('namespace')]);
        }

        if ($container->getByType(AnnotationLoader::class)) {
            $router->addSetup('loadAnnotation');
            $container->register($this->prefix('annotation_listener'), Listener::class);
        } else {
            $router->addSetup('?->addRoute(??)', [
                '@self', new PhpLiteral('...'), new Statement([new Reference($this->prefix('collector')), 'getCollection']),
            ]);
        }

        $middlewares = \array_merge(
            [
                ErrorHandlerMiddleware::class,
                $container->getByType(CacheItemPoolInterface::class) ? CacheControlMiddleware::class : null,
                CookiesMiddleware::class,
                SessionMiddleware::class,
                AccessControlMiddleware::class,
            ],
            $this->getFromConfig('middlewares'),
            [PathMiddleware::class, ContentSecurityPolicyMiddleware::class, HttpMiddleware::class],
        );

        $container->register($this->prefix('pipeline'), RoutePipeline::class)
            ->addSetup('?->addMiddleware(...?)', [
                '@self',
                \array_map(function ($middleware) {
                    if (\is_string($middleware) && \class_exists($middleware)) {
                        return new PhpLiteral($middleware . '::class');
                    }

                    return  $middleware;
                }, \array_filter($middlewares)),
            ])
            ->addSetup([new Reference('Tracy\Bar'), 'addPanel'], [new Statement(RoutesPanel::class, [$router])]);

        if ($container->getParameter('consoleMode')) {
            $container->register($this->prefix('command_debug'), RouteCommand::class)
                ->addTag('console.command', 'debug:routes');
        }

        $container->addAlias('router', $this->prefix('pipeline'));
    }

    /**
     * {@inheritDoc}
     */
    public function loadFromFile(string $file): array
    {
        if ('@' === $file[0]) {
            foreach ($this->compiler->getExtensions() as $name => $extension) {
                try {
                    $file = ExtensionLoader::getLocation($extension, $file, false);
                } catch (Nette\NotSupportedException $e) {
                    continue;
                }
            }
        }

        $loader = $this->createLoader();
        $loader->addAdapter('yml', NeonAdapter::class);
        $loader->addAdapter('yaml', NeonAdapter::class);
        $loader->addAdapter('xml', XmlAdapter::class);

        $res = $loader->load($file);
        $this->compiler->addDependencies($loader->getDependencies());

        return $res;
    }

    /**
     * {@inheritDoc}
     */
    public function beforeCompile(): void
    {
        $container = $this->getContainerBuilder();
        $listeners = $container->findByType(RouteListenerInterface::class);

        $container->getDefinitionByType(FlightRouter::class)
            ->addSetup(
                '?->addRouteListener(...?)',
                ['@self', $this->getHelper()->getServiceDefinitionsFromDefinitions($listeners)]
            );
    }

    private function addRoute(ServiceDefinition $collector, $routes): void
    {
        $container = $this->getContainerBuilder();

        foreach ($routes as $index => $route) {
            $methods      = empty($route->methods) ? ['GET', 'HEAD'] : $route->methods;
            $name         = null !== $route->name ? $route->name : 'generated_route_' . $index;
            $host         = null !== $route->domain
                ? $container->formatPhp('->setDomain(?)', [$route->domain]) : null;
            $defaults     = !empty($route->defaults)
                ? $container->formatPhp('->setDefaults(?)', [$this->resolveArugments($route->defaults)]) : null;
            $requirements = !empty($route->requirements)
                ? $container->formatPhp('->setPatterns(?)', [$this->resolveArugments($route->requirements)]) : null;
            $middlewares  = !empty($route->middlewares)
                ? $container->formatPhp('->addMiddleware(...?)', [$route->middlewares]) : null;
            $arguments    = null !== $route->arguments
                ? $container->formatPhp('->setArguments(?)', [$this->resolveArugments($route->arguments)]) : null;

            // Route on debug mode
            if ($container->getParameter('debugMode') && $route->mode == 'DEBUG-MODE') {
                $collector->addSetup(
                    "?->map(?, ?, ?, ?){$host}{$middlewares}{$defaults}{$requirements}{$arguments}",
                    ['@self', $name, $methods, $route->path, $route->controller]
                );

                continue;
            }

            // Route on deploy mode
            if ($container->getParameter('productionMode') && $route->mode == 'DEPLOY-MODE') {
                $collector->addSetup(
                    "?->map(?, ?, ?, ?){$host}{$middlewares}{$defaults}{$requirements}{$arguments}",
                    ['@self', $name, $methods, $route->path, $route->controller]
                );

                continue;
            }

            // Route on all mode
            if (null === $route->mode) {
                $collector->addSetup(
                    "?->map(?, ?, ?, ?){$host}{$middlewares}{$defaults}{$requirements}{$arguments}",
                    ['@self', $name, $methods, $route->path, $route->controller]
                );
            }
        }
    }

    /**
     * @param array<string,mixed>|string $arguments
     *
     * @return array<string,mixed>
     */
    private function resolveArugments($arguments): array
    {
        if (\is_array($arguments)) {
            return $arguments;
        }

        $arguments    = \explode(', ', \trim($arguments, '[]'));
        $newArguments = [];

        foreach ($arguments as $argument) {
            $values = \explode(' => ', $argument);

            // Found an argument
            $newArguments[$values[0]] = $values[1];
        }

        return $newArguments;
    }
}
