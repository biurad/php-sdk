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
use DivineNii\Invoker\Interfaces\ArgumentValueResolverInterface;
use DivineNii\Invoker\Interfaces\InvokerInterface;
use DivineNii\Invoker\Invoker;

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
        $type      = $container->findByType(ArgumentValueResolverInterface::class);
        $invoker   = $container->getDefinitionByType(InvokerInterface::class);

        // Register as services
        foreach ($this->getServiceDefinitionsFromDefinitions($type) as $definition) {
            $invoker->addSetup('?->getArgumentResolver()->prependResolver(?)', ['@self', $definition]);
        }
    }
}
