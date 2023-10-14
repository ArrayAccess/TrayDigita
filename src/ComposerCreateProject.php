<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita;

use Exception;
use function class_exists;
use function dirname;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function getcwd;
use function in_array;
use function is_link;
use function preg_match;
use function random_bytes;
use function sha1;
use function sprintf;
use function str_replace;
use const DIRECTORY_SEPARATOR;
use const PHP_SAPI;

class ComposerCreateProject
{
    /**
     * @noinspection PhpMissingReturnTypeInspection
     * @noinspection HtmlRequiredLangAttribute
     * @noinspection PhpUnused
     */
    public static function composerDoCreateProject($event)
    {
        if (!in_array(PHP_SAPI, ['cli', 'phpdbg', 'embed'], true)) {
            if (!class_exists(Web::class)) {
                require_once __DIR__ .'/Web.php';
            }
            Web::showError(
                new Exception("Application can running on through cli only!")
            );
            exit(255);
        }

        /**
         * @noinspection PhpUndefinedClassInspection
         * @noinspection PhpFullyQualifiedNameUsageInspection
         * @noinspection PhpUndefinedNamespaceInspection
         */
        if (!$event instanceof \Composer\Script\Event) {
            return;
        }
        // only allow post create project
        if ($event->getName() !== 'post-create-project-cmd') {
            return;
        }
        $consoleIO = $event->getIO();
        $installDir = getcwd();
        ///*
        // $package = $event->getComposer()->getPackage();
        // $event->getComposer()->getInstallationManager()->getInstallPath($package);
        $prefixUnix = DIRECTORY_SEPARATOR === '/'
            ? "#!/usr/bin/env php\n"
            : '';
        $vendorDir = $event->getComposer()->getConfig()->get('vendor-dir');
        if (!$vendorDir) {
            return;
        }
        $vendor = rtrim($vendorDir, '\\/');
        $installDir = rtrim($installDir, '\\/');
        $vendor = str_replace('\\', '/', $vendor);
        $installDir = str_replace('\\', '/', $installDir);
        if (str_starts_with($vendor, $installDir . '/')) {
            return;
        }
        $vendor = substr($vendor, strlen($installDir) + 1);
        $vendor = addcslashes($vendor, "'");
        $createFiles = [
            'data/README.md' => <<<MD
## DATA STORAGE

This directory contains permanent data.

MD,
            'storage/README.md' => <<<MD
## TEMPORARY STORAGE

This directory contains temporary data storage directory

MD,
            'app/Commands/README.md' => <<<MD
## CONSOLE COMMANDS DIRECTORY

Commands directory for application console.

The command file will autoload into application console.

Can be generated with:

```bash
php bin/tray-digita app:generate:command
```

MD,
            'app/Controllers/README.md' => <<<MD
## CONTROLLER DIRECTORY

Controller directory for route controller.

The controllers file on controllers directory will autoload via kernel init.

Can be generated with:

```bash
php bin/tray-digita app:generate:controller
```

MD,
            'app/DatabaseEvents/README.md' => <<<MD
## DATABASE EVENTS DIRECTORY

Database directory for database events.

Can be generated with:

```bash
php bin/tray-digita app:generate:database-event
```

MD,
            'app/Entities/README.md' => <<<MD
## ENTITIES DIRECTORY

Entity directory for entity collection.

The entity file on entities directory will autoload via connection object.

Can be generated with:

```bash
php bin/tray-digita app:generate:entity
```

MD,
            'app/Languages/README.md' => <<<MD
## LANGUAGES DIRECTORY

Languages directory contain various language files (po/mo).

> NOTE

if no prefixed with text domain, it will loaded text domain as : `default`
MD,
            'app/Middlewares/README.md' => <<<MD
## MIDDLEWARES DIRECTORY

Middlewares directory for application middleware.

Middleware will automatically load.

Can be generated with:

```bash
php bin/tray-digita app:generate:middleware
```

MD,
            'app/Migrations/README.md' => <<<MD
## MIGRATIONS DIRECTORY

Migrations directory, this will work when you install doctrine migrations

`doctrine/migrations@^3.6`
MD,
            'app/Modules/README.md' => <<<MD
## MODULES DIRECTORY

Modules directory this will autoload via kernel init.


Can be generated with:

```bash
php bin/tray-digita app:generate:module
```

MD,
            'app/Schedulers/README.md' => <<<MD
## SCHEDULERS DIRECTORY

Schedulers directory contains various scheduler object & will autoload.

Can be generated with:

```bash
php bin/tray-digita app:generate:scheduler
```

MD,
            'app/Views/README.md' => <<<MD
## VIEWS DIRECTORY

Directory to handle view
MD,
            'app/Views/base.twig' => <<<'TWIG'
<!DOCTYPE html>
<html{{ html_attributes() }}>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ title??'' }}</title>
    {{ content_header() }}
{% block head %}{% endblock %}
</head>
<body{{ body_attributes() }}>
<div id="page" class="page-wrapper">
{{ body_open() }}
{% block body %}{% endblock %}
{{ body_close() }}
</div>
<!-- #page -->
{{ content_footer() }}
</body>
</html>
TWIG,
            'app/Views/errors/404.twig' => <<<'TWIG'
{% extends "base.twig" %}
{% if title is not defined %}
    {% set title = translate('404 page not found') %}
{% endif %}
{% block body %}
    <div id="content" class="container page-error error-404">
        <header>
            <h1 class="page-title">404</h1>
        </header>
        <div class="content">
            {{ translate('The page you requested was not found.') }}
        </div>
    </div>
{% endblock %}
TWIG,
            'app/Views/errors/500.twig' => <<<'TWIG'
{% extends "base.twig" %}
{% block body %}
<div class="exception">
    <h1 class="title-error-code">500</h1>
    <h2>{{ translate('Internal Server Error') }}</h2>
    <code>{{ exception.getMessage()|protect_path }}</code>
    {% if (displayErrorDetails ?? null) %}
        <pre>{{ exception.getTraceAsString()|protect_path }}</pre>
    {% endif %}
</div>
{% endblock %}
TWIG,
            // htaccess
            'public/.htaccess' => <<<APACHECONF
# .htaccess place in document root (public/)
# Disable directory browsing
Options -Indexes
# Handle 403
ErrorDocument 403 /index.php

# Deny access to dot files
<FilesMatch "^\.">
    Order allow,deny
    Deny from all
</FilesMatch>

# Rewrite
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^index\.php$ - [L]
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule . /index.php [L]
</IfModule>

APACHECONF,
            // public index file
            'public/index.php' => <<<PHP
<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Root\Public;

use ArrayAccess\TrayDigita\Web;
use function dirname;
use function define;
use const DIRECTORY_SEPARATOR;

// phpcs:disable PSR1.Files.SideEffects
return (function () {
    // define app directory
    define('TD_APP_DIRECTORY', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app');

    // define current index file
    define('TD_INDEX_FILE', __FILE__);

    // require autoloader
    require dirname(__DIR__) .'/$vendor/autoload.php';

    // should use return to builtin web server running properly
    return Web::serve();
})();

PHP,
            'bin/tray-digita' => <<<PHP
$prefixUnix<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Root\Bin;

use ArrayAccess\TrayDigita\Bin;
use function dirname;
use const DIRECTORY_SEPARATOR;

(function() {
    // define app directory
    define('TD_APP_DIRECTORY', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app');

    // define current index file
    define('TD_INDEX_FILE', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'index.php');

    require dirname(__DIR__) .'/$vendor/autoload.php';
    Bin::run();
    exit(255);
})();

PHP
        ];
        $consoleIO->write('<info>Preparing to create application structures</info>');
        if (!file_exists($installDir . DIRECTORY_SEPARATOR . 'config.php')) {
            if (!is_dir($installDir)) {
                mkdir($installDir, 0755, true);
            }
            $configFile = file_get_contents(dirname(__DIR__) .'/config.example.php');
            $configFile = str_replace(
                [
                    'random_secret_key',
                    'random_salt_key',
                    'random_nonce_key',
                ],
                [
                    sha1(random_bytes(16)),
                    sha1(random_bytes(16)),
                    sha1(random_bytes(16)),
                ],
                $configFile
            );
            $consoleIO->write(
                sprintf(
                    '[GENERATING CONFIG] <comment>%s</comment>',
                    $installDir . '/config.php'
                ),
                true,
                $consoleIO::VERBOSE
            );
            file_put_contents($installDir . '/config.php', $configFile);
        }
        foreach ($createFiles as $pathName => $content) {
            $path = $installDir . DIRECTORY_SEPARATOR . $pathName;
            if (file_exists($path)) {
                $isConsole = $pathName === 'bin/tray-digita';
                if (!$isConsole || is_link($path)) {
                    continue;
                }
                $consoleContent = file_get_contents($path);
                // check if by default
                if (preg_match('~((?i)define)\(\s*[\"\']TD_~', $consoleContent)) {
                    continue;
                }
                unset($consoleContent);
            }

            if (!is_dir(dirname($path))) {
                $consoleIO->write(
                    sprintf(
                        '[CREATING DIRECTORY] <comment>%s</comment>',
                        dirname($path)
                    ),
                    true,
                    $consoleIO::VERBOSE
                );
                mkdir(dirname($path), 0755, true);
            }
            $consoleIO->write(
                sprintf('[WRITING FILE] <comment>%s</comment>', $path),
                true,
                $consoleIO::VERBOSE
            );
            file_put_contents($path, $content);
        }
        // */
    }
}
