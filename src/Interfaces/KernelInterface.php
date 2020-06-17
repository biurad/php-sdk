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

namespace BiuradPHP\MVC\Interfaces;

use BiuradPHP\DependencyInjection\Interfaces\FactoryInterface;
use BiuradPHP\MVC\Exceptions\FrameworkIOException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;

/**
 * HttpKernelInterface handles a Request to convert it to a Response.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
interface KernelInterface
{
    public const MAX_REQUEST = 20;

    /**
     * Add new dispatcher. This method must only be called before method `serve`
     * will be invoked.
     *
     * @param DispatcherInterface $dispatcher
     */
    public function addDispatcher(DispatcherInterface $dispatcher);

    /**
     * Start application and serve user requests using selected dispatcher or throw
     * an exception.
     *
     * @throws FrameworkIoException
     * @throws Throwable
     */
    public function serve();

    /**
     * Handles a request to convert it to a response.
     * Exceptions are not caught.
     *
     * @param Request $request
     *
     * @throws Throwable
     *
     * @return mixed|ResponseInterface
     */
    public function processRequest(Request $request);

    /**
     * Get the dependency Container.
     *
     * @return FactoryInterface
     */
    public function getDependencyContainer(): FactoryInterface;

    /**
     * Determine if the application is running in the console.
     *
     * @return bool
     */
    public function runningInConsole(): bool;

    /**
     * Determine if the application is in vagrant environment.
     *
     * @return bool
     */
    public function isVagrantEnvironment(): bool;

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
    public function path($uri, $first = false);
}
