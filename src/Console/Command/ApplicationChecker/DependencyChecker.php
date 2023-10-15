<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Console\Command\ApplicationChecker;

use ArrayAccess\TrayDigita\Console\Command\Traits\WriterHelperTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function curl_version;
use function defined;
use function extension_loaded;
use function function_exists;
use function phpversion;
use function sprintf;
use function str_replace;
use function strtolower;
use function ucwords;
use const OPENSSL_VERSION_TEXT;
use const PHP_EXTRA_VERSION;
use const PHP_VERSION;
use const PHP_VERSION_ID;

class DependencyChecker extends AbstractChecker
{
    use WriterHelperTrait;

    public function check(InputInterface $input, OutputInterface $output): int
    {
        if ($output->isQuiet()) {
            return Command::SUCCESS;
        }
        $this->write(
            $output,
            sprintf(
                '%s <comment>%s%s</comment>',
                $this->translateContext('Php version', 'console'),
                PHP_VERSION,
                PHP_EXTRA_VERSION ? '-'. PHP_EXTRA_VERSION : ''
            ),
            PHP_VERSION_ID >= 80200
        );
        $requiredExtension = [
            'cURL' => function_exists('curl_version')
                ? (curl_version()['version']??PHP_VERSION)
                : null,
            'PDO' => null,
            'PDO MySQL' => null,
            'MBString' => null,
            'OpenSSL' => defined('OPENSSL_VERSION_TEXT') ? OPENSSL_VERSION_TEXT : null,
            'JSON' => null,
            'GD' => null,
            'Intl' => null,
        ];
        $notExists = [];
        $exists = [];
        foreach ($requiredExtension as $extension => $version) {
            $ext = strtolower(ucwords(str_replace(' ', '_', $extension)));
            if (!extension_loaded($ext)) {
                $notExists[$extension] = sprintf(
                    '<fg=red;options=bold>[x]</> %s %s',
                    $extension,
                    $this->translateContext('extension is not exist', 'console')
                );
                continue;
            }
            if (!$version) {
                $version = phpversion($ext);
                $version = $version !== phpversion() ? $version : null;
            }
            $exists[$extension] = sprintf(
                '%s <comment>%s</comment> %s %s',
                '<fg=green;options=bold>[√]</>',
                $this->translateContext('extension valid', 'console'),
                $extension,
                $version ? sprintf(
                    "%s [<comment>%s</comment>]",
                    $this->translateContext('with version', 'console'),
                    $version
                ) : ''
            );
        }
        if (empty($notExists)) {
            $this->writeSuccess(
                $output,
                $this->translateContext(
                    'All required extensions are installed',
                    'console'
                )
            );
        } else {
            $this->writeWarning(
                $output,
                sprintf(
                    '<comment>%s</comment> %s',
                    count($notExists),
                    $this->translateContext(
                        'required extensions is not installed',
                        'console'
                    )
                ),
            );
        }
        foreach ($requiredExtension as $extension => $version) {
            if (isset($notExists[$extension])) {
                $this->writeIndent(
                    $output,
                    $notExists[$extension]
                );
                continue;
            }
            $this->writeIndent(
                $output,
                $exists[$extension]
            );
        }
        $suggestedExtension = [
            'Redis' => null,
            'Memcached' => null,
            'APCU' => null,
            'XML' => null,
            'OpCache' => null,
            'iMAP' => null,
            'igBinary' => null,
        ];
        $exists = [];
        foreach ($suggestedExtension as $extension => $version) {
            $ext = strtolower(ucwords(str_replace(' ', '_', $extension)));
            if (!extension_loaded($ext)) {
                if ($ext !== 'opcache') {
                    continue;
                }
                if (!function_exists('opcache_get_configuration')
                    || !function_exists('opcache_reset')
                ) {
                    continue;
                }
            }
            $exists[$extension] = sprintf(
                '%s <comment>%s</comment> %s %s',
                '<fg=green;options=bold>[√]</>',
                $extension,
                $this->translateContext('extension installed', 'console'),
                $version ? sprintf("with version [<comment>%s</comment>]", $version) : ''
            );
        }
        if (empty($exists)) {
            $this->writeWarning(
                $output,
                $this->translateContext(
                    'No recommended extensions installed',
                    'console'
                )
            );
        } else {
            $this->write(
                $output,
                sprintf(
                    '<comment>%s</comment> %s',
                    count($exists),
                    $this->translatePluralContext(
                        'recommended extension installed',
                        'recommended extensions installed',
                        count($exists),
                        'console'
                    )
                ),
                true
            );
        }
        foreach ($suggestedExtension as $extension => $version) {
            if (!isset($exists[$extension])) {
                continue;
            }
            $this->writeIndent(
                $output,
                $exists[$extension]
            );
        }
        return Command::SUCCESS;
    }
}
