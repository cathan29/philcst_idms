# Decisions and unresolved assumptions

## Provisional decisions

1. Laravel monolith with first-party permission tables avoids an unnecessary package.
2. Existing entry/exit tables remain intact; conversion needs owner review.
3. New QR payloads use opaque UUIDs. Scans verify only; staff confirm transitions.
4. Production uses MySQL; SQLite remains for local tests.

## Requires PhilCST verification

1. Representative authorized QR payloads and issuer/format.
2. Scanner models, terminators, workstation ownership, and heartbeat capabilities.
3. Exact printing, quality-check, hold, replacement, claimant verification, and reversal procedure.
4. Official branding assets and approved colors.
5. Academic vocabulary, program list, CSV layouts, update authority, cutoff, and retention policy.
6. Infrastructure, backup owner, recovery objectives, and UAT approvers.

These unknowns do not block safe schema work, but they block claims of completed hardware integration or approved operations.
