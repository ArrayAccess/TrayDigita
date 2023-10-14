<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Routing;

use ArrayAccess\TrayDigita\Exceptions\InvalidArgument\InvalidArgumentException;
use ArrayAccess\TrayDigita\Routing\Attributes\Abstracts\HttpMethodAttributeAbstract;
use ArrayAccess\TrayDigita\Routing\Interfaces\ControllerInterface;
use ArrayAccess\TrayDigita\Routing\Interfaces\RouteInterface;
use ReflectionClass;
use Throwable;
use function array_keys;
use function array_values;
use function explode;
use function is_array;
use function is_callable;
use function is_object;
use function is_string;
use function sprintf;
use function str_replace;
use function strtoupper;
use function trim;

class Route implements RouteInterface
{
    const DEFAULT_PRIORITY = 10;

    const PLACEHOLDERS = [
        '?:id:' => '(?:.+)?',
        '?:num:' => '(?:\d+)?',
        '?:any:' => '(?:.*)?',
        '?:hex:' => '(?:[0-9A-Fa-f]+)?',
        '?:lower_hex:' => '(?:[0-9a-f]+)?',
        '?:upper_hex:' => '(?:[0-9A-F]+)?',
        '?:alpha:' => '(?:[A-Za-z]+)?',
        '?:lower_alpha:' => '(?:[a-z]+)?',
        '?:upper_alpha:' => '(?:[A-Z]+)?',
        '?:uuid:'    => '(?:(?i)[0-9A-F]{8}-[0-9A-F]{4}-[345][0-9A-F]{3}-[89AB][0-9A-F]{3}-[0-9A-F]{12})?',
        '?:uuid_v3:' => '(?:(?i)[0-9A-F]{8}-[0-9A-F]{4}-3[0-9A-F]{3}-[89AB][0-9A-F]{3}-[0-9A-F]{12})?',
        '?:uuid_v4:' => '(?:(?i)[0-9A-F]{8}-[0-9A-F]{4}-4[0-9A-F]{3}-[89AB][0-9A-F]{3}-[0-9A-F]{12})?',
        '?:uuid_v5:' => '(?:(?i)[0-9A-F]{8}-[0-9A-F]{4}-5[0-9A-F]{3}-[89AB][0-9A-F]{3}-[0-9A-F]{12})?',
        '?:slug:'    => '(?:[a-z0-9]+(?:(?:[0-9a-z_\-]+)?[a-z0-9]+)?)?',
        ':id:'  => '(?:.+)',
        ':num:' => '(?:\d+)',
        ':any:' => '(?:.*)',
        ':hex:' => '(?:[0-9A-Fa-f]+)',
        ':lower_hex:' => '(?:[0-9a-f]+)',
        ':upper_hex:' => '(?:[0-9A-F]+)',
        ':alpha:' => '(?:[A-Za-z]+)',
        ':lower_alpha:' => '(?:[a-z]+)',
        ':upper_alpha:' => '(?:[A-Z]+)',
        ':uuid:'    => '(?:(?i)[0-9A-F]{8}-[0-9A-F]{4}-[345][0-9A-F]{3}-[89AB][0-9A-F]{3}-[0-9A-F]{12})',
        ':uuid_v3:' => '(?:(?i)[0-9A-F]{8}-[0-9A-F]{4}-3[0-9A-F]{3}-[89AB][0-9A-F]{3}-[0-9A-F]{12})',
        ':uuid_v4:' => '(?:(?i)[0-9A-F]{8}-[0-9A-F]{4}-4[0-9A-F]{3}-[89AB][0-9A-F]{3}-[0-9A-F]{12})',
        ':uuid_v5:' => '(?:(?i)[0-9A-F]{8}-[0-9A-F]{4}-5[0-9A-F]{3}-[89AB][0-9A-F]{3}-[0-9A-F]{12})',
        ':slug:'    => '(?:[a-z0-9]+(?:(?:[0-9a-z_\-]+)?[a-z0-9]+)?)',
    ];

    protected string $pattern;

    protected string $compiledPattern;

    protected array $methods = [];

    protected int $priority;

