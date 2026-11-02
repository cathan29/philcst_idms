# PhilCST ID Monitoring & Issuance Management System

Laravel 12 application for student ID production, verification, and controlled release at the Philippine College of Science and Technology.

## Status

This repository began as an entry/exit scanning prototype. That legacy workflow remains available while a normalized issuance domain is introduced. The foundation now includes login throttling, account deactivation enforcement, roles and permissions, normalized student/enrollment/card tables, status history, locked transition validation, import/audit/settings storage, and interactive administrator provisioning.

Do not use a legacy `id_holders` scan as proof that a physical ID was printed or released.

## Windows setup

```powershell
Copy-Item .env.example .env
composer install
php artisan key:generate
npm install
php artisan migrate
php artisan db:seed
php artisan idms:provision-admin
npm run build
php artisan test
```

Development currently defaults to SQLite. For deployment, configure a dedicated MySQL database and least-privilege user in `.env`, then migrate only after a verified backup.

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=philcst_idms
DB_USERNAME=philcst_idms_app
DB_PASSWORD=replace-with-a-secret
```

Never commit `.env`, real student CSV files, database backups, or credentials. See [docs/architecture.md](docs/architecture.md), [docs/business-rules.md](docs/business-rules.md), and [docs/decisions.md](docs/decisions.md) for scope and unresolved institutional questions.
