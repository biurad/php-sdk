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

use Biurad\Framework\Debug\Template\TemplatesPanel;
use Closure;
use DivineNii\Invoker\CallableResolver;
use Flight\Routing\Interfaces\RouteInterface;
use Flight\Routing\ProfileRoute;
use Flight\Routing\Router;
use Nette;
use Nette\Utils\Callback;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use Tracy;

/**
 * Routing debugger for Debug Bar.
 */
final class RoutesPanel implements Tracy\IBarPanel
{
    use Nette\SmartObject;

    protected $routeCount = 0;

    protected $renderCount = 0;

    protected $memoryCount = 0;

    /** @var ProfileRoute */
    private $profiler;

    /** @var array */
    private $routes = [];

    /** @var ReflectionClass|ReflectionFunction|ReflectionMethod|string */
    private $source;

    public function __construct(Router $router)
    {
        $this->profiler = $router->getProfile() ?? [];
    }

    /**
     * Renders tab.
     */
    public function getTab(): string
    {
        return Nette\Utils\Helpers::capture(function (): void {
            require __DIR__ . '/templates/RoutingPanel.tab.phtml';
        });
    }

    /**
     * Renders panel.
     */
    public function getPanel(): string
    {
        $duration = 0;

        $this->processData($this->profiler);

        foreach ($this->profiler as $profiler) {
            $duration += $profiler->getDuration();
        }

        $duration = TemplatesPanel::formatDuration($duration);
        $memory   = TemplatesPanel::formatBytes($this->memoryCount);

        return Nette\Utils\Helpers::capture(function () use ($memory, $duration): void {
            $source = $this->source;

            require __DIR__ . '/templates/RoutingPanel.panel.phtml';
        });
    }

    private function findSource(RouteInterface $route)
    {
        $presenter = $route->getController();

        if ($presenter instanceof RequestHandlerInterface) {
            $presenter = \get_class($presenter) . '@' . 'handle';
        } elseif (\is_string($presenter) && \function_exists($presenter)) {
            $presenter = $presenter;
        } elseif (\is_callable($presenter) && !$presenter instanceof Closure) {
            $presenter = (\is_object($presenter[0]) ? \get_class($presenter[0]) : $presenter[0]) . '@' . $presenter[1];
        }

        [$class, $method] = [$presenter, null];

        if (\is_string($presenter) && 1 === \preg_match(CallableResolver::CALLABLE_PATTERN, $presenter, $matches)) {
            [, $class, $method] = $matches;
        }

        $rc = \is_callable($presenter) ? Callback::toReflection($presenter) : new ReflectionClass($class);

        return isset($method) && $rc->hasMethod($method) ? $rc->getMethod($method) : $rc;
    }

    private function processData(ProfileRoute $profile): void
    {
        $this->memoryCount += $profile->getMemoryUsage();

        if ($profile->isRoute()) {
            $this->routeCount += 1;
            $this->routes[] = [
                'name'     => $profile->getName(),
                'duration' => TemplatesPanel::formatDuration($profile->getDuration()),
                'memory'   => TemplatesPanel::formatBytes($profile->getMemoryUsage()),
                'matched'  => $profile->isMatched(),
                'route'    => $profile->getRoute(),
            ];

            if ($profile->isMatched()) {
                $this->source = $this->findSource($profile->getRoute());
            }

            return;
        }

        foreach ($profile as $p) {
            $this->processData($p);
        }
    }
}
