<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Database\Wrapper;

use ArrayAccess\TrayDigita\Event\Interfaces\ManagerIndicateInterface;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerInterface;
use ArrayAccess\TrayDigita\Traits\Manager\ManagerDispatcherTrait;
use Doctrine\DBAL\Driver\Middleware\AbstractStatementMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;

class StatementWrapper extends AbstractStatementMiddleware implements ManagerIndicateInterface
{
    use ManagerDispatcherTrait;

    public function __construct(
        public readonly ConnectionWrapper $connectionWrapper,
        Statement $wrappedStatement
    ) {
        parent::__construct($wrappedStatement);
    }

    protected function getPrefixNameEventIdentity(): ?string
    {
        return 'connection';
    }

    public function getManager(): ?ManagerInterface
    {
        return $this->connectionWrapper->getManager();
    }

    public function execute($params = null): Result
    {
        return $this->dispatchWrap(
            fn () => parent::execute($params),
            $params,
            $this->connectionWrapper
        );
    }
}
