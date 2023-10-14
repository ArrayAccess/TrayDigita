<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Console\Command;

use ArrayAccess\TrayDigita\Collection\Config;
use ArrayAccess\TrayDigita\Container\Interfaces\ContainerAllocatorInterface;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerAllocatorInterface;
use ArrayAccess\TrayDigita\Exceptions\InvalidArgument\InteractiveArgumentException;
use ArrayAccess\TrayDigita\Kernel\Decorator;
use ArrayAccess\TrayDigita\Traits\Container\ContainerAllocatorTrait;
use ArrayAccess\TrayDigita\Traits\Manager\ManagerAllocatorTrait;
use ArrayAccess\TrayDigita\Traits\Service\TranslatorTrait;
use ArrayAccess\TrayDigita\Util\Filter\Consolidation;
use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;
use function array_pop;
use function date;
use function dirname;
use function explode;
use function file_put_contents;
use function implode;
use function is_dir;
use function is_string;
use function ltrim;
use function mkdir;
use function preg_replace;
use function preg_replace_callback;
use function realpath;
use function rtrim;
use function sprintf;
use function str_replace;
use function strlen;
use function strtolower;
use function substr;
use function trim;
use function ucwords;
use const DIRECTORY_SEPARATOR;

class EntityGenerator extends Command implements ContainerAllocatorInterface, ManagerAllocatorInterface
{
    use ContainerAllocatorTrait,
        ManagerAllocatorTrait,
        TranslatorTrait;

    private ?string $entityDir = null;
    private string $entityNamespace;

    public function __construct(string $name = null)
    {
        $entityNamespace = Decorator::kernel()?->getEntityNamespace();
        if (!$entityNamespace) {
            $namespace = dirname(
                str_replace(
                    '\\',
                    '/',
                    __NAMESPACE__
                )
            );
            $appNameSpace = str_replace('/', '\\', dirname($namespace)) . '\\App';
            $entityNamespace = "$appNameSpace\\Entities\\";
        }
        $this->entityNamespace = $entityNamespace;
        parent::__construct($name);
    }

    protected function configure() : void
    {
        $namespace = rtrim($this->entityNamespace, '\\');
        $this
            ->setName('app:generate:entity')
            ->setAliases(['generate-entity'])
            ->setDescription(
                $this->translate('Generate entity class.')
            )->setDefinition([
                new InputOption(
                    'print',
                    'p',
                    InputOption::VALUE_NONE,
                    $this->translate('Print generated class file only')
                )
            ])->setHelp(
                sprintf(
                    $this->translate(<<<EOT
The %s help you to create %s object.

Entity will use prefix namespace with %s

EOT),
                    '<info>%command.name%</info>',
                    'entity',
                    sprintf(
                        '<comment>%s</comment>',
                        $namespace
                    )
                )
            );
    }

    /**
     * @param string $name
     * @return ?array{tableName: string, className:string}
     */
    protected function filterNames(string $name) : ?array
    {
        /** @noinspection DuplicatedCode */
        $name = trim($name);
        $name = ltrim(str_replace(['/', '_', '-'], '\\', $name), '\\');
        $name = preg_replace('~\\\+~', '\\', $name);
        $name = ucwords($name, '\\');
        $name = preg_replace_callback('~_([a-z])~', static function ($e) {
            return ucwords($e[1]);
        }, $name);
        $name = trim($name, '_');
        $tableName = preg_replace('~[\\\_]+~', '_', $name);
        $tableName = preg_replace_callback('~(_[a-zA-Z0-9]|[A-Z0-9])~', static function ($e) {
            return "_".trim($e[1], '_');
        }, $tableName);
        $tableName = strtolower(trim($tableName, '_'));
        return $name !== '' && Consolidation::isValidClassName($name)
            ? [
                'tableName' => $tableName,
                'className' => $name,
            ]
            : null;
    }

    private ?array $entityLists = null;

