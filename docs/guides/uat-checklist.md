# Final UAT Checklist

Record the tester, role, environment, device/browser, date, evidence, and result for every item. Use PASS, FAIL, NOT TESTED, or NOT APPLICABLE. Use approved non-production records and never reset the live database.

## Environment and security

- [ ] Production-like APP_DEBUG=false and HTTPS origin.
- [ ] Secure session cookies, CSRF, login throttling, logout, and inactive-user denial.
- [ ] Least-privilege database/service accounts and restricted storage permissions.
- [ ] Administrator bootstrap secret rotated; demo-holder seeding disabled.
- [ ] Unauthorized direct URLs and lifecycle POST requests return 403.
- [ ] Security headers present; HSTS present on secure production responses.
- [ ] Composer and npm security audits accepted.

## Workflow

- [ ] Create a valid student and enrollment.
- [ ] Reject a duplicate student number and invalid fields.
- [ ] Create initial, reprint, and replacement production requests.
- [ ] Reject duplicate/invalid production requests and transitions.
- [ ] Mark printed and ready; confirm timestamps, staff, status, history, dashboard, and reports.
- [ ] Verification shows accurate student, term, production, release, discrepancy, and history data without changing status.
- [ ] Reject release before eligibility.
- [ ] Release an eligible ID with acknowledgement and optional claimant fields.
- [ ] Reject duplicate release; verify the released result.
- [ ] Reconcile Student Records → Repro/Printed → Released → Verification.

## Import and reporting

- [ ] Download the CSV template.
- [ ] Preview a valid file and reject invalid encoding, headers, rows, programs, terms, and duplicates.
- [ ] Confirm insert-only and reviewed update modes in an isolated database.
- [ ] Verify one-use confirmation, transactional behavior, error CSV, counts, and audit record.
- [ ] Verify every report type and filter.
- [ ] Compare screen, print, PDF, and CSV records for the same filters.
- [ ] Confirm PDF limit behavior and CSV formula neutralization.

## Scanner hardware

- [ ] Real approved camera grants permission over HTTPS and decodes issued cards.
- [ ] Camera denial/unavailable states are understandable and recoverable.
- [ ] Camera stops after stop, navigation, tab hiding, and unload.
- [ ] Actual USB scanner works as keyboard input and submits correctly.
- [ ] Manual and actual QR image input work.
- [ ] Unknown, invalid, duplicate, pre-print, ready, released, and discrepancy scenarios are correct.

## Browser and accessibility

- [ ] Login, dashboard, students, production, printed, released, scanner, reports, users, settings, and audit pages.
- [ ] Current supported Chrome/Edge/Firefox/Safari versions as institutionally required.
- [ ] Desktop, laptop, tablet, and phone layouts.
- [ ] Keyboard navigation, visible focus, dialog focus/escape behavior, labels, status communication, contrast, and zoom.
- [ ] Wide tables are usable without document-level overflow.
- [ ] Loading, empty, success, validation, permission, and server-error states.
- [ ] Print layout and PDF branding/content.

## Operations and recovery

- [ ] Scheduled backup completes and transfers to approved off-site storage.
- [ ] Manifest/checksum verification succeeds.
- [ ] Full restoration succeeds on an isolated host and row/history/file counts reconcile.
- [ ] Monitoring, log retention, incident response, rollback, RPO, and RTO are approved.
- [ ] Representative infrastructure load is acceptable for expected concurrency.
- [ ] Stakeholders approve claimant data, privacy retention, operational roles, and go-live.

Production approval requires signed institutional acceptance of all essential items. The Phase 3 audit does not substitute for that approval.