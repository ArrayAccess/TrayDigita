<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Database;

use ArrayAccess\TrayDigita\Database\Interfaces\DatabaseEventInterface;

abstract class DatabaseEvent implements DatabaseEventInterface
{
    final public function __construct(protected Connection $connection)
    {
    }

    public function getConnection(): Connection
    {
        return $this->connection;
    }
}
