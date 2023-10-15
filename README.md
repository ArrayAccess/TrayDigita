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

## Note

constant ```TD_APP_DIRECTORY``` ~~must be declared~~ on console or index loader.


## Installation

Create project using composer

```bash
composer create-project arrayaccess/traydigita --prefer-dist --stability=dev example.com
```

- copy [config.example.php](config.example.php) to `config.php`
- create database & put info `config.php`
- Doing install database schema via command line

```bash
php bin/tray-digita app:db --schema --execute
```

then follow the steps

## Console & Generator

Read the : **[Console](CONSOLE.md)**

## Translation

Read the : **[Translation](TRANSLATION.md)**

## Coding Standard

Read the : **[Coding Standard](CODING_STANDARD.md)**
