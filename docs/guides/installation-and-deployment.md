# Installation and Production Deployment

This guide applies to the existing Laravel 12 PHILCST ID Monitoring System. It does not authorize a production deployment or a live database reset.

## Prerequisites

- PHP 8.2 or newer with PDO MySQL, mbstring, OpenSSL, tokenizer, XML/DOM, ctype, fileinfo, GD, and ZIP.
- Composer 2.
- Node.js with npm.
- MySQL or MariaDB and the matching mysqldump utility.
- A supported web server configured to serve the public directory only.
- HTTPS with a trusted certificate for production camera access and secure sessions.

## Initial installation

1. Copy the repository to the application host.
2. Run composer install.
3. Run npm ci, then npm run build.
4. Copy .env.example to .env and set a unique APP_KEY with php artisan key:generate.
5. Configure the approved database and least-privilege database account.
6. Run php artisan migrate. On production, use php artisan migrate --force.
7. Grant the web-server identity write access only to storage and bootstrap/cache.
8. Configure the web root to the public directory.
9. Run php artisan optimize after production environment values are final.

Never run migrate:fresh, db:wipe, or a destructive reset against an existing institutional database.

## Required production environment

Use values appropriate to the deployment; do not copy local defaults unchanged:

    APP_ENV=production
    APP_DEBUG=false
    APP_URL=https://idms.example.edu
    LOG_LEVEL=warning
    SESSION_DRIVER=database
    SESSION_SECURE_COOKIE=true
    SESSION_HTTP_ONLY=true
    SESSION_SAME_SITE=lax
    IDMS_REPORT_PDF_LIMIT=500
    IDMS_SEED_DEMO_HOLDERS=false

Store APP_KEY, database credentials, administrator bootstrap credentials, mail credentials, and backup encryption/storage credentials in the approved secret manager. Do not commit .env.

If production seeding is explicitly approved, set a unique IDMS_SEED_ADMIN_PASSWORD immediately before php artisan db:seed --force, confirm the administrator, rotate/remove the bootstrap secret, and leave IDMS_SEED_DEMO_HOLDERS=false. Normal deployments should not reseed established databases.

## Web and runtime hardening

- Terminate TLS at the trusted reverse proxy or web server and redirect HTTP to HTTPS.
- Do not expose php artisan serve to a network; it is a development server.
- Restrict access to .env, storage, vendor, database dumps, and source-control metadata.
- Run PHP and queue workers as non-administrator service identities.
- Configure trusted proxies correctly so Laravel detects HTTPS.
- Apply host firewall rules and database network restrictions.
- Retain the application security headers. HSTS is emitted only for secure production requests.
- Schedule queue workers and backups under a supervised service account.
- Set log retention and restrict log access because operational identifiers may be present.
- Use a real institutional administrator email; disable unused accounts and apply least-privilege roles.

## Deployment checklist

1. Create and verify an approved backup.
2. Deploy source and run composer install --no-dev --optimize-autoloader.
3. Run npm ci and npm run build.
4. Review php artisan migrate:status; then run php artisan migrate --force.
5. Run php artisan config:cache, route:cache, view:cache, and event:cache as applicable.
6. Run the automated suite in pre-production.
7. Exercise the final UAT checklist, including a real camera and USB scanner.
8. Confirm HTTPS, cookies, rate limiting, file permissions, database privileges, monitoring, and rollback.
9. Obtain institutional acceptance before production cutover.

## Local verification URL

For local-only testing:

    php artisan serve --host=127.0.0.1 --port=8000

Open http://127.0.0.1:8000/login. Never bind the development server to a public interface.