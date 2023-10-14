<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Database\Entities\Traits;

use ArrayAccess\TrayDigita\Database\Entities\Abstracts\AbstractEntity;
use DateTimeInterface;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use function password_hash;
use function password_needs_rehash;
use function password_verify;
use const PASSWORD_DEFAULT;

trait PasswordTrait
{
    abstract public function getId() : int;

    abstract public function getUpdatedAt() : ?DateTimeInterface;

    abstract public function getPassword() : ?string;

    abstract public function setPassword(string $password);

    /**
     * Hash the password
     *
     * @param string $password
     * @return string
     */
    public function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    /**
     * Validate whether password is match
     *
     * @param string $password
     * @return bool
     */
    public function isPasswordMatch(string $password) : bool
    {
        $pass = $this->getPassword();
        return $pass && password_verify($password, $pass);
    }

    /** @noinspection PhpInstanceofIsAlwaysTrueInspection */
    public function passwordBasedIdUpdatedAt(
        PrePersistEventArgs|PostLoadEventArgs|PreUpdateEventArgs $event
    ) : void {
        if ($this instanceof AbstractEntity) {
            $this->setEntityManager($event->getObjectManager());
        }
        $password = $this->getPassword();
        if (!$password) {
            return;
        }
        if (!password_needs_rehash($password, PASSWORD_DEFAULT)) {
            return;
        }
        $newPassword = $this->hashPassword($password);
        $this->setPassword($newPassword);
        if ($event instanceof PreUpdateEventArgs && $event->hasChangedField('password')) {
            $event->setNewValue('password', $newPassword);
        }
        if ($event instanceof PostLoadEventArgs) {
            $date = $this->getUpdatedAt();
            if ($date !== null) {
                $date = str_starts_with($date->format('Y'), '-')
                    ? '0000-00-00 00:00:00'
                    : $date->format('Y-m-d H:i:s');
            }

            // use query builder to make sure updated_at still same
            $event
                ->getObjectManager()
                ->createQueryBuilder()
                ->update($this::class, 'x')
                ->set('x.password', ':password')
                ->set('x.updated_at', ':updated_at')
                ->where('x.id = :id')
                ->setParameters([
                    'password' => $this->getPassword(),
                    'updated_at' => $date,
                    'id' => $this->getId()
                ])
                ->getQuery()
                ->execute();
        }
    }
}
