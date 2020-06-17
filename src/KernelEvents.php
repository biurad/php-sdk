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

use BiuradPHP\Routing\Events as Flight;
use Symfony\Component\Console\Event;

/**
 * Contains all events thrown in the HttpKernel component.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
final class KernelEvents
{
    /**
     * The REQUEST event occurs at the very beginning of request
     * dispatching.
     *
     * This event allows you to create a response for a request before any
     * other code in the framework is executed.
     */
    public const REQUEST = Events\RequestEvent::class;

    /**
     * The EXCEPTION event occurs when an uncaught exception appears.
     *
     * This event allows you to create a response for a thrown exception or
     * to modify the thrown exception.
     */
    public const EXCEPTION = Events\ExceptionEvent::class;

    /**
     * The CONTROLLER event occurs once a controller was found for
     * handling a request.
     *
     * This event allows you to change the controller that will handle the
     * request.
     */
    public const CONTROLLER = Flight\ControllerEvent::class;

    /**
     * The CONTROLLER_ARGUMENTS event occurs once controller arguments have been resolved.
     *
     * This event allows you to change the arguments that will be passed to
     * the controller.
     */
    public const CONTROLLER_ARGUMENTS = Flight\ControllerArgumentsEvent::class;

    /**
     * The RESPONSE event occurs once a response was created for
     * replying to a request.
     *
     * This event allows you to modify or replace the response that will be
     * replied.
     */
    public const RESPONSE = Events\ResponseEvent::class;

    /**
     * The TERMINATE event occurs once a response was sent.
     *
     * This event allows you to run expensive post-response jobs.
     */
    public const TERMINATE = Events\TerminateEvent::class;

    /**
     * The FINISH_REQUEST event occurs when a response was generated for a request.
     *
     * This event allows you to reset the global and environmental state of
     * the application, when it was changed during the request.
     */
    public const FINISH_REQUEST = Events\FinishRequestEvent::class;

    /**
     * The COMMAND event allows you to attach listeners before any command is
     * executed by the console. It also allows you to modify the command, input and output
     * before they are handled to the command.
     */
    public const CONSOLE_COMMAND = Event\ConsoleCommandEvent::class;

    /**
     * The TERMINATE event allows you to attach listeners after a command is
     * executed by the console.
     */
    public const CONSOLE_TERMINATE = Event\ConsoleTerminateEvent::class;

    /**
     * The ERROR event occurs when an uncaught exception or error appears.
     *
     * This event allows you to deal with the exception/error or
     * to modify the thrown exception.
     */
    public const CONSOLE_ERROR = Event\ConsoleErrorEvent::class;
}
