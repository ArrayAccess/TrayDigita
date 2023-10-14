<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Database\Interfaces;

use ArrayAccess\TrayDigita\Database\Connection;

interface DatabaseEventInterface
{
    public function __construct(Connection $connection);

    public function getConnection() : Connection;
}
