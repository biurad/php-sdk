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
use Biurad\Framework\Interfaces\BundleInterface;
use ReflectionObject;

class BundlesExtension extends Extension
{
    /** @var BundleInterface[] */
    private $bundles;

    public function __construct(iterable $bundles)
    {
        $this->bundles = $bundles;
    }

    /**
     * {@inheritdoc}
     */
    public function beforeCompile(): void
    {
        $container = $this->getContainerBuilder();

        foreach ($this->bundles as $bundle) {
            if ($bundle instanceof BundleInterface) {
                $bundle->build($container);
                $this->compiler->addDependencies([(new ReflectionObject($bundle))->getFileName()]);
            }
        }
    }
}
