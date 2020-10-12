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

namespace Biurad\Framework\DependencyInjection;

use Nette\DI\CompilerExtension as NetteCompilerExtension;
use Nette\DI\ContainerBuilder;
use Nette\DI\Definitions\Definition;
use Nette\DI\Definitions\FactoryDefinition;
use Nette\DI\Definitions\LocatorDefinition;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\DI\Resolver;

/**
 * Configurator compiling extension.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
abstract class Extension extends NetteCompilerExtension
{
    /**
     * @return Builder
     */
    public function getContainerBuilder(): ContainerBuilder
    {
        return $this->compiler->getContainerBuilder();
    }

    /**
     * Get a key from config using dots on string
     *
     * @return mixed
     */
    public function getFromConfig(string $key)
    {
        return Builder::arrayGet($this->config, $key);
    }

    /**
     * Returns the configuration array for the given extension.
     *
     * @param string $extension The extension class name
     * @param string $config    The config in dotted form
     *
     * @return mixed value from extension config or null if not found
     */
    protected function getExtensionConfig(string $extension, string $config)
    {
        $extensions = $this->compiler->getExtensions($extension);

        if (empty($extensions) || \count($extensions) !== 1) {
            return null;
        }

        return Builder::arrayGet(\current($extensions)->getConfig(), $config);
    }

    /**
     * @param Definition[] $definitions
     *
     * @return ServiceDefinition[]
     */
    protected function getServiceDefinitionsFromDefinitions(array $definitions): array
    {
        $serviceDefinitions = [];
        $resolver           = new Resolver($this->compiler->getContainerBuilder());

        foreach ($definitions as $definition) {
            if ($definition instanceof ServiceDefinition) {
                $serviceDefinitions[] = $definition;
            } elseif ($definition instanceof FactoryDefinition) {
                $serviceDefinitions[] = $definition->getResultDefinition();
            } elseif ($definition instanceof LocatorDefinition) {
                $references = $definition->getReferences();

                foreach ($references as $reference) {
                    // Check that reference is valid
                    $reference = $resolver->normalizeReference($reference);
                    // Get definition by reference
                    $definition = $resolver->resolveReference($reference);
                    // Only ServiceDefinition should be possible here
                    \assert($definition instanceof ServiceDefinition);
                    $serviceDefinitions[] = $definition;
                }
            } else {
                // Definition is of type:
                // accessor - service definition exists independently
                // imported - runtime-created service, cannot work with
                // unknown
                continue;
            }
        }

        // Filter out duplicates - we cannot distinguish if service from LocatorDefinition
        // is created by accessor or factory so duplicates are possible
        $serviceDefinitions = \array_unique($serviceDefinitions, \SORT_REGULAR);

        return $serviceDefinitions;
    }
}
