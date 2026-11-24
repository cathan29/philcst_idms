# Backup and Recovery Guide

The configured production-style engine is MySQL/MariaDB. The application command also supports SQLite for isolated UAT. Restoration must never be tested against the live database.

## Create and verify a backup

Run from the project directory as a restricted service account:

    php artisan idms:backup

Or select an approved destination:

    php artisan idms:backup --output=storage/app/private/backups/idms-before-deploy.zip

For MySQL/MariaDB, the command runs mysqldump with a private temporary defaults file, a consistent transaction, routines, and triggers. Configure IDMS_MYSQLDUMP_BINARY only when mysqldump is not on PATH or in the expected XAMPP location.

The ZIP contains:

- database/database.sql for MySQL/MariaDB, or database/database.sqlite for SQLite
- files from storage/app/private, excluding backups
- manifest.json with SHA-256 hashes

The command verifies ZIP integrity, every manifest hash, and the database entry before reporting success. It removes an incomplete archive when verification fails. The .env file and credentials are intentionally excluded.

Copy verified archives to approved encrypted off-site storage with access control, retention, immutability/versioning, and monitoring. A local ZIP alone is not a disaster-recovery plan.

## Protect configuration and uploads

- Back up the approved secret-manager entries separately; never add .env to the archive.
- Treat backup archives as sensitive student information.
- Restrict read/write access to the backup service account and recovery custodians.
- Record backup time, archive checksum, host, database, command result, and custodian without logging credentials or row data.
- Define retention and secure destruction with institutional approval.

## Isolated restoration test

1. Obtain recovery approval and provision a separate host, separate database, and separate storage directory.
2. Verify the archive with ZIP integrity tools and the hashes in manifest.json before extraction.
3. Extract into a private temporary directory.
4. Create an empty isolated database with a least-privilege test account.
5. For MySQL/MariaDB, import database/database.sql using the database client's secure credential mechanism. For SQLite, copy database/database.sqlite to the isolated path.
6. Restore private files beneath storage/app/private without overwriting the live directory.
7. Create a new isolated .env from the approved secret source. Set APP_ENV to a non-production recovery/UAT value, APP_DEBUG=false, a separate APP_URL, and the isolated database.
8. Run php artisan migrate:status. Apply only reviewed forward migrations if required; do not use migrate:fresh.
9. Run php artisan optimize:clear, then the smoke tests and UAT checklist.
10. Reconcile row counts, lifecycle samples, private file availability, audit history, and login/authorization.
11. Destroy or securely retain the isolated restoration according to the approved test record.

Do not point the restored application at production queues, mail, external integrations, or the live database.

## Scheduling and recovery objectives

Schedule php artisan idms:backup using the platform scheduler, then transfer and monitor the result. The institution must approve backup frequency, retention, recovery-point objective, recovery-time objective, encryption, off-site location, and restore custodians.

A real verified backup archive was created during Phase 3. An end-to-end restore was not executed because no isolated MySQL restoration host or institutional recovery approval was provided.