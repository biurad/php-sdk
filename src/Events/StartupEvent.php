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

use BiuradPHP\MVC\Interfaces\DispatcherInterface;
use BiuradPHP\MVC\Interfaces\KernelInterface;
use Psr\Http\Message\ServerRequestInterface;

class StartupEvent extends KernelEvent
{
    private $dispatcher;

    public function __construct(
        ?KernelInterface $kernel,
        ServerRequestInterface $request,
        DispatcherInterface $dispatcher
    ) {
        parent::__construct($kernel, $request);

        $this->setDispatcher($dispatcher);
    }

    public function getDispatcher(): DispatcherInterface
    {
        return $this->dispatcher;
    }

    public function setDispatcher(DispatcherInterface $dispatcher): void
    {
        $this->dispatcher = $dispatcher;
    }
}
