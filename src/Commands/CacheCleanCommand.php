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

use Doctrine\Common\Cache\Cache as DoctrineCache;
use Doctrine\Common\Cache\FlushableCache;
use Nette\Utils\FileSystem;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

/**
 * Remove every file located in cache directory and
 * clean doctrine cache.
 *
 * @final
 */
class CacheCleanCommand extends Command
{
    public static $defaultName = 'app:clean';

    /** @var DoctrineCache|FlushableCache */
    private $caching;

    /** @var string */
    private $cacheDirectory;

    /** @var string */
    private $logsDirectory;

    public function __construct(DoctrineCache $cache, string $cacheDirectory, string $logsDirectory)
    {
        $this->caching        = $cache;
        $this->cacheDirectory = $cacheDirectory;
        $this->logsDirectory  = $logsDirectory;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Clean application runtime cache')
            ->setHelp(
                <<<'EOT'
The <info>%command.name%</info> command clean caches in applicatiom.
Adding a --logs option will clear logs in cache folder.

Any time you change directories, expecially in templating. Remember to always
run this command, even if you want to commit your app to production.
EOT
            )
            ->addOption('logs', null, InputOption::VALUE_NONE, 'logs will be cleared from cache directory')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $logsDirectory  = $input->getOption('logs') ? $this->logsDirectory : null;

        if (!\file_exists($this->cacheDirectory)) {
            $output->writeln('Cache directory is missing, no cache to be cleaned.');

            return 1;
        }

        if ($input->getOption('logs') && !\file_exists($logsDirectory)) {
            $output->writeln('Cache directory is missing, no cache to be cleaned.');

            return 1;
        }

        if ($output->isVerbose()) {
            $output->writeln('<info>Cleaning application cache:</info>');
            $output->writeln('');
        }

        // Cleaning Doctrine Cache...
        if (true !== $this->caching->flushAll()) {
            $output->writeln('<info>Failed cleaning doctrine cache:</info>');
        }

        foreach (\array_filter([$this->cacheDirectory, $logsDirectory]) as $cacheDirectory) {
            /** @var RecursiveIteratorIterator|SplFileInfo[] $iterator */
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($cacheDirectory),
                RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach (@$iterator as $file) {
                if (!\is_file($filename = $file->getPathName())) {
                    continue;
                }

                try {
                    FileSystem::delete($filename);
                } catch (Throwable $e) {
                    $output->writeln(
                        \sprintf(
                            "<fg=red>[errored]</fg=red> `%s`: <fg=red>%s</fg=red>\n",
                            $cacheDirectory . '/' . $filename,
                            $e->getMessage()
                        )
                    );

                    continue;
                }

                if ($output->isVerbose()) {
                    $output->write(
                        \sprintf(
                            "<fg=green>[deleted]</fg=green> `%s`\n",
                            $cacheDirectory . '/' . $filename
                        )
                    );
                }
            }
        }

        (new SymfonyStyle($input, $output))->success('Runtime cache has been cleared.');

        return 0;
    }
}
