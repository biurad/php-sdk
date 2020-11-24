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

namespace Biurad\Framework;

use Biurad\Framework\DependencyInjection\Builder;
use Biurad\Framework\Interfaces\BundleInterface;
use LogicException;
use Nette\DI\CompilerExtension;
use Nette\DI\ContainerBuilder;
use ReflectionObject;
use Contributte\DI\TContainerAware;
/**
 * An implementation of BundleInterface that adds a few conventions for DependencyInjection extensions.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class Bundle implements BundleInterface
{
    use TContainerAware;

    /** @var null|string */
    protected $path;

    /** @var null|string */
    protected $extension;

    /**
     * {@inheritdoc}
     */
    public function boot(): void
    {
    }

    /**
     * {@inheritdoc}
     *
     * This method can be overridden to register compilation passes,
     * other extensions, ...
     *
     * @param Builder $container
     */
    public function build(ContainerBuilder $container): void
    {
    }

    /**
     * Returns the bundle's container extension.
     *
     * @throws LogicException
     *
     * @return null|string The container compiler extension
     */
    public function getContainerExtension(): ?string
    {
        if (null !== $this->extension && !$this->extension instanceof CompilerExtension) {
            throw new LogicException(\sprintf(
                'Extension "%s" must implement Nette\DI\CompilerExtension.',
                get_debug_type($this->extension)
            ));
        }

        return $this->extension;
    }

    /**
     * {@inheritdoc}
     */
    public function getPath(): string
    {
        if (null === $this->path) {
            $reflected  = new ReflectionObject($this);
            $this->path = \dirname($reflected->getFileName());
        }

        return $this->path;
    }
}
