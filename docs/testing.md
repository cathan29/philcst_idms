# Testing

Run `php artisan test` and `vendor\bin\pint --test` on Windows. Critical suites cover transition rules, QR interpretation, deactivation, authorization, duplicate students, imports, release concurrency, rollback, and reports.

Concurrency tests must run against MySQL because SQLite does not reproduce InnoDB row locks. Browser/device interaction and representative large-file benchmarks remain deployment gates.
