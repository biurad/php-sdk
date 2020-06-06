<?php /** @noinspection PhpUndefinedMethodInspection */

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

namespace BiuradPHP\MVC\EventListeners;

use BiuradPHP\DependencyInjection\Interfaces\FactoryInterface;
use BiuradPHP\Events\Interfaces\EventSubscriberInterface;
use BiuradPHP\MVC\Events\FinishRequestEvent;
use BiuradPHP\MVC\KernelEvents;
use BiuradPHP\Routing\Events\ControllerArgumentsEvent;
use BiuradPHP\Routing\Events\ControllerEvent;

/**
 * Let's broaacast framework as event and listen to it.
 */
class KernelListener implements EventSubscriberInterface
{
    private $container;

    public function __construct(FactoryInterface $container)
    {
        $this->container = $container;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::CONTROLLER            => ['onCurrentController', -100],
            KernelEvents::CONTROLLER_ARGUMENTS  => 'onControllerArguments',
            KernelEvents::FINISH_REQUEST        => ['onKernelFinishRequest', 0],
        ];
    }

    /**
     * 'listens' to the 'ControllerEvent' event, which is
     * triggered whenever a controller is executed in the application.
     *
     * @param ControllerEvent $event
     */
    public function onCurrentController(ControllerEvent $event): void
    {
        //
    }

    /**
     * 'listens' to the 'ControllerArgumentsEvent' event on controller,
     *
     * @param ControllerArgumentsEvent $event
     */
    public function onControllerArguments(ControllerArgumentsEvent $event): void
    {
        //
    }

    /**
     * After a sub-request is done, we need to reset the routing context to the parent request so that the URL generator
     * operates on the correct context again.
     *
     * @param FinishRequestEvent $event
     */
    public function onKernelFinishRequest(FinishRequestEvent $event): void
    {
        //
    }
}
