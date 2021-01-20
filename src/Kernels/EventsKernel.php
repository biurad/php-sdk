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

namespace Biurad\Framework\Kernels;

use Biurad\DependencyInjection\FactoryInterface;
use Biurad\Framework\Event\ExceptionEvent;
use Biurad\Framework\Event\RequestEvent;
use Biurad\Framework\Event\ResponseEvent;
use Biurad\Framework\Event\TerminateEvent;
use Biurad\Framework\KernelEvents;
use Biurad\Http\Response;
use Exception;
use GuzzleHttp\Exception\BadResponseException;
use Laminas\HttpHandlerRunner\Emitter\SapiStreamEmitter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Throwable;

class EventsKernel extends HttpKernel
{
    /** @var EventDispatcherInterface */
    protected $dispatcher;

    /**
     * EventsKernel constructor.
     *
     * @param FactoryInterface     $dependencyContainer
     * @param null|LoggerInterface $logger
     */
    public function __construct(FactoryInterface $dependencyContainer, ?LoggerInterface $logger = null)
    {
        $this->dispatcher = $dependencyContainer->get(EventDispatcherInterface::class);

        parent::__construct($dependencyContainer, $logger);
    }

    /**
     * {@inheritdoc}
     */
    public function serve(ServerRequestInterface $request, bool $catch = true)
    {
        $response = parent::serve($request, $catch);

        if ($response instanceof ResponseInterface) {
            $response = $this->filterResponse($response, $request);

            $this->terminate($request, $response);

            if (static::class === __CLASS__) {
                (new SapiStreamEmitter())->emit($response);
            }
        }

        return $response;
    }

    /**
     * {@inheritdoc}
     */
    public function handleRequest(ServerRequestInterface $request)
    {
        $event = new RequestEvent($this, $request);
        $this->dispatcher->dispatch($event, KernelEvents::REQUEST);

        if ($event->hasResponse()) {
            return $event->getResponse();
        }

        return parent::handleRequest($request);
    }

    /**
     * Get the symfony's event dispatcher.
     *
     * @return null|EventDispatcherInterface
     */
    public function getEventDisptacher(): EventDispatcherInterface
    {
        return $this->dispatcher;
    }

    /**
     * {@inheritdoc}
     */
    public function terminate(ServerRequestInterface $request, ResponseInterface $response): void
    {
        $event = new TerminateEvent($this, $request, $response);
        $this->dispatcher->dispatch($event, KernelEvents::TERMINATE);
    }

    /**
     * @param Throwable              $exception
     * @param ServerRequestInterface $request
     */
    public function terminateWithException(Throwable $exception, ServerRequestInterface $request): void
    {
        $response = $this->handleThrowable($exception, $request);
        $this->terminate($request, $response);
    }

    /**
     * Filters a response object.
     *
     * @throws RuntimeException if the passed object is not a Response instance
     */
    private function filterResponse(ResponseInterface $response, ServerRequestInterface $request)
    {
        $event = new ResponseEvent($this, $request, $response);
        $this->dispatcher->dispatch($event, KernelEvents::RESPONSE);

        return $event->getResponse();
    }

    /**
     * Handles a throwable by trying to convert it to a Response.
     *
     * @throws Exception
     */
    protected function handleThrowable(Throwable $e, ServerRequestInterface $request): ResponseInterface
    {
        $event = new ExceptionEvent($this, $request, $e);
        $this->dispatcher->dispatch($event, KernelEvents::EXCEPTION);

        // a listener might have replaced the exception
        $e = $event->getThrowable();

        if (!$event->hasResponse()) {
            throw $e;
        }

        /** @var Response $response */
        $response = $event->getResponse();

        // the developer asked for a specific status code
        if (!$event->isAllowingCustomResponseCode() && !$response->isClientError() && !$response->isServerError() && !$response->isRedirect()) {
            // ensure that we actually have an error response
            if ($e instanceof BadResponseException) {
                // keep the HTTP status code and headers
                $response = $e->getResponse();
                $request  = $e->getRequest();
            } else {
                $response = $response->withStatus(500);
            }
        }

        try {
            return $this->filterResponse($response, $request);
        } catch (Exception $e) {
            return $response;
        }
    }
}
