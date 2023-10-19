<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Database\Wrapper;

use ArrayAccess\TrayDigita\Event\Interfaces\ManagerIndicateInterface;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerInterface;
use ArrayAccess\TrayDigita\Traits\Manager\ManagerDispatcherTrait;
use Doctrine\DBAL\Driver\Middleware\AbstractResultMiddleware;
use Doctrine\DBAL\Driver\Result;

class ResultWrapper extends AbstractResultMiddleware implements ManagerIndicateInterface
{
    use ManagerDispatcherTrait;

    public function __construct(
        public readonly StatementWrapper $statementWrapper,
        Result $result
    ) {
        parent::__construct($result);
    }

    public function getManager(): ?ManagerInterface
    {
        return $this->statementWrapper->getManager();
    }

    public function fetchNumeric()
    {
        return $this->dispatchWrap(
            fn () => parent::fetchNumeric(),
            $this->statementWrapper
        );
    }

    public function fetchAssociative()
    {
        return $this->dispatchWrap(
            fn () => parent::fetchAssociative(),
            $this->statementWrapper
        );
    }

    public function fetchOne()
    {
        return $this->dispatchWrap(
            fn () => parent::fetchOne(),
            $this->statementWrapper
        );
    }

    public function fetchAllNumeric(): array
    {
        return $this->dispatchWrap(
            fn () => parent::fetchAllNumeric(),
            $this->statementWrapper
        );
    }

    public function fetchAllAssociative(): array
    {
        return $this->dispatchWrap(
            fn () => parent::fetchAllAssociative(),
            $this->statementWrapper
        );
    }

    public function fetchFirstColumn(): array
    {
        return $this->dispatchWrap(
            fn () => parent::fetchFirstColumn(),
            $this->statementWrapper
        );
    }

    public function rowCount(): int
    {
        return $this->dispatchWrap(
            fn () => parent::rowCount(),
            $this->statementWrapper
        );
    }

    public function columnCount(): int
    {
        return $this->dispatchWrap(
            fn () => parent::columnCount(),
            $this->statementWrapper
        );
    }

    public function free(): void
    {
        $this->dispatchWrap(
            fn () => parent::free(),
            $this->statementWrapper
        );
    }
}
