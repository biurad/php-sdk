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
use Biurad\Framework\Interfaces\ContainerAwareInterface;

class ContainerAwareExtension extends Extension
{
    /**
     * Tweak DI container
     */
    public function beforeCompile(): void
    {
        $container = $this->getContainerBuilder();
        $type      = $container->findByType(ContainerAwareInterface::class);

        // Register as services
        foreach ($this->getServiceDefinitionsFromDefinitions($type) as $definition) {
            $definition->addSetup('setContainer');
        }
    }
}
