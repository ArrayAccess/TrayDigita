<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Console\Command;

use ArrayAccess\TrayDigita\Exceptions\Runtime\UnsupportedRuntimeException;
use ArrayAccess\TrayDigita\Kernel\Interfaces\KernelInterface;
use ArrayAccess\TrayDigita\PossibleRoot;
use ArrayAccess\TrayDigita\Traits\Container\ContainerAllocatorTrait;
use ArrayAccess\TrayDigita\Traits\Service\TranslatorTrait;
use ArrayAccess\TrayDigita\Util\Filter\Consolidation;
use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;
use ArrayAccess\TrayDigita\Util\Filter\Ip;
use Composer\Autoload\ClassLoader;
use ReflectionClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use function array_slice;
use function array_unique;
use function array_unshift;
use function chdir;
use function defined;
use function dirname;
use function error_clear_last;
use function escapeshellcmd;
use function exec;
use function fclose;
use function file_exists;
use function fsockopen;
use function function_exists;
use function is_dir;
use function is_file;
use function is_numeric;
use function is_resource;
use function is_string;
use function rand;
use function range;
use function realpath;
use function restore_error_handler;
use function set_error_handler;
use function shuffle;
use function sleep;
use function sprintf;
use function strtolower;
use function trim;
use const DIRECTORY_SEPARATOR;
use const PHP_BINARY;
use const TD_INDEX_FILE;

/**
 * @final
 */
final class BuiltInWebServer extends Command
{
    use
        ContainerAllocatorTrait,
        TranslatorTrait;

    const BLACKLIST_PORT = [
        80 => 'HTTP',
        443 => 'HTTPS',
        3306 => 'MySQL',
        53 => 'DNS',
        22 => 'SSH',
        21 => 'FTP',
        110 => 'POP3',
        995 => 'SSL POP3',
        143 => 'IMAP',
        993 => 'SSL IMAP',
        25 => 'SMTP',
        26 => 'SMTP',
        587 => 'SSL SMTP',
        5432 => 'PostgresSQL',
        6379 => 'Redis Server',
        27017 => 'MongoDB Server',
        27018 => 'MongoDB Server',
        11211 => 'Memcached',
        111 => 'Systemd',
    ];

