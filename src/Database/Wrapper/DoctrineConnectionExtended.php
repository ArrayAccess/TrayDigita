<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Database\Wrapper;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Statement;

/**
 * Class to override the prepare method of the Doctrine Connection class.
 */
class DoctrineConnectionExtended extends Connection
{
    public function prepare(string $sql): Statement
    {
        return new DoctrineStatementExtended($this, parent::prepare($sql)->getWrappedStatement(), $sql);
    }
}
