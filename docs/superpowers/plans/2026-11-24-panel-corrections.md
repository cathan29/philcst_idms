# Panel Corrections Sequential Implementation Plan

Execution mode: single agent, sequential, test-driven. Existing dirty-worktree changes are preserved.

1. Baseline and traceability: inspect working changes, run baseline, maintain the compliance ledger.
2. Academic context: red tests for exact semester mapping (including zero), exclusive cached active term, invalidation, and historical switching; implement and verify.
3. CSV-only enrollment: red tests for disabled manual creation, exact headers, no section, duplicate prevention, cross-term enrollment, and leading zeros; implement and verify.
4. Term scoping: red tests and integration for dashboard, registry, production, scanner, verification, and reports.
5. Release integrity: red tests, additive release migration/model, legacy backfill, one-card uniqueness, active-term/eligibility/authorization checks, atomic transition.
6. Manual-first scanner: red UI/source/browser tests for Enter, clear/refocus, explicit feedback, and secondary camera control; implement and verify.
7. Central verification: red tests for no card, multiple reprints, release evidence, discrepancies, active-term isolation, and read-only behavior; implement one projection.
8. Essential reports: red five-student scenario, six formula-safe datasets, matching CSV and simplified filters; implement and verify.
9. Antislop UI pass: remove obsolete request/section language, show active term, verify labels, keyboard focus, states, table overflow, navigation, touch targets, and responsive reflow while preserving PHILCST branding.
10. Review and final verification: correctness review, authorization/validation/transaction/audit/privacy review, full suite/build/application boot, browser viewport matrix and screenshots, final ledger and numbered readiness report.

No separate worktree or commits are created because this task explicitly continues an existing integrated, dirty worktree whose changes must be preserved.