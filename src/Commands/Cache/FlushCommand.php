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

namespace Biurad\Framework\Commands\Cache;

use Doctrine\Common\Cache\Cache as DoctrineCache;
use Doctrine\Common\Cache\FlushableCache;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Clean doctrine cache.
 *
 * @final
 */
class FlushCommand extends Command
{
    public static $defaultName = 'cache:flush';

    /** @var DoctrineCache */
    private $cache;

    public function __construct(DoctrineCache $cache)
    {
        $this->cache = $cache;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Flush Doctrine cache and application runtime cache')
            ->setHelp(
                <<<'EOT'
The <info>%command.name%</info> command flushes doctrine cache and clean app caches.
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
        // Cleaning Doctrine Cache...

        if (!$this->cache instanceof FlushableCache) {
            return 1;
        }

        if (true !== $this->cache->flushAll()) {
            $output->writeln('<info>Failed cleaning doctrine cache:</info>');
        }

        return $this->getApplication()->find('cache:clean')
            ->run(new ArrayInput(['--logs' => $input->getOption('logs')]), $output);
    }
}
