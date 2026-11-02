# Database design

- `students`: stable identity; unique string student number; separate name components.
- `programs`, `academic_years`, `student_enrollments`: normalized academic history.
- `id_cards`: one row per physical issuance, including replacements and an opaque UUID token.
- `id_status_histories`: append-only transition evidence.
- `scanner_devices`: station identity and purpose; activity is not proof of online presence.
- `import_batches`, `import_errors`: private-file provenance and row outcomes.
- `roles`, `permissions`, pivots: server-side authorization.
- `audit_logs`, `system_settings`: accountability and validated configuration.

Legacy `id_holders` and legacy `scan_logs` are retained to avoid destructive migration. A reviewed migration plan is required before retirement.
