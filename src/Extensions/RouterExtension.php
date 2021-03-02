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
use Biurad\Framework\Kernel;
use Biurad\Framework\Loaders\ExtensionLoader;
use Biurad\Http\Middlewares\AccessControlMiddleware;
use Biurad\Http\Middlewares\ContentSecurityPolicyMiddleware;
use Biurad\Http\Middlewares\CookiesMiddleware;
use Biurad\Http\Middlewares\ErrorHandlerMiddleware;
use Biurad\Http\Middlewares\HttpMiddleware;
use Biurad\Http\Middlewares\SessionMiddleware;
use Flight\Routing\Annotation\Listener;
use Flight\Routing\Interfaces\RouteListenerInterface;
use Flight\Routing\Matchers\SimpleRouteDumper;
use Flight\Routing\Matchers\SimpleRouteMatcher;
use Flight\Routing\Middlewares\PathMiddleware;
use Flight\Routing\RouteCollection;
use Flight\Routing\Router as FlightRouter;
use Nette;
use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\Reference;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\DI\Definitions\Statement;
use Nette\Loaders\RobotLoader;
use Nette\PhpGenerator\PhpLiteral;
use Nette\Schema\Expect;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class RouterExtension extends Extension
{
    /**
     * {@inheritDoc}
     */
    public function getConfigSchema(): Nette\Schema\Schema
    {
        return Nette\Schema\Expect::structure([
            'redirect_permanent'    => Nette\Schema\Expect::bool(false),
            'response_error'        => Nette\Schema\Expect::bool(true),
            'options'               => Nette\Schema\Expect::structure([
                'namespace'            => Nette\Schema\Expect::string(),
                'matcher_class'        => Nette\Schema\Expect::string(SimpleRouteMatcher::class),
                'matcher_dumper_class' => Nette\Schema\Expect::string(SimpleRouteDumper::class),
                'cache_dir'            => Nette\Schema\Expect::string(),
                'options_skip'         => Nette\Schema\Expect::bool(false),
                'debug'                => Nette\Schema\Expect::bool($this->getContainerBuilder()->parameters['debugMode']),
            ])->castTo('array'),
            'resource'              => Nette\Schema\Expect::string()->nullable()->assert(function ($value) {
                return \is_dir($value) || \class_exists($value) || \interface_exists($value);
            }),
            'middlewares'           => Expect::array()->before(function ($value) {
                return \is_string($value) ? [$value] : $value;
            }),
            'shortcut'              => Expect::arrayOf(
                Expect::structure([
                    'path'          => Nette\Schema\Expect::string(),
                    'name'          => Nette\Schema\Expect::string()->nullable(),
                    'methods'       => Expect::list()->items('string')->before(function ($methods) {
                        return \is_string($methods) ? [$methods] : $methods;
                    }),
                    'mode'          => Nette\Schema\Expect::anyOf('DEPLOY-MODE', 'DEBUG-MODE', null),
                    'controller'    => Expect::anyOf(Expect::string(), Expect::array(), Expect::object()),
                    'domain'        => Nette\Schema\Expect::list()->items('string'),
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
            $container->register($this->prefix('collector'), RouteCollection::class)->setArgument(0, false),
            $this->getFromConfig('shortcut')
        );

        if ($container->getByType(EventDispatcherInterface::class)) {
            //$container->register($this->prefix('route_listener'), EventRouteListener::class);
        }

        $router = $container->register($this->prefix('factory'), FlightRouter::class)
            ->setArgument('options', $this->getFromConfig('options'));

        if ($container->getByType(AnnotationLoader::class)) {
            $router->addSetup('loadAnnotation');
            $container->register($this->prefix('annotation_listener'), Listener::class);
        } else {
            $router->addSetup("?->addRoute(??)", [
                '@self', new PhpLiteral('...'), new Statement([new Reference($this->prefix('collector')), 'getRoutes']),
            ]);
        }

        $middlewares = \array_merge(
            [
                PathMiddleware::class,
                HttpMiddleware::class,
            ],
            $this->getFromConfig('middlewares'),
            [
                CookiesMiddleware::class,
                SessionMiddleware::class,
                AccessControlMiddleware::class,
                ContentSecurityPolicyMiddleware::class,
                //RouteHandlerMiddleware::class,
                new Statement(ErrorHandlerMiddleware::class, [$this->getFromConfig('response_error')])
            ],
        );

        $router->addSetup('?->addMiddleware(...?)', [
            '@self',
            \array_map(function ($middleware) {
                if (\is_string($middleware) && \class_exists($middleware)) {
                    return new PhpLiteral($middleware . '::class');
                }

                return  $middleware;
            }, \array_filter($middlewares)),
        ]);

        if ($container->getParameter('consoleMode')) {
            $container->register($this->prefix('command_debug'), RouteCommand::class)
                ->addTag('console.command', 'debug:routes');
        }

        $container->addAlias('router', $this->prefix('factory'));
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

        $all = [];

        if (\is_string($presenter = $this->getFromConfig('resource'))) {
            foreach ($container->findByType($presenter) as $def) {
                $all[$def->getType()] = $def;
            }
        }

        $counter = 0;

        foreach ($this->findControllers() as $class) {
            if (!isset($all[$class])) {
                $all[$class] = $container->addDefinition($this->prefix('handler.' . (string) ++$counter))
                    ->setType($class);
            }
        }

        foreach ($all as $def) {
            $def->addTag(Nette\DI\Extensions\InjectExtension::TAG_INJECT)
                ->setAutowired(false);
        }

        $listeners = $container->findByType(RouteListenerInterface::class);
        $router    = $container->getDefinitionByType(FlightRouter::class);

        if (!empty($listeners)) {
            $router->addSetup(
                '?->addRouteListener(...?)',
                ['@self', $this->getHelper()->getServiceDefinitionsFromDefinitions($listeners)]
            );
        }

        $router->addSetup([new Reference('Tracy\Bar'), 'addPanel'], [new Statement(RoutesPanel::class)]);
    }

    protected function createRobotLoader(): RobotLoader
    {
        $robot = new RobotLoader();
        $robot->addDirectory($this->getFromConfig('resource'));
        $robot->acceptFiles = ['*.php'];
        $robot->rebuild();

        return $robot;
    }

    /**
     * @return string[]
     */
    private function findControllers(): array
    {
        if (null === $resource = $this->getFromConfig('resource')) {
            return [];
        }

        if (\is_dir($resource)) {
            $robot   = $this->createRobotLoader();
            $classes = [];

            foreach (\array_unique(\array_keys($robot->getIndexedClasses())) as $class) {
                // Skip not existing class
                if (!\class_exists($class)) {
                    continue;
                }

                // Remove `Biurad\Framework\Kernel` class
                if (\is_subclass_of($class, Kernel::class) || \is_subclass_of($class, CompilerExtension::class)) {
                    continue;
                }

                $classes[] = $class;
            }

            return $classes;
        }
        $container = $this->getContainerBuilder();

        return $this->findClasses([$container->getParameter('appDir')], $resource);
    }

    private function addRoute(ServiceDefinition $collector, $routes): void
    {
        $container = $this->getContainerBuilder();

        foreach ($routes as $route) {
            $methods      = empty($route->methods) ? ['GET', 'HEAD'] : $route->methods;
            $name         = null !== $route->name 
                ? $container->literal('->bind(?)', [$route->name ?: '$service->generateRouteName(\'\')']) : null;
            $host         = null !== $route->domain
                ? $container->literal('->domain(...?)', [$route->domain]) : null;
            $defaults     = !empty($route->defaults)
                ? $container->literal('->defaults(?)', [$this->resolveArugments($route->defaults)]) : null;
            $requirements = !empty($route->requirements)
                ? $container->literal('->asserts(?)', [$this->resolveArugments($route->requirements)]) : null;
            $middlewares  = !empty($route->middlewares)
                ? $container->literal('->middleware(...?)', [$route->middlewares]) : null;
            $arguments    = null !== $route->arguments
                ? $container->literal('->arguments(?)', [$this->resolveArugments($route->arguments)]) : null;

            // Route on debug mode
            if ($container->getParameter('debugMode') && $route->mode == 'DEBUG-MODE') {
                $collector->addSetup(
                    "?->addRoute(?, ?, ?){$name}{$host}{$middlewares}{$defaults}{$requirements}{$arguments}",
                    ['@self', $route->path, $methods, $route->controller]
                );

                continue;
            }

            // Route on deploy mode
            if ($container->getParameter('productionMode') && $route->mode == 'DEPLOY-MODE') {
                $collector->addSetup(
                    "?->addRoute(?, ?, ?){$name}{$host}{$middlewares}{$defaults}{$requirements}{$arguments}",
                    ['@self', $route->path, $methods, $route->controller]
                );

                continue;
            }

            // Route on all mode
            if (null === $route->mode) {
                $collector->addSetup(
                    "?->addRoute(?, ?, ?){$name}{$host}{$middlewares}{$defaults}{$requirements}{$arguments}",
                    ['@self',$route->path, $methods, $route->controller]
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