    protected function configure(): void
    {
        $this
            ->setName('app:server')
            ->setAliases(['server'])
            ->setDescription(
                $this->translateContext(
                    'Create temporary php builtin web server.',
                    'console'
                )
            )->setDefinition([
                new InputOption(
                    'port',
                    'p',
                    InputOption::VALUE_OPTIONAL,
                    $this->translateContext(
                        'Port number that used for listening server',
                        'console'
                    ),
                    'auto'
                ),
                new InputOption(
                    'host',
                    'H',
                    InputOption::VALUE_OPTIONAL,
                    $this->translateContext(
                        'Host that used to listening server.',
                        'console'
                    ),
                    '127.0.0.1',
                    [
                        '127.0.0.1',
                        'localhost',
                        '0.0.0.0'
                    ]
                ),
                new InputOption(
                    'index-file',
                    'i',
                    InputOption::VALUE_OPTIONAL,
                    $this->translateContext('Public index.php file.', 'console')
                )
            ])->setHelp(
                sprintf(
                    $this->translateContext(
                        "The %s help you to create temporary php builtin web server like:\n\n"
                        . "    %s\n\n"
                        . "You can use %s\n\n"
                        . "Host accept local %s\n"
                        . "Port accept range between %s",
                        'console'
                    ),
                    '<info>%command.name%</info>',
                    '<info>php %command.full_name% --host=the_host --port=numeric_port</info>',
                    sprintf(
                        '<info>cd /path/to/public && php -S [(string) hostname/IP]:[(integer) %s] index.php</info>',
                        $this->translateContext('port number', 'console')
                    ),
                    sprintf(
                        '<comment>IPv4 & localhost</comment> [%s: <comment>127.0.0.1</comment>]',
                        $this->translateContext('default', 'console')
                    ),
                    sprintf(
                        '<comment>1024 - 49151</comment> [%s: <comment>8000 or auto</comment>]',
                        $this->translateContext('default', 'console')
                    )
                )
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        if (!Consolidation::isCli()) {
            throw new UnsupportedRuntimeException(
                'Application should be run in CLI mode'
            );
        }

        $port = strtolower(trim($input->getOption('port')??''));
        $port = $port?:'auto';

        if ($port !== 'auto') {
            if (!is_numeric($port) || str_contains($port, '.')) {
                $output->writeln('');
                $output->writeln(sprintf(
                    '<error>%s</error>',
                    sprintf(
                        $this->translateContext('Command %s Error!', 'console'),
                        $this->getName()
                    )
                ));
                $output->writeln(
                    $this->translateContext(
                        'Port is Invalid! option port must be numeric.',
                        'console'
                    )
                );
                return self::INVALID;
            }
            $port = (int) $port;
            if (isset(self::BLACKLIST_PORT[$port])) {
                $output->writeln('');
                $output->writeln(sprintf(
                    '<error>%s</error>',
                    sprintf(
                        $this->translateContext('Command %s Error!', 'console'),
                        $this->getName()
                    )
                ));
                $output->writeln(
                    sprintf(
                        $this->translateContext(
                            'Port is Invalid! option port should not use: %s',
                            'console'
                        ),
                        sprintf(
                            '<info>%s</info>',
                            $port
                        )
                    )
                );
                $output->writeln(
                    sprintf(
                        $this->translateContext(
                            'Typically this port used by %s application.',
                            'console'
                        ),
                        sprintf(
                            '<options=bold>%s</>',
                            self::BLACKLIST_PORT[$port]
                        )
                    )
                );
                return self::INVALID;
            }
            if ($port < 1024 || $port > 49151) {
                $output->writeln('');
                $output->writeln(sprintf(
                    '<error>%s</error>',
                    sprintf(
                        $this->translateContext('Command %s Error!', 'console'),
                        $this->getName()
                    )
                ));
                $output->writeln(
                    sprintf(
                        $this->translateContext(
                            'Port is out of range! option port must be between: %s to %s',
                            'console'
                        ),
                        sprintf(
                            '<info>%d</info>',
                            1024
                        ),
                        sprintf(
                            '<info>%d</info>',
                            49151
                        )
                    )
                );
                return self::INVALID;
            }
        }

        $host = $input->getOption('host');
        $host = strtolower(trim($host??'')?:'127.0.0.1');
        $isIp = Ip::isValidIpv4($host);
        $isLocal = $isIp && Ip::isLocalIP($host);
        if (!$isIp && $host !== 'localhost') {
            $output->writeln('');
            $output->writeln(sprintf(
                '<error>%s</error>',
                sprintf(
                    $this->translateContext('Command %s Error!', 'console'),
                    $this->getName()
                )
            ));
            $output->writeln(
                $this->translateContext(
                    'Option host is invalid. Host only accept "localhost" and local IP address.',
                    'console'
                )
            );
            return self::INVALID;
        }
        if ($isIp && !$isLocal) {
            $output->writeln('');
            $output->writeln(sprintf(
                '<error>%s</error>',
                sprintf(
                    $this->translateContext('Command %s Error!', 'console'),
                    $this->getName()
                )
            ));
            $output->writeln(
                sprintf(
                    $this->translateContext(
                        'Option host is invalid. IP %s is not a local IP address.',
                        'console'
                    ),
                    $host
                )
            );
            return self::INVALID;
        }

        return $this->doProcess($host, $port, $input, $output);
    }

    private ?string $rootDirectory = null;

    private function getRootDirectory() : string
    {
        if ($this->rootDirectory) {
            return $this->rootDirectory;
        }
        $this->rootDirectory = ContainerHelper::use(KernelInterface::class, $this->getContainer())
            ?->getRootDirectory()??PossibleRoot::getPossibleRootDirectory();
        if ($this->rootDirectory && is_dir($this->rootDirectory)) {
            return $this->rootDirectory = realpath($this->rootDirectory)?:$this->rootDirectory;
        }
        $ref = new ReflectionClass(ClassLoader::class);
        return $this->rootDirectory = dirname($ref->getFileName(), 3);
    }

    private function getPublicFile() : string
    {
        return $this->getRootDirectory()
            . DIRECTORY_SEPARATOR
            . 'public'
            . DIRECTORY_SEPARATOR
            . 'index.php';
    }

    protected function doProcess(
        string $host,
        int|string $port,
        InputInterface $input,
        OutputInterface $output
    ) : int {
        if (defined('TD_INDEX_FILE')) {
            $publicFile = TD_INDEX_FILE;
        }

        $index = $input->getOption('index-file');
        $publicFile ??= $index?:null;
        $publicFile = is_string($publicFile) ? realpath($publicFile) : null;
        if (!$publicFile || !is_file($publicFile)) {
            $publicFile = $this->getPublicFile();
        }

        if (!$publicFile || !is_file($publicFile)) {
            $output->writeln('');
            $output->writeln(sprintf(
                '<error>%s</error>',
                sprintf(
                    $this->translateContext('Command %s Error!', 'console'),
                    $this->getName()
                )
            ));
            $output->writeln(
                $this->translateContext(
                    'Could not detect public root file.',
                    'console'
                )
            );
            return self::FAILURE;
        }
        if (!str_starts_with($publicFile, $this->getRootDirectory())) {
            $output->writeln('');
            $output->writeln(sprintf(
                '<error>%s</error>',
                sprintf(
                    $this->translateContext('Command %s Error!', 'console'),
                    $this->getName()
                )
            ));
            $output->writeln(
                $this->translateContext(
                    'Public root file is not in application directory!',
                    'console'
                )
            );
            return self::INVALID;
        }
        if (!defined('PHP_BINARY')) {
            $output->writeln('');
            $output->writeln(sprintf(
                '<error>%s</error>',
                sprintf(
                    $this->translateContext('Command %s Error!', 'console'),
                    $this->getName()
                )
            ));
            $output->writeln(
                $this->translateContext(
                    'Could not detect php binary executable file.',
                    'console'
                )
            );
            return self::FAILURE;
        }
        if (!function_exists('exec')) {
            $output->writeln('');
            $output->writeln(sprintf(
                '<error>%s</error>',
                sprintf(
                    $this->translateContext('Command %s Error!', 'console'),
                    $this->getName()
                )
            ));
            $output->writeln(
                $this->translateContext('Function [exec] is not exist', 'console')
            );
            return self::FAILURE;
        }
        set_error_handler(fn () => error_clear_last());
        $ports = $port === 'auto' ? range(8000, 9000) : [$port];
        shuffle($ports);
        array_unshift(
            $ports,
            8000,
            8080
        );

        $ports = array_slice($ports, 0, rand(50, 100));
        $ports = array_unique($ports);

        $io = new SymfonyStyle($input, $output);
        $progressBar = $io->createProgressBar();
        $progressBar->setMaxSteps(count($ports));
        $progressBar->setEmptyBarCharacter('░'); // light shade character \u2591
        $progressBar->setProgressCharacter('');
        $progressBar->setBarCharacter('▓'); // dark shade character \u2593
        $progressBar->setFormat(
            sprintf(
                '%s [%%message%%] [%%bar%%] %%elapsed:6s%%',
                $this->translateContext('Checking', 'console')
            )
        );
        $usedPort = null;
        foreach ($ports as $p) {
            $progressBar->setMessage(
                sprintf(
                    "%s : $p",
                    $this->translateContext('Port', 'console')
                )
            );
            $progressBar->advance();
            sleep(1);
            $usedPort = $p;
            $serverConn = fsockopen(
                $host,
                $p,
                $errorCode,
                $errorMessage,
                3
            );
            if (is_resource($serverConn)) {
                $usedPort = null;
                fclose($serverConn);
                continue;
            }
            break;
        }
        $progressBar->finish();
        restore_error_handler();
        $progressBar->clear();
        if ($usedPort === null) {
            $output->writeln('');
            $output->writeln(sprintf(
                '<error>%s</error>',
                sprintf(
                    $this->translateContext('Command %s Error!', 'console'),
                    $this->getName()
                )
            ));
            $output->writeln(
                $port === 'auto'
                    ? sprintf(
                        sprintf(
                            $this->translateContext(
                                'Can not determine port that can be used after doing %s tests',
                                'console'
                            ),
                            '<comment>%d</comment>'
                        ),
                        count($ports)
                    ) : sprintf(
                        sprintf(
                            $this->translateContext(
                                'Can not listening %s with port %s',
                                'console'
                            ),
                            '<comment>%s</comment>',
                            '<comment>%d</comment>'
                        ),
                        $host,
                        $port
                    )
            );
            return self::INVALID;
        }

        $port = $usedPort;
        $output->writeln(
            $this->getApplication()->getLongVersion()
        );
        $output->writeln('');
        /** @noinspection HttpUrlsUsage */
        $output->writeln(
            sprintf(
                '<info>Listening builtin web server on : </info> http://%s:%d',
                $host,
                $port
            )
        );

        $path = escapeshellcmd($publicFile);

        $command = sprintf(
            "%s -S $host:$port '$path'",
            PHP_BINARY
        );

        $output->writeln('');
        $output->writeln('Executing command:');
        $output->writeln('');
        if (Consolidation::isUnix()) {
            $output->writeln(
                sprintf(
                    "<comment>cd %s</comment>",
                    dirname($publicFile)
                )
            );
            $output->writeln('');
        }
        $output->writeln(
            sprintf(
                '<comment>%s</comment>',
                $command
            )
        );

        $output->writeln('');
        $output->writeln(
            sprintf(
                $this->translateContext('Press %s to exit.', 'console'),
                '<options=bold>Ctrl+C</>'
            )
        );
        $output->writeln('');
        if (Consolidation::isUnix()
            && (
                !$output->isVeryVerbose()
                && ! $output->isDebug()
            )
            && file_exists('/dev/null')
        ) {
            $command .= " 2>&1 & echo $!";
            $command = "cd " . dirname($publicFile) . " && $command";
        }

        // point to public directory
        chdir(dirname($publicFile));

        exec($command, $array, $result_code);
        if ($result_code !== 0) {
            return self::FAILURE;
        } elseif (!empty($array[1])) {
            $output->writeln('');
            $output->writeln(sprintf(
                '<error>%s</error>',
                sprintf(
                    $this->translateContext('Command %s Error!', 'console'),
                    $this->getName()
                )
            ));
            $output->writeln(sprintf('<error>%s</error>', $array[1]));
            return self::FAILURE;
        }
        return self::SUCCESS;
    }
}
