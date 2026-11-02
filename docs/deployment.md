# Deployment

Provision HTTPS, PHP 8.2+, MySQL, built assets, a least-privilege database user, private storage, queue workers, scheduler execution, log rotation, and monitored backups. Set `APP_ENV=production`, `APP_DEBUG=false`, secure cookies, institutional URL/timezone, and a generated application key.

Before migration: stop writes, back up files and database, verify restore separately, test staging, then run `php artisan migrate --force`. Provision the administrator interactively. Never seed known credentials.
