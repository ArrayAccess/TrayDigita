<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Database\Wrapper;

use ArrayAccess\TrayDigita\Database\Connection;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerIndicateInterface;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerInterface;
use ArrayAccess\TrayDigita\Traits\Manager\ManagerDispatcherTrait;
use Doctrine\DBAL\Driver\Connection as DoctrineConnection;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;

class ConnectionWrapper extends AbstractConnectionMiddleware implements ManagerIndicateInterface
{
    use ManagerDispatcherTrait;

    public function __construct(
        protected Connection $databaseConnection,
        DoctrineConnection $wrappedConnection
    ) {
        parent::__construct($wrappedConnection);
    }

    protected function getPrefixNameEventIdentity(): ?string
    {
        return 'connection';
    }


    public function getManager(): ?ManagerInterface
    {
        return $this->databaseConnection->getManager();
    }


    public function prepare(string $sql): Statement
    {
        // @dispatch(connection.queryString)
        $this->dispatchEvent(
            "queryString",
            $sql,
            $this->databaseConnection
        );
        try {
            // @dispatch(connection.beforePrepare)
            $this->dispatchBefore(
                $sql,
                $this->databaseConnection
            );

            $prepared = new StatementWrapper(
                $this,
                parent::prepare($sql)
            );

            // @dispatch(connection.prepare)
            $this->dispatchCurrent(
                $sql,
                $this->databaseConnection
            );
            return $prepared;
        } finally {
            // @dispatch(connection.afterPrepare)
            $this->dispatchAfter(
                $sql,
                $this->databaseConnection
            );
        }
    }

    public function query(string $sql): Result
    {
        // @dispatch(connection.queryString)
        $this->dispatchEvent(
            "queryString",
            $sql,
            $this->databaseConnection
        );
        try {
            // @dispatch(connection.beforeQuery)
            $this->dispatchBefore(
                $sql,
                $this->databaseConnection
            );

            $queried = parent::query($sql);

            // @dispatch(connection.query)
            $this->dispatchCurrent(
                $sql,
                $this->databaseConnection
            );
            return $queried;
        } finally {
            // @dispatch(connection.afterQuery)
            $this->dispatchAfter(
                $sql,
                $this->databaseConnection
            );
        }
    }

    public function exec(string $sql): int
    {
        // @dispatch(connection.queryString)
        $this->dispatchEvent(
            "queryString",
            $sql,
            $this->databaseConnection
        );
        try {
            // @dispatch(connection.beforeExec)
            $this->dispatchBefore(
                $sql,
                $this->databaseConnection
            );

            $exec = parent::exec($sql);

            // @dispatch(connection.exec)
            $this->dispatchCurrent(
                $sql,
                $this->databaseConnection,
                $exec
            );
            return $exec;
        } finally {
            // @dispatch(connection.afterExec)
            $this->dispatchAfter(
                $sql,
                $this->databaseConnection,
                $exec??null
            );
        }
    }

    public function beginTransaction(): bool
    {
        // @dispatch(connection.beforeBeginTransaction)
        $this->dispatchBefore($this->databaseConnection);

        $transact = parent::beginTransaction();

        // @dispatch(connection.beginTransaction)
        $this->dispatchCurrent($transact, $this->databaseConnection);

        return $transact;
    }

    public function commit(): bool
    {
        // @dispatch(connection.beforeCommit)
        $this->dispatchBefore($this->databaseConnection);

        $commit = parent::commit();

        // @dispatch(connection.commit)
        $this->dispatchCurrent($commit, $this->databaseConnection);
        return $commit;
    }

    public function rollBack(): bool
    {
        // @dispatch(connection.beforeRollback)
        $this->dispatchBefore($this->databaseConnection);

        $rollback = parent::rollBack();

        // @dispatch(connection.rollback)
        $this->dispatchCurrent($rollback, $this->databaseConnection);
        return $rollback;
    }
}
