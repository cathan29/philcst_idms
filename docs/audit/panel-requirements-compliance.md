# Panel Requirements Compliance Ledger

Audit date: 2026-11-24

Status vocabulary: `Fully implemented`, `Partially implemented`, `Missing`, `Not tested`, `Not applicable`.

| ID | Requirement | Status | Evidence |
|---|---|---|---|
| PR-01 | Current enrollment workflow is CSV-only | Fully implemented | Manual create/store routes removed; `PanelEnrollmentComplianceTest` passes. |
| PR-02 | Exact nine-column CSV template | Fully implemented | Template header is `student_number,first_name,middle_name,last_name,suffix,program,year_level,academic_year,semester`. |
| PR-03 | No block/section in current workflow; preserve legacy column/data | Fully implemented | Import, registry, production, reports, and current student detail omit section; no destructive column migration. |
| PR-04 | One enrollment per student and academic term | Fully implemented | Additive unique index plus import upsert and database regression test. |
| PR-05 | Preserve leading-zero student numbers | Fully implemented | String validation, import, lookup, display, and regression coverage. |
| PR-06 | Semester 0 Summer, 1 First, 2 Second | Fully implemented | `SemesterCode` rejects unknown input and explicitly tests numeric/string zero. |
| PR-07 | One persisted and cached active term | Fully implemented | `ActiveAcademicTerm` provides transactional exclusive activation and cache invalidation. |
| PR-08 | Active term displayed and automatically scopes operational modules | Fully implemented | Shared context strip; dashboard, students, import, production, release, verification, and reports scoped server-side. |
| PR-09 | Safe historical switching | Fully implemented | Historical records remain stored; a term must be activated before it becomes operational/importable. |
| PR-10 | Repro Table represents distinct physical cards/reprints | Fully implemented | One `id_cards` row per issuance, replacement link/sequence, distinct dates and history. |
| PR-11 | Release indicator derives from exact card release transaction | Fully implemented | `id_releases.id_card_id` is unique and is the displayed/reporting source of truth. |
| PR-12 | Reject unprinted, unknown, duplicate, wrong-term, wrong-card release | Fully implemented | Transactional lifecycle service and release regression suite. |
| PR-13 | Manual student-number/USB Enter workflow is primary; camera optional | Fully implemented | Manual field is first, clears/refocuses, camera is secondary disclosure; Playwright Enter check passes. |
| PR-14 | Optional eight-digit normalization only if institutionally safe | Not applicable | No authoritative collision-free format was supplied; exact identifiers and leading zeros remain authoritative. |
| PR-15 | Central read-only verification across enrollment, cards, releases, discrepancies | Fully implemented | Active-term projection returns all physical cards, normalized releases, lifecycle history, and discrepancies without mutation. |
| PR-16 | Six essential reprint-safe reports | Fully implemented | Enrolled, printed, released, printed-not-released, without-printed, and without-released datasets. |
| PR-17 | Exact five-student formula scenario | Fully implemented | `OperationalReportFormulaTest` verifies 5/4/2/2/3/2/2/3 outcomes. |
| PR-18 | Simple filters; visible/exported data match; formula-safe CSV | Fully implemented | Shared query path and CSV injection neutralization tests. |
| PR-19 | Preserve secure administration and users | Fully implemented | Backend RBAC, strong passwords, self-lockout protection, account middleware, and audit tests. |
| PR-20 | Defer public/student ID request portal; retain staff reprint recording | Fully implemented | No public registration/request portal; internal staff action is presented as a production record. |
| PR-21 | Direct ID artwork/physical printing is future work | Not applicable | Explicitly deferred; no unapproved PHILCST card design invented. |
| PR-22 | Preserve Laravel 12, PHILCST branding, architecture, and records | Fully implemented | Laravel 12 retained; additive migrations; verified backup; no database reset; temporary UAT data removed. |
| PR-23 | Sequential implementation, review, security audit, automated/browser/responsive testing | Fully implemented | 114 tests/560 assertions, 60/60 Playwright checks, security audits, build, syntax, and isolated performance suite pass. |

## Test limitations

- Physical USB barcode scanner hardware: Not tested.
- Physical camera permission/preview: Not tested in headless Chrome; the unavailable-camera recovery state passed.
- Physical ID printer and official card artwork: Not applicable to this phase.
- Production HTTPS, permissions, and real deployment environment: Not tested; documented configuration remains required.