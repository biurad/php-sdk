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
use Biurad\Framework\Dispatchers\CliDispatcher;
use Biurad\Framework\Dispatchers\HttpDispatcher;
use Biurad\Framework\ExtensionLoader;
use Biurad\Framework\Interfaces\KernelInterface;
use Biurad\Framework\Kernels\EventsKernel;
use Biurad\Framework\Kernels\HttpKernel;
use Nette;
use Nette\DI\Definitions\Statement;
use Nette\Schema\Expect;
use Symfony\Component\EventDispatcher\EventDispatcher;

class FrameworkExtension extends Extension
{
    /**
     * {@inheritDoc}
     */
    public function getConfigSchema(): Nette\Schema\Schema
    {
        return Nette\Schema\Expect::structure([
            'content_security_policy' => Nette\Schema\Expect::bool(false),
            'error_template'          => Nette\Schema\Expect::string(),
            'dispatchers'             => Nette\Schema\Expect::listOf(Expect::string()->assert('class_exists'))
                ->default([HttpDispatcher::class, CliDispatcher::class]),
            'imports'                 => Nette\Schema\Expect::list(),
            'cache_driver'            => Nette\Schema\Expect::string()->default(\extension_loaded('apcu') ? 'apcu' : 'array'),
        ])->castTo('array');
    }

    /**
     * {@inheritDoc}
     */
    public function loadConfiguration(): void
    {
        $container = $this->getContainerBuilder();

        foreach ($this->compiler->getExtensions() as $name => $extension) {
            foreach ($this->getFromConfig('imports') as $resource) {
                try {
                    $path = ExtensionLoader::getLocation($extension, $resource);
                } catch (Nette\NotSupportedException $e) {
                    continue;
                }

                $this->compiler->loadDefinitionsFromConfig([$name => $this->loadFromFile($path)]);
            }
        }

        $container->register(
            $this->prefix('app'),
            \class_exists(EventDispatcher::class) ? EventsKernel::class : HttpKernel::class
        )
        ->setType(KernelInterface::class)
        ->addSetup('?->addDispatcher(...?)', [
            '@self',
            \array_map(
                function (string $dispatcher) {
                    return new Statement($dispatcher);
                },
                $this->getFromConfig('dispatchers')
            ), ]);

        $container->addAlias('application', $this->prefix('app'));
    }
}
