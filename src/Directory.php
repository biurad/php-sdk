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

use ArrayAccess;
use ArrayIterator;
use IteratorAggregate;
use Nette\Schema;
use RuntimeException;

class Directory implements IteratorAggregate, ArrayAccess
{
    /** @var array<string,string> */
    private $directories;

    public function __construct(array $directories)
    {
        $schema = Schema\Expect::structure([
            'root'       => Schema\Expect::string()->assert('file_exists')->required(),
            'configDir'  => Schema\Expect::string()->required(),
            'tempDir'    => Schema\Expect::string()->required(),
            'logDir'     => Schema\Expect::string(),
            'envFile'    => Schema\Expect::string(),
            'bundleFile' => Schema\Expect::string(),
            'bundles'    => Schema\Expect::listOf('string|object'),
        ])->before([$this, 'resolveDirectories'])
            ->castTo('array');

        try {
            $normalized = (new Schema\Processor())->process($schema, $directories);
        } catch (Schema\ValidationException $e) {
            throw new RuntimeException('Data are not valid: ' . $e->getMessage());
        }

        $this->directories = $normalized;
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        return new ArrayIterator($this->directories);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetGet($offset)
    {
        return $this->directories[$offset] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function offsetSet($offset, $value)
    {
        return $this->directories[$offset] = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function offsetExists($offset)
    {
        return isset($this->directories[$offset]);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetUnset($offset): void
    {
        unset($this->directories[$offset]);
    }

    /**
     * @param array<string,mixed> $directories
     *
     * @return array<string,mixed>
     */
    public function resolveDirectories(array $directories): array
    {
        $newDirectoris = [];
        $rootPath      = \rtrim($directories['root'], '\\/');

        foreach ($directories as $name => $path) {
            // Remove root directory for $directories and set new
            if ('root' === $name) {
                $newDirectoris['root'] = $rootPath;

                continue;
            }

            $newDirectoris[$name] = \sprintf('%s/%s', $rootPath, \trim($path, '\\/'));
        }

        // Directory to contain logs
        if (\file_exists($logDir = $newDirectories['logDir'] ?? $newDirectoris['tempDir'] . '/logs')) {
            $newDirectoris['logDir'] = $logDir;
        }

        // Load bundles if exist
        if (\file_exists($bundleDir = $newDirectories['bundleFile'] ?? $directories['configDir'] . '/bundles.php')) {
            $newDirectoris['bundles'] = require $bundleDir;
        }

        // Environment file ...
        if (!isset($newDirectories['envFile']) && \file_exists($envFile = $rootPath . '/.env')) {
            $newDirectoris['envFile'] = $envFile;
        }

        return $newDirectoris;
    }
}
