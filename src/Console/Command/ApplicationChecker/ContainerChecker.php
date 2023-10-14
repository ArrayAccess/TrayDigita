<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Console\Command\ApplicationChecker;

use ArrayAccess\TrayDigita\Console\Command\Traits\WriterHelperTrait;
use ArrayAccess\TrayDigita\Container\Container;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function sprintf;

class ContainerChecker extends AbstractChecker
{
    use WriterHelperTrait;

    public function check(InputInterface $input, OutputInterface $output) : int
    {
        $container = $this->applicationCheck->getContainer();
        if (!$container) {
            $this->writeDanger(
                $output,
                $this->translate('Container object not set')
            );
            return Command::FAILURE;
        }
        $this->write(
            $output,
            sprintf(
                $this->translate('Container object is set [%s]'),
                sprintf(
                    '<info>%s</info>',
                    $container::class
                )
            ),
            true
        );
        if ($output->isVerbose()) {
            if ($container instanceof Container) {
                $output->writeln('', OutputInterface::VERBOSITY_VERY_VERBOSE);
                $this->writeIndent(
                    $output,
                    sprintf(
                        '<info>%s</info>',
                        sprintf(
                            $this->translate('Registered Container (%d)'),
                            count($container->keys())
                        ),
                    ),
                    OutputInterface::VERBOSITY_VERBOSE
                );

                foreach ($container->keys() as $key) {
                    $this->writeIndent(
                        $output,
                        "<comment>- $key</comment>",
                        OutputInterface::VERBOSITY_VERY_VERBOSE
                    );
                }

                $output->writeln('', OutputInterface::VERBOSITY_DEBUG);
                $this->writeIndent(
                    $output,
                    sprintf(
                        '<info>%s</info>',
                        sprintf(
                            $this->translate('Registered Aliases (%d)'),
                            count($container->getAliases())
                        )
                    ),
                    OutputInterface::VERBOSITY_VERBOSE
                );

                if ($output->isDebug()) {
                    foreach ($container->getAliases() as $aliasName => $alias) {
                        $this->writeIndent(
                            $output,
                            "<info>- $aliasName</info> [<comment>$alias</comment>]",
                            OutputInterface::VERBOSITY_DEBUG
                        );
                    }
                }
            }
        }

        return Command::SUCCESS;
    }
}
