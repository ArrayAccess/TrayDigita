<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\View\Twig\Loader;

use ArrayAccess\TrayDigita\View\Engines\TwigEngine;
use Twig\Error\LoaderError;
use Twig\Loader\FilesystemLoader;

class FileLoader extends FilesystemLoader
{
    public function __construct(
        protected TwigEngine $twigEngine,
        $paths = [],
        string $rootPath = null
    ) {
        parent::__construct($paths, $rootPath);
    }

    public function getTwigEngine(): TwigEngine
    {
        return $this->twigEngine;
    }

    protected function findTemplate(string $name, bool $throw = true): ?string
    {
        try {
            return parent::findTemplate($name);
        } catch (LoaderError $e) {
            if ($this->twigEngine->exist($name)) {
                unset($this->errorCache[$name]);
                return $this->cache[$name] = $this->twigEngine->getFile($name);
            }
            if (!$throw) {
                throw $e;
            }
            throw $e;
        }
    }
}
