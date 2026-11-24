# PHILCST Panel Corrections Design

## Objective

Align the existing Laravel 12 ID Monitoring System with the final panel instructions without discarding completed Phase 2/3 work, redesigning polished pages, or resetting institutional data.

## Direction

Reading this as: an internal registrar and ID-office operations system for PHILCST staff, using the existing institutional navy, gold, and white visual language, dial ENERGY 1 / RHYTHM 1 / MOTION 1.

The calm, predictable layout supports repetitive clerical work. Gold remains a restrained institutional accent, not decoration. Existing typography and component conventions remain because consistency lowers operator error. Tables and cards are used only where the underlying records or grouped actions require them.

## Design decisions

### Active academic context

`AcademicTerm` remains persisted truth. A small `ActiveAcademicTerm` service retrieves the single active term through Laravel cache, fails clearly when none exists, and invalidates on term changes. Activation is transactional and closes every other active term. Operational controllers use this service as their default boundary.

### Semester compatibility

A value object maps 0 to Summer, 1 to First Semester, and 2 to Second Semester. It accepts only explicit known legacy aliases at application boundaries. Existing rows are not destructively rewritten, and zero receives dedicated tests.

### Enrollment

CSV is the only enrollment-entry path. Public student create/store is removed; correction/edit remains. The importer publishes the exact panel header and associates one enrollment with one student and target term. Legacy section data remains stored but is absent from current templates, filters, tables, and exports.

### Physical cards and releases

An `IdCard` is one physical production record, including each reprint. A new additive `IdRelease` row proves that one exact card was released. The release table has a unique card foreign key. Existing released cards are backfilled from current acknowledgement fields. Release creation and card transition share one transaction.

### Operational input

The release screen starts with one labelled identifier input optimized for Enter and USB keyboard scanners. It reports unknown, ineligible, duplicate, and term-mismatch outcomes, then clears and refocuses. Camera QR remains secondary and shares server rules.

### Verification

One term-scoped query loads a student, matching enrollment, every physical card for the term, histories, and release records. It returns a read-only projection and discrepancy flags without changing lifecycle state.

### Reports

Reports default to the active term and expose six datasets: enrolled students, printed physical cards, released physical cards, printed-not-released inventory, enrolled students without a printed card, and enrolled students without a released card. Card counts and unique-student counts remain distinct so reprints do not corrupt formulas. CSV uses the same dataset and formula escaping.

## Safety

All schema work is additive and reversible. Existing release columns remain compatibility snapshots. No reset, destructive rewrite, broad cleanup, new artwork, or invented ID design is performed. Authorization is enforced at request and domain boundaries.

## Verification

Each correction begins with a focused failing test, followed by the smallest implementation and focused pass. Final gates are the complete suite, production build, migration review, security review, and actual browser checks across desktop, laptop, tablet, and mobile.