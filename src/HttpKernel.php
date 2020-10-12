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

use Biurad\Framework\Event\ExceptionEvent;
use Biurad\Framework\Event\FinishRequestEvent;
use Biurad\Framework\Event\ResponseEvent;
use Biurad\Framework\Event\TerminateEvent;
use Biurad\Framework\Interfaces\DispatcherInterface;
use Biurad\Framework\Interfaces\FactoryInterface;
use Biurad\Framework\Interfaces\HttpKernelInterface;
use Biurad\Http\Response;
use Exception;
use GuzzleHttp\Exception\BadResponseException;
use Nette\SmartObject;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Throwable;

class HttpKernel implements HttpKernelInterface
{
    use SmartObject;

    /** @var null|string */
    protected $base;

    /** @var FactoryInterface */
    protected $container;

    /** @var DispatcherInterface[] */
    protected $dispatchers = [];

    /** @var EventDispatcherInterface */
    protected $dispatcher;

    /** @var LoggerInterface */
    protected $logger;

    /**
     * HttpKernel constructor.
     *
     * @param FactoryInterface     $dependencyContainer
     * @param null|LoggerInterface $logger
     */
    public function __construct(FactoryInterface $dependencyContainer, ?LoggerInterface $logger = null)
    {
        $this->logger    = $logger;
        $this->container = $dependencyContainer;

        // Set the main base path.
        $this->base       = $dependencyContainer->getParameters()['rootDir'] ?? null;
        $this->dispatcher = $dependencyContainer->get(EventDispatcherInterface::class);

        /*
        * Enable the facade system by default.
        * This doesn't observe the exact facade pattern.
        */
        $dependencyContainer->callMethod([Framework::class, 'setFactory']);
    }

    /**
     * Add new dispatcher. This method must only be called before method `serve`
     * will be invoked.
     *
     * @param DispatcherInterface $dispatcher
     */
    public function addDispatcher(DispatcherInterface $dispatcher): void
    {
        $this->dispatchers[] = $dispatcher;
    }

    /**
     * {@inheritdoc}
     */
    public function serve(ServerRequestInterface $request, bool $catch = true)
    {
        try {
            $response = $this->handleRequest($request);
        } catch (Throwable $e) {
            $this->logException(
                $e,
                \sprintf(
                    'Uncaught PHP Exception %s: "%s" at %s line %s',
                    \get_class($e),
                    $e->getMessage(),
                    $e->getFile(),
                    $e->getLine()
                )
            );

            if (false === $catch || $this->isRunningInConsole()) {
                $this->finishRequest($request);

                throw $e;
            }

            return $this->handleThrowable($e, $request);
        }

        if ($response instanceof ResponseInterface) {
            return $this->filterResponse($response, $request);
        }
    }

    /**
     * Handles a request to convert it to a response.
     *
     * Exceptions are not caught.
     *
     * @param ServerRequestInterface $request
     *
     * @return mixed|ResponseInterface
     */
    public function handleRequest(ServerRequestInterface $request)
    {
        $event = new Event\RequestEvent($this, $request);
        $this->dispatcher->dispatch($event, KernelEvents::REQUEST);

        if ($event->hasResponse()) {
            return $event->getResponse();
        }

        foreach ($this->dispatchers as $dispatcher) {
            if ($dispatcher->canServe()) {
                return $this->container->callMethod([$dispatcher, 'serve'], [$this, $request]);
            }
        }

        throw new RuntimeException('Unable to locate active dispatcher.');
    }

    /**
     * @return string The base path
     */
    public function getBase(): ?string
    {
        return $this->base;
    }

    /**
     * {@inheritdoc}
     */
    public function getEventDisptacher(): EventDispatcherInterface
    {
        return $this->dispatcher;
    }

    /**
     * {@inheritdoc}
     */
    public function getContainer(): FactoryInterface
    {
        return $this->container;
    }

    /**
     * Determine if the application is running in the console.
     *
     * @return bool
     */
    public function isRunningInConsole(): bool
    {
        return \in_array(\PHP_SAPI, ['cli', 'phpdbg', 'embed'], true);
    }

    /**
     * Determine if the application is in vagrant environment.
     *
     * @return bool
     */
    public function isVagrantEnvironment(): bool
    {
        return (\getenv('HOME') === '/home/vagrant' || \getenv('VAGRANT') === 'VAGRANT') && \is_dir('/dev/shm');
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
     * Logs an exception.
     *
     * @param Throwable $exception
     * @param string    $message
     */
    protected function logException(Throwable $exception, string $message): void
    {
        if (null !== $this->logger) {
            if (!$exception instanceof BadResponseException || $exception->getCode() >= 500) {
                $this->logger->critical($message, ['exception' => $exception]);
            } else {
                $this->logger->error($message, ['exception' => $exception]);
            }
        }
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
        $this->finishRequest($request);

        return $event->getResponse();
    }

    /**
     * Publishes the finish request event, then pop the request from the stack.
     */
    private function finishRequest(ServerRequestInterface $request): void
    {
        $this->dispatcher->dispatch(new FinishRequestEvent($this, $request), KernelEvents::FINISH_REQUEST);
    }

    /**
     * Handles a throwable by trying to convert it to a Response.
     *
     * @throws Exception
     */
    private function handleThrowable(Throwable $e, ServerRequestInterface $request): ResponseInterface
    {
        $event = new ExceptionEvent($this, $request, $e);
        $this->dispatcher->dispatch($event, KernelEvents::EXCEPTION);

        // a listener might have replaced the exception
        $e = $event->getThrowable();

        if (!$event->hasResponse()) {
            $this->finishRequest($request);

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
