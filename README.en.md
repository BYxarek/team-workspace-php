# Team Workspace PHP

[Русский](README.md)

[![Version](https://img.shields.io/badge/version-1.0.0-7c5cff)](CHANGELOG.md)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-777bb4?logo=php&logoColor=white)](https://www.php.net/)
[![CI](https://github.com/BYxarek/team-workspace-php/actions/workflows/ci.yml/badge.svg)](https://github.com/BYxarek/team-workspace-php/actions/workflows/ci.yml)

A self-hosted team workspace built with Apache, PHP 8.2 and MySQL 8.0. Current version: **1.0.0**.

## Features

- password and Passkey (WebAuthn) authentication with key management;
- Passkey-only mode and mandatory key setup after registration;
- `user`, `developer`, and `founder` roles with an admin panel;
- Kanban-style To-do boards, categories, tags, drag-and-drop and AJAX sync;
- private, selectively shared and public read-only boards;
- archive, audit log, events and user statistics;
- responsive dark UI with mobile bottom navigation.

## Requirements

- Apache with `mod_rewrite` and `AllowOverride All`;
- PHP 8.2+ with `pdo_mysql`, `mbstring`, `openssl` and Argon2id;
- MySQL 8.0;
- Composer 2.

## Install

```bash
git clone https://github.com/BYxarek/team-workspace-php.git
cd team-workspace-php
composer install --no-dev --optimize-autoloader
cp config.example.php config.php
```

Configure `app.url`, `app.base_path` and MySQL in `config.php`, import `database/schema.sql`, then create the first founder:

```bash
php database/create_founder.php founder 'CHANGE_THIS_PASSWORD'
php deploy-check.php
```

Using `public/` as Apache DocumentRoot is recommended. Root `index.php` and `.htaccess` also support subdirectory deployments.

## Update

```bash
git pull --ff-only
composer install --no-dev --optimize-autoloader
```

Apply new SQL files from `database/migrations/` in order. Never commit `config.php` or `docs/dostup.txt`.

## Checks

```bash
composer validate --strict
composer audit --locked
php tests/passkeys_test.php
```

## Author

[BYxarek](https://github.com/BYxarek)

## License

[Apache License 2.0](LICENSE). Keep [NOTICE](NOTICE) when redistributing the project.
