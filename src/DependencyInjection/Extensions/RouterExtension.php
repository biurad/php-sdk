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

use Biurad\Framework\DependencyInjection\Extension;
use Biurad\Framework\DependencyInjection\XmlAdapter;
use Biurad\Framework\ExtensionLoader;
use Biurad\Framework\Router as FrameworkRouter;
use Biurad\Http\Middlewares\AccessControlMiddleware;
use Biurad\Http\Middlewares\ContentSecurityPolicyMiddleware;
use Biurad\Http\Middlewares\CookiesMiddleware;
use Biurad\Http\Middlewares\ErrorHandlerMiddleware;
use Biurad\Http\Middlewares\HttpMiddleware;
use Biurad\Http\Middlewares\SessionMiddleware;
use Flight\Routing\Middlewares\PathMiddleware;
use Flight\Routing\RouteCollector;
use Flight\Routing\RoutePipeline;
use Nette;
use Nette\DI\Config\Adapters\NeonAdapter;
use Nette\DI\Definitions\Reference;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\DI\Definitions\Statement;
use Nette\PhpGenerator\Literal;
use Nette\Schema\Expect;

class RouterExtension extends Extension
{
    /**
     * {@inheritDoc}
     */
    public function getConfigSchema(): Nette\Schema\Schema
    {
        return Nette\Schema\Expect::structure([
            'namespace'             => Nette\Schema\Expect::string()->default(null),
            'matcher'               => Expect::anyOf(Expect::string(), Expect::object())->before(function ($value) {
                return (\is_string($value) && \class_exists($value)) ? new Statement($value) : $value;
            }),
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
                    'requirements'  => Nette\Schema\Expect::array(),
                    'defaults'      => Nette\Schema\Expect::array(),
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
        $matcher   = $this->getFromConfig('matcher');

        $this->addRoute(
            $container->register($this->prefix('collector'), RouteCollector::class),
            $this->getFromConfig('shortcut')
        );

        $router = $container->register($this->prefix('factory'), FrameworkRouter::class)
            ->setArgument('matcher', \is_string($matcher) ? new Reference($matcher) : $matcher)
            ->addSetup('addParameters', [$this->getFromConfig('requirements')])
            ->addSetup('addParameters', [$this->getFromConfig('defaults'), FrameworkRouter::TYPE_DEFAULT])
            ->addSetup('?->addRoute(??->getCollection())', [
                '@self', new Literal('...'), new Reference($this->prefix('collector')),
            ]);

        if (null !== $this->getFromConfig('namespace')) {
            $router->addSetup('setNamespace', [$this->getFromConfig('namespace')]);
        }

        $container->register($this->prefix('pipeline'), RoutePipeline::class)
            ->addSetup('?->addMiddleware(...?)', [
                '@self',
                \array_merge(
                    $this->getFromConfig('middlewares'),
                    [
                        PathMiddleware::class,
                        HttpMiddleware::class,
                        AccessControlMiddleware::class,
                        ContentSecurityPolicyMiddleware::class,
                        SessionMiddleware::class,
                        CookiesMiddleware::class,
                        ErrorHandlerMiddleware::class,
                    ]
                ),
            ]);

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

    private function addRoute(ServiceDefinition $collector, $routes): void
    {
        $container = $this->getContainerBuilder();

        foreach ($routes as $index => $route) {
            $methods      = empty($route->methods) ? ['GET', 'HEAD'] : $route->methods;
            $name         = null !== $route->name ? $route->name : 'generated_route_' . $index;
            $host         = null !== $route->domain
                ? $container->formatPhp('->setDomain(?)', [$route->domain]) : null;
            $defaults     = !empty($route->defaults)
                ? $container->formatPhp('->setDefaults(?)', [$route->defaults]) : null;
            $requirements = !empty($route->requirements)
                ? $container->formatPhp('->setPatterns(?)', [$route->requirements]) : null;
            $middlewares  = !empty($route->middlewares)
                ? $container->formatPhp('->addMiddleware(...?)', [$route->middlewares]) : null;

            // Route on debug mode
            if ($container->getParameter('debugMode') && $route->mode == 'DEBUG-MODE') {
                $collector->addSetup(
                    "?->map(?, ?, ?, ?){$host}{$middlewares}{$defaults}{$requirements}",
                    ['@self', $name, $methods, $route->path, $route->controller]
                );

                continue;
            }

            // Route on deploy mode
            if ($container->getParameter('productionMode') && $route->mode == 'DEPLOY-MODE') {
                $collector->addSetup(
                    "?->map(?, ?, ?, ?){$host}{$middlewares}{$defaults}{$requirements}",
                    ['@self', $name, $methods, $route->path, $route->controller]
                );

                continue;
            }

            // Route on all mode
            if (null === $route->mode) {
                $collector->addSetup(
                    "?->map(?, ?, ?, ?){$host}{$middlewares}{$defaults}{$requirements}",
                    ['@self', $name, $methods, $route->path, $route->controller]
                );
            }
        }
    }
}
