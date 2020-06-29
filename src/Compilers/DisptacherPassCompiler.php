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

namespace BiuradPHP\MVC\Compilers;

use BiuradPHP\DependencyInjection\Compilers\ContainerBuilder;
use BiuradPHP\DependencyInjection\Interfaces\CompilerPassInterface;
use BiuradPHP\MVC\Application;
use BiuradPHP\MVC\Interfaces\DispatcherInterface;

class DisptacherPassCompiler implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container): void
    {
        $framework = $container->getDefinitionByType(Application::class);

        foreach ($container->findByType(DispatcherInterface::class) as $name => $definition) {
            $newStatement = $definition->getFactory();
            $container->removeDefinition($name);

            $framework->addSetup('addDispatcher', [$newStatement]);
        }
    }
}
