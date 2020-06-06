<?php
/** @noinspection PhpUndefinedClassInspection */

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

namespace BiuradPHP\MVC\Dispatchers;

use BiuradPHP\MVC\Interfaces\DispatcherInterface;
use BiuradPHP\MVC\Interfaces\KernelInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use BiuradPHP\MVC\Events\FinishRequestEvent;
use BiuradPHP\MVC\Events\ResponseEvent;
use BiuradPHP\MVC\Events\StartupEvent;
use BiuradPHP\Events\Interfaces\EventDispatcherInterface;
use BiuradPHP\Http\Exceptions\ClientExceptions\TooManyRequestsException;
use BiuradPHP\MVC\Events\RequestEvent;

final class SapiDispatcher implements DispatcherInterface
{
    public const MAX_REQUEST = 20;

    /** @var EventDispatcherInterface */
    private $dispatcher;

    /** @var ServerRequestInterface[] */
    private $requests = [];

    public function __construct(EventDispatcherInterface $events)
    {
        $this->dispatcher = $events;
    }

    /**
     * @inheritdoc
     */
    public function canServe(): bool
    {
        return PHP_SAPI !== 'cli';
    }

    /**
     * Handles a request to convert it to a response.
     * Exceptions are not caught.
     *
     * @inheritdoc
     */
    public function serve(StartupEvent $event): ResponseInterface
    {
        // On demand to save some memory.
        process:
        if (count($this->requests) > self::MAX_REQUEST) {
            $exception = new TooManyRequestsException();
            $exception->withMessage('Too many request detected in application life cycle.');

            throw $exception;
        }

        // request
        $event = new RequestEvent($event->getKernel(), $event->getRequest());
        $this->dispatcher->dispatch($event);

        // Add a new request to stack.
        $this->requests[] = $event->getRequest();

        if ($event->hasResponse()) {
            return $this->filterResponse($event->getResponse(), $event->getRequest(), $event->getKernel());
        }

        goto process;
    }

    /**
     * Filters a response object.
     *
     * @param ResponseInterface $response
     * @param ServerRequestInterface $request
     * @param KernelInterface $kernel
     *
     * @return ResponseInterface
     */
    private function filterResponse(ResponseInterface $response, ServerRequestInterface $request, KernelInterface $kernel): ResponseInterface
    {
        $event = new ResponseEvent($kernel, clone $request, $response);

        $this->dispatcher->dispatch($event);

        $this->finishRequest($request, $event->getKernel());

        return $event->getResponse();
    }

    /**
     * Publishes the finish request event, then pop the request from the stack.
     *
     * Note that the order of the operations is important here, otherwise
     * operations can lead to weird results.
     *
     * @param ServerRequestInterface $request
     * @param KernelInterface $kernel
     */
    private function finishRequest(ServerRequestInterface $request, KernelInterface $kernel)
    {
        $this->dispatcher->dispatch(new FinishRequestEvent($kernel, $request));
        array_pop($this->requests);
    }
}
