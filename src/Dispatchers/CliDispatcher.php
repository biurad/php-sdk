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

namespace Biurad\Framework\Dispatchers;

use Biurad\Framework\Interfaces\DispatcherInterface;
use Biurad\Framework\Interfaces\HttpKernelInterface;
use Symfony\Component\Console\Application;

class CliDispatcher implements DispatcherInterface
{
    /**
     * {@inheritdoc}
     */
    public function canServe(): bool
    {
        return \in_array(\PHP_SAPI, ['cli', 'phpdbg', 'embed'], true);
    }

    /**
     * {@inheritdoc}
     */
    public function serve(HttpKernelInterface $kernel)
    {
        $application = $kernel->getContainer()->get(Application::class);

        if ($application instanceof Application) {
            $application->setDispatcher($kernel->getEventDisptacher());

            return $application->run();
        }
    }
}
