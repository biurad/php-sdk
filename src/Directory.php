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

use ArrayObject;
use Closure;
use Nette\Schema;
use RuntimeException;

class Directory extends ArrayObject
{
    /**
     * @param array<string,string> $directories
     */
    public function __construct(array $directories)
    {
        $schema = Schema\Expect::structure([
            'root'       => Schema\Expect::string()->assert('file_exists')->required(),
            'configDir'  => Schema\Expect::string()->required(),
            'tempDir'    => Schema\Expect::string()->required(),
            'logDir'     => Schema\Expect::string(),
            'envFile'    => Schema\Expect::string(),
        ])->before(Closure::fromCallable([$this, 'resolveDirectories']))
            ->castTo('array');

        try {
            $normalized = (new Schema\Processor())->process($schema, $directories);
        } catch (Schema\ValidationException $e) {
            throw new RuntimeException('Data are not valid: ' . $e->getMessage());
        }

        parent::__construct($normalized, ArrayObject::ARRAY_AS_PROPS);
    }

    /**
     * @param array<string,mixed> $directories
     *
     * @return array<string,mixed>
     */
    private function resolveDirectories(array $directories): array
    {
        $newDirectories = [];
        $rootPath       = \rtrim($directories['root'], '\\/');

        foreach ($directories as $name => $path) {
            // Remove root directory for $directories and set new
            if ('root' === $name) {
                $newDirectories['root'] = $rootPath;

                continue;
            }

            $newDirectories[$name] = \sprintf('%s/%s', $rootPath, \trim($path, '\\/'));
        }

        // Directory to contain logs
        if (\file_exists($logDir = $newDirectories['logDir'] ?? $newDirectories['tempDir'] . '/logs')) {
            $newDirectories['logDir'] = $logDir;
        }

        // Environment file ...
        if (!isset($newDirectories['envFile']) && \file_exists($envFile = $rootPath . '/.env')) {
            $newDirectories['envFile'] = $envFile;
        }

        return $newDirectories;
    }
}
