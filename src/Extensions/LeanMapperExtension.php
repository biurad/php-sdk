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
use Dibi\Event;
use Nette;
use Nette\Schema\Expect;
use Nette\Utils\Strings;

class LeanMapperExtension extends Extension
{
    /** @var string|string[] */
    private $scanDirs;

    /**
     * @param string|string[] $scanDirs
     */
    public function __construct($scanDirs = [])
    {
        $this->scanDirs = $scanDirs;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigSchema(): Nette\Schema\Schema
    {
        return Nette\Schema\Expect::structure([
            'connection'             => Nette\Schema\Expect::structure([
                'lazy'               => Nette\Schema\Expect::bool()->default(true),
                'result'             => Nette\Schema\Expect::structure([
                    'formatDateTime' => Nette\Schema\Expect::string(),
                    'formatJson'     => Expect::anyOf('string', 'object', 'array')->default('array'),
                ])->castTo('array'),
                'substitutes'        => Nette\Schema\Expect::arrayOf(Expect::string()),
                'onConnect'          => Nette\Schema\Expect::listOf(Expect::string()),
                'flags'              => Nette\Schema\Expect::int(),
                'username'           => Nette\Schema\Expect::string()->before(function ($value) {
                    return Strings::startsWith($value, 'ini://') ? \ini_get(\substr($value, 6)) : $value;
                }),
                'password'          => Nette\Schema\Expect::string()->nullable()
                    ->before(function ($value) {
                        return Strings::startsWith($value, 'ini://') ? \ini_get(\substr($value, 6)) : $value;
                    }),
                'host'              => Nette\Schema\Expect::string()
                    ->before(function ($value) {
                        return Strings::startsWith($value, 'ini://') ? \ini_get(\substr($value, 6)) : $value;
                    }),
                'port'              => Nette\Schema\Expect::int()->nullable()
                    ->before(function ($value) {
                        return Strings::startsWith($value, 'ini://') ? \ini_get(\substr($value, 6)) : $value;
                    }),
                'dsn'               => Nette\Schema\Expect::string(),
                'database'          => Nette\Schema\Expect::string(),
                'driver'            => Nette\Schema\Expect::anyOf(...[
                    Expect::string()->assert('class_exists'),
                    'firebird', 'dummy', 'mysqli', 'pdo', 'odbc', 'oracle', 'postgre', 'sqlite3', 'sqlsrv',
                ])->default('mysqli'),
            ])->otherItems('array')->castTo('array'),
            'profiler' => Nette\Schema\Expect::bool()->default(true),
            'scanDirs' => Nette\Schema\Expect::list()->default([$this->scanDirs])->before(function ($value) {
                return \is_string($value) ? [$value] : $value;
            }),
            'logFile' => Nette\Schema\Expect::string()->assert('file_exists'),
        ])->castTo('array');
    }

    /**
     * {@inheritdoc}
     */
    public function loadConfiguration(): void
    {
        $container = $this->getContainerBuilder();
        $index     = 1;
        $flags     = 0;

        if (!\class_exists('LeanMapper\Repository')) {
            return;
        }

        $repositories = $this->findClasses($this->getFromConfig('scanDirs'), 'LeanMapper\Repository');

        foreach ($repositories as $repositoryClass) {
            $container->register($this->prefix('table.' . $index++), $repositoryClass);
        }

        $container->register($this->prefix('mapper'), 'LeanMapper\DefaultMapper');
        $container->register($this->prefix('entityFactory'), 'LeanMapper\DefaultEntityFactory');

        $connection = $container->register($this->prefix('connection'), 'LeanMapper\Connection')
            ->setArguments([$this->getFromConfig('connection')]);

        if (!empty($this->getFromConfig('connection.flags'))) {
            foreach ($this->getFromConfig('connection.flags') as $flag) {
                $flags |= \constant($flag);
            }

            $this->config['connection']['flags'] = $flags;
        }

        if (\class_exists('Tracy\Debugger') && $container->getParameter('debugMode') && $this->getFromConfig('profiler')) {
            $panel = $container->register($this->prefix('panel'), 'Dibi\Bridges\Tracy\Panel')
                ->setArguments([true, Event::ALL]);

            $connection->addSetup([$panel, 'register'], [$connection]);

            if (null !== $this->getFromConfig('logFile')) {
                $fileLogger = $container->register($this->prefix('fileLogger'), 'Dibi\Loggers\FileLogger')
                    ->setArguments([$this->getFromConfig('logFile')]);

                $connection->addSetup('?->onEvent[] = ?', ['@self', [$fileLogger, 'logEvent']]);
            }
        }
    }
}
