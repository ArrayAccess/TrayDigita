<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Console\Command\ApplicationChecker;

use ArrayAccess\TrayDigita\Console\Command\Traits\WriterHelperTrait;
use ArrayAccess\TrayDigita\L10n\Translations\Interfaces\TranslatorInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use function sprintf;

class TranslatorChecker extends AbstractChecker
{
    use WriterHelperTrait;

    public function check(InputInterface $input, OutputInterface $output): int
    {
        $container = $this->applicationCheck->getContainer();
        if (!$container?->has(TranslatorInterface::class)) {
            $this->writeDanger(
                $output,
                $this->translate('Can not get translator object from container')
            );
            return Command::FAILURE;
        }
        try {
            $translator = $container->get(TranslatorInterface::class);
        } catch (Throwable) {
            $translator = null;
        }
        if (!$translator instanceof TranslatorInterface) {
            $this->writeDanger(
                $output,
                $this->translate('Translator is not valid object from container')
            );
            return Command::FAILURE;
        }

        $this->write(
            $output,
            sprintf(
                '%s [<info>%s</info>]',
                $this->translate('Translator object is set'),
                $translator::class
            ),
            true
        );
        $this->writeIndent(
            $output,
            count($translator->getAdapters()) === 0 ?
                sprintf(
                    '<comment>%s</comment>',
                    $this->translate('No Adapter Registered')
                )
                : sprintf(
                    '<info>%s</info>',
                    sprintf(
                        $this->translate('Registered Adapters (%d)'),
                        count($translator->getAdapters())
                    )
                ),
            OutputInterface::VERBOSITY_VERBOSE
        );
        foreach ($translator->getAdapters() as $adapter) {
            $this
                ->applicationCheck
                ->writeIndent(
                    $output,
                    sprintf(
                        '<info>- %s</info> [%s]',
                        $adapter->getName(),
                        $adapter::class
                    ),
                    OutputInterface::VERBOSITY_VERBOSE
                );
        }

        $this->writeIndent(
            $output,
            sprintf(
                '<info>%s</info> [<comment>%s</comment>]',
                $this->translate('Current Language'),
                $translator->getLanguage()
            ),
            OutputInterface::VERBOSITY_VERBOSE
        );
        return Command::SUCCESS;
    }
}
