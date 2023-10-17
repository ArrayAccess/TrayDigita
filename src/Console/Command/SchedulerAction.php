<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Console\Command;

use ArrayAccess\TrayDigita\Console\Command\Traits\WriterHelperTrait;
use ArrayAccess\TrayDigita\Container\Interfaces\ContainerAllocatorInterface;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerAllocatorInterface;
use ArrayAccess\TrayDigita\Exceptions\Runtime\RuntimeException;
use ArrayAccess\TrayDigita\Kernel\Decorator;
use ArrayAccess\TrayDigita\Scheduler\Abstracts\Task;
use ArrayAccess\TrayDigita\Scheduler\Runner;
use ArrayAccess\TrayDigita\Scheduler\Scheduler;
use ArrayAccess\TrayDigita\Traits\Container\ContainerAllocatorTrait;
use ArrayAccess\TrayDigita\Traits\Manager\ManagerAllocatorTrait;
use ArrayAccess\TrayDigita\Traits\Service\TranslatorTrait;
use ArrayAccess\TrayDigita\Util\Filter\Consolidation;
use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;
use ArrayAccess\TrayDigita\Util\Filter\Conversion;
use DateTime;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableCellStyle;
use Symfony\Component\Console\Helper\TableStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use function date;
use function date_default_timezone_get;
use function is_array;
use function round;
use function spl_object_hash;
use function sprintf;

class SchedulerAction extends Command implements ContainerAllocatorInterface, ManagerAllocatorInterface
{
    use ContainerAllocatorTrait,
        WriterHelperTrait,
        ManagerAllocatorTrait,
        TranslatorTrait;

