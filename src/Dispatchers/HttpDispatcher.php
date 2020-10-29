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

namespace Biurad\Framework\Dispatchers;

use Biurad\Framework\Interfaces\DispatcherInterface;
use Biurad\Framework\Interfaces\HttpKernelInterface;
use Flight\Routing\Router as FlightRouter;
use Flight\Routing\RoutePipeline;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ServerRequestInterface;

class HttpDispatcher implements DispatcherInterface
{
    public const MAX_REQUEST = 20;

    /** @var ServerRequestInterface[] */
    private $requests = [];

    /**
     * {@inheritdoc}
     */
    public function canServe(): bool
    {
        return !\in_array(\PHP_SAPI, ['cli', 'phpdbg', 'embed'], true);
    }

    /**
     * {@inheritdoc}
     */
    public function serve(HttpKernelInterface $kernel, ServerRequestInterface $request = null)
    {
        $container = $kernel->getContainer();
        $pipeline  = $container->get(RoutePipeline::class);

        // On demand to save some memory.
        process:
        if (\count($this->requests) > self::MAX_REQUEST) {
            throw new RequestException('Too many request detected in application life cycle.', $request);
        }

        // Add a new request to stack.
        $this->requests[] = $request;

        if (null !== $request && $pipeline instanceof RoutePipeline) {
            return $pipeline->process($request, $container->get(FlightRouter::class));
        }

        goto process;
    }
}
