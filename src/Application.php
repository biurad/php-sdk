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

use ArrayIterator;
use BiuradPHP\DependencyInjection;
use BiuradPHP\DependencyInjection\Interfaces\FactoryInterface;
use BiuradPHP\Events\Interfaces\EventDispatcherInterface;
use BiuradPHP\Http\Interfaces\RequestExceptionInterface;
use BiuradPHP\Loader\Interfaces\ResourceLocatorInterface;
use BiuradPHP\MVC\Events\ExceptionEvent;
use BiuradPHP\MVC\Events\TerminateEvent;
use BiuradPHP\MVC\Exceptions\ErrorResponseGenerator;
use BiuradPHP\MVC\Exceptions\FrameworkIOException;
use BiuradPHP\MVC\Interfaces\DispatcherInterface;
use BiuradPHP\MVC\Interfaces\KernelInterface as HttpKernelInterface;
use Exception;
use Flight\Routing\Services\HttpPublisher;
use IteratorAggregate;
use Nette\SmartObject;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Throwable;

/**
 * Application class provides methods for handling current request and stores common runtime state.
 * It is also an IoC container.
 */
class Application implements HttpKernelInterface, IteratorAggregate, LoggerAwareInterface
{
    use SmartObject;
    use LoggerAwareTrait;

    /** @var DispatcherInterface[] */
    protected $dispatchers = [];

    /** @var FactoryInterface */
    private $dependencyContainer;

    private $dispatcher;

    private $base;

    private $paths = [];

    /**
     * Application constructor.
     *
     * @param EventDispatcherInterface $dispatcher
     * @param null|FactoryInterface    $dependencyContainer
     */
    private function __construct(EventDispatcherInterface $dispatcher, FactoryInterface $dependencyContainer)
    {
        $this->dispatcher = $dispatcher;

        // Set the main base path.
        $this->base = $dependencyContainer->getParameter('path.ROOT');
        $this->initDependencyContainer($dependencyContainer);
    }

    /**
     * @return array
     */
    public function __sleep()
    {
        return ['dispatcher', 'dependencyContainer'];
    }

    public function __wakeup(): void
    {
        $this->__construct($this->dispatcher, $this->dependencyContainer);
    }

    /**
     * Run the Application call statically.
     *
     * @param EventDispatcherInterface $dispatcher
     * @param null|FactoryInterface    $dependencyContainer
     *
     * @return Application
     */
    public static function init(EventDispatcherInterface $dispatcher, FactoryInterface $dependencyContainer): self
    {
        return new static($dispatcher, $dependencyContainer);
    }

    /**
     * Use Full Paths for Better Performance.
     *
     * The full path starting from the index.php file. Improves performance (a bit).
     *
     * Path written in url format. eg $app->path('app://bootstrap.php');
     *
     * @param string $uri
     * @param bool   $first
     *
     * @return mixed|string
     */
    public function path($uri, $first = false)
    {
        $locator = $this->dependencyContainer->get(ResourceLocatorInterface::class);
        \assert($locator instanceof ResourceLocatorInterface);

        if ($locator->isStream($uri)) {
            return $locator->findResource($uri, true, $first);
        }

        return $locator->getBase() . '/' . \str_replace('://', '/', $uri);
    }

    /**
     * @return string The base path
     */
    public function getBase(): string
    {
        return $this->base;
    }

    /**
     * Get the dependency Container.
     *
     * @return DependencyInjection\Interfaces\FactoryInterface
     */
    public function getDependencyContainer(): FactoryInterface
    {
        return $this->dependencyContainer;
    }

    /**
     * Set the dependency Container.
     *
     * @param DependencyInjection\Interfaces\FactoryInterface $dependencyContainer
     */
    public function setDependencyContainer(FactoryInterface $dependencyContainer): void
    {
        $this->dependencyContainer = $dependencyContainer;
    }

    /**
     * Determine if the application is running in the console.
     *
     * @return bool
     */
    public function runningInConsole(): bool
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
     * Start application and serve user requests using selected dispatcher or throw
     * an exception.
     */
    public function serve(): void
    {
        $request = $this->dependencyContainer->get(Request::class);

        try {
            $response = $this->processRequest($request);
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

            $response = $this->handleThrowable($e, $request);
        }

        if ($response instanceof ResponseInterface) {
            // Send response to  the browser...
            $this->terminate($request, $response); // Turn Off The Lights...
            (new HttpPublisher())->publish($response, $this->dependencyContainer->get('emitter'));

            return; // Response sent...
        }

        /* @noinspection PhpExpressionResultUnusedInspection */
        $response; // Might be running from console or some other server...
    }

    /**
     * {@inheritdoc}
     */
    public function terminate(Request $request, ResponseInterface $response): void
    {
        $event = new TerminateEvent($this, $request, $response);
        $this->dispatcher->dispatch($event);
    }

    /**
     * @param Throwable $exception
     * @param Request   $request
     *
     * @throws Exception
     *
     * @internal
     */
    public function terminateWithException(Throwable $exception, Request $request): void
    {
        $response = $this->handleThrowable($exception, $request);

        $this->terminate($request, $response);
    }

    /**
     * Handles a request to convert it to a response.
     *
     * Exceptions are not caught.
     *
     * @param Request $request
     *
     * @throws Throwable
     *
     * @return mixed|ResponseInterface
     */
    public function processRequest(Request $request)
    {
        // on startup
        foreach ($this as $dispatcher) {
            if (true === $dispatcher->canServe()) {
                $event = new Events\StartupEvent($this, $request, $dispatcher);
                $this->dispatcher->dispatch($event);

                return $this->dependencyContainer->callMethod([$event->getDispatcher(), 'serve'], [$event]);
            }
        }

        throw new FrameworkIOException('Unable to locate active dispatcher.');
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        return new ArrayIterator($this->dispatchers);
    }

    /**
     * @param FactoryInterface $dependencyContainer
     */
    private function initDependencyContainer(FactoryInterface $dependencyContainer): void
    {
        $this->setDependencyContainer($dependencyContainer);

        /*
        * Enable the facade system by default.
        * This doesn't observe the exact facade pattern.
        */
        $dependencyContainer->callMethod([Framework::class, 'setApplication']);

        $this->paths = null;
    }

    /**
     * Handles a throwable by trying to convert it to a Response.
     *
     * Use this middleware as the outermost (or close to outermost) middleware layer,
     * and use it to intercept PHP errors and exceptions.
     *
     * @param Throwable $e
     * @param Request   $request
     *
     * @return ResponseInterface
     */
    private function handleThrowable(Throwable $e, Request $request): ResponseInterface
    {
        $event = new ExceptionEvent($this, $request, $e);
        $this->dispatcher->dispatch($event);

        // a listener might have replaced the exception and returned a response.
        $errorResponse  = $this->dependencyContainer->get(ErrorResponseGenerator::class);

        return $errorResponse($event, $e);
    }

    /**
     * Logs an exception.
     *
     * @param Throwable $exception
     * @param string    $message
     */
    private function logException(Throwable $exception, string $message): void
    {
        if (null !== $this->logger) {
            if (!$exception instanceof RequestExceptionInterface || $exception->getCode() >= 500) {
                $this->logger->critical($message, ['exception' => $exception]);
            } else {
                $this->logger->error($message, ['exception' => $exception]);
            }
        }
    }
}
