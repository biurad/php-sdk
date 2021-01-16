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

namespace Biurad\Framework\Event;

use Biurad\Framework\Kernels\KernelInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Base class for events thrown in the HttpKernel component.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class KernelEvent extends Event
{
    private $kernel;

    private $request;

    /**
     * @param KernelInterface $kernel
     * @param Request             $request
     */
    public function __construct(KernelInterface $kernel, Request $request)
    {
        $this->kernel  = $kernel;
        $this->request = $request;
    }

    /**
     * Returns the kernel in which this event was thrown.
     *
     * @return KernelInterface
     */
    public function getKernel()
    {
        return $this->kernel;
    }

    /**
     * Returns the request the kernel is currently processing.
     *
     * @return Request
     */
    public function getRequest()
    {
        return $this->request;
    }
}
