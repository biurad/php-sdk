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

use Biurad\Framework\SpiralMigrator;
use Spiral\Migrations\Config\MigrationConfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

abstract class SpiralMigrateCommand extends Command
{
    /** @var SpiralMigrator */
    protected $migrator;

    /** @var MigrationConfig */
    protected $config;

    /**
     * @param SpiralMigrator  $migrator
     * @param MigrationConfig $config
     */
    public function __construct(SpiralMigrator $migrator, MigrationConfig $config)
    {
        $this->migrator = $migrator;
        $this->config   = $config;

        parent::__construct();
    }

    /**
     * Return's default Description.
     *
     * @return string
     */
    abstract protected function defineDescription(): string;

    /**
     * Return's default Option for command.
     *
     * @return array
     */
    protected function defineOption(): array
    {
        return [];
    }

    /**
     * Return default Options.
     *
     * @return array
     */
    protected function defineOptions(): array
    {
        return \array_merge($this->defineOption(), [
            new InputOption('force', 's', InputOption::VALUE_NONE, 'Skip safe environment check'),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setDefinition($this->defineOptions())
            ->setDescription($this->defineDescription())
        ;
    }

    /**
     * @param OutputInterface $output
     *
     * @return bool
     */
    protected function verifyConfigured(OutputInterface $output): bool
    {
        if (!$this->migrator->isConfigured()) {
            $output->writeln('');
            $output->writeln(
                "<fg=red>Migrations are not configured yet, run '<info>migrate:init</info>' first.</fg=red>"
            );

            return false;
        }

        return true;
    }

    /**
     * Check if current environment is safe to run migration.
     *
     * @param InputInterface $input
     * @param SymfonyStyle   $io
     *
     * @return bool
     */
    protected function verifyEnvironment(InputInterface $input, SymfonyStyle $io): bool
    {
        if ($input->getOption('force') || $this->config->isSafe()) {
            //Safe to run
            return true;
        }

        $io->newLine();
        $io->writeln('<fg=red>Confirmation is required to run migrations!</fg=red>');

        if (!$this->askConfirmation($io)) {
            $io->writeln('<comment>Cancelling operation...</comment>');

            return false;
        }

        return true;
    }

    /**
     * @param SymfonyStyle $io
     *
     * @return bool
     */
    protected function askConfirmation(SymfonyStyle $io): bool
    {
        $confirmation = $io->askQuestion(
            new ConfirmationQuestion('<question>Would you like to continue?</question> ')
        );

        return $confirmation;
    }
}
