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

namespace Biurad\Framework\Commands;

use Closure;
use DivineNii\Invoker\CallableResolver;
use DivineNii\Invoker\Interfaces\InvokerInterface;
use Flight\Routing\Interfaces\RouteCollectorInterface;
use Flight\Routing\Interfaces\RouteInterface;
use Flight\Routing\Router;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RouteListCommand extends Command
{
    public static $defaultName = 'app:routes';

    /** @var Router */
    private $router;

    /** @var CallableResolver */
    private $callable;

    /** @var Table */
    private $table;

    /**
     * @param Router           $collector
     * @param InvokerInterface $invoker
     */
    public function __construct(Router $router, InvokerInterface $invoker)
    {
        $this->router   = $router;
        $this->callable = $invoker->getCallableResolver();

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setDescription('List application routes')
            ->setHelp(
                <<<'EOT'
The <info>%command.name%</info> command returns lists of routes in applicatiom.

Any time you add a new route or annotated class, remember to run "app:clean"
command, even if you want to commit your app to production.
EOT
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->table = new Table($output);
        $grid        = $this->table->setHeaders(['Name:', 'Verbs:', 'Pattern:', 'Target:']);

        foreach ($this->router->getRoutes() as $route) {
            if ($route instanceof RouteInterface) {
                $grid->addRow(
                    [
                        $route->getName(),
                        $this->getVerbs($route),
                        $this->getPattern($route),
                        $this->getTarget($route),
                    ]
                );
            }
        }

        $grid->render();

        return 0;
    }

    /**
     * @param RouteInterface $route
     *
     * @return string
     */
    private function getVerbs(RouteInterface $route): string
    {
        if ($route->getMethods() === RouteCollectorInterface::HTTP_METHODS_STANDARD) {
            return '*';
        }

        $result = [];

        foreach ($route->getMethods() as $verb) {
            switch (\strtolower($verb)) {
                case 'get':
                    $verb = '<fg=green>GET</>';

                    break;
                case 'post':
                    $verb = '<fg=blue>POST</>';

                    break;
                case 'put':
                    $verb = '<fg=yellow>PUT</>';

                    break;
                case 'delete':
                    $verb = '<fg=red>DELETE</>';

                    break;
            }

            $result[] = $verb;
        }

        return \implode(', ', $result);
    }

    /**
     * @param RouteInterface $route
     *
     * @return string
     */
    private function getPattern(RouteInterface $route): string
    {
        $pattern = \str_replace(
            '[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}',
            'uuid',
            $route->getPath()
        );

        return \preg_replace_callback(
            '/{([^>]*)}/',
            static function ($m) {
                return \sprintf('<fg=magenta>%s</>', $m[0]);
            },
            $pattern
        );
    }

    /**
     * @param RouteInterface $route
     *
     * @throws ReflectionException
     *
     * @return string
     */
    private function getTarget(RouteInterface $route): string
    {
        if (
            \is_string($controller = $route->getController()) &&
            \is_a($controller, RequestHandlerInterface::class, true)
        ) {
            $controller = [$controller, 'handle'];
        }

        if (\is_string($target = $controller) && \function_exists($controller)) {
            $target = Closure::fromCallable($controller);
        }

        if (!str_ends_with($route->getName(), '__restful')) {
            $target = $this->callable->resolve($target);
        }

        switch (true) {
            case $target instanceof Closure:
                $reflection = new ReflectionFunction($target);
                $args       = ['php', $reflection->getName()];

                if (false !== $reflection->getFileName()) {
                    $args = [\basename($reflection->getFileName()), $reflection->getStartLine()];
                }

                return \sprintf('Closure(%s:%s)', ...$args);

            case \is_callable($target):
                $reflection = new ReflectionMethod($target[0], $target[1]);

                return \sprintf(
                    '%s->%s',
                    $reflection->getDeclaringClass()->getName(),
                    $reflection->getName()
                );
            default:
                return \is_object($target) ? \get_class($target) : $target;
        }
    }
}