    protected function configure(): void
    {
        $this
            ->setName('app:scheduler')
            ->setAliases(['run-scheduler'])
            ->setDescription(
                $this->translateContext('List or run scheduler.', 'console')
            )
            ->setDefinition([
                new InputOption(
                    'run',
                    'x',
                    InputOption::VALUE_NONE,
                    $this->translateContext('Run the pending schedulers', 'console')
                )
            ])
            ->setHelp(
                sprintf(
                    $this->translateContext(
                        'The %s help you to list & run scheduler',
                        'console'
                    ),
                    '<info>%command.name%</info>'
                )
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // no execute
        if (!Consolidation::isCli()) {
            return self::INVALID;
        }
        $scheduler = ContainerHelper::service(
            Scheduler::class,
            $this->getContainer()
        );

        if (!$scheduler instanceof Scheduler) {
            throw new RuntimeException(
                $this->translateContext(
                    'Object scheduler is not valid',
                    'console'
                )
            );
        }
        try {
            if (!$input->getOption('run')) {
                return $this->listScheduler($scheduler, $output);
            }

            return $this->runScheduler($scheduler, $input, $output);
        } finally {
            $this->printUsage($output);
        }
    }

    private function runScheduler(Scheduler $scheduler, InputInterface $input, OutputInterface $output): int
    {
        $interactive = $input->isInteractive() && ! $output->isQuiet();
        $table = new Table($output);
        $table->setStyle('box');
        $table->setColumnMaxWidth(0, 40);
        $table->setColumnMaxWidth(1, 40);
        $table->setHeaders([
            $this->translateContext('Id', 'console'),
            $this->translateContext('Name', 'console'),
            $this->translateContext('Status', 'console'),
            $this->translateContext('Last Execution', 'console'),
            $this->translateContext('Execution Time', 'console'),
        ]);
        $total = 0;
        $queue = $scheduler->getQueueProcessed()['queue'];
        $wait = $this->translateContext('Waiting', 'console');
        foreach ($queue as $task) {
            $total++;
            $record = $scheduler->getRecordLoader()->getRecord($task);
            $lastExec = $this->translateContext('Unknown', 'console');
            if ($record) {
                $lastExec = date('Y-m-d H:i:s', $record->getLastExecutionTime());
            }
            $table->addRow([
                $task->getIdentity(),
                $task->getName(),
                $wait,
                $lastExec,
                $wait
            ]);
        }
        if ($total === 0) {
            $table->addRow([
                new TableCell(
                    $this->translateContext('No Schedulers Waiting For Execution', 'console'),
                    [
                        'colspan' => 5,
                        'style' => new TableCellStyle([
                            'align' => 'center',
                            'options' => 'bold'
                        ])
                    ]
                )
            ]);
            $table->render();
            return self::SUCCESS;
        }
        $table->render();
        $io = new SymfonyStyle($input, $output);
        if ($interactive) {
            $confirm = new ConfirmationQuestion(
                $this->translatePluralContext(
                    'Are you sure to execute scheduler ?',
                    'Are you sure to execute schedulers ?',
                    $total,
                    'console'
                )
            );
            $confirm->setMultiline(false);
            if (!$io->askQuestion($confirm)) {
                return self::SUCCESS;
            }
            $c = 0;
            while ($c++ < 4) {
                $this->clearLine($output);
            }
        }

        // clear
        do {
            $this->clearLine($output);
        } while ($total-- > -4);

        $manager = $scheduler->getManager();
        if (!$manager) {
            $manager = Decorator::manager();
            $scheduler->setManager($manager);
        }
        $progressBar = $io->createProgressBar();
        $progressBar->setMaxSteps($total);
        $progressBar->setEmptyBarCharacter('░'); // light shade character \u2591
        $progressBar->setProgressCharacter('');
        $progressBar->setBarCharacter('▓'); // dark shade character \u2593
        $progressBar->setFormat('<info>%current%</info> [%bar%] %elapsed:6s% [<comment>%message%</comment>]');
        $table->setRows([]);
        $manager->attach(
            'scheduler.beforeProcessing',
            function ($task, Runner $runner) use ($progressBar) {
                $progressBar->setMessage($runner->getTask()->getName());
            }
        );

        $manager->attach(
            'scheduler.afterProcessing',
            function ($task, Runner $runner, int $time) use (&$queue) {
                $task = $runner->getTask();
                $id = spl_object_hash($task);
                $status = match ($runner->getStatusCode()) {
                    Runner::STATUS_SUCCESS => $this->translateContext('Success', 'console'),
                    Runner::STATUS_EXITED  => $this->translateContext('Exited', 'console'),
                    Runner::STATUS_STOPPED => $this->translateContext('Stopped', 'console'),
                    Runner::STATUS_FAILURE => $this->translateContext('Failure', 'console'),
                    default => $this->translateContext('Unknown', 'console')
                };
                $total = $runner->getExecutionDuration();
                $queue[$id] = [
                    $task->getIdentity(),
                    $task->getName(),
                    $status,
                    date('Y-m-d H:i:s', $time),
                    $total !== null ? sprintf(
                        '%s ms',
                        round($total, 5)
                    ) : $this->translateContext('Unknown', 'console')
                ];
            }
        );

        $scheduler->run();
        $progressBar->finish();
        $progressBar->clear();
        foreach ($queue as $item) {
            if (!is_array($item)) {
                $record = $scheduler->getRecordLoader()->getRecord($item);
                $lastExec = $this->translateContext('Unknown', 'console');
                if ($record) {
                    $lastExec = date('Y-m-d H:i:s', $record->getLastExecutionTime());
                }
                $item = [
                    $item->getIdentity(),
                    $item->getName(),
                    $this->translateContext('Skipped', 'console'),
                    $lastExec,
                    $this->translateContext('Skipped', 'console'),
                ];
            }
            $table->addRow($item);
        }

        $table->render();
        return self::SUCCESS;
    }

    private function clearLine(OutputInterface $output): void
    {
        $output->write("\r\033[K\033[1A\r\033[K\r");
    }

    private function listScheduler(Scheduler $scheduler, OutputInterface $output): int
    {
        $table = new Table($output);
        $table->setStyle('box');
        $table->setColumnMaxWidth(0, 40);
        $table->setColumnMaxWidth(1, 40);
        $table->setHeaders([
            $this->translateContext('Id', 'console'),
            $this->translateContext('Name', 'console'),
            $this->translateContext('Status', 'console'),
            $this->translateContext('Last Execution', 'console'),
            $this->translateContext('Next Run Date', 'console'),
        ]);
        $total = 0;
        $inQueue = 0;
        $skippedCount = 0;
        $finishedCount = 0;
        foreach ($scheduler->getQueue() as $task) {
            $total++;
            $interval = $scheduler->getNextRunDate($task);
            $skipped  = ! $scheduler->shouldRun($task);
            $skippedCount += $skipped ? 1 : 0;
            $inQueue += !$skipped ? 1 : 0;
            $record = $scheduler->getRecordLoader()->getRecord($task);
            $lastExec = $this->translateContext('Unknown', 'console');
            if ($record) {
                $lastExec = date('Y-m-d H:i:s', $record->getLastExecutionTime());
            }
            $table->addRow([
                $task->getIdentity(),
                $task->getName(),
                $skipped
                    ? $this->translateContext('Skipped', 'console')
                    : $this->translateContext('Need To Execute', 'console'),
                $lastExec,
                date('Y-m-d H:i:s', $interval?->getTimestamp())
            ]);
        }

        foreach ($scheduler->getFinished() as $runner) {
            $finishedCount++;
            $total++;
            $task = $runner->getTask();
            $interval = $scheduler->getNextRunDate($task);
            $lastExec = date('Y-m-d H:i:s', $runner->getLastRecord()->getLastExecutionTime());
            $table->addRow([
                $task->getIdentity(),
                $task->getName(),
                $this->translateContext('Finished', 'console'),
                $lastExec,
                date('Y-m-d H:i:s', $interval?->getTimestamp())
            ]);
        }

        foreach ($scheduler->getSkipped() as $task) {
            $skippedCount++;
            $total++;
            $interval = $scheduler->getNextRunDate($task);
            $record = $scheduler->getRecordLoader()->getRecord($task);
            $lastExec = $this->translateContext('Unknown', 'console');
            if ($record) {
                $lastExec = date('Y-m-d H:i:s', $record->getLastExecutionTime());
            }
            $table->addRow([
                $task->getIdentity(),
                $task->getName(),
                $this->translateContext('Skipped', 'console'),
                $lastExec,
                date('Y-m-d H:i:s', $interval?->getTimestamp())
            ]);
        }
        if ($total === 0) {
            $table->addRow([
                new TableCell(
                    $this->translateContext('No Schedulers Registered', 'console'),
                    [
                        'colspan' => 5,
                        'style' => new TableCellStyle([
                            'align' => 'center',
                            'options' => 'bold'
                        ])
                    ]
                )
            ]);
        }
        $output->writeln(
            sprintf(
                '<info>%s</info>: %s',
                $this->translateContext(
                    'Total',
                    'console'
                ),
                $total
            )
        );
        $output->writeln(
            sprintf(
                '<info>%s</info>: %s',
                $this->translateContext('Using Timezone', 'console'),
                date_default_timezone_get()
            )
        );
        $output->writeln(
            sprintf(
                '<info>%s</info>: %s',
                $this->translateContext('Timezone Offset', 'console'),
                Conversion::convertOffsetToSQLTimezone((new DateTime())->getOffset()),
            )
        );
        $output->writeln(
            sprintf(
                '<info>%s</info>: %d',
                $this->translateContext('Skipped', 'console'),
                $skippedCount
            )
        );
        $output->writeln(
            sprintf(
                '<info>%s</info>: %d',
                $this->translateContext('Need To Execute', 'console'),
                $inQueue
            )
        );
        $output->writeln(
            sprintf(
                '<info>%s</info>: %d',
                $this->translateContext('Finished', 'console'),
                $finishedCount
            )
        );

        $output->writeln('');
        $table->render();
        return self::SUCCESS;
    }
}
