<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Database\Entities\Abstracts;

use ArrayAccess\TrayDigita\Auth\Google\Authenticator;
use ArrayAccess\TrayDigita\Container\Interfaces\ContainerAllocatorInterface;
use ArrayAccess\TrayDigita\Database\Entities\Interfaces\UserEntityInterface;
use ArrayAccess\TrayDigita\Database\Entities\Traits\PasswordTrait;
use ArrayAccess\TrayDigita\Database\Entities\Traits\UserStatusTrait;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerAllocatorInterface;
use ArrayAccess\TrayDigita\Exceptions\InvalidArgument\InvalidEmailException;
use ArrayAccess\TrayDigita\Exceptions\InvalidArgument\InvalidUsernameException;
use ArrayAccess\TrayDigita\Traits\Container\ContainerAllocatorTrait;
use ArrayAccess\TrayDigita\Traits\Manager\ManagerAllocatorTrait;
use ArrayAccess\TrayDigita\Util\Filter\StringFilter;
use ArrayAccess\TrayDigita\Util\Generator\RandomString;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\HasLifecycleCallbacks;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\PostLoad;
use Doctrine\ORM\Mapping\PrePersist;
use Doctrine\ORM\Mapping\PreUpdate;
use Doctrine\ORM\Mapping\UniqueConstraint;
use function is_int;
use function is_string;
use function max;

/**
 * @property-read int $id
 * @property-read string $username
 * @property-read string $email
 * @property-read string $password
 * @property-read ?int $attachment_id
 * @property-read string $first_name
 * @property-read ?string $last_name
 * @property-read string $role
 * @property-read string $status
 * @property-read ?string $security_key
 * @property-read ?string $auth_key
 * @property-read DateTimeInterface $created_at
 * @property-read DateTimeInterface $updated_at
 * @property-read ?DateTimeInterface $deleted_at
 */
