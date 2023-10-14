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
                $this->translate('Php version'),
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
                    $this->translate('extension is not exist')
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
                $this->translate('extension valid'),
                $extension,
                $version ? sprintf(
                    "%s [<comment>%s</comment>]",
                    $this->translate('with version'),
                    $version
                ) : ''
            );
        }
        if (empty($notExists)) {
            $this->writeSuccess(
                $output,
                $this->translate('All required extensions are installed')
            );
        } else {
            $this->writeWarning(
                $output,
                sprintf(
                    '<comment>%s</comment> %s',
                    count($notExists),
                    $this->translate('required extensions is not installed')
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
                $this->translate('extension installed'),
                $version ? sprintf("with version [<comment>%s</comment>]", $version) : ''
            );
        }
        if (empty($exists)) {
            $this->writeWarning(
                $output,
                $this->translate(
                    'No recommended extensions installed'
                )
            );
        } else {
            $this->write(
                $output,
                sprintf(
                    '<comment>%s</comment> %s',
                    count($exists),
                    $this->translatePlural(
                        'recommended extension installed',
                        'recommended extensions installed',
                        count($exists)
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
