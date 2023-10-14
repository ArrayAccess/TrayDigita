<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Auth\Cookie;

use ArrayAccess\TrayDigita\Auth\Cookie\Interfaces\UserEntityFactoryInterface;
use ArrayAccess\TrayDigita\Auth\Generator\HashIdentity;
use ArrayAccess\TrayDigita\Database\Entities\Interfaces\UserEntityInterface;
use function is_int;

class UserAuth
{
    public function __construct(
        protected HashIdentity $hashIdentity
    ) {
    }

    public function getUserId(string $token)
    {
        return $this->hashIdentity->extract($token)['user_id'] ?? null;
    }

    public function getHashIdentity(): HashIdentity
    {
        return $this->hashIdentity;
    }

    public function getUser(string $token, UserEntityFactoryInterface $userFactory): ?UserEntityInterface
    {
        $id = $this->getUserId($token);
        return is_int($id) ? $userFactory->findById($id) : null;
    }
}
