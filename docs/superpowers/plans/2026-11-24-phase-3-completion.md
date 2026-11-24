# Phase 3 completion plan

Spec: `docs/superpowers/specs/2026-11-24-phase-3-design.md`

1. QR scanner fallback: add decoder dependency and module, image-input decoding, camera lifecycle/error states, and browser tests using generated QR images.
2. Release acknowledgement: add nullable evidence columns, server validation, transactional persistence/history, accessible confirmation UI, and regressions.
3. Reports: centralize filters/query/summary; add required dimensions and types; add print/PDF outputs sharing the same filters; test CSV safety and authorization.
4. Import audit: add transaction-bound non-PII completion audit and validate permissions, duplicates, invalid rows, summaries, and isolated rollback.
5. Backup/security operations: add safe backup/verify commands or equivalent tooling, isolated restore test, production configuration documentation, and security review fixes.
6. Performance: generate isolated 1k/5k/10k datasets, benchmark representative reads/exports, inspect indexes/query counts/memory, and record actual measurements.
7. Final verification: run syntax/static checks, full tests, asset build, migrations in isolation, browser workflow/responsive/accessibility audit, screenshot evidence, and update traceability/readiness documentation.

Global constraints: single-agent sequential execution; preserve records and architecture; no database reset; no fabricated hardware, benchmark, browser, or test result; fix confirmed critical/high findings before completion.
