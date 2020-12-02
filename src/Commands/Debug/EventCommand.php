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

namespace Biurad\Framework\Commands\Debug;

use Biurad\Events\TraceableEventDispatcher;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class EventCommand extends Command
{
    public static $defaultName = 'debug:events';

    /** @var EventsDispatcherInterface */
    private $dispatcher;

    /** @var Table */
    private $table;

    /**
     * @param EventDispatcherInterface $dispatcher
     */
    public function __construct(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setDescription('List application events')
            ->setHelp(
                <<<'EOT'
The <info>%command.name%</info> command returns lists of events in applicatiom.

Any time you add a new event or annotated class, remember to run "cache:flush"
command, even if you want to commit your app to production.
EOT
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->table = new Table($output);
        $dispatcher  = clone $this->dispatcher;

        if (!$dispatcher instanceof TraceableEventDispatcher) {
            return 1;
        }

        $grid = $this->table->setHeaders(['Name:', 'Time Laps:']);

        foreach ($dispatcher->getEventsLogs() as $debug) {
            $grid->addRow([$debug['event'], $debug['duration']]);
        }

        $grid->render();

        return 0;
    }
}
