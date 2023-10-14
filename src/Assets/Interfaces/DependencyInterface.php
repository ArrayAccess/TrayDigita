<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Assets\Interfaces;

use Stringable;

interface DependencyInterface extends Stringable
{
    public function __construct(DependenciesInterface $dependencies);

    public function getDependencies(): DependenciesInterface;

    public function getAttributes(): array;

    public function hasAttribute(string $name) : bool;

    public function removeAttribute(string $name) : static;

    public function setAttribute(string $name, string|int|bool|float|null $value) : static;

    public function getId(): string;

    public function getInherits(): array;

    public function render(): string;

    /**
     * @return Stringable|string
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function getSource();
}
