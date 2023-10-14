<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Database\Entities\Abstracts;

use ArrayAccess\TrayDigita\Database\Connection;
use ArrayAccess\TrayDigita\Database\Entities\Traits\FieldNameGetter;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerInterface;
use ArrayAccess\TrayDigita\Traits\Service\CallStackTraceTrait;
use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\Persistence\ObjectManager;
use JsonSerializable;
use Psr\Container\ContainerInterface;
use function array_filter;
use function debug_backtrace;
use function get_object_vars;
use function in_array;
use function is_object;
use function is_string;
use function preg_match;
use function property_exists;
use function spl_object_hash;
use function sprintf;
use function str_replace;
use function strtolower;
use const DEBUG_BACKTRACE_IGNORE_ARGS;

class AbstractEntity implements JsonSerializable
{
    use FieldNameGetter,
        CallStackTraceTrait;

    private ?EntityManagerInterface $entityManagerObject = null;

    /**
     * Allow association mapping to serve on @method toArray()
     * @var bool $entityAllowAssociations
     */
    protected bool $entityAllowAssociations = false;

    /**
     * Create field / column aliases on @method toArray()
     * @var array $entityFieldAliases
     */
    protected array $entityFieldAliases = [];

    /**
     * Blacklisted fields / columns on @method maskArray()
     * @var array<string> $entityBlackListedFields
     */
    protected array $entityBlackListedFields = [];

    /**
     * List to display on @method toArray()
     * @var ?array<string, string> $entityDisplayFields
     */
    protected ?array $entityDisplayFields = null;

    /**
     * Include property to display @method toArray()
     * @var ?array<string> $entityIncludeDisplayFieldsProperty
     */
    protected ?array $entityIncludeDisplayFieldsProperty = null;

    public function getEntityManager(): ?EntityManagerInterface
    {
        return $this->entityManagerObject;
    }

    public function setEntityManager(EntityManagerInterface $entityManagerObject): void
    {
        $this->entityManagerObject = $entityManagerObject;
    }

    public function getEntityFieldAliases(): array
    {
        return $this->entityFieldAliases;
    }

    public function setEntityFieldAliases(array $entityFieldAliases): void
    {
        $this->entityFieldAliases = $entityFieldAliases;
    }

    public function setEntityBlackListedFields(array $entityBlackListedFields): void
    {
        $this->entityBlackListedFields = $entityBlackListedFields;
    }

    public function setEntityDisplayFields(?array $entityDisplayFields): void
    {
        $this->entityDisplayFields = $entityDisplayFields;
    }

    public function setEntityIncludeDisplayFieldsProperty(?array $entityIncludeDisplayFieldsProperty): void
    {
        $this->entityIncludeDisplayFieldsProperty = $entityIncludeDisplayFieldsProperty;
    }

    protected function getEntityBlacklistedFields() : array
    {
        return $this->entityBlackListedFields;
    }

    protected function getEntityDisplayFields() : ?array
    {
        return $this->entityDisplayFields;
    }

    protected function getEntityIncludeDisplayFieldsProperty() : ?array
    {
        return $this->entityIncludeDisplayFieldsProperty;
    }

    protected function isEntityAllowAssociations() : bool
    {
        return $this->entityAllowAssociations;
    }

    public function setEntityAllowAssociations(bool $entityAllowAssociations): void
    {
        $this->entityAllowAssociations = $entityAllowAssociations;
    }


    /**
     * @var int $entityDeepCallIncrement
     */
    private int $entityDeepCallIncrement = 0;

    /**
     * @var bool $entityStopSelfCall
     * @private
     */
    private bool $entityStopSelfCall = false;

    /**
     * Limit looping current entity self call
     *
     * @var int $entityMaxStackLooping
     */
    protected int $entityMaxStackLooping = 2;

    private function protectSelfCall(&$data, $key): void
    {
        if ($this->entityStopSelfCall === true) {
            unset($data[$key]);
            $this->entityStopSelfCall = false;
            $this->entityDeepCallIncrement = 0;
        }
    }

    protected function toArrayResolve($mockup = false): array
    {
        // prevent looping
        if ($this->entityDeepCallIncrement++ >= $this->entityMaxStackLooping) {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]??[];
            if (($trace['class']??null) === __CLASS__ && $trace['function'] === __FUNCTION__) {
                $this->entityStopSelfCall = true;
                return [];
            }
        }

        $this->configureMethodsParameterFields();
        $className = $this::class;
        foreach (self::$objectFields[$className] as $item) {
            $fields[$item] = $item;
        }

        $includeFields = $this->getEntityIncludeDisplayFieldsProperty()??[];
        foreach ($includeFields as $item) {
            if (!is_string($item)
                || !property_exists($this, $item)
                || isset($fields[$item])
            ) {
                continue;
            }
            $fields[$item] = $item;
        }
        if (empty($fields)) {
            $this->entityDeepCallIncrement = 0;
            return [];
        }

