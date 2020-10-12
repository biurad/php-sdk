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

namespace Biurad\Framework\DependencyInjection;

use Biurad\Framework\Container;
use Nette;
use Nette\Bridges\DITracy\ContainerPanel as DITracyContainerPanel;
use ReflectionClass;
use Tracy;

/**
 * Dependency injection container panel for Debugger Bar.
 */
class ContainerPanel implements Tracy\IBarPanel
{
    use Nette\SmartObject;

    /** @var null|float */
    public static $compilationTime;

    /** @var Nette\DI\Container */
    private $container;

    /** @var null|float */
    private $elapsedTime;

    /** @var string */
    private $diPath;

    public function __construct(Container $container)
    {
        $this->container   = $container;
        $this->elapsedTime = self::$compilationTime ? \microtime(true) - self::$compilationTime : null;
        $this->diPath      = \dirname((new ReflectionClass(DITracyContainerPanel::class))->getFileName());
    }

    /**
     * Renders tab.
     */
    public function getTab(): string
    {
        return Nette\Utils\Helpers::capture(function (): void {
            $elapsedTime = $this->elapsedTime;

            require  $this->diPath . '/templates/ContainerPanel.tab.phtml';
        });
    }

    /**
     * Renders panel.
     */
    public function getPanel(): string
    {
        $rc    = new ReflectionClass($this->container);
        $tags  = [];
        $types = [];

        foreach ($rc->getMethods() as $method) {
            if (\preg_match('#^createService(.+)#', $method->name, $m) && $method->getReturnType()) {
                $types[\lcfirst(\str_replace('__', '.', $m[1]))] = $method->getReturnType()->getName();
            }
        }
        $types = $this->getContainerProperty('types') + $types;
        \ksort($types);

        foreach ($this->getContainerProperty('tags') as $tag => $tmp) {
            foreach ($tmp as $service => $val) {
                $tags[$service][$tag] = $val;
            }
        }

        return Nette\Utils\Helpers::capture(function () use ($tags, $types, $rc): void {
            $container = $this->container;
            $file = $rc->getFileName();
            $instances = $this->getContainerProperty('instances');
            $wiring = $this->getContainerProperty('wiring');

            require $this->diPath . '/templates/ContainerPanel.panel.phtml';
        });
    }

    private function getContainerProperty(string $name)
    {
        $prop = (new ReflectionClass(Container::class))->getProperty($name);
        $prop->setAccessible(true);

        return $prop->getValue($this->container);
    }
}
