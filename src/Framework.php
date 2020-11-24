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

use Biurad\DependencyInjection\FactoryInterface;
use Nette\SmartObject;

/**
 * A proxy static binding as instance of isseted class.
 * Attention, this abstraction is currently under re-thinking process
 * in order to replace it (non breaking change). Potentially deprecated.
 *
 * The class must be compiled with an alias call.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 *
 * @see Biurad\Framework\AbstractKernel
 */
class Framework
{
    use SmartObject;

    /**
     * The application instance being facaded.
     *
     * @var FactoryInterface
     */
    protected static $container;

    /**
     * Handles calls to class methods.
     *
     * @param string $name Method name
     * @param $arguments
     *
     * @return mixed Callback results
     */
    public static function __callStatic($name, $arguments)
    {
        $container = static::$container;

        if ($container->has($name)) {
            return $container->get($name);
        }

        return $container->createInstance($name, $arguments);
    }

    /**
     * Dynamically access container services.
     *
     * @param string $name
     *
     * @return mixed
     */
    public function __get(string $name)
    {
        return static::$container->get($name);
    }

    /**
     * Dynamically set container services.
     *
     * @param string $name
     * @param mixed  $value
     *
     * @return mixed
     */
    public function __set(string $name, $value)
    {
        return static::$container->addService($name, $value);
    }

    /**
     * Set the instance implemeting Psr interface.
     *
     * @param FactoryInterface $container
     *
     * @return $this
     */
    public static function setFactory(FactoryInterface $container): self
    {
        static::$container = $container;

        return new static();
    }
}
