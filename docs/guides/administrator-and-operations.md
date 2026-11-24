# Administrator and Operational User Guide

## Access model

Access is enforced on the server, not only in navigation.

| Role | Intended capability |
|---|---|
| Administrator | Student, import, lifecycle, report, settings, user, and audit administration |
| ID Production Staff | View students/cards, create production requests, and perform printing transitions |
| Releasing Staff | View students/cards and release eligible IDs |
| Viewer / Auditor | Read students, reports, and audit history |

Use User Management to assign the smallest suitable role and deactivate accounts that no longer require access. The system prevents an administrator from disabling their own active account. Do not share accounts.

## Lifecycle

The normalized operational lifecycle is:

Student record → Pending → For Printing → Printed → Ready for Release → Released

For Printing is the system's equivalent of in-production. On Hold, Cancelled, and Revoked are controlled exception states. Reprints and replacements create separate linked card records; prior history is retained.

## Daily workflow

1. Create or import the student and enrollment for the correct academic term.
2. Open Repro Table / ID Production and create a production request.
3. Move only eligible cards through authorized transitions. Individual and batch operations share the same server-side rules.
4. Record printing; the system captures the authenticated operator and server time.
5. Mark the card ready for release after the production check.
6. Use Verification to compare the student, enrollment, card, print, release, and history records. Verification is read-only and never releases a card.
7. In Releasing mode, confirm the release acknowledgement. Claimant name, relationship, and remarks are optional; the acknowledgement checkbox is required. The system records the authenticated staff member and server time.
8. Use Reports and Audit Logs for reconciliation.

Duplicate releases and invalid stage changes are rejected. Do not correct data by editing the database directly.

## Verification and discrepancies

Search or scan using the student number or card token. The result distinguishes unknown records, pre-print, printed, ready, released, duplicates, and inconsistent timestamps/status evidence. A discrepancy is a prompt for investigation; it does not change lifecycle status.

Investigate against Student Records, Production history, Released IDs, and Audit Logs. Any administrative correction must use an authorized application operation and retain a reason where available.

## Reports

Reports support record type, status, program, year level, academic year, semester, academic term, and date filters. The same filters drive the on-screen table, print view, PDF, and CSV.

PDF is intentionally limited by IDMS_REPORT_PDF_LIMIT (500 by default) to protect application memory. Narrow the filters or use streaming CSV for larger datasets. Spreadsheet-like values are neutralized in CSV exports.

## Settings and audit

Create academic years, terms, and programs before import. Do not rename institutional codes casually after operations begin. Audit records are read-only in the application and record actors, affected records, status changes, timestamps, and relevant metadata.

## Operational boundaries

- Student photographs are not supported by the current architecture.
- Digital signatures and identity-document uploads are not collected.
- Legacy holder/scan data remains separate from the normalized student/card lifecycle.
- Use only approved retention, privacy, and claimant-verification policies.
- Escalate conflicting or duplicate institutional records; do not delete historical card records.