        $this->assertCallstack();
        $data = [];
        foreach ($fields as $key => $item) {
            $name  = is_string($item) ? $item : $key;
            $key   = is_string($key) ? $key : $name;
            $data[$key] = null;
            if (!is_string($name)) {
                continue;
            }
            $lower = strtolower($name);
            if (isset(self::$objectMethodIsGetFields[$className][$lower])) {
                $data[$key] = $this->{self::$objectMethodIsGetFields[$className][$lower]}();
                if ($data[$key] instanceof AbstractEntity) {
                    $same = $data[$key] === $this;
                    $data[$key] = $mockup ? $data[$key]->maskArray() : $data[$key]->toArray();
                    $same && $this->protectSelfCall($data, $key);
                    continue;
                }
            }
            $nextLower = str_replace('_', '', $lower);
            if ($nextLower !== $lower
                && isset(self::$objectMethodIsGetFields[$className][$nextLower])
            ) {
                $data[$key] = $this->{self::$objectMethodIsGetFields[$className][$nextLower]}();
                if ($data[$key] instanceof AbstractEntity) {
                    $same = $data[$key] === $this;
                    $data[$key] = $mockup ? $data[$key]->maskArray() : $data[$key]->toArray();
                    $same && $this->protectSelfCall($data, $key);
                }
                continue;
            }

            $name = self::$objectFields[$className][$lower]??null;
            if ($name) {
                $data[$key] = $this->$name??null;
                if ($data[$key] instanceof AbstractEntity) {
                    $same = $data[$key] === $this;
                    $data[$key] = $mockup ? $data[$key]->maskArray() : $data[$key]->toArray();
                    $same && $this->protectSelfCall($data, $key);
                }
            }
        }

        $this->clearCallstack();
        $this->entityDeepCallIncrement = 0;
        return $data;
    }

    /**
     * Resolve the data into array
     *
     * @return array
     */
    public function toArray() : array
    {
        return $this->toArrayResolve();
    }

    /**
     * @return array
     */
    protected function maskArray(): array
    {
        $this->configureMethodsParameterFields();
        /**
         * Blacklist data
         *
         * Values @uses Collection always skipped
         */
        $blackLists = array_filter(
            $this->getEntityBlacklistedFields(),
            'is_string'
        );
        $aliases = $this->getEntityFieldAliases();
        $fields = $this->getEntityDisplayFields();
        $className = $this::class;
        if ($fields === null) {
            if ($this->isEntityAllowAssociations()) {
                foreach (self::$objectFields[$className] as $item) {
                    $fields[$item] = $item;
                }
                // unset($fields['fieldsarray']);
            } else {
                $columns = self::$columnsFields[$className];
                // fallback default
                $em = $this->getEntityManager()
                    ??ContainerHelper::use(Connection::class)
                    ?->getEntityManager();
                if ($em && empty($columns)) {
                    $metadata = $em->getClassMetadata($className);
                    foreach ($metadata->getFieldNames() as $item) {
                        try {
                            $mapping = $metadata->getFieldMapping($item);
                        } catch (MappingException) {
                            continue;
                        }
                        /** @noinspection DuplicatedCode */
                        $fieldName = $mapping['fieldName']??null;
                        $columnName = $mapping['columnName']??null;
                        if (!$fieldName || !$columnName) {
                            continue;
                        }
                        $columnName = strtolower($columnName);
                        $columns[$fieldName] = $fieldName;
                        self::$columnsFields[$className][$columnName] = $fieldName;
                    }
                }
                foreach ($columns as $item) {
                    $fields[$item] = $item;
                }
            }
        }

        $includeFields = $this->getEntityIncludeDisplayFieldsProperty()??[];
        foreach ($includeFields as $item) {
            if (!is_string($item)
                || !property_exists($this, $item)
                || isset($fields[$item])
            ) {
                continue;
            }
            $fields[$item] = $item;
        }
        if (empty($fields)) {
            return [];
        }
        $data = [];
        foreach ($this->toArrayResolve(true) as $key => $item) {
            if (!isset($fields[$key])
                || in_array($key, $blackLists)
                || $item instanceof Collection
            ) {
                continue;
            }
            $key = is_string($aliases[$key]??null)
                ? $aliases[$key]
                : $key;
            $data[$key] = $item;
        }
        return $data;
    }

    public function jsonSerialize(): array
    {
        return $this->maskArray();
    }

    // protect from usage
    public function __debugInfo(): ?array
    {
        $info = get_object_vars($this);
        foreach ($info as $key => $data) {
            // proxy / disable internal property
            if (str_starts_with($key, '__')
                || $data instanceof ManagerInterface
                || $data instanceof ObjectManager
                || $data instanceof ContainerInterface
                || $data instanceof Connection
                || $data instanceof Collection
            ) {
                if (is_object($data)) {
                    $info[$key] = sprintf(
                        'object[%s](%s)',
                        spl_object_hash($data),
                        $data::class
                    );
                    continue;
                }
                continue;
            }

            if (preg_match(
            // filter
                '~secret|nonce|salt|key|auth|pass|license|hash~',
                strtolower($key)
            )) {
                $info[$key] = '<redacted>';
            }
        }
        return $info;
    }
}
