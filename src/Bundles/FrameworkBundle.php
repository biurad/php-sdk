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
use Biurad\Framework\DependencyInjection\Extensions\FrameworkExtension;
use Biurad\Framework\Dispatchers\CliDispatcher;
use Biurad\Framework\Dispatchers\HttpDispatcher;
use Biurad\Framework\Interfaces\HttpKernelInterface;
use Biurad\Http\Factories\GuzzleHttpPsr7Factory;
use Flight\Routing\Publisher;
use Laminas\HttpHandlerRunner\Emitter\SapiStreamEmitter;
use Nette\DI\ContainerBuilder;
use Nette\DI\Definitions\Statement;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class FrameworkBundle extends Bundle
{
    public function boot(): void
    {
        $kernel   = $this->container->get(HttpKernelInterface::class);
        $request  = GuzzleHttpPsr7Factory::fromGlobalRequest();

        $response = $kernel->serve($request);

        if ($response instanceof ResponseInterface) {
            // Send response to  the browser...
            (new Publisher())->publish($response, new SapiStreamEmitter());
            $kernel->terminate($request, $response);
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

        // Add default dispatchers ...
        $container->getDefinitionByType(HttpKernelInterface::class)
            ->addSetup('addDispatcher', [new Statement(HttpDispatcher::class)])
            ->addSetup('addDispatcher', [new Statement(CliDispatcher::class)]);
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
