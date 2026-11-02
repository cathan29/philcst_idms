# Semester-based data architecture

## Domain boundaries

- `students` stores permanent identity keyed by the official `student_number` string.
- `academic_years` and `academic_terms` provide controlled academic context. A term belongs to one academic year and has a code, display name, optional dates, and active/closed status.
- `student_enrollments` stores program, year level, section, and status for one student and term. Imports update this record idempotently and never infer year-level progression.
- `id_cards` and `id_status_histories` remain independent physical issuance records. Enrollment imports do not create cards or change print/release history.
- `import_batches` records the selected term, optional coverage program, mapping, mode, and row totals. `import_errors` stores rejected-row diagnostics.

## Import workflow

The operator selects an active academic term and either all-program or specific-program coverage before uploading. CSV parsing uses `SplFileObject`, validates UTF-8 and headers, preserves student numbers as strings, and accepts configured aliases. Academic-year or semester columns are optional; when present, they must agree with the selected term.

Preview and validation do not write student data. Confirmation uses a one-time token. Commit runs in a transaction, matches identities by student number, and creates or updates only the selected term enrollment according to import mode. Missing students in a later file are never deleted.

The Laravel limit is configurable through `IDMS_IMPORT_MAX_KB` and defaults to 25 MB. The deployed PHP `upload_max_filesize` and `post_max_size` must exceed that value; the verified XAMPP values are currently 40 MB each. Temporary CSVs use Laravel's private local disk.

## Registry and history

The registry supports academic-year and academic-term filters. Term totals count unique students, and rows show the enrollment selected by the term filter. Student details show enrollment history separately from physical ID issuance history.

## Institutional decision still required

PhilCST must confirm whether a physical ID is semester-specific, academic-year-specific, or reusable. Until confirmed, the application deliberately does not enforce one-card-per-term and does not create a card from an enrollment import.