# Student CSV Import Guide

Only users with imports.manage may import students. Use an isolated database for practice and never use fictional records in the live normalized lifecycle.

## Prepare the system

1. In Settings, confirm the academic year, academic term, and authorized programs.
2. Open Student Records and choose Import CSV.
3. Download the application template rather than recreating the headers manually.
4. Select the academic term and, if appropriate, restrict the import to one program.

The file must be UTF-8 CSV and no larger than the configured limit (25 MB by default).

## Columns

Required mapped columns:

- student_number
- first_name
- last_name
- program
- year_level

Supported optional columns:

- middle_name
- suffix
- status
- section
- academic_year
- semester

The template contains student_number, first_name, middle_name, last_name, suffix, program, year_level, section, academic_year, and semester. Status defaults to active when omitted. Accepted statuses are active, inactive, graduated, and withdrawn.

Program values must match an active configured program. Academic year and semester values, when supplied, must match the selected term. Student numbers must be unique within the file and in the normalized student table.

## Preview, validate, and commit

1. Upload the CSV. The application stores it privately and displays a preview.
2. Review or correct the column mapping. A source column cannot map to multiple fields.
3. Review the validation summary and row errors.
4. Choose Insert only to preserve existing student identity data, or Insert/update to apply reviewed identity and enrollment changes.
5. Confirm once. The one-use confirmation token prevents accidental repeat submissions.
6. Review inserted, updated, unchanged, duplicate, and rejected counts.
7. Download the error CSV when rows need correction.

The commit is transactional. A failed commit does not leave a partially completed import. Each completed batch records the uploader, file hash, term, mapping, mode, counts, and a non-PII audit summary. Result and error downloads are restricted to the uploader.

## Duplicate and retry behavior

- Duplicate student numbers within one CSV are rejected.
- Existing students are matched by student_number.
- Insert only can add a missing enrollment without overwriting existing identity fields.
- Insert/update changes only the reviewed normalized fields and term enrollment.
- A consumed or expired confirmation cannot be replayed.
- Correct the source file and create a new preview; do not manipulate import tables directly.

## Privacy and retention

CSV previews and import error data may contain student information and are stored on the private disk, not the public web root. Restrict filesystem and backup access. Establish an institutional retention schedule for preview files, import batches, and row-level errors before production use.