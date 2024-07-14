<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Database\Result;

use ArrayAccess\TrayDigita\App\Modules\Users\Entities\Site;
use ArrayAccess\TrayDigita\Database\Connection;
use ArrayAccess\TrayDigita\Database\Entities\Abstracts\AbstractEntity;
use ArrayAccess\TrayDigita\Database\Helper\Expression;
use ArrayAccess\TrayDigita\Exceptions\InvalidArgument\InvalidArgumentException;
use ArrayAccess\TrayDigita\Exceptions\Runtime\RuntimeException;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Expr\Comparison;
use Doctrine\Common\Collections\Expr\CompositeExpression;
use Doctrine\Common\Collections\Selectable;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\ObjectRepository;
use function is_string;
use function max;
use function preg_match;
use function sprintf;
use function str_contains;
use function trim;

abstract class AbstractRepositoryFinder
{
    protected ?string $columnSearch = null;

    public function __construct(public readonly Connection $connection)
    {
    }

    public function getConnection(): Connection
    {
        return $this->connection;
    }

    public function findByCriteria(Criteria $criteria) : LazyResultCriteria
    {
        return new LazyResultCriteria(
            $this,
            $criteria
        );
    }

    abstract public function getRepository() : ObjectRepository&Selectable;

    /**
     * @param string $searchQuery
     * @param int|Site|null $site
     * @param int $limit
     * @param int $offset
     * @param array<string, string<Criteria::ASC|Criteria::DESC>> $orderBy
     * @param CompositeExpression|Comparison ...$expressions
     * @return LazyResultCriteria
     * @throws \Doctrine\DBAL\Exception
     * @throws \Doctrine\DBAL\Schema\SchemaException
     * @noinspection PhpFullyQualifiedNameUsageInspection
     * @noinspection PhpUnusedParameterInspection
     */
    public function search(
        string $searchQuery,
        int|Site|null $site = null,
        int $limit = 10,
        int $offset = 0,
        array $orderBy = [],
        CompositeExpression|Comparison ...$expressions
    ) : LazyResultCriteria {
        if ($this->columnSearch === null) {
            throw new RuntimeException(
                sprintf(
                    '%s does not declare column search',
                    $this::class
                )
            );
        }
        $repository = $this->getRepository();
        $metadata = $this
            ->connection
            ->getEntityManager()
            ->getClassMetadata($repository->getClassName());
        $tableName = $metadata->getTableName();
        $table = $this
            ->connection
            ->createSchemaManager()
            ->introspectTable($tableName);
        if (!$table->hasColumn($this->columnSearch)) {
            throw new RuntimeException(
                sprintf(
                    'Column "%s" does not exist in table : %s',
                    $this->columnSearch,
                    $tableName
                )
            );
        }

        $columnSearch = $table->getColumn($this->columnSearch)->getName();
        $offset = max($offset, 0);
        $limit = max($limit, 1);
        $searchQuery = trim($searchQuery);
        $orderings = [];
        foreach ($orderBy as $column => $sort) {
            if (!is_string($column)
                || !is_string($sort)
            ) {
                continue;
            }
            if (!$table->hasColumn($column)) {
                continue;
            }
            $orderings[$table->getColumn($column)->getName()] = $sort;
        }

        $criteria = Expression::criteria()
            ->where(
                Expression::orX(
                    Expression::eq($columnSearch, $searchQuery),
                    Expression::startsWith($columnSearch, $searchQuery),
                    Expression::endsWith($columnSearch, $searchQuery)
                )
            )
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->orderBy($orderings);
        foreach ($expressions as $expression) {
            $expression = $this->assertExpression($metadata, $expression);
            $criteria->andWhere($expression);
        }

        return $this->findByCriteria($criteria);
    }

    private function assertExpression(
        ClassMetadata $metadata,
        CompositeExpression|Comparison $expression
    ) : CompositeExpression|Comparison {
        /**
         * @var class-string<CompositeExpression|Comparison> $className
         */
        $className = $expression::class;
        if ($expression instanceof Comparison) {
            if ($metadata->hasField($expression->getField())) {
                return $expression;
            }
            $wrapped = null;
            $field = $expression->getField();
            // does not support concat
            if (str_contains($field, '(')) {
                preg_match(
                    '~^\s*([a-zA-Z][a-zA-Z_]*)\(\s*([^)]+\s*)\)\s*$~',
                    $field,
                    $match
                );
                if ($match) {
                    $field = trim($match[2]);
                    $wrapped = $match[1];
                }
            }

            if ($metadata->hasField($field)) {
                return $expression;
            }
            $newField = $metadata->getFieldName($field);
            if ($newField === $field) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Field %s does not exists in entity %s',
                        $field,
                        $metadata->getname()
                    )
                );
            }

            $newField = $wrapped ? $wrapped . "($newField)" : $newField;
            // if contain column and create new comparison
            return new $className(
                $newField,
                $expression->getOperator(),
                $expression->getValue()
            );
        }

        $newExpression = [];
        foreach ($expression->getExpressionList() as $list) {
            $newList = $this->assertExpression($metadata, $list);
            if ([] !== $newExpression || $newList !== $list) {
                $newExpression[] = $newList;
            }
        }
        if (!empty($newExpression)) {
            return new $className(
                $expression->getType(),
                $newExpression
            );
        }
        return $expression;
    }

    abstract public function find($id) : ?AbstractEntity;

    public function findById(int $id) : ?AbstractEntity
    {
        return $this->getRepository()->findOneBy(['id' => $id]);
    }
}
