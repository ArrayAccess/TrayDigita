<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\View\Interfaces;

interface ViewEngineInterface
{
    public function __construct(ViewInterface $view);

    public function getExtensions() : array;

    public function getView() : ViewInterface;

    public function setParameters(array $parameters);

    public function setParameter($name, $value);

    public function hasParameter($name) : bool;

    public function getParameters() : array;

    public function getParameter($name);

    public function removeParameter($name);

    public function getFile(string $filePath) : ?string;

    public function exist(string $path) : bool;

    public function partial(string $path, array $parameters) : string;

    public function render(string $path, array $parameters, ?ViewInterface $view = null) : string;

    public function clearVariableCache();
}
