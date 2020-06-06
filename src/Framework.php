<?php

declare(strict_types=1);

/*
 * This code is under BSD 3-Clause "New" or "Revised" License.
 *
 * ---------------------------------------------------------------------------
 * BiuradPHP Framework is a new scheme of php architecture which is simple,  |
 * yet has powerful features. The framework has been built carefully 	     |
 * following the rules of the new PHP 7.2 and 7.3 above, with no support     |
 * for the old versions of PHP. As this framework was inspired by            |
 * several conference talks about the future of PHP and its development,     |
 * this framework has the easiest and best approach to the PHP world,        |
 * of course, using a few intentionally procedural programming module.       |
 * This makes BiuradPHP framework extremely readable and usable for all.     |
 * BiuradPHP is a 35% clone of symfony framework and 30% clone of Nette	     |
 * framework. The performance of BiuradPHP is 300ms on development mode and  |
 * on production mode it's even better with great defense security.          |
 * ---------------------------------------------------------------------------
 *
 * PHP version 7.2 and above required
 *
 * @category  BiuradPHP-Framework
 *
 * @author    Divine Niiquaye Ibok <divineibok@gmail.com>
 * @copyright 2019 Biurad Group (https://biurad.com/)
 * @license   https://opensource.org/licenses/BSD-3-Clause License
 *
 * @link      https://www.biurad.com/projects/biurad-framework
 */

namespace BiuradPHP\MVC;

use BiuradPHP\Database\DatabaseInterface;
use BiuradPHP\Events\Interfaces\EventDispatcherInterface;
use BiuradPHP\FileManager\FileManager;
use BiuradPHP\Loader\Resources\UniformResourceLocator;
use BiuradPHP\Security\Interfaces\EncrypterInterface;
use BiuradPHP\Session\Session;
use Flight\Routing\RouteCollector;
use Nette;
use BiuradPHP\DependencyInjection\Interfaces;
use BiuradPHP\MVC\Exceptions\FrameworkIOException;
use Cycle\ORM\ORMInterface;
use Doctrine\Common\Annotations\Reader;
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
     * Set the instance implemeting Psr interface.
     *
     * @param ContainerInterface $container
     *
     * @return $this
     */
    public static function setApplication(ContainerInterface $container): self
    {
        static::$container = $container;

        return new static;
    }

    /**
     * Handles calls to class methods.
     *
     * @param string $name Method name
     * @param $arguments
     * @return mixed Callback results
     *
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

        throw new FrameworkIOException(sprintf('[%s] is not implemented to %s', $name, static::$container));
    }

    /**
     * Dynamically access container services.
     *
     * @param string $name
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
     * @param mixed $value
     *
     * @return mixed
     */
    public function __set(string $name, $value)
    {
        return static::$container->bind($name, $value);
    }
}
