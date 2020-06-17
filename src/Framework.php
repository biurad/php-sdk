<?php

declare(strict_types=1);

/*
 * This file is part of BiuradPHP opensource projects.
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

namespace BiuradPHP\MVC;

use BiuradPHP\Database\DatabaseInterface;
use BiuradPHP\DependencyInjection\Interfaces;
use BiuradPHP\Events\Interfaces\EventDispatcherInterface;
use BiuradPHP\FileManager\FileManager;
use BiuradPHP\Loader\Resources\UniformResourceLocator;
use BiuradPHP\MVC\Exceptions\FrameworkIOException;
use BiuradPHP\Security\Interfaces\EncrypterInterface;
use BiuradPHP\Session\Session;
use Cycle\ORM\ORMInterface;
use Doctrine\Common\Annotations\Reader;
use Flight\Routing\RouteCollector;
use Nette;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * A proxy static binding as instance of isseted class.
 * Attention, this abstraction is currently under re-thinking process
 * in order to replace it (non breaking change). Potentially deprecated.
 *
 * The class must be compiled with an alias call.
 *
 * @author    Divine Niiquaye Ibok <divineibok@gmail.com>
 * @license   BSD-3-Clause
 *
 * @method static CacheInterface cache()
 * @method static ServerRequestInterface request()
 * @method static RouteCollector router()
 * @method static ResponseInterface response()
 * @method static Session session()
 * @method static EventDispatcherInterface events()
 * @method static EncrypterInterface encrypter()
 * @method static UniformResourceLocator locator()
 * @method static FileManager flysystem()
 * @method static Application application()
 * @method static Reader annotation()
 * @method static ORMInterface orm()
 * @method static DatabaseInterface database()
 *
 * @see \BiuradPHP\MVC\Application
 */
class Framework
{
    use Nette\SmartObject;

    /**
     * The application instance being facaded.
     *
     * @var ContainerInterface
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

        if ($container instanceof Interfaces\FactoryInterface) {
            return $container->createInstance($name, $arguments);
        }

        throw new FrameworkIOException(\sprintf('[%s] is not implemented to %s', $name, static::$container));
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
        return static::$container->bind($name, $value);
    }

    /**
     * Set the instance implemeting Psr interface.
     *
     * @param ContainerInterface $container
     *
     * @return $this
     */
    public static function setApplication(ContainerInterface $container): self
    {
        static::$container = $container;

        return new static();
    }
}
