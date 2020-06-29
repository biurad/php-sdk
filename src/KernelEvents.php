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

use Symfony\Component\Console\ConsoleEvents;

/**
 * Contains all events thrown in the HttpKernel component.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
final class KernelEvents
{
    /**
     * The START_UP events occurs at the very beginning of the
     * framework.
     *
     * This event allows you to add something or use a request before any
     * other code in the framework is executed.
     *
     * @Event("BiuradPHP\MVC\Events\StartupEvent")
     */
    public const START_UP = 'kernel.start';

    /**
     * The REQUEST event occurs only at servers that supports its
     * dispatching.
     *
     * This event allows you to create a response for a request before any
     * other code in the framework is executed.
     *
     * @Event("BiuradPHP\MVC\Events\RequestEvent")
     */
    public const REQUEST = 'kernel.request';

    /**
     * The EXCEPTION event occurs when an uncaught exception appears.
     *
     * This event allows you to create a response for a thrown exception or
     * to modify the thrown exception.
     *
     * @Event("BiuradPHP\MVC\Events\RequestEvent")
     */
    public const EXCEPTION = 'kernel.exception';

    /**
     * The CONTROLLER event occurs once a controller was found for
     * handling a request.
     *
     * This event allows you to change the controller that will handle the
     * request.
     *
     * @Event("BiuradPHP\Routing\Events\ControllerEvent")
     */
    public const CONTROLLER = 'kernel.controller';

    /**
     * The CONTROLLER_ARGUMENTS event occurs once controller arguments have been resolved.
     *
     * This event allows you to change the arguments that will be passed to
     * the controller.
     *
     * @Event("BiuradPHP\Routing\Events\ControllerArgumentsEvent")
     */
    public const CONTROLLER_ARGUMENTS = 'kernel.controller_arguments';

    /**
     * The RESPONSE event occurs once a response was created for
     * replying to a request.
     *
     * This event allows you to modify or replace the response that will be
     * replied.
     *
     * @Event("BiuradPHP\MVC\Events\ResponseEvent")
     */
    public const RESPONSE = 'kernel.response';

    /**
     * The TERMINATE event occurs once a response was sent.
     *
     * This event allows you to run expensive post-response jobs.
     *
     * @Event("BiuradPHP\MVC\Events\TerminateEvent")
     */
    public const TERMINATE = 'kernel.terminate';

    /**
     * The FINISH_REQUEST event occurs when a response was generated for a request.
     *
     * This event allows you to reset the global and environmental state of
     * the application, when it was changed during the request.
     *
     * @Event("BiuradPHP\MVC\Events\FinishRequestEvent")
     */
    public const FINISH_REQUEST = 'kernel.finish';

    /**
     * The COMMAND event allows you to attach listeners before any command is
     * executed by the console. It also allows you to modify the command, input and output
     * before they are handled to the command.
     *
     * @Event("Symfony\Component\Console\Event\ConsoleCommandEvent")
     */
    public const CONSOLE_COMMAND = ConsoleEvents::COMMAND;

    /**
     * The TERMINATE event allows you to attach listeners after a command is
     * executed by the console.
     *
     * @Event("Symfony\Component\Console\Event\ConsoleTerminateEvent")
     */
    public const CONSOLE_TERMINATE = ConsoleEvents::TERMINATE;

    /**
     * The ERROR event occurs when an uncaught exception or error appears.
     *
     * This event allows you to deal with the exception/error or
     * to modify the thrown exception.
     *
     * @Event("Symfony\Component\Console\Event\ConsoleErrorEvent")
     */
    public const CONSOLE_ERROR = ConsoleEvents::ERROR;
}
