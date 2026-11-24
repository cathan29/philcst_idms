# Phase 3 completion design

## Scope

Complete the confirmed production-readiness gaps without replacing the Laravel 12 domain model, changing PHILCST branding, resetting data, or merging legacy scan attendance into normalized ID-card history.

## Decisions

1. QR decoding becomes a Vite-built page module backed by the maintained `qr-scanner` package. Camera, keyboard-emulated input, manual entry, and uploaded QR images share the same database verification endpoint. Streams stop on detection, navigation, visibility loss, and explicit stop.
2. Release evidence is additive and privacy-minimal: an explicit acknowledgement is required; claimant name, relationship/authorization, and remarks remain optional. Staff and time remain server-derived. No signatures or document uploads are introduced.
3. One report query owns filters for the screen, summary, CSV, print view, and PDF. It adds year level, academic year, semester, report type, and discrepancy filters. Exported spreadsheet cells retain formula-injection protection.
4. Imports remain preview-first and transactional. Successful commits gain a non-PII audit event with batch counts; no legacy data is migrated automatically.
5. Backup tooling targets the configured database and private application files, excludes environment secrets, verifies archives, and only documents isolated restore. It never restores over the live database.
6. Performance checks run only against isolated generated databases at 1k, 5k, and 10k records and record real timings/query counts. Normal tests never populate the live database.

## Security and UX constraints

- Existing permissions remain authoritative at routes and controllers.
- Verification stays read-only; only explicit production/release actions transition status.
- Empty, loading, camera-denied, decode-failed, validation, duplicate, and server-error states must remain usable with a keyboard.
- UI changes are limited to controls and evidence required by these workflows; current spacing, typography, and PHILCST color system remain intact.

## Verification

Each behavior is introduced through a failing regression test, then verified by focused tests, the complete Laravel suite, production asset build, static checks, and browser automation at desktop/tablet/mobile widths. Physical camera/scanner testing is reported separately and never inferred from automation.
