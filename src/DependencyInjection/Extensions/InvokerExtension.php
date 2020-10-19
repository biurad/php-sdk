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

namespace Biurad\Framework\DependencyInjection\Extensions;

use Biurad\Framework\DependencyInjection\Extension;
use DivineNii\Invoker\Interfaces\InvokerInterface;
use DivineNii\Invoker\Invoker;
use Nette\DI\Definitions\Reference;

class InvokerExtension extends Extension
{
    /**
     * {@inheritDoc}
     */
    public function loadConfiguration(): void
    {
        $container = $this->getContainerBuilder();

        $container->register($this->prefix('invoker'), Invoker::class)
            ->setType(InvokerInterface::class);
    }

    /**
     * {@inheritDoc}
     */
    public function beforeCompile(): void
    {
        $container = $this->getContainerBuilder();
        $invoker   = $container->getDefinitionByType(InvokerInterface::class);

        $argumentServices = $container->findByTag('invoker.argument');

        \uasort($argumentServices, function ($a, $b) {
            return !(\is_int($a) && \is_int($b)) ? 0 : $b <=> $a;
        });

        // Register as services
        foreach ($argumentServices as $id => $value) {
            $invoker->addSetup('?->getArgumentResolver()->prependResolver(?)', ['@self', new Reference($id)]);
        }
    }
}
