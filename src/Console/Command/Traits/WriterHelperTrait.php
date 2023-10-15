<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Console\Command\Traits;

use ArrayAccess\TrayDigita\Kernel\Decorator;
use ArrayAccess\TrayDigita\Util\Filter\Consolidation;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use function max;
use function memory_get_peak_usage;
use function memory_get_usage;
use function microtime;
use function round;
use function sprintf;

trait WriterHelperTrait
{
    const MODE_SUCCESS = true;
    const MODE_WARNING = 3;
    const MODE_INFO = 4;
    const MODE_DANGER = false;

    protected string $spacing = '    ';

    protected string $successCharacters = '<fg=green;options=bold>[√]</>';
    protected string $warningCharacters = '<fg=yellow;options=bold>[!]</>';
    protected string $dangerCharacters = '<fg=red;options=bold>[X]</>';
    protected string $infoCharacters = '<fg=bright-blue;options=bold>[i]</>';

    public function getSpacing(): string
    {
        return $this->spacing;
    }

    public function getSuccessCharacters(): string
    {
        return $this->successCharacters;
    }

    public function getWarningCharacters(): string
    {
        return $this->warningCharacters;
    }

    public function getDangerCharacters(): string
    {
        return $this->dangerCharacters;
    }

    public function getInfoCharacters(): string
    {
        return $this->infoCharacters;
    }

    public function write(
        OutputInterface $output,
        string $message,
        bool|int $mode,
        int $verbosity = OutputInterface::OUTPUT_NORMAL
    ): int {

        $status = $this->getPrefixCharacter($mode);
        $output->writeln(sprintf('%s%s', $status .' ', $message), $verbosity);
        return $mode ? Command::SUCCESS : Command::FAILURE;
    }

    public function getPrefixCharacter($mode): string
    {
        return match ($mode) {
            self::MODE_SUCCESS,
            Command::SUCCESS => $this->getSuccessCharacters(),
            self::MODE_WARNING => $this->getWarningCharacters(),
            self::MODE_INFO => $this->getInfoCharacters(),
            self::MODE_DANGER,
            Command::FAILURE,
            Command::INVALID => $this->getDangerCharacters(),
            default => '',
        };
    }

    public function writeWarning(
        OutputInterface $output,
        string $message,
        int $verbosity = OutputInterface::OUTPUT_NORMAL
    ): int {
        return $this->write($output, $message, self::MODE_WARNING, $verbosity);
    }

    public function writeDanger(
        OutputInterface $output,
        string $message,
        int $verbosity = OutputInterface::OUTPUT_NORMAL
    ): int {
        return $this->write($output, $message, self::MODE_DANGER, $verbosity);
    }

    public function writeSuccess(
        OutputInterface $output,
        string $message,
        int $verbosity = OutputInterface::OUTPUT_NORMAL
    ): int {
        return $this->write($output, $message, self::MODE_SUCCESS, $verbosity);
    }

    public function writeInfo(
        OutputInterface $output,
        string $message,
        int $verbosity = OutputInterface::OUTPUT_NORMAL
    ): int {
        return $this->write($output, $message, self::MODE_INFO, $verbosity);
    }

    public function writeIndent(
        OutputInterface $output,
        string $message,
        int $verbosity = OutputInterface::OUTPUT_NORMAL,
        int|bool|null $mode = null
    ): int {
        $mode = $this->getPrefixCharacter($mode);
        $mode = $mode ? "$mode " : $mode;
        $space = $this->getSpacing();
        $output->writeln(sprintf('%s%s%s', $space, $mode, $message), $verbosity);
        return Command::SUCCESS;
    }

    public function getStartTime(): float
    {
        return Decorator::kernel()->getStartTime();
    }

    public function getStartMemory(): int
    {
        return Decorator::kernel()->getStartMemory();
    }

    public function printUsage(OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln(
            sprintf(
                $this->translateContext(
                    'Time %s secs; Memory Usage: %s; Memory Peak Usage: %s',
                    'console'
                ),
                round(microtime(true) - $this->getStartTime(), 3),
                Consolidation::sizeFormat(
                    max(memory_get_usage() - $this->getStartMemory(), 0),
                    3
                ),
                Consolidation::sizeFormat(memory_get_peak_usage(), 3)
            )
        );
        $output->writeln('');
    }
}
