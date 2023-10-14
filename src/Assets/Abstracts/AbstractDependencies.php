<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Assets\Abstracts;

use ArrayAccess\TrayDigita\Assets\Interfaces\AssetsCollectionInterface;
use ArrayAccess\TrayDigita\Assets\Interfaces\DependenciesInterface;
use ArrayAccess\TrayDigita\Assets\Interfaces\DependencyInlineInterface;
use ArrayAccess\TrayDigita\Assets\Interfaces\DependencyInterface;
use ArrayAccess\TrayDigita\Assets\Interfaces\DependencyUriInterface;
use ArrayAccess\TrayDigita\Exceptions\InvalidArgument\ObjectMismatchException;
use ArrayAccess\TrayDigita\Exceptions\InvalidArgument\UnsupportedArgumentException;
use Psr\Http\Message\UriInterface;
use Stringable;
use function array_filter;
use function array_merge;
use function array_unique;
use function is_string;
use function sprintf;

abstract class AbstractDependencies implements DependenciesInterface
{
    /**
     * @var array<string, DependencyInterface>
     */
    protected array $dependencies = [];

    /**
     * @var array<string, bool>
     */
    protected array $rendered = [];

    public function __construct(public readonly AssetsCollectionInterface $packages)
    {
    }

    public function getAssetsCollection(): AssetsCollectionInterface
    {
        return $this->packages;
    }

    public function register(DependencyInterface $dependency): bool
    {
        $id = $dependency->getId();
        if (isset($this->dependencies[$id])) {
            return false;
        }
        if (!$this->isSupportInline() && $dependency instanceof DependencyInlineInterface) {
            throw new UnsupportedArgumentException(
                sprintf(
                    'Dependencies "%s" does not support inline source',
                    $this->getId()
                )
            );
        }

        if ($dependency->getDependencies() !== $this) {
            throw new ObjectMismatchException(
                'Dependency object is mismatch'
            );
        }
        $this->dependencies[$id] = $dependency;
        return true;
    }

    public function deregister(DependencyInterface|string $dependency): ?DependencyInterface
    {
        $id = is_string($dependency) ? $dependency : $dependency->getId();
        $dependency = $this->dependencies[$id]??null;
        unset($this->dependencies[$id]);
        return $dependency;
    }

    /**
     * @param string $id
     * @return ?DependencyInterface
     */
    public function get(string $id): ?DependencyInterface
    {
        return $this->dependencies[$id] ?? null;
    }

    public function remove(string $id): void
    {
        unset($this->dependencies[$id]);
    }

    protected function internalRender(
        DependencyInterface $dependency,
        &$res = [],
        array &$inQueue = [],
        ...$inherits
    ): void {
        $id = $dependency->getId();
        if (isset($res[$id])) {
            return;
        }
        $inQueue[$id] = true;
        $inheritance = $dependency->getInherits();
        $inherits = array_filter($inherits, 'is_string');
        if (!empty($inherits)) {
            $inheritance = array_unique(
                array_merge(
                    $inheritance,
                    $inherits
                )
            );
        }
        foreach ($inheritance as $inherit) {
            if ($inherit === $id
                || !is_string($inherit)
                || !($dep = $this->get($inherit))
            ) {
                continue;
            }
            if (isset($inQueue[$inherit])) {
                continue;
            }
            $this->internalRender($dep, $res, $inQueue);
        }
        $res[$id] = $dependency;
    }

    /**
     * @param string $id
     * @param string ...$inherits
     * @return ?array<DependencyInterface>
     */
    public function getRenderCollection(string $id, string ...$inherits): ?array
    {
        $dependency = $this->get($id);
        if (!$dependency) {
            return null;
        }
        $inQueue = [];
        $this->internalRender($dependency, $res, $inQueue, ...$inherits);
        return $res;
    }

    public function isRendered(string $id): bool
    {
        return isset($this->rendered[$id]);
    }

    public function render(
        string $id,
        bool $skipRendered = false,
        string ...$inherits
    ) : string {
        $return = '';
        foreach ($this->getRenderCollection($id, ...$inherits)??[] as $key => $script) {
            if ($skipRendered && isset($this->rendered[$key])) {
                continue;
            }
            $this->rendered[$key] = true;
            $return .= "\n".$script->render();
        }
        return $return;
    }

    public function has(string $id): bool
    {
        return isset($this->dependencies[$id]);
    }

    public function getDependencies(): array
    {
        return $this->dependencies;
    }

    public function registerURL(
        string $id,
        string|UriInterface $source,
        array $attributes = [],
        string ...$inherits
    ) : ?DependencyUriInterface {
        if ($this->has($id)) {
            return null;
        }
        $dep = static::createURL($id, $source, $attributes, ...$inherits);
        if ($this->register($dep)) {
            return $dep;
        }
        return null;
    }

    public function registerInline(
        string $id,
        string|Stringable $source,
        array $attributes = [],
        string ...$inherits
    ) : ?DependencyInlineInterface {
        if (!$this->isSupportInline() || $this->has($id)) {
            return null;
        }
        $dep = static::createInline($id, $source, $attributes, ...$inherits);
        if ($this->register($dep)) {
            return $dep;
        }
        return null;
    }
}
