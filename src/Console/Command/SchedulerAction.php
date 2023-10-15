<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Console\Command;

use ArrayAccess\TrayDigita\Console\Command\Traits\WriterHelperTrait;
use ArrayAccess\TrayDigita\Container\Interfaces\ContainerAllocatorInterface;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerAllocatorInterface;
use ArrayAccess\TrayDigita\Exceptions\Runtime\RuntimeException;
use ArrayAccess\TrayDigita\Scheduler\Abstracts\Task;
use ArrayAccess\TrayDigita\Scheduler\Scheduler;
use ArrayAccess\TrayDigita\Traits\Container\ContainerAllocatorTrait;
use ArrayAccess\TrayDigita\Traits\Manager\ManagerAllocatorTrait;
use ArrayAccess\TrayDigita\Traits\Service\TranslatorTrait;
use ArrayAccess\TrayDigita\Util\Filter\Consolidation;
use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use function is_int;
use function max;
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
        $isRun = $input->getOption('run');
        $isDirect = !$input->isInteractive() || $output->isQuiet();
        if (!$isRun && $isDirect) {
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

        $queue   = [];
        $skipped = [];
        foreach ($scheduler->getQueue() as $key => $task) {
            if ($scheduler->shouldRun($task)) {
                $queue[$key] = $task;
            } else {
                $skipped[$key] = $task;
            }
        }

        try {
            $countQueue = count($queue);
            $countSkipped = count($skipped);
            if ($isRun) {
                if ($countQueue === 0) {
                    $output->writeln(
                        sprintf(
                            '<info>%s</info>',
                            $this->translateContext('No task in queue', 'console')
                        )
                    );
                    return self::SUCCESS;
                }
                $output->writeln(
                    sprintf(
                        '<info>%s</info>',
                        sprintf(
                            $this->translatePluralContext(
                                'Executing %s task',
                                'Executing %s tasks',
                                $countQueue,
                                'console'
                            ),
                            $countQueue
                        )
                    )
                );
                $scheduler->run();
                return self::SUCCESS;
            }

            $this->write(
                $output,
                sprintf(
                    '<info>%s</info> %s',
                    $countQueue,
                    $this->translatePluralContext(
                        'scheduler in queue to run',
                        'schedulers in queue to run',
                        $countQueue,
                        'console'
                    )
                ),
                $countQueue === 0 ? self::MODE_SUCCESS : self::MODE_WARNING
            );
            /** @noinspection DuplicatedCode */
            foreach ($queue as $task) {
                $interval = $task->getInterval();
                if (is_int($interval)) {
                    $every = sprintf(
                        $this->translateContext('run every %s seconds', 'console'),
                        sprintf(
                            '<comment>%s</comment>',
                            max($interval, Task::MINIMUM_INTERVAL_TIME)
                        )
                    );
                } else {
                    $every = sprintf(
                        $this->translateContext('next executed time %s', 'console'),
                        sprintf(
                            '[<comment>%s</comment>]',
                            $interval->getNextRunDate()->format('Y-m-d H:i:s e')
                        )
                    );
                }
                $message = sprintf(
                    '<info>%s</info> %s',
                    $task->getName(),
                    $every
                );
                $this->writeIndent(
                    $output,
                    $message,
                    mode: self::MODE_INFO
                );
            }

            $this->write(
                $output,
                sprintf(
                    '<info>%s</info> %s',
                    $countSkipped,
                    $this->translatePluralContext(
                        'scheduler skipped',
                        'schedulers skipped',
                        $countSkipped,
                        'console'
                    )
                ),
                self::MODE_SUCCESS
            );
            /** @noinspection DuplicatedCode */
            foreach ($skipped as $task) {
                $interval = $task->getInterval();
                if (is_int($interval)) {
                    $every = sprintf(
                        $this->translateContext('run every %s seconds', 'console'),
                        sprintf(
                            '<comment>%s</comment>',
                            max($interval, Task::MINIMUM_INTERVAL_TIME)
                        )
                    );
                } else {
                    $every = sprintf(
                        $this->translateContext('next executed time %s', 'console'),
                        sprintf(
                            '[<comment>%s</comment>]',
                            $interval->getNextRunDate()->format('Y-m-d H:i:s e')
                        )
                    );
                }

                $message = sprintf(
                    '<info>%s</info> %s',
                    $task->getName(),
                    $every
                );
                $this->writeIndent(
                    $output,
                    $message,
                    mode: self::MODE_INFO
                );
            }
            return self::SUCCESS;
        } finally {
            $this->printUsage($output);
        }
    }
}