    private function isFileExists(string $className) : bool
    {
        $className = trim($className, '\\');
        $lowerClassName = strtolower($className);
        if ($this->entityLists === null) {
            $this->entityLists = [];
            $entityDir = $this->entityDir;
            $lengthStart = strlen($entityDir) + 1;
            foreach (Finder::create()
                         ->in($entityDir)
                         ->ignoreVCS(true)
                         ->ignoreDotFiles(true)
                         // depth <= 10
                         ->depth('<= 10')
                         ->name('/^[_A-za-z]([a-zA-Z0-9]+)?\.php$/')
                         ->files() as $file
            ) {
                $realPath = $file->getRealPath();
                $baseClassName = substr(
                    $realPath,
                    $lengthStart,
                    -4
                );
                $class = str_replace('/', '\\', $baseClassName);
                $this->entityLists[strtolower($class)] = $baseClassName;
            }
        }
        return isset($this->entityLists[$lowerClassName]);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return ?array{tableName: string, className:string}
     */
    protected function askClassName(InputInterface $input, OutputInterface $output) : ?array
    {
        $io = new SymfonyStyle($input, $output);
        return $io->ask(
            $this->translate('Please enter entity name'),
            null,
            function ($name) {
                $definitions = $this->filterNames($name);
                if ($definitions === null) {
                    throw new InteractiveArgumentException(
                        $this->translate('Please enter valid entity name!')
                    );
                }
                $tableName = $definitions['tableName'];
                $className = $definitions['className'];
                if (!Consolidation::allowedClassName($className)) {
                    throw new InteractiveArgumentException(
                        sprintf(
                            $this->translate(
                                'Entity [%s] is invalid! class name contain reserved keyword!'
                            ),
                            $className
                        )
                    );
                }
                if (strlen($tableName) > 64) {
                    throw new InteractiveArgumentException(
                        sprintf(
                            $this->translate(
                                'Table name [%s] is too long! Must be less or equal 64 characters'
                            ),
                            $tableName
                        )
                    );
                }
                if ($this->isFileExists($className)) {
                    throw new InteractiveArgumentException(
                        sprintf(
                            $this->translate('Entity [%s] exist'),
                            $this->entityNamespace . $className
                        )
                    );
                }
                return $definitions;
            }
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($output->isQuiet()) {
            return self::INVALID;
        }

        $input->setInteractive(true);
        $container = $this->getContainer();
        $config = ContainerHelper::use(Config::class, $container)??new Config();
        $path = $config->get('path');
        $path = $path instanceof Config ? $path : null;
        $entityDir = $path?->get('entity');
        if (is_string($entityDir) && is_dir($entityDir)) {
            $this->entityDir = realpath($entityDir)??$entityDir;
        }

        if (!$this->entityDir) {
            $output->writeln(
                sprintf(
                    $this->translate(
                        '%s Could not detect entity directory'
                    ),
                    '<fg=red;options=bold>[X]</>'
                )
            );
            return self::FAILURE;
        }

        $named = $this->askClassName($input, $output);
        if (!$named && !$input->isInteractive()) {
            $output->writeln(
                sprintf(
                    $this->translate(
                        '%s generator only support in interactive mode'
                    ),
                    '<fg=red;options=bold>[X]</>'
                )
            );
            return self::FAILURE;
        }

        $fileName = $this->entityDir
            . DIRECTORY_SEPARATOR
            . str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $named['className'])
            . '.php';

        if ($input->getOption('print')) {
            $output->writeln(
                $this->generateEntityContent($named['tableName'], $named['className'])
            );
            return self::SUCCESS;
        }
        $output->writeln(
            sprintf(
                $this->translate(
                    'Entity Name  : %s'
                ),
                sprintf(
                    '<comment>%s</comment>',
                    $named['className']
                )
            )
        );
        $output->writeln(
            sprintf(
                $this->translate(
                    'Table Name  : %s'
                ),
                sprintf(
                    '<comment>%s</comment>',
                    $named['tableName']
                )
            )
        );
        $className = $this->entityNamespace . $named['className'];
        $output->writeln(
            sprintf(
                $this->translate(
                    'Entity Class : %s'
                ),
                sprintf(
                    '<comment>%s</comment>',
                    $className
                )
            )
        );
        $output->writeln(
            sprintf(
                $this->translate(
                    'Entity File  : %s'
                ),
                sprintf(
                    '<comment>%s</comment>',
                    $fileName
                )
            )
        );
        /** @noinspection DuplicatedCode */
        $io = new SymfonyStyle($input, $output);
        $answer = !$input->isInteractive() || $io->ask(
            $this->translate('Are you sure to continue (Yes/No)?'),
            null,
            function ($e) {
                $e = !is_string($e) ? '' : $e;
                $e = strtolower(trim($e));
                $ask = match ($e) {
                    'yes' => true,
                    'no' => false,
                    default => null
                };
                if ($ask === null) {
                    throw new InteractiveArgumentException(
                        $this->translate('Please enter valid answer! (Yes / No)')
                    );
                }
                return $ask;
            }
        );
        if ($answer) {
            if (!is_dir(dirname($fileName))) {
                @mkdir(dirname($fileName), 0755, true);
            }
            $status = @file_put_contents(
                $fileName,
                $this->generateEntityContent(
                    $named['tableName'],
                    $named['className']
                )
            );
            if (!$status) {
                $output->writeln(
                    sprintf(
                        $this->translate(
                            '%s Could not save entity!'
                        ),
                        '<fg=red;options=bold>[X]</>'
                    )
                );
                return self::SUCCESS;
            }
            $output->writeln(
                sprintf(
                    $this->translate(
                        '%s Entity successfully created!'
                    ),
                    '<fg=green;options=bold>[√]</>'
                )
            );
            return self::FAILURE;
        }
        $output->writeln(
            sprintf(
                '<comment>%s</comment>',
                $this->translate('Operation cancelled!')
            )
        );
        return self::SUCCESS;
    }

    private function generateEntityContent(string $tableName, string $className): string
    {
        $classes   = explode('\\', $className);
        $baseClassName = array_pop($classes);
        $namespace = trim($this->entityNamespace, '\\');
        if (!empty($classes)) {
            $namespace .= '\\' . implode('\\', $classes);
        }
        $time = date('c');
        return <<<PHP
<?php
declare(strict_types=1);

namespace $namespace;

use ArrayAccess\TrayDigita\Database\Entities\Abstracts\AbstractEntity;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\HasLifecycleCallbacks;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Index;
use Doctrine\ORM\Mapping\Table;

/**
 * Autogenerated entity : $className
 * @date $time
 */
#[Entity]
#[Table(
    name: self::TABLE_NAME,
    options: [
        'charset' => 'utf8mb4', // remove this or change to utf8 if not use mysql
        'collation' => 'utf8mb4_unicode_ci',  // remove this if not use mysql
        'comment' => 'Table $tableName'
    ]
)]
/*
#[Index(
    columns: ['column_1', 'column_2'],
    name: 'some_index_name'
)]
*/
// doctrine event support
// see @https://www.doctrine-project.org/projects/doctrine-orm/en/2.16/reference/events.html
#[HasLifecycleCallbacks]
class $baseClassName extends AbstractEntity
{
    const TABLE_NAME = '$tableName';
    
