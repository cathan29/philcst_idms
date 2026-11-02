# Architecture

PhilCST IDMS is a Laravel-first monolith using Blade, server-side pagination, Eloquent, queued jobs where file size justifies them, and MySQL in production. The browser-based scan station initially accepts keyboard-emulated QR input; a scan verifies identity but never confirms printing or release.

Authentication and permission middleware protect student registry, imports, production, release, reports, settings, and audit functions. Stable identity lives in `students`; term-specific details live in `student_enrollments`; every physical issuance lives in `id_cards`. `id_status_histories` is append-only. Services own business rules so controllers remain transport-oriented.

CSV validation and mapping precede any write. Production staff explicitly confirm printing and readiness. Releasing staff verify the claimant and explicitly confirm release. Sensitive transitions use a transaction and row lock.

Production requires PHP 8.2+, supported MySQL, HTTPS, private import storage, queue workers for approved large imports, scheduler execution, restricted filesystem permissions, and tested encrypted backups. SQLite is development-only.
