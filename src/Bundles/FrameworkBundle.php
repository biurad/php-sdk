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

namespace Biurad\Framework\Bundles;

use Biurad\Framework\Bundle;
use Biurad\Framework\Extensions\FrameworkExtension;
use Biurad\Framework\Interfaces\KernelInterface;
use Biurad\Framework\Kernels\EventsKernel;
use Biurad\Http\Factories\GuzzleHttpPsr7Factory;
use Laminas\HttpHandlerRunner\Emitter\SapiStreamEmitter;
use Nette\DI\ContainerBuilder;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class FrameworkBundle extends Bundle
{
    public function boot(): void
    {
        if (null !== $kernel = $this->container->getByType(KernelInterface::class)) {
            $request  = GuzzleHttpPsr7Factory::fromGlobalRequest();
            $response = $kernel->serve($request);

            if ($response instanceof ResponseInterface) {
                // Send response to  the browser...
                (new SapiStreamEmitter())->emit($response);

                if ($kernel instanceof EventsKernel) {
                    $kernel->terminate($request, $response);
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container): void
    {
        // Incase no logger service ...
        if (null === $container->getByType(LoggerInterface::class, false)) {
            $container->register('logger', NullLogger::class);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getContainerExtension(): ?string
    {
        $extension = parent::getContainerExtension();

        if ($extension instanceof FrameworkExtension) {
            return $extension;
        }

        return $this->extension = FrameworkExtension::class;
    }
}
