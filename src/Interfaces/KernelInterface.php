<?php

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
    const MAX_REQUEST = 20;

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
     * @return mixed|ResponseInterface
     * @throws Throwable
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
     * @return string|mixed
     */
    public function path($uri, $first = false);
}
