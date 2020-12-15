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

namespace Biurad\Framework\Extensions;

use Biurad\DependencyInjection\Extension;
use DivineNii\Invoker\ArgumentResolver;
use DivineNii\Invoker\Interfaces\InvokerInterface;
use DivineNii\Invoker\Invoker;
use Nette\DI\Definitions\Reference;
use Nette\PhpGenerator\Helpers;

class InvokerExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function loadConfiguration(): void
    {
        $container = $this->getContainerBuilder();

        $container->register($this->prefix('invoker'), Invoker::class)
            ->setType(InvokerInterface::class);
    }

    /**
     * {@inheritdoc}
     */
    public function beforeCompile(): void
    {
        $container = $this->getContainerBuilder();
        $invoker   = $container->getDefinitionByType(InvokerInterface::class);

        if ($container->findByTag('invoker.argument')) {
            foreach (ArgumentResolver::getDefaultArgumentValueResolvers() as $index => $argument) {
                $argumentName = 'argument_' . Helpers::extractShortName(get_class($argument));

                $container->register($this->prefix($argumentName), get_class($argument))
                    ->addTag('invoker.argument', $index);
            }
        }

        $argumentServices = $container->findByTag('invoker.argument');

        \uasort($argumentServices, function ($a, $b) {
            $a = !\is_int($a) ? 0 : $a;

            return $a <=> $b;
        });

        // Register as services
        $invoker->setArgument(
            0,
            \array_map(
                function ($value) {
                    return new Reference($value);
                },
                \array_keys($argumentServices)
            ),
        );
    }
}
