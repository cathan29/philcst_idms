# CSV import guide

Implemented workflow: private upload → CSV validation → header inspection and explicit mapping → preview → duplicate/existing classification → selected mode → transactional commit → summary and row-level import-error records.

Supported modes are validate-only, insert-only, and insert-or-update. Updates are shown before approval. Existing card records and status history are not deleted or reset by student imports. Exports must neutralize formula-leading cells (`=`, `+`, `-`, `@`). Uploaded files remain outside `public/` and follow an institution-approved retention policy.
