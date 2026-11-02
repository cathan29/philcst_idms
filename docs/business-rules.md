# Business rules

Default flow: Pending → For Printing → Printed → Ready for Release → Released. On Hold and Revoked require a reason. Returning from hold is an authorized exception. Released cards cannot be released again; they may only be revoked through an elevated, audited operation.

A scan resolves a possible identity and records an attempt. It does not prove printing, quality control, possession, or release. Unknown payloads never create records. New QR codes use opaque random tokens and contain no personal data.

Student numbers remain strings. Names are stored as separate supplied fields; never infer a surname from a full-name string. Missing rows in a later CSV never delete students or issuance history.
