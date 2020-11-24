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

use Psr\Http\Message\ResponseInterface;

/**
 * Dispatchers are general application flow controllers, system should start them and pass exception
 * or instance of snapshot into them when error happens.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
interface DispatcherInterface
{
    /**
     * Must return true if dispatcher expects to handle requests in a current environment.
     *
     * @return bool
     */
    public function canServe(): bool;

    /**
     * Start request execution.
     * Exceptions are caught.
     *
     * @param HttpKernelInterface $kernel
     *
     * @return mixed|ResponseInterface
     */
    public function serve(KernelInterface $kernel);
}
