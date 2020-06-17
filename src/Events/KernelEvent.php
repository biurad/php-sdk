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

namespace BiuradPHP\MVC\Events;

use BiuradPHP\Events\Concerns\StoppableTrait;
use BiuradPHP\MVC\Application;
use BiuradPHP\MVC\Interfaces\KernelInterface as HttpKernelInterface;
use Psr\EventDispatcher\StoppableEventInterface;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Base class for events thrown in the HttpKernel component.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class KernelEvent implements StoppableEventInterface
{
    use StoppableTrait;

    private $kernel;

    private $request;

    /**
     * @param null|HttpKernelInterface $kernel
     * @param Request                  $request
     */
    public function __construct(?HttpKernelInterface $kernel = null, Request $request)
    {
        $this->kernel  = $kernel;
        $this->request = $request;
    }

    /**
     * Returns the kernel in which this event was thrown.
     *
     * @return Application|HttpKernelInterface
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
    public function getRequest(): Request
    {
        return $this->request;
    }

    /**
     * Sets a request to replace currently processing request
     *
     * @param null|Request $request
     */
    public function setRequest(?Request $request): void
    {
        $this->request = $request;
    }
}
