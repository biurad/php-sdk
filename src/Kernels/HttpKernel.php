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

use Biurad\Framework\AbstractKernel;
use Biurad\Http\Response;
use Flight\Routing\Router;
use GuzzleHttp\Exception\BadResponseException;
use Laminas\HttpHandlerRunner\Emitter\SapiStreamEmitter;
use Nette\Utils\Helpers;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\Console\Application;
use Throwable;

class HttpKernel extends AbstractKernel
{
    public const MAX_REQUEST = 20;

    /**
     * {@inheritdoc}
     */
    public function serve(ServerRequestInterface $request, bool $catch = true)
    {
        $response = parent::serve($request, $catch);

        // Send response to  the browser...
        if (static::class === __CLASS__ && $response instanceof ResponseInterface) {
            (new SapiStreamEmitter())->emit($response);
        }

        return $response;
    }

    /**
     * {@inheritdoc}
     */
    public function handleRequest(ServerRequestInterface $request)
    {
        if ($this->isRunningInConsole()) {
            /** @var Application $application */
            $application = $this->container->get(Application::class);

            if ($this instanceof EventsKernel) {
                $application->setDispatcher($this->getEventDisptacher());
            }

            return $application->run();
        }

        return $this->container->get(Router::class)->handle($request);
    }

    /**
     * {@inheritdoc}
     */
    protected function handleThrowable(Throwable $e, ServerRequestInterface $request): ResponseInterface
    {
        if (null === $errorPage = $this->container->getParameter('env.APP_ERROR_PAGE')) {
            throw $e;
        }

        /** @var Response $response */
        $response = $this->container->get(ResponseFactoryInterface::class)->createResponse();

        // ensure that we actually have an error response
        if ($e instanceof BadResponseException) {
            // keep the HTTP status code and headers
            $response = $e->getResponse();
            $request  = $e->getRequest();
        } else {
            $response = $response->withStatus(500);
        }

        $response->getBody()->write(
            Helpers::capture(
                function () use ($request, $e, $errorPage): void {
                    require \sprintf('%s/%s', $this->getBase(), ltrim($errorPage, '\/'));
                }
            )
        );

        return $response;
    }
}
