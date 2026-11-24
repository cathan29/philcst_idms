# Operational Reports Implementation Plan

**Goal:** Provide secure, filterable operational ID reports and make working monitoring tools reachable from the dashboard.

**Delivered:**

- `IdReportController` supplies one validated query for the report and CSV export.
- Both report routes require the existing `cards.view` permission.
- CSV exports use stable primary-key chunking and neutralize formula-like cell values.
- Dashboard and sidebar links expose the existing QR scanner and the new reports page only to authorized users.
- Feature coverage verifies authorization, filtering, export parity, and CSV-injection protection.