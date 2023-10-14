<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Console\Command;

use ArrayAccess\TrayDigita\Exceptions\InvalidArgument\InteractiveArgumentException;
use ArrayAccess\TrayDigita\Exceptions\Runtime\UnsupportedRuntimeException;
use ArrayAccess\TrayDigita\Traits\Container\ContainerAllocatorTrait;
use ArrayAccess\TrayDigita\Traits\Service\TranslatorTrait;
use ArrayAccess\TrayDigita\Util\Filter\Consolidation;
use Composer\Autoload\ClassLoader;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;
use function basename;
use function date;
use function dirname;
use function error_clear_last;
use function fclose;
use function filemtime;
use function fopen;
use function fwrite;
use function is_dir;
use function is_resource;
use function is_string;
use function is_writable;
use function ksort;
use function md5_file;
use function mkdir;
use function restore_error_handler;
use function set_error_handler;
use function sha1_file;
use function sprintf;
use function strtolower;
use function touch;
use function trim;
use function usleep;
use const DIRECTORY_SEPARATOR;

class ChecksumGenerator extends Command
{
    use ContainerAllocatorTrait,
        TranslatorTrait;

    protected function configure(): void
    {
        $this
            ->setName('app:generate:checksums')
            ->setAliases(['generate-checksums'])
            ->setDescription(
                $this->translate(
                    'Create list of core file checksums.'
                )
            )->setDefinition([
                new InputOption(
                    'print',
                    'p',
                    InputOption::VALUE_OPTIONAL,
                    description: $this->translate(
                        'Display checksums on terminal without writing to disk'
                    ),
                    default: false,
                    suggestedValues: ['sha1', 'md5']
                ),
            ])
            ->setHelp(
                sprintf(
                    $this->translate(<<<'EOT'
The %s creating checksum files on %s directory.
EOT),
                    '<info>%command.name%</info>',
                    '<comment>checksum</comment>'
                )
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!Consolidation::isCli()) {
            throw new UnsupportedRuntimeException(
                'Application should be run in CLI mode'
            );
        }
        $srcDirectory = dirname(__DIR__, 2);
        try {
            $ref = new ReflectionClass(ClassLoader::class);
            $checksumDirectory = dirname($ref->getFileName(), 3)
                . DIRECTORY_SEPARATOR
                . 'checksums';
        } catch (Throwable) {
            $checksumDirectory = dirname($srcDirectory)
                . DIRECTORY_SEPARATOR
                . 'checksums';
        }

        $io = new SymfonyStyle($input, $output);
        // $input->setInteractive(true);
        $quiet = $input->getOption('quiet');
        $print = $input->getOption('print');
        $printOnly = $print !== false;
        $printMode = is_string($print) && strtolower($print) === 'sha1'
            ? 'sha1'
            : 'md5';
        if ($printOnly && $quiet) {
            return self::SUCCESS;
        }
        if (!$quiet && !$printOnly) {
            $output->writeln(
                sprintf(
                    $this->translate(
                        'Files will be put in directory: %s'
                    ),
                    sprintf(
                        '<comment>%s</comment>',
                        $checksumDirectory
                    )
                )
            );
            $answer = $input->isInteractive()
                ? $io->ask(
                    $this->translate('Are you sure to continue (Yes/No)?'),
                    null,
                    static function ($e) {
                        $e = !is_string($e) ? '' : $e;
                        $e = strtolower(trim($e));
                        $ask = match ($e) {
                            'yes' => true,
                            'no' => false,
                            default => null
                        };
                        if ($ask === null) {
                            throw new InteractiveArgumentException(
                                $this->translate('Please enter valid answer! (Yes / No)')
                            );
                        }
                        return $ask;
                    }
                ) : true;
            if (!$answer) {
                $output->writeln(
                    sprintf(
                        '<comment>%s</comment>',
                        $this->translate('Operation cancelled!')
                    )
                );
                return self::SUCCESS;
            }
        }


        set_error_handler(static fn() => error_clear_last());
        try {
            if (!$printOnly) {
                if (!is_dir($checksumDirectory)) {
                    mkdir($checksumDirectory, 0755, true);
                }
                if (!is_dir($checksumDirectory)
                    || !is_writable($checksumDirectory)
                ) {
                    return self::FAILURE;
                }
                touch($checksumDirectory . '/.gitkeep');
                $md5File = $checksumDirectory . DIRECTORY_SEPARATOR . 'checksums.md5sum';
                $sha1File = $checksumDirectory . DIRECTORY_SEPARATOR . 'checksums.sha1sum';
                $md5 = fopen($md5File, 'wb+');
                if (!is_resource($md5)) {
                    return self::FAILURE;
                }
                $sha1 = fopen($sha1File, 'wb+');
                if (!is_resource($sha1)) {
                    fclose($md5);
                    return self::FAILURE;
                }
            }
        } finally {
            restore_error_handler();
        }

        $start = true;
        $iterator = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
            $srcDirectory,
            FilesystemIterator::CURRENT_AS_SELF
            |FilesystemIterator::SKIP_DOTS
            |FilesystemIterator::UNIX_PATHS
        )) as $directory) {
            /**
             * @var RecursiveDirectoryIterator $directory
             */
            if ($directory->isDot() || !$directory->isFile()) {
                continue;
            }
            $iterator[
            basename($srcDirectory) . DIRECTORY_SEPARATOR . $directory->getSubPathname()
            ] = $directory->getRealPath();
        }
        ksort($iterator);

        $progressBar = !$printOnly ? $io->createProgressBar() : null;
        $progressBar?->setMaxSteps(count($iterator));
        $progressBar?->setEmptyBarCharacter('░'); // light shade character \u2591
        $progressBar?->setProgressCharacter('');
        $progressBar?->setBarCharacter('▓'); // dark shade character \u2593
        $progressBar?->setFormat('<info>%current%</info> [%bar%] %elapsed:6s% [<comment>%message%</comment>]');
        foreach ($iterator as $subPathName => $realPath) {
            $linefeed = $start ? "" : "\r\n";
            $start = false;
            $progressBar?->setMessage($subPathName);
            $progressBar?->advance();
            $sha1Sum = sha1_file($realPath);
            $md5Sum = md5_file($realPath);
            if (isset($md5) && isset($sha1)) {
                fwrite(
                    $md5,
                    sprintf(
                        "%s%s %s",
                        $linefeed,
                        $md5Sum,
                        $subPathName
                    )
                );
                fwrite(
                    $sha1,
                    sprintf(
                        "%s%s %s",
                        $linefeed,
                        $sha1Sum,
                        $subPathName
                    )
                );

                !$quiet && usleep(5000);
                continue;
            }
            $hash = $printMode === 'sha1' ? $sha1Sum : $md5Sum;
            $output->writeln(
                sprintf(
                    '<info>[%s]</info> <comment>%s</comment> %s',
                    date('c', filemtime($realPath)),
                    $hash,
                    $subPathName
                )
            );
        }
        $progressBar?->finish();
        $progressBar?->clear();
        isset($md5) && fclose($md5);
        isset($sha1) && fclose($sha1);
        $output->writeln(
            sprintf(
                '%s calculate %s files',
                sprintf(
                    '<info>%s</info>',
                    $this->translate('Done!')
                ),
                sprintf(
                    '<comment>%d</comment>',
                    count($iterator)
                )
            )
        );
        if (!$printOnly) {
            $output->writeln(
                sprintf(
                    '%s: <comment>%s</comment>',
                    $this->translate('Source'),
                    $srcDirectory
                )
            );
            $output->writeln(
                sprintf(
                    'MD5   : <comment>%s</comment>',
                    $md5File
                )
            );
            $output->writeln(
                sprintf(
                    'SHA1  : <comment>%s</comment>',
                    $sha1File
                )
            );
        }
        return self::SUCCESS;
    }
}
