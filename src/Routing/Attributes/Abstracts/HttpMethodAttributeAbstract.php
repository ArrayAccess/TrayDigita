<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Routing\Attributes\Abstracts;

use ArrayAccess\TrayDigita\Routing\Attributes\Interfaces\HttpMethodAttributeInterface;
use ArrayAccess\TrayDigita\Routing\Route;
use ArrayAccess\TrayDigita\Util\Filter\Consolidation;
use function strtoupper;

// phpcs:disable PSR1.Files.SideEffects
abstract readonly class HttpMethodAttributeAbstract implements HttpMethodAttributeInterface
{
    public array $methods;

    const ANY_METHODS = [
        'DELETE',
        'HEAD',
        'POST',
        'TRACE',
        'GET',
        'PATCH',
        'OPTIONS',
        'PUT',
        'CONNECT',
    ];

    public function __construct(
        public string $pattern,
        public int $priority = Route::DEFAULT_PRIORITY,
        public ?string $name = null,
        public ?string $hostName = null
    ) {
        $method = strtoupper(
            Consolidation::classShortName($this::class)
        );

        $this->methods = $method === 'ANY'
            ? self::ANY_METHODS
            : ($method === 'ALL' ? ['*'] : [$method]);
    }

    /**
     * @return array<string>
     */
    public function getMethods(): array
    {
        return $this->methods;
    }

    /**
     * @return string
     */
    public function getPattern(): string
    {
        return $this->pattern;
    }

    /**
     * @return int
     */
    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @return string|null
     */
    public function getHostName(): ?string
    {
        return $this->hostName;
    }
}
