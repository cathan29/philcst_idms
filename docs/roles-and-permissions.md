# Roles and permissions

Seeded roles are Administrator, ID Production Staff, Releasing Staff, and Viewer/Auditor. Super Administrator is created only by `php artisan idms:provision-admin`; no known default password exists.

Permissions are checked on the server. Navigation visibility is only a convenience. Deactivation is enforced on login and every authenticated request. Permission grants and sensitive account changes must be audited as administration is implemented.
