<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Database\Result;

use ArrayAccess\TrayDigita\Database\Entities\Abstracts\AbstractEntity;
use ArrayAccess\TrayDigita\Util\Filter\Consolidation;
use Countable;
use Doctrine\Common\Collections\AbstractLazyCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Expr\Comparison;
use Doctrine\Common\Collections\Expr\CompositeExpression;
use Doctrine\Common\Collections\Expr\Expression;
use Doctrine\Common\Collections\ReadableCollection;
use Doctrine\Common\Collections\Selectable;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\LazyCriteriaCollection;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\Persisters\Entity\BasicEntityPersister;
use Doctrine\ORM\Persisters\Entity\EntityPersister;
use Doctrine\ORM\Persisters\Exception\CantUseInOperatorOnCompositeKeys;
use Doctrine\ORM\Persisters\Exception\UnrecognizedField;
use Doctrine\ORM\Repository\Exception\InvalidFindByCall;
use Exception;
use IteratorAggregate;
use Traversable;
use function implode;
use function is_array;
use function preg_match;
use function sprintf;
use function str_contains;
use function trim;

/**
 * @template T of object|AbstractEntity
 * @psalm-template TKey of array-key
 * @template-covariant T
 * @template-extends Collection<TKey, T>
 * @mixin AbstractLazyCollection&Selectable
 * @property-read int $total
 */
class LazyResultCriteria implements IteratorAggregate, Countable
{
    private int $total;

    private ?Collection $collection = null;

    private ?EntityPersister $persister = null;

    public function __construct(
        public readonly AbstractRepositoryFinder $finder,
        public readonly Criteria $criteria
    ) {
    }

    public function getFinder(): AbstractRepositoryFinder
    {
        return $this->finder;
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
    }

    /**
     * @return int
     */
    public function getTotal(): int
    {
        return $this->total ??= $this
            ->getInternalPersister()
            ->count($this->getCriteria());
    }

    /**
     * @return Selectable&ReadableCollection
     */
    public function getCollection(): ReadableCollection&Selectable
    {
        return $this->collection ??= new class(
            $this->getInternalPersister(),
            $this->getCriteria()
        ) extends LazyCriteriaCollection {
            public function __debugInfo(): ?array
            {
                return Consolidation::debugInfo(
                    $this,
                    excludeKeys: ['collection']
                );
            }
        };
    }

    private function containBracket(Expression $expression): bool
    {
        if ($expression instanceof CompositeExpression) {
            foreach ($expression->getExpressionList() as $expression) {
                if ($this->containBracket($expression)) {
                    return true;
                }
            }
            return false;
        } elseif ($expression instanceof Comparison) {
            return str_contains($expression->getField(), '(');
        }
        return false;
    }

