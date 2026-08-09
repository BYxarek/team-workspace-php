# Team Workspace PHP

[English](README.en.md)

[![Version](https://img.shields.io/badge/version-1.0.1-7c5cff)](CHANGELOG.md)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-777bb4?logo=php&logoColor=white)](https://www.php.net/)
[![CI](https://github.com/BYxarek/team-workspace-php/actions/workflows/ci.yml/badge.svg)](https://github.com/BYxarek/team-workspace-php/actions/workflows/ci.yml)

Внутренний портал команды на Apache, PHP 8.2 и MySQL 8.0. Версия **1.0.1**.

## Возможности

- вход по паролю и Passkey (WebAuthn), управление ключами в профиле;
- режим входа только через Passkey и обязательная привязка ключа после регистрации;
- роли `user`, `developer`, `founder` и административная панель;
- To-do доски, категории, теги, drag-and-drop задач и AJAX-синхронизация;
- приватные, выборочные и публичные read-only доски;
- архив, журнал действий, события и статистика пользователей;
- адаптивная тёмная тема с мобильной нижней навигацией.

## Требования

- Apache с `mod_rewrite` и `AllowOverride All`;
- PHP 8.2+ с `pdo_mysql`, `mbstring`, `openssl` и Argon2id;
- MySQL 8.0;
- Composer 2.

## Установка

```bash
git clone https://github.com/BYxarek/team-workspace-php.git
cd team-workspace-php
composer install --no-dev --optimize-autoloader
cp config.example.php config.php
```

1. Заполните `config.php`: `app.url`, `app.base_path` и параметры MySQL.
2. Импортируйте `database/schema.sql`.
3. Создайте первого основателя:

```bash
php database/create_founder.php founder 'CHANGE_THIS_PASSWORD'
```

4. Запустите проверку окружения:

```bash
php deploy-check.php
```

Рекомендуемый DocumentRoot — каталог `public/`. Размещение проекта в подкаталоге также поддерживается корневыми `index.php` и `.htaccess`.

## Обновление

```bash
git pull --ff-only
composer install --no-dev --optimize-autoloader
```

Примените новые SQL-файлы из `database/migrations/` по порядку. Секреты из `config.php` и `docs/dostup.txt` не должны попадать в Git.

## Проверки

```bash
composer validate --strict
composer audit --locked
php tests/passkeys_test.php
```

CI дополнительно проверяет синтаксис всех PHP-файлов.

## Автор

[BYxarek](https://github.com/BYxarek)

## Лицензия

[Apache License 2.0](LICENSE). При распространении сохраняйте файл [NOTICE](NOTICE).