#[UniqueConstraint(
    name: 'unique_username',
    columns: ['username']
)]
#[UniqueConstraint(
    name: 'unique_email',
    columns: ['email']
)]
#[UniqueConstraint(
    name: 'unique_identity_number',
    columns: ['identity_number']
)]
#[HasLifecycleCallbacks]
abstract class AbstractUser extends AbstractEntity implements
    UserEntityInterface,
    ContainerAllocatorInterface,
    ManagerAllocatorInterface
{
    use UserStatusTrait,
        ContainerAllocatorTrait,
        PasswordTrait,
        ManagerAllocatorTrait;

    const DEFAULT_AUTH_LENGTH = 32;
    const MINIMUM_AUTH_LENGTH = 10;

    const AUTH_PERIOD = 30;

    // MAXIMUM AUTH PERIOD is 10 minutes
    const MAX_AUTH_PERIOD = 600;

    #[Id]
    #[GeneratedValue('AUTO')]
    #[Column(
        name: 'id',
        type: Types::BIGINT,
        length: 20,
        updatable: false,
        options: [
            'unsigned' => true,
            'comment' => 'User id'
        ]
    )]
    protected int $id;


    #[Column(
        name: 'identity_number',
        type: Types::STRING,
        length: 255,
        unique: true,
        nullable: true,
        updatable: true,
        options: [
            'default' => null,
            'comment' => 'Unique identity number'
        ]
    )]
    protected ?string $identity_number = null;

    #[Column(
        name: 'username',
        type: Types::STRING,
        length: 255,
        unique: true,
        nullable: false,
        updatable: true,
        options: [
            'comment' => 'Unique username'
        ]
    )]
    protected string $username;

    #[Column(
        name: 'email',
        type: Types::STRING,
        length: 320,
        unique: true,
        nullable: false,
        updatable: true,
        options: [
            'comment' => 'Unique email'
        ]
    )]
    protected string $email;

    #[Column(
        name: 'password',
        type: Types::STRING,
        length: 255,
        unique: false,
        nullable: false,
        updatable: true,
        options: [
            'comment' => 'User password'
        ]
    )]
    protected string $password;

    #[Column(
        name: 'attachment_id',
        type: Types::BIGINT,
        length: 20,
        nullable: true,
        options: [
            'default' => null,
            'unsigned' => true,
            'comment' => 'User avatar'
        ]
    )]
    protected ?int $attachment_id = null;

    #[Column(
        name: 'first_name',
        type: Types::STRING,
        length: 128,
        unique: false,
        nullable: false,
        updatable: true,
        options: [
            'comment' => 'User first name'
        ]
    )]
    protected string $first_name;

    #[Column(
        name: 'last_name',
        type: Types::STRING,
        length: 128,
        unique: false,
        nullable: true,
        updatable: true,
        options: [
            'default' => null,
            'comment' => 'Nullable user last name'
        ]
    )]
    protected ?string $last_name = null;

    #[Column(
        name: 'role',
        type: Types::STRING,
        length: 128,
        unique: false,
        nullable: true,
        updatable: true,
        options: [
            'comment' => 'User role / capability or user type'
        ]
    )]
    protected ?string $role = null;

    #[Column(
        name: 'status',
        type: Types::STRING,
        length: 64,
        unique: false,
        nullable: false,
        updatable: true,
        options: [
            'comment' => 'User status'
        ]
    )]
    protected string $status;

    #[Column(
        name: 'security_key',
        type: Types::STRING,
        length: 128,
        unique: false,
        nullable: true,
        updatable: true,
        options: [
            'default' => null,
            'comment' => 'Per user security key'
        ]
    )]
    protected ?string $security_key = null;

    #[Column(
        name: 'auth_key',
        type: Types::STRING,
        length: 128,
        unique: false,
        nullable: true,
        updatable: true,
        options: [
            'default' => null,
            'comment' => 'Per user auth key for totp'
        ]
    )]
    protected ?string $auth_key = null;

    #[Column(
        name: 'created_at',
        type: Types::DATETIME_IMMUTABLE,
        updatable: false,
        options: [
            'default' => 'CURRENT_TIMESTAMP',
            'comment' => 'User created time'
        ]
    )]
    protected DateTimeInterface $created_at;

    #[Column(
        name: 'updated_at',
        type: Types::DATETIME_IMMUTABLE,
        unique: false,
        updatable: false,
        options: [
            'attribute' => 'ON UPDATE CURRENT_TIMESTAMP',
            'default' => '0000-00-00 00:00:00',
            'comment' => 'User updated time'
        ],
        // columnDefinition: "DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP"
    )]
    protected DateTimeInterface $updated_at;

    #[Column(
        name: 'deleted_at',
        type: Types::DATETIME_IMMUTABLE,
        nullable: true,
        options: [
            'default' => null,
            'comment' => 'User deleted time'
        ]
    )]
    protected ?DateTimeInterface $deleted_at = null;

    /**
     * Protect behavior data
     * @see jsonSerialize()
     * @var array<string> $entityBlackListedFields
     */
    protected array $entityBlackListedFields = [
        'password',
        'email',
        'security_key',
        'auth_key'
    ];

    public function __construct()
    {
        $this->created_at = new DateTimeImmutable();
        $this->updated_at = new DateTimeImmutable('0000-00-00 00:00:00');
        $this->deleted_at = null;
        $this->role = null;
        $this->attachment_id = null;
        $this->identity_number = null;
        $this->security_key = RandomString::char(128);
    }

    public static function generateAuthCode(
        string|int|float $prefix = ''
    ): string {
        $length = static::DEFAULT_AUTH_LENGTH;
        /** @noinspection PhpConditionAlreadyCheckedInspection */
        $length = is_int($length) ? $length : self::DEFAULT_AUTH_LENGTH;
        $length = max($length, self::MINIMUM_AUTH_LENGTH);
        return Authenticator::generateRandomCode($length, $prefix);
    }

    public function isUseAuthKey(): bool
    {
        $auth = $this->getAuthKey();
        return is_string($auth) && trim($auth) !== '';
    }

    public function isValidAuthKey(string $code): bool
    {
        if (!$this->isUseAuthKey()) {
            return false;
        }
        $period = static::AUTH_PERIOD;
        /** @noinspection PhpConditionAlreadyCheckedInspection */
        $period = ! is_int($period) ? self::AUTH_PERIOD : $period;
        $period = max(self::AUTH_PERIOD, $period);
        // set maximum period
        $period = min(self::MAX_AUTH_PERIOD, $period);
        $authKey = trim($this->getAuthKey());
        return Authenticator::authenticate(
            $authKey,
            $code,
            period: $period
        );
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): void
    {
        $username = trim($username);
        if ($username === '') {
            throw new InvalidUsernameException(
                $username,
                'Username could not be empty or whitespace only.'
            );
        }
        $this->username = $username;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $email = trim($email);
        if (StringFilter::filterEmailCommon($email) === false) {
            throw new InvalidEmailException(
                $email,
                sprintf(
                    '%s is not valid email address.',
                    $email
                )
            );
        }

        $this->email = $email;
    }

    public function getIdentityNumber(): ?string
    {
        return $this->identity_number;
    }

    public function setIdentityNumber(?string $identity_number): void
    {
        if (is_string($identity_number)) {
            $identity_number = trim($identity_number);
            $identity_number = $identity_number === '' ? null : $identity_number;
        }
        $this->identity_number = $identity_number;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): void
    {
        $this->password = $password;
    }

    public function getAttachmentId(): ?int
    {
        return $this->attachment_id;
    }

    public function setAttachmentId(?int $attachment_id): void
    {
        $this->attachment_id = $attachment_id;
    }

    public function getFirstName(): string
    {
        return $this->first_name;
    }

    public function setFirstName(string $firstName) : void
    {
        $this->first_name = trim($firstName);
    }

    public function getLastName(): ?string
    {
        return $this->last_name;
    }

    public function setLastName(?string $lastname) : void
    {
        $this->last_name = $lastname ? trim($lastname) : null;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role) : void
    {
        $this->role = trim($role);
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status) : void
    {
        $this->status = trim($status);
    }

    public function getSecurityKey(): ?string
    {
        return $this->security_key;
    }

    public function setSecurityKey(?string $securityKey) : void
    {
        $this->security_key = $securityKey;
    }

    public function getAuthKey(): ?string
    {
        return $this->auth_key;
    }

    public function setAuthKey(?string $authKey) : void
    {
        $this->auth_key = $authKey ? trim($authKey) : null;
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->created_at;
    }

    public function getUpdatedAt(): DateTimeInterface
    {
        return $this->updated_at;
    }

    public function getDeletedAt(): ?DateTimeInterface
    {
        return $this->deleted_at;
    }

    public function setDeletedAt(?DateTimeInterface $deletedAt) : void
    {
        $this->deleted_at = $deletedAt;
    }

    #[
        PreUpdate,
        PostLoad,
        PrePersist
    ]
    public function passwordCheck(
        PrePersistEventArgs|PostLoadEventArgs|PreUpdateEventArgs $event
    ) : void {
        $this->passwordBasedIdUpdatedAt($event);
    }
}
