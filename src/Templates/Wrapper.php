<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Templates;

use ArrayAccess\TrayDigita\Util\Filter\DataNormalizer;
use ArrayAccess\TrayDigita\View\Interfaces\ViewInterface;
use const DIRECTORY_SEPARATOR;

final class Wrapper
{
    protected string $templatePath;

    protected string $publicDirectory;

    /**
     * @param ViewInterface $view
     * @param string $publicDir
     * @param string $templatesPath
     */
    public function __construct(
        public readonly ViewInterface $view,
        string $publicDir,
        string $templatesPath = 'templates'
    ) {
        $this->templatePath   = ltrim(
            DataNormalizer::normalizeDirectorySeparator($templatesPath, true),
            DIRECTORY_SEPARATOR
        );
        $this->publicDirectory = DataNormalizer::normalizeDirectorySeparator($publicDir, true);
    }

    public function getView(): ViewInterface
    {
        return $this->view;
    }

    public function getTemplatePath(): string
    {
        return $this->templatePath;
    }

    public function getPublicDirectory(): string
    {
        return $this->publicDirectory;
    }
}
