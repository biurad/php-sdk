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

use Biurad\Framework\Kernels\ConsoleKernel;
use DateTime;
use Locale;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * A console command to display information about the current installation.
 *
 * @final
 */
class AboutCommand extends Command
{
    public static $defaultName = 'about';

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Displays information about the current project')
            ->setHelp(
                <<<'EOT'
The <info>%command.name%</info> command displays information about the current BiuradPHP project.

The <info>PHP</info> section displays important configuration that could affect your application. The values might
be different between web and CLI.

The <info>Environment</info> section displays the current environment variables managed by Symfony Dotenv. It will not
be shown if no variables were found. The values might be different between web and CLI.
EOT
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $application = $this->getApplication();

        if (!$application instanceof ConsoleKernel) {
            return 1;
        }

        $container = $application->getContainer();
        $rootDir   = $container->getParameter('rootDir');
        $io        = new SymfonyStyle($input, $output);

        $rows = [
            ['<info>Biurad PHP</>'],
            new TableSeparator(),
            ['Version', $application->getVersion()],
            ['Docs', 'See https://docs.biurad.com for more info'],
            ['Copyright', 'Biurad Lap hello@biurad.com'],
            new TableSeparator(),
            ['<info>Kernel</>'],
            new TableSeparator(),
            ['Type', \get_class($container)],
            ['Environment', $input->hasOption('env') ? $input->getOption('env') : $container->getParameter('envMode')],
            ['Debug', $container->getParameter('debugMode') ? 'true' : 'false'],
            ['Cache directory', self::formatPath($container->getParameter('tempDir'), $rootDir)],
            ['Log directory', self::formatPath($container->getParameter('logDir'), $rootDir)],
            new TableSeparator(),
            ['<info>PHP</>'],
            new TableSeparator(),
            ['Version', \PHP_VERSION],
            ['Architecture', (\PHP_INT_SIZE * 8) . ' bits'],
            ['Intl locale', \class_exists('Locale', false) && Locale::getDefault() ? Locale::getDefault() : 'n/a'],
            ['Timezone', \date_default_timezone_get() . ' (<comment>' . (new DateTime())->format(DateTime::W3C) . '</>)'],
            ['OPcache', \extension_loaded('Zend OPcache') && \filter_var(\ini_get('opcache.enable'), \FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false'],
            ['APCu', \extension_loaded('apcu') && \filter_var(\ini_get('apc.enabled'), \FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false'],
            ['Xdebug', \extension_loaded('xdebug') ? 'true' : 'false'],
        ];

        if ($dotenv = self::getDotenvVars()) {
            $rows = \array_merge($rows, [
                new TableSeparator(),
                ['<info>Environment (.env)</>'],
                new TableSeparator(),
            ], \array_map(function ($value, $name) {
                return [$name, $value];
            }, $dotenv, \array_keys($dotenv)));
        }

        $io->table([], $rows);

        return 0;
    }

    private static function getDotenvVars(): array
    {
        $vars = [];

        if (!$dotenv = \getenv('SYMFONY_DOTENV_VARS')) {
            return $vars;
        }

        foreach (\explode(',', $dotenv) as $name) {
            if ('' !== $name && false !== $value = \getenv($name)) {
                $vars[$name] = $value;
            }
        }

        return $vars;
    }

    private static function formatPath(string $path, string $baseDir): string
    {
        return \preg_replace('~^' . \preg_quote($baseDir, '~') . '~', '.', $path);
    }
}
