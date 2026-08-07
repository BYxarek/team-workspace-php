# Team Workspace PHP

[English](README.en.md)

**Team Workspace PHP** — лёгкий внутренний портал для команды разработки на Apache, PHP 8.2 и MySQL 8.0. Проект включает авторизацию по логину/паролю, роли, Kanban/To-do доски, аудит действий, публичные read-only ссылки и адаптивный тёмный интерфейс.

## Возможности

- роли `user`, `developer`, `founder`;
- регистрация, которую founder может включать и отключать;
- To-do доски с категориями, тегами и задачами;
- drag-and-drop задач между категориями;
- AJAX-синхронизация каждые 5 секунд;
- read-only доступ обычных пользователей к выбранным доскам;
- публичные read-only ссылки на отдельные доски;
- журнал действий To-do с пагинацией;
- архивирование, просмотр, редактирование и восстановление досок;
- административная панель со статистикой и управлением пользователями;
- фильтрация событий по правам доступа;
- адаптивная тёмная тема и мобильная нижняя навигация;
- без Composer-зависимостей — достаточно PHP + PDO MySQL.

## Требования

- Apache 2.4+
- PHP 8.2+
- MySQL 8.0+
- PHP extensions: `pdo_mysql`, `mbstring`
- `mod_rewrite`
- поддержка Argon2id в PHP

## Быстрый запуск

```bash
cp config.example.php config.php
```

Отредактируйте `config.php`, затем импортируйте схему:

```bash
mysql -u YOUR_USER -p YOUR_DATABASE < database/schema.sql
```

Создайте первого founder:

```bash
php database/create_founder.php founder 'CHANGE_THIS_PASSWORD'
```

Для Apache включите `mod_rewrite` и разрешите `.htaccess` (`AllowOverride All`). Рекомендуемый DocumentRoot — каталог `public/`, но проект также содержит front controller для запуска из корня.

## Установка в подкаталог

Если приложение размещено, например, в `/tools/team-workspace`, задайте:

```php
'app' => [
    'url' => 'https://example.com',
    'base_path' => '/tools/team-workspace',
],
```

Также адаптируйте `RewriteBase` и `ErrorDocument` в корневом `.htaccess` под ваш подкаталог.

## Конфигурация

Реальный `config.php` **не хранится в Git**. Используйте только `config.example.php` как шаблон.

Основные параметры:

```php
'app' => [
    'name' => 'Team Workspace',
    'url' => 'https://example.com',
    'base_path' => '',
],
'database' => [
    'host' => '127.0.0.1',
    'port' => 3306,
    'database' => 'team_workspace',
    'username' => 'team_workspace',
    'password' => 'CHANGE_ME',
],
```

## Модель прав

- **user** — базовый пользователь; видит только выданные ему To-do доски в read-only режиме.
- **developer** — создаёт задачи, редактирует свои задачи и перемещает задачи между категориями.
- **founder** — полный административный доступ, включая управление пользователями, досками, архивом и журналом.

## Безопасность

- пароли хешируются Argon2id;
- сессии работают через `HttpOnly` cookie;
- CSRF-защита для изменяющих запросов;
- серверные RBAC/ownership-проверки;
- публичные ссылки работают только в read-only режиме;
- `config.php` исключён из Git;
- реальные пароли, адреса инфраструктуры и ключи в репозитории отсутствуют.

Перед публикацией собственной версии рекомендуется дополнительно проверить историю Git на случай случайно закоммиченных секретов.

## Структура

```text
app/              PHP-логика и views
database/         схема БД и создание первого founder
errors/           страницы ошибок
public/           публичный front controller, API и assets
config.example.php
index.php
.htaccess
```

## Лицензия

Проект распространяется по стандартной **Apache License 2.0**. При распространении сохраняйте файл [NOTICE](NOTICE), содержащий копирайт автора и ссылку на оригинальный репозиторий. См. [LICENSE](LICENSE).
