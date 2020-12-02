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

namespace Biurad\Framework\Commands\Server;

use Biurad\Framework\Commands\WebServer;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Runs a local web server in a background process.
 *
 * @author Christian Flothmann <christian.flothmann@xabbuh.de>
 */
class StartCommand extends Command
{
    protected static $defaultName = 'server:start';

    private $documentRoot;

    private $environment;

    private $io;

    public function __construct(string $documentRoot = null, string $environment = null)
    {
        $this->documentRoot = $documentRoot;
        $this->environment  = $environment;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setDefinition([
                new InputArgument('addressport', InputArgument::OPTIONAL, 'The address to listen to (can be address:port, address, or port)'),
                new InputOption('docroot', 'd', InputOption::VALUE_REQUIRED, 'Document root'),
                new InputOption('router', 'r', InputOption::VALUE_REQUIRED, 'Path to custom router script'),
                new InputOption('pidfile', null, InputOption::VALUE_REQUIRED, 'PID file'),
            ])
            ->setDescription('Starts a local web server in the background')
            ->setHelp(
                <<<'EOF'
The <info>%command.name%</info> command runs a local web server: By default, the server
listens on <comment>127.0.0.1</> address and the port number is automatically selected
as the first free port starting from <comment>8000</>:

  <info>php %command.full_name%</info>

The server is run in the background and you can keep executing other commands.
Execute <comment>server:stop</> to stop it.

Change the default address and port by passing them as an argument:

  <info>php %command.full_name% 127.0.0.1:8080</info>

Use the <info>--docroot</info> option to change the default docroot directory:

  <info>php %command.full_name% --docroot=htdocs/</info>

Specify your own router script via the <info>--router</info> option:

  <info>php %command.full_name% --router=app/config/router.php</info>

See also: http://www.php.net/manual/en/features.commandline.webserver.php
EOF
            )
        ;
    }

    /**
     * This optional method is the first one executed for a command after configure()
     * and is useful to initialize properties based on the input arguments and options.
     */
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        // SymfonyStyle is an optional feature that Symfony provides so you can
        // apply a consistent look to the commands of your application.
        // See https://symfony.com/doc/current/console/style.html
        $this->io = new SymfonyStyle($input, $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output);
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!\extension_loaded('pcntl')) {
            $this->io->error([
                'This command needs the pcntl extension to run.',
                'You can either install it or use the "server:run" command instead.',
            ]);

            if ($this->io->confirm('Do you want to execute <info>server:run</info> immediately?', false)) {
                return $this->getApplication()->find('server:run')->run($input, $output);
            }

            return 1;
        }

        if (null === $documentRoot = $input->getOption('docroot')) {
            if (!$this->documentRoot) {
                $this->io->error('The document root directory must be either passed as first argument of the constructor or through the "docroot" input option.');

                return 1;
            }
            $documentRoot = $this->documentRoot;
        }

        if (!$env = $this->environment) {
            if ($input->hasOption('env') && !$env = $input->getOption('env')) {
                $this->io->error('The environment must be either passed as second argument of the constructor or through the "--env" input option.');

                return 1;
            }
            $this->io->error('The environment must be passed as second argument of the constructor.');

            return 1;
        }

        if ('production' === $env) {
            $this->io->error('Running this server in production environment is NOT recommended!');

            return 1;
        }

        try {
            $server = new WebServer\WebServer();

            if ($server->isRunning($input->getOption('pidfile'))) {
                $this->io->error(\sprintf('The web server has already been started. It is currently listening on http://%s. Please stop the web server before you try to start it again.', $server->getAddress($input->getOption('pidfile'))));

                return 1;
            }

            $config = new WebServer\WebServerConfig($documentRoot, $env, $input->getArgument('addressport'), $input->getOption('router'));

            if (WebServer\WebServer::STARTED === $server->start($config, $input->getOption('pidfile'))) {
                $message = \sprintf('Server listening on http://%s', $config->getAddress());

                if ('' !== $displayAddress = $config->getDisplayAddress()) {
                    $message = \sprintf('Server listening on all interfaces, port %s -- see http://%s', $config->getPort(), $displayAddress);
                }
                $this->io->success($message);

                if (\ini_get('xdebug.profiler_enable_trigger')) {
                    $this->io->comment('Xdebug profiler trigger enabled.');
                }
            }
        } catch (Exception $e) {
            $this->io->error($e->getMessage());

            return 1;
        }

        return 0;
    }
}
