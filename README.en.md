# Team Workspace PHP

[Русский](README.md)

**Team Workspace PHP** is a lightweight internal workspace for development teams built for Apache, PHP 8.2 and MySQL 8.0. It provides username/password authentication, role-based access, Kanban-style To-do boards, audit logs, public read-only links and a responsive dark UI.

## Features

- `user`, `developer`, and `founder` roles;
- registration that can be enabled or disabled by the founder;
- To-do boards with categories, tags and tasks;
- drag-and-drop task movement;
- AJAX synchronization every 5 seconds;
- per-board read-only access for regular users;
- public read-only board links;
- paginated To-do audit log;
- board archiving, viewing, editing and restoring;
- admin dashboard with statistics and user management;
- event visibility filtered by permissions;
- responsive dark theme with mobile bottom navigation;
- no Composer dependencies — PHP + PDO MySQL is enough.

## Requirements

- Apache 2.4+
- PHP 8.2+
- MySQL 8.0+
- PHP extensions: `pdo_mysql`, `mbstring`
- Apache `mod_rewrite`
- Argon2id support in PHP

## Quick start

```bash
cp config.example.php config.php
```

Edit `config.php`, then import the database schema:

```bash
mysql -u YOUR_USER -p YOUR_DATABASE < database/schema.sql
```

Create the initial founder account:

```bash
php database/create_founder.php founder 'CHANGE_THIS_PASSWORD'
```

Enable Apache `mod_rewrite` and allow `.htaccess` (`AllowOverride All`). Using `public/` as the DocumentRoot is recommended, although the repository also contains a root front controller.

## Subdirectory deployment

For a deployment such as `/tools/team-workspace`, configure:

```php
'app' => [
    'url' => 'https://example.com',
    'base_path' => '/tools/team-workspace',
],
```

Also adapt `RewriteBase` and `ErrorDocument` paths in the root `.htaccess`.

## Configuration

The real `config.php` is **not tracked by Git**. Copy `config.example.php` and provide environment-specific values locally.

## Roles

- **user** — basic account with read-only access only to explicitly shared boards.
- **developer** — can create tasks, edit owned tasks and move tasks across categories.
- **founder** — unrestricted administrative access, including users, boards, archive and audit log management.

## Security

- Argon2id password hashing;
- `HttpOnly` session cookies;
- CSRF protection for state-changing requests;
- server-side RBAC and ownership checks;
- public boards are strictly read-only;
- `config.php` is ignored by Git;
- no production credentials, infrastructure addresses or private keys are included.

Before publishing your own fork, scan the complete Git history for accidentally committed secrets.

## Project structure

```text
app/              PHP logic and views
database/         schema and initial founder helper
errors/           error pages
public/           public front controller, API and assets
config.example.php
index.php
.htaccess
```

## License

The project is distributed under the standard **Apache License 2.0**. When redistributing the project, retain the [NOTICE](NOTICE) file containing the author copyright and original repository link. See [LICENSE](LICENSE).