    protected function getInternalPersister() : EntityPersister
    {
        if ($this->persister) {
            return $this->persister;
        }
        if (! $this->containBracket($this->getCriteria()->getWhereExpression())) {
            return $this->persister = $this
                ->getFinder()
                ->getConnection()
                ->getEntityManager()
                ->getUnitOfWork()
                ->getEntityPersister($this->getFinder()->getRepository()->getClassName());
        }
        $em = $this->getFinder()->getConnection()->getEntityManager();
        return $this->persister = new class(
            $em,
            $em->getClassMetadata($this->getFinder()->getRepository()->getClassName())
        ) extends BasicEntityPersister {
            private static array $comparisonMap = [
                Comparison::EQ          => '= %s',
                Comparison::NEQ         => '!= %s',
                Comparison::GT          => '> %s',
                Comparison::GTE         => '>= %s',
                Comparison::LT          => '< %s',
                Comparison::LTE         => '<= %s',
                Comparison::IN          => 'IN (%s)',
                Comparison::NIN         => 'NOT IN (%s)',
                Comparison::CONTAINS    => 'LIKE %s',
                Comparison::STARTS_WITH => 'LIKE %s',
                Comparison::ENDS_WITH   => 'LIKE %s',
            ];

            private function getSelectConditionStatementColumnSQL(
                string $field,
                ?array $assoc = null
            ): array {
                if (isset($this->class->fieldMappings[$field])) {
                    $className = $this->class->fieldMappings[$field]['inherited'] ?? $this->class->name;

                    return [$this->getSQLTableAlias($className)
                        . '.'
                        . $this->quoteStrategy->getColumnName($field, $this->class, $this->platform)];
                }

                if (isset($this->class->associationMappings[$field])) {
                    $association = $this->class->associationMappings[$field];
                    // Many-To-Many requires join table check for joinColumn
                    $columns = [];
                    $class   = $this->class;

                    if ($association['type'] === ClassMetadataInfo::MANY_TO_MANY) {
                        if (! $association['isOwningSide']) {
                            $association = $assoc;
                        }

                        $joinTableName = $this
                            ->quoteStrategy
                            ->getJoinTableName($association, $class, $this->platform);
                        $joinColumns   = $assoc['isOwningSide']
                            ? $association['joinTable']['joinColumns']
                            : $association['joinTable']['inverseJoinColumns'];

                        foreach ($joinColumns as $joinColumn) {
                            $columns[] = $joinTableName
                                . '.'
                                . $this->quoteStrategy->getJoinColumnName($joinColumn, $class, $this->platform);
                        }
                    } else {
                        if (! $association['isOwningSide']) {
                            throw InvalidFindByCall::fromInverseSideUsage(
                                $this->class->name,
                                $field
                            );
                        }

                        $className = $association['inherited'] ?? $this->class->name;

                        foreach ($association['joinColumns'] as $joinColumn) {
                            $columns[] = $this->getSQLTableAlias($className)
                                . '.'
                                . $this
                                    ->quoteStrategy
                                    ->getJoinColumnName($joinColumn, $this->class, $this->platform);
                        }
                    }

                    return $columns;
                }

                if ($assoc !== null && !str_contains($field, ' ') && !str_contains($field, '(')) {
                    return [$field];
                }

                if (preg_match(
                    '~^\s*([a-zA-Z][a-zA-Z_]*)\(\s*([^)]+\s*)\)\s*$~',
                    $field,
                    $match
                ) && !empty($match)
                ) {
                    $match[2] = trim($match[2]);
                    if ($this->getClassMetadata()->hasField($match[2])) {
                        return [$field];
                    }
                }

                throw UnrecognizedField::byFullyQualifiedName($this->class->name, $field);
            }

            public function getSelectConditionStatementSQL(
                $field,
                $value,
                $assoc = null,
                $comparison = null
            ): string {
                $selectedColumns = [];
                $columns         = $this->getSelectConditionStatementColumnSQL($field, $assoc);

                if (count($columns) > 1 && $comparison === Comparison::IN) {
                    /*
                     *  @todo try to support multi-column IN expressions.
                     *  Example: (col1, col2) IN (('val1A', 'val2A'), ('val1B', 'val2B'))
                     */
                    throw CantUseInOperatorOnCompositeKeys::create();
                }

                foreach ($columns as $column) {
                    $placeholder = '?';

                    if (isset($this->class->fieldMappings[$field]['requireSQLConversion'])) {
                        $type        = Type::getType($this->class->fieldMappings[$field]['type']);
                        $placeholder = $type->convertToDatabaseValueSQL($placeholder, $this->platform);
                    }

                    if ($comparison !== null) {
                        // special case null value handling
                        if (($comparison === Comparison::EQ || $comparison === Comparison::IS) && $value === null) {
                            $selectedColumns[] = $column . ' IS NULL';

                            continue;
                        }

                        if ($comparison === Comparison::NEQ && $value === null) {
                            $selectedColumns[] = $column . ' IS NOT NULL';

                            continue;
                        }

                        $selectedColumns[] = $column . ' ' . sprintf(self::$comparisonMap[$comparison], $placeholder);

                        continue;
                    }

                    if (is_array($value)) {
                        $in = sprintf('%s IN (%s)', $column, $placeholder);

                        if (in_array(null, $value, true)) {
                            $selectedColumns[] = sprintf('(%s OR %s IS NULL)', $in, $column);

                            continue;
                        }

                        $selectedColumns[] = $in;

                        continue;
                    }

                    if ($value === null) {
                        $selectedColumns[] = sprintf('%s IS NULL', $column);

                        continue;
                    }

                    $selectedColumns[] = sprintf('%s = %s', $column, $placeholder);
                }

                return implode(' AND ', $selectedColumns);
            }
        };
    }

    /**
     * @return Traversable<TKey, T>
     * @throws Exception
     */
    public function getIterator(): Traversable
    {
        return $this->getCollection()->getIterator();
    }

    public function count(): int
    {
        return $this->getCollection()->count();
    }

    public function __call(string $name, array $arguments)
    {
        return $this->getCollection()->$name(...$arguments);
    }

    /**
     * @return array<TKey, T|AbstractEntity>
     */
    public function toArray(): array
    {
        return $this->getCollection()->toArray();
    }

    public function __get(string $name)
    {
        return match ($name) {
            'total' => $this->getTotal(),
            'collection' => $this->getCollection(),
            default => $this->$name??null,
        };
    }

    public function __isset(string $name): bool
    {
        return match ($name) {
            'total',
            'collection' => true,
            default => isset($this->$name),
        };
    }

    public function __debugInfo(): ?array
    {
        return Consolidation::debugInfo(
            $this,
            excludeKeys: ['criteria']
        );
    }
}
