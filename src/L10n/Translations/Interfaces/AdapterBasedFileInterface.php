<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\L10n\Translations\Interfaces;

interface AdapterBasedFileInterface extends AdapterInterface
{
    /**
     * @param string $directory
     * @param string $domain
     * @param bool $strict
     * @return bool
     */
    public function registerDirectory(
        string $directory,
        string $domain,
        bool $strict = false
    ) : bool;
}
