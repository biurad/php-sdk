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
use DivineNii\Invoker\CallableResolver;
use Flight\Routing\DebugRoute;
use Flight\Routing\Route;
use Flight\Routing\Router;
use Nette;
use Nette\Utils\Callback;
use Psr\Http\Server\RequestHandlerInterface;
use Tracy;

/**
 * Routing debugger for Debug Bar.
 */
final class RoutesPanel implements Tracy\IBarPanel
{
    use Nette\SmartObject;

    /** @var int */
    protected $routeCount = 0;

    /** @var int */
    protected $renderCount = 0;

    /** @var int */
    protected $memoryCount = 0;

    /** @var float */
    protected $duration = 0;

    /** @var DebugRoute */
    private $profiler;

    /** @var array */
    private $routes = [];

    /** @var \ReflectionClass|\ReflectionFunction|\ReflectionMethod|string */
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
        $this->processData($this->profiler);

        return Nette\Utils\Helpers::capture(function (): void {
            $duration = TemplatesPanel::formatDuration($this->duration);

            require __DIR__ . '/templates/RoutingPanel.tab.phtml';
        });
    }

    /**
     * Renders panel.
     */
    public function getPanel(): string
    {
        return Nette\Utils\Helpers::capture(function (): void {
            $source = $this->source;
            $duration = TemplatesPanel::formatDuration($this->duration);
            $memory   = TemplatesPanel::formatBytes($this->memoryCount);

            require __DIR__ . '/templates/RoutingPanel.panel.phtml';
        });
    }

    private function findSource(Route $route)
    {
        $presenter = $route->get('controller');

        if ($presenter instanceof RequestHandlerInterface) {
            $presenter = \get_class($presenter) . '@' . 'handle';
        } elseif (\is_string($presenter) && \function_exists($presenter)) {
            $presenter = $presenter;
        } elseif (\is_callable($presenter) && !$presenter instanceof \Closure) {
            $presenter = (\is_object($presenter[0]) ? \get_class($presenter[0]) : $presenter[0]) . '@' . $presenter[1];
        }

        [$class, $method] = [$presenter, null];

        if (\is_string($presenter) && 1 === \preg_match(CallableResolver::CALLABLE_PATTERN, $presenter, $matches)) {
            [, $class, $method] = $matches;
        }

        $rc = \is_callable($presenter) ? Callback::toReflection($presenter) : new \ReflectionClass($class);

        return isset($method) && $rc->hasMethod($method) ? $rc->getMethod($method) : $rc;
    }

    private function processData(DebugRoute $profile): void
    {
        if ($profile->isRoute()) {
            $this->routeCount += 1;
            $this->routes[] = [
                'name'     => $profile->getName(),
                'matched'  => $profile->isMatched(),
                'route'    => $profile->getRoute(),
            ];

            if ($profile->isMatched()) {
                $this->memoryCount = $profile->getMemoryUsage();
                $this->duration    = $profile->getDuration();
                $this->source      = $this->findSource($profile->getRoute());
            }

            return;
        }

        foreach ($profile as $p) {
            $this->processData($p);
        }
    }
}