    #[Id]
    #[GeneratedValue('AUTO')]
    #[Column(
        name: 'id',
        type: Types::BIGINT,
        length: 20,
        updatable: false,
        options: [
            'unsigned' => true,
            'comment' => 'Primary key'
        ]
    )]
    protected int \$id;

    #[Column(
        name: 'created_at',
        type: Types::DATETIME_MUTABLE,
        updatable: false,
        options: [
            'default' => 'CURRENT_TIMESTAMP',
            'comment' => 'Created at column'
        ]
    )]
    protected DateTimeInterface \$created_at;

    #[Column(
        name: 'updated_at',
        type: Types::DATETIME_IMMUTABLE,
        unique: false,
        updatable: false,
        options: [
            'attribute' => 'ON UPDATE CURRENT_TIMESTAMP', // this column attribute
            'default' => '0000-00-00 00:00:00',
            'comment' => 'Updated at column'
        ],
        // columnDefinition: "DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP"
    )]
    protected DateTimeInterface \$updated_at;

    #[Column(
        name: 'deleted_at',
        type: Types::DATETIME_IMMUTABLE,
        nullable: true,
        options: [
            'default' => null,
            'comment' => 'Deleted at column'
        ]
    )]
    protected ?DateTimeInterface \$deleted_at = null;

    public function getId() : int
    {
        return \$this->id;
    }
    
    public function getCreatedAt(): DateTimeInterface
    {
        return \$this->created_at;
    }

    public function getUpdatedAt(): DateTimeInterface
    {
        return \$this->updated_at;
    }

    public function getDeletedAt(): ?DateTimeInterface
    {
        return \$this->deleted_at;
    }

    public function setDeletedAt(?DateTimeInterface \$deletedAt) : void
    {
        \$this->deleted_at = \$deletedAt;
    }
}

PHP;
    }
}
