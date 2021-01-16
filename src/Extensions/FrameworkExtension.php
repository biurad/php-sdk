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
use Biurad\Framework\Loaders\ExtensionLoader;
use Biurad\Framework\Kernels\KernelInterface;
use Biurad\Framework\Kernels\EventsKernel;
use Biurad\Framework\Kernels\HttpKernel;
use Nette;
use Nette\Schema\Expect;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
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
            'error_template'          => Nette\Schema\Expect::string()->nullable(),
            'kernel_class'            => Nette\Schema\Expect::string()->nullable()->assert('class_exists'),
            'imports'                 => Nette\Schema\Expect::list(),
            'cache_driver'            => Expect::string()->default(\extension_loaded('apcu') ? 'apcu' : 'array'),
        ])->castTo('array');
    }

    /**
     * {@inheritDoc}
     */
    public function loadConfiguration(): void
    {
        $container = $this->getContainerBuilder();

        if (null !== $errorPage = $this->getFromConfig('error_template')) {
            $container->setParameter('env.APP_ERROR_PAGE', $errorPage);
        }

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

        $kernelClass = \class_exists(EventDispatcher::class) ? EventsKernel::class : HttpKernel::class;

        if (null !== $this->getFromConfig('kernel_class')) {
            $kernelClass = $this->getFromConfig('kernel_class');
        }

        $container->register($this->prefix('app'), $kernelClass)->setType(KernelInterface::class);
        $container->addAlias('application', $this->prefix('app'));
    }

    /**
     * {@inheritdoc}
     */
    public function beforeCompile()
    {
        $container = $this->getContainerBuilder();

        // Incase no logger service ...
        if (null === $container->getByType(LoggerInterface::class)) {
            $container->register('logger', NullLogger::class);
        }
    }
}
