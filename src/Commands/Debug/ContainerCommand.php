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

namespace Biurad\Framework\Commands\Debug;

use Biurad\DependencyInjection\Container;
use Biurad\DependencyInjection\FactoryInterface;
use ReflectionClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ContainerCommand extends Command
{
    public static $defaultName = 'debug:container';

    /** @var FactoryInterface */
    private $container;

    /** @var Table */
    private $table;

    public function __construct(FactoryInterface $container)
    {
        $this->container = $container;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setDescription('List application container instances')
            ->setHelp(
                <<<'EOT'
The <info>%command.name%</info> command returns lists of container instances.

Any time you add a new service or extension, remember to run "cache:flush"
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
        $this->table  = new Table($output);
        $diReflection = new ReflectionClass($this->container);
        $types        = [];

        foreach ($diReflection->getMethods() as $method) {
            if (\preg_match('#^createService(.+)#', $method->name, $m) && $method->getReturnType()) {
                $types[\lcfirst(\str_replace('__', '.', $m[1]))] = $method->getReturnType()->getName();
            }
        }
        $types = $this->getContainerProperty('types') + $types;
        \ksort($types);

        $instances = $this->getContainerProperty('instances');
        $wiring    = $this->getContainerProperty('wiring');
        $grid      = $this->table->setHeaders(['Name:', 'Autowired:', 'Service:']);

        foreach ($types as $name => $type) {
            $autowired = \in_array($name, \array_merge($wiring[$type][0] ?? [], $wiring[$type][1] ?? []), true);
            $autowired = $autowired ? 'yes' : (isset($wiring[$type]) ? 'no' : '?');
            $service   = $this->getService($instances, $name, $type);

            $grid->addRow([
                \sprintf(\is_array($service) ? '<fg=magenta>%s</>' : '%s', (string) $name),
                \sprintf(\is_array($service) ? '<fg=magenta>%s</>' : '%s', $autowired),
                \is_array($service) ? \key($service) : (string) $service,
            ]);
        }

        $grid->render();

        return 0;
    }

    private function getContainerProperty(string $name)
    {
        $prop = (new ReflectionClass(Container::class))->getProperty($name);
        $prop->setAccessible(true);

        return $prop->getValue($this->container);
    }

    /**
     * @param array  $instances
     * @param string $name
     * @param mixed  $type
     *
     * @return mixed
     */
    private function getService(array $instances, string $name, $type)
    {
        static $service;

        if (isset($instances[$name]) && !$instances[$name] instanceof Container) {
            $service = $instances[$name];
            $service = [\sprintf('<fg=magenta>%s</>', \is_object($service) ? \get_class($service) : $service) => true];
        } elseif (isset($instances[$name])) {
            $service = \get_class($instances[$name]);
        } elseif (\is_string($type)) {
            $service = $type;
        }

        return $service;
    }
}
