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
use Biurad\Framework\Interfaces\DispatcherInterface;
use Biurad\Framework\Interfaces\KernelInterface;
use Exception;
use GuzzleHttp\Exception\BadResponseException;
use Nette\SmartObject;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Throwable;

abstract class AbstractKernel implements KernelInterface
{
    use SmartObject;

    /** @var null|string */
    protected $base;

    /** @var FactoryInterface */
    protected $container;

    /** @var DispatcherInterface[] */
    protected $dispatchers = [];

    /** @var LoggerInterface */
    protected $logger;

    /**
     * Kernel constructor.
     *
     * @param FactoryInterface     $dependencyContainer
     * @param null|LoggerInterface $logger
     */
    public function __construct(FactoryInterface $dependencyContainer, ?LoggerInterface $logger = null)
    {
        $this->logger    = $logger;
        $this->container = $dependencyContainer;
        $this->base      = $dependencyContainer->getParameter('rootDir') ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function addDispatcher(DispatcherInterface ...$dispatchers): void
    {
        foreach ($dispatchers as $dispatcher) {
            $this->dispatchers[] = $dispatcher;
        }
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
                throw $e;
            }

            $response = $this->handleThrowable($e, $request);
        }

        return $response;
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
    abstract public function handleRequest(ServerRequestInterface $request);

    /**
     * Handles a throwable by trying to convert it to a Response.
     *
     * @param Throwable              $e
     * @param ServerRequestInterface $request
     *
     * @throws Exception
     *
     * @return ResponseInterface
     */
    abstract protected function handleThrowable(Throwable $e, ServerRequestInterface $request): ResponseInterface;

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
}
