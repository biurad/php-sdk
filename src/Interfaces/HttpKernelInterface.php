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

namespace Biurad\Framework\Interfaces;

use Exception;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * HttpKernelInterface handles app Dispatchers.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
interface HttpKernelInterface
{
    /**
     * Handles a served Dispatcher.
     *
     * When $catch is true, the implementation must catch all exceptions
     * and do its best to convert them to a Response instance.
     *
     * @param ServerRequestInterface $request
     * @param bool                   $catch   Whether to catch exceptions or not
     *
     * @throws Exception When an Exception occurs during processing
     *
     * @return mixed
     */
    public function serve(ServerRequestInterface $request, bool $catch = true);

    /**
     * Get the symfony's event dispatcher.
     *
     * @return EventDispatcherInterface
     */
    public function getEventDisptacher(): EventDispatcherInterface;

    /**
     * Get the Nette DI container
     *
     * @return FactoryInterface
     */
    public function getContainer(): FactoryInterface;
}