    /**
     * @var callable|array
     */
    protected $callback;

    /**
     * @throws InvalidArgumentException
     */
    public function __construct(
        array|string $methods,
        string $pattern,
        callable|array $controller,
        ?int $priority = self::DEFAULT_PRIORITY,
        protected ?string $name = null,
        protected ?string $hostName = null
    ) {
        $this->priority = $priority??self::DEFAULT_PRIORITY;
        $this->setRoutePattern($pattern);
        $methods = empty($methods) ? 'ANY' : $methods;
        $this->methods = $this->filterMethods($methods);
        if (is_callable($controller)) {
            $this->setHandler($controller);
        } else {
            $controller = array_values($controller);
            if (count($controller) < 2) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Argument controller must be contain class of %s and method',
                        ControllerInterface::class
                    )
                );
            }
            $this->setController($controller[0], $controller[1]);
        }
    }

    /**
     * @param string $pattern
     * @return string
     */
    protected function setRoutePattern(string $pattern): string
    {
        $this->pattern = $pattern;
        $placeholder = static::PLACEHOLDERS;
        if (!is_array($placeholder)) {
            $placeholder = self::PLACEHOLDERS;
        }
        $pattern = str_replace(
            array_keys($placeholder),
            array_values($placeholder),
            $pattern
        );
        $this->compiledPattern = $pattern;
        return $pattern;
    }

    /**
     * @return string
     */
    public function getPattern(): string
    {
        return $this->pattern;
    }

    /**
     * @return string
     */
    public function getCompiledPattern(): string
    {
        return $this->compiledPattern;
    }

    /**
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @param string|null $name
     * @return $this
     */
    public function setName(?string $name): static
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return int
     */
    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * @param int $priority
     * @return Route
     */
    public function setPriority(int $priority): static
    {
        $this->priority = $priority;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getHostName(): ?string
    {
        return $this->hostName;
    }

    /**
     * @param ?string $hostName
     * @return Route
     */
    public function setHostName(?string $hostName): static
    {
        $this->hostName = $hostName;
        return $this;
    }

    public function setHandler(callable $callback): static
    {
        $this->callback = $callback;
        return $this;
    }

    /**
     * @return array
     */
    public function getMethods(): array
    {
        return array_keys($this->methods);
    }

    /**
     * @return array{0:ControllerInterface, 1:string}|callable
     */
    public function getCallback(): callable|array
    {
        return $this->callback;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function setController(
        string|ControllerInterface $controller,
        string $method
    ): static {
        try {
            $ref = new ReflectionClass($controller);
            $refMethod = $ref->getMethod($method);
        } catch (Throwable $e) {
            throw new InvalidArgumentException(
                $e->getMessage()
            );
        }

        if (!$ref->isSubclassOf(ControllerInterface::class)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Controller must be implement %s',
                    ControllerInterface::class
                )
            );
        }

        $controllerName = is_object($controller) ? $controller : $ref->getName();
        $this->callback = [
            $controllerName,
            $refMethod->getName()
        ];
        return $this;
    }

    public function containMethod(string $method): bool
    {
        // always true
        if (isset($this->methods['*'])) {
            return true;
        }
        if ($method === '*') {
            return true;
        }

        $method = trim(strtoupper($method));
        return isset($this->methods[$method]);
    }

    public static function filterMethods(string|array $methods): array
    {
        if ($methods === '*') {
            return ['*' => true];
        }

        if (is_string($methods)) {
            $methods = trim($methods);
            $methods = explode(' ', $methods);
        }

        $httpMethods = [];
        foreach ($methods as $method) {
            if (!is_string($method)) {
                continue;
            }
            $method = trim($method);
            if ($method === '') {
                continue;
            }
            if ($method === '*') {
                return ['*' => true];
            }
            $method = strtoupper($method);
            $httpMethods[$method] = true;
        }

        if (isset($httpMethods['ANY'])) {
            unset($httpMethods['ANY']);
            foreach (HttpMethodAttributeAbstract::ANY_METHODS as $theMethod) {
                $httpMethods[$theMethod] = true;
            }
        }

        return $httpMethods;
    }
}
