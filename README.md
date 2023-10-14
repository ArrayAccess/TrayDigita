# TRAY DIGITA


## Requirements

- php 8.2 or later
- ext-curl
- ext-pdo
- ext-json
- ext-openssl
- ext-pdo
- ext-gd
- ext-pdo_mysql
- ext-fileinfo

Also see [composer.json](composer.json)

## NOTE

constant ```TD_APP_DIRECTORY``` must be declared on console or index loader.


## INSTALL

- copy [config.example.php](config.example.php) to `config.php`
- create database & put info `config.php`
- Doing install database schema via command line

```bash
php bin/tray-digita app:db --schema --execute
```

then follow the steps

## CONSOLE & GENERATOR

see [bin/tray-digita](bin/tray-digita)

```txt
app:check                          Check & validate application.
app:db                             [app:database] Check & validate database.
app:generate:checksums             [generate-checksums] Create list of core file checksums.
app:generate:command               [generate-command] Generate command class.
app:generate:controller            [generate-controller] Generate controller class.
app:generate:database-event        [generate-database-event] Generate database event class.
app:generate:entity                [generate-entity] Generate entity class.
app:generate:middleware            [generate-middleware] Generate middleware class.
app:generate:module                [generate-module] Generate module class.
app:generate:scheduler             [generate-scheduler] Generate scheduler class.
app:scheduler                      [run-scheduler] List or run scheduler.
app:server                         [server] Create temporary php builtin web server.
```

### DEVELOPMENT / BUILTIN WEB SERVER

Create / Start temporary builtin web server

```bash
php bin/tray-digita app:server
```

### APPLICATION VALIDATION

Check about application runtime

```bash
php bin/tray-digita app:check
```

### DATABASE CONSOLE

Check database configurations & available entities

```bash
php bin/tray-digita app:db
```

Check the database schema entity

```bash
php bin/tray-digita app:db --schema
```


Print / Show SQL query changed schema to console

```bash
php bin/tray-digita app:db --schema --print
```

Print / Show SQL query created schema entities table to console

```bash
php bin/tray-digita app:db --schema --print --dump
```

Optimize table on database (MySQL Platform Only)

```bash
php bin/tray-digita app:db --schema --optimize
```

### GENERATOR

> Controller Generator

Generate controller (placed into `app/Controllers`)

```bash
php bin/tray-digita app:generate:controller
```


> Entity Generator

Generate entity (placed into `app/Entities`)

```bash
php bin/tray-digita app:generate:entity
```


> Command Generator

Generate Command (placed into `app/Commands`)

```bash
php bin/tray-digita app:generate:command
```


> Middleware Generator

Generate middleware (placed into `app/Middlewares`)

```bash
php bin/tray-digita app:generate:middleware
```


> Scheduler Generator


Generate scheduler  (placed into `app/Schedulers`)

```bash
php bin/tray-digita app:generate:scheduler
```

> Module Generator


Generate module  (placed into `app/Modules`)

```bash
php bin/tray-digita app:generate:module
```


> Database Event Generator


Generate database event  (placed into `app/DatabaseEvents`)

```bash
php bin/tray-digita app:generate:database-event
```


> File Checksums Generator

Generate list of integrity / checksums files (placed into `checksums/`)

```bash
php bin/tray-digita app:generate:checksums
```

> Scheduler

List the queued / skipped tasks

```bash
php bin/tray-digita app:scheduler
```

Run the tasks

```bash
php bin/tray-digita app:scheduler --run
```

## CODING STANDARD

Read the : **[CODING_STANDARD](CODING_STANDARD.md)**