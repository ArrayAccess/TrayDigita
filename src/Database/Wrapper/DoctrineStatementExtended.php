<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Database\Wrapper;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Statement;

/**
 * @internal
 */
class DoctrineStatementExtended extends Statement
{
    public function bindValue($param, $value, $type = ParameterType::STRING)
    {
        return parent::bindValue($param, $value, $type);
    }
}
