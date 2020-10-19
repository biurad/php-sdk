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

namespace Biurad\Framework\Commands;

use Exception;
use Spiral\Database\Config\DatabaseConfig;
use Spiral\Database\Config\DatabasePartial;
use Spiral\Database\DatabaseProviderInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * List of every configured database, it's tables and count of records.
 *
 * @final
 */
class SpiralDbListCommand extends Command
{
    /**
     * No information available placeholder.
     */
    private const SKIP = '<comment>---</comment>';

    protected static $defaultName = 'spiral:db:list';

    /** @var DatabaseProviderInterface */
    private $factory;

    /** @var DatabaseConfig */
    private $config;

    /** @var SymfonyStyle */
    private $io;

    /** @var Table */
    private $table;

    public function __construct(DatabaseConfig $config, DatabaseProviderInterface $dbal)
    {
        $this->factory = $dbal;
        $this->config  = $config;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setDefinition([
                new InputArgument('db', InputArgument::OPTIONAL, 'Database name'),
            ])
            ->setDescription('Get list of available databases, their tables and records count')
            ->setHelp(
                <<<EOT
The <info>%command.name%</info> command list the default connections databases:

    <info>php %command.full_name%</info>

You can also optionally specify the name of a database name to view it's connection and tables:

    <info>php %command.full_name% migrations</info>
EOT
            )
        ;
    }

    /**
     * This optional method is the first one executed for a command after configure()
     * and is useful to initialize properties based on the input arguments and options.
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        // SymfonyStyle is an optional feature that Symfony provides so you can
        // apply a consistent look to the commands of your application.
        // See https://symfony.com/doc/current/console/style.html
        $this->io    = new SymfonyStyle($input, $output);
        $this->table = new Table($output);
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        //Every available database
        $databases = $this->config->getDatabases();

        if ($input->getArgument('db')) {
            $databases = [$input->getArgument('db')];
        }

        if (empty($databases)) {
            $this->io->error('No databases found.');

            return 1;
        }

        $grid = $this->table->setHeaders([
            'Name (ID):',
            'Database:',
            'Driver:',
            'Prefix:',
            'Status:',
            'Tables:',
            'Count Records:',
        ]);

        foreach ($databases as $database) {
            if ($database instanceof DatabasePartial) {
                $database = $database->getName();
            }

            $database = $this->factory->database($database);
            $driver   = $database->getDriver();

            $source = $driver->getSource();

            if (\is_file($driver->getSource())) {
                $source = \basename($driver->getSource());
            }

            $header = [
                $database->getName(), $source,
                $driver->getType(),
                $database->getPrefix() ?: self::SKIP,
            ];

            try {
                $driver->connect();
            } catch (Exception $exception) {
                $grid->addRow(\array_merge($header, [
                    "<fg=red>{$exception->getMessage()}</fg=red>",
                    self::SKIP,
                    self::SKIP,
                ]));

                if ($database->getName() !== \end($databases)) {
                    $grid->addRow(new TableSeparator());
                }

                continue;
            }

            $header[] = '<info>connected</info>';

            foreach ($database->getTables() as $table) {
                $grid->addRow(\array_merge(
                    $header,
                    [$table->getName(), \number_format($table->count())]
                ));
                $header = ['', '', '', '', ''];
            }

            $header[1] && $grid->addRow(\array_merge($header, ['no tables', 'no records']));

            if ($database->getName() !== \end($databases)) {
                $grid->addRow(new TableSeparator());
            }
        }

        $grid->render();

        return 0;
    }
}
