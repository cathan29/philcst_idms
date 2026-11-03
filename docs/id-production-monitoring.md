# ID Production Monitoring architecture

The module monitors physical ID issuance; it does not design or print cards. `id_cards.status` is the authoritative lifecycle and `id_status_histories` is the append-only audit log. Production requests are created manually by authorized staff from existing student identities. CSV enrollment import never creates an issuance.

Lifecycle: Pending → For Printing → Printed → Ready for Release. On Hold records the previous state and documented reason, and may resume only that state. Cancellation requires a reason. Released and Revoked remain compatible with the separate releasing lifecycle.

Request creation locks the student row, rejects duplicate active production requests, and requires a replacement-class reason when a released ID already exists. The stored UUID is an internal identifier only and is not declared to be PhilCST's physical QR format.

Bulk actions use the same centralized transition service and report eligible and ineligible records. No production bulk action can mark cards Released.

## Pending institutional decisions

- Whether a physical ID is reusable, academic-year-specific, or semester-specific.
- The authoritative QR payload format used on existing PhilCST cards.
- Whether production assignments require supervisors or station-specific queues.
- The final configurable list of hold and cancellation reasons.