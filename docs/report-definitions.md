# Report definitions

- Issuance count: physical `id_cards`, including replacements unless explicitly excluded.
- Unique students: distinct `student_id`, never card count.
- Printed: authorized confirmation/`printed_at`, not scans.
- Released: locked transition to Released in the selected institutional date range.
- Scan attempts: verification attempts, separate from production outcomes.

Daily boundaries use `Asia/Manila` and a configurable operational cutoff. Aggregations require accuracy tests before release.
