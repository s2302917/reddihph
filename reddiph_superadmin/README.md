# Super Admin (PHP MVC)

Run locally with XAMPP:
1. Start Apache and MySQL.
2. The Super Admin and website connect to the same database. The Super Admin uses the same `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASS` environment variables as the website; these override its own `config/database.local.php`, which in turn overrides `config/config.php`. A local config file should return an array with `host`, `port`, `database`, `username`, and `password` keys. No website files need to be changed.
3. Before deployment, confirm the shared database already has the tables and columns required by the Super Admin. Do not import a full database backup over an existing database. Use reviewed migrations for schema changes and keep data-bearing SQL backups out of the public web root.
4. Create a Super Admin row in `user_type` only if one does not already exist. Generate a password hash with `php -r "echo password_hash('your-password', PASSWORD_DEFAULT), PHP_EOL;"`, then insert a row using that hash:
   ```sql
   INSERT INTO user_type (role, full_name, hospital_name, work_email, password_hash)
   VALUES ('super_admin', 'Super Admin', 'Administration', 'admin@example.com', '<generated-password-hash>');
   ```
   The login rejects every other role.
5. Open http://localhost/reddiph/reddiph_superadmin/
6. Sign in, then use the Manage sidebar to visit Overview, Users, Hospitals and barangays, and Audit logs. These pages provide database-backed overview metrics, account management, directory management, and audit search. Users and the hospital/barangay directory use server-side search and pagination in batches of 15; the directory lets administrators switch between hospitals and barangays, create or edit either type, and archive or restore records. Directory archive and barangay visibility actions require the additive `20261022_portal_pages.sql` migration. The shared `20261029_shared_platform_audit.sql` migration adds platform attribution, standardized three-word action descriptions, and idempotent migration-history import. Apply reviewed migrations only after backing up and confirming the deployed schema. New application events are recorded to the shared audit table within the relevant write transaction where supported.
   Hiding a barangay removes its card from the Admin dashboard and excludes it from new signup, location, and map-selection options. Archiving also retires it from the active directory while preserving its record and history; archived records can be restored.
7. Super Admin sessions expire after 15 minutes without pointer, keyboard, touch, or scroll activity. The portal refreshes active sessions in the background and sends a best-effort logout request when the tab closes; server-side idle expiry remains enforced if the browser cannot send that request.

Controllers, models, and views are stored in `app/controller/`, `app/models/`, and `app/views/`. View files are kept directly in `app/views/` with names such as `LoginView.php`; model files use names such as `LoginModel.php`. Page-specific CSS remains under `public/assets/css/`, and shared page interactions are in `public/assets/js/script.js`. Roles and permissions, UI and branding, and system settings pages are not included in this Super Admin portal.

## Hostinger subdomain deployment

The subdomain uses the separate `superadmin/` web-root folder. Its small
`index.php` launcher loads the MVC application from the sibling
`reddiph_superadmin/` folder, keeping application code and configuration
outside the subdomain's document root.

1. Upload the **contents** of the repository's `superadmin/` folder to
   `/home/u672637579/domains/reddiph.com/public_html/superadmin/`. It must
   contain `index.php` and `.htaccess`.
2. Upload the **contents** of the repository's `reddiph_superadmin/` folder to
   `/home/u672637579/domains/reddiph.com/public_html/reddiph_superadmin/`. That
   directory must directly contain `index.php`, `.htaccess`, `app/`, `config/`,
   and `public/`. Do not nest another `reddiph_superadmin/` directory inside
   it. The launcher expects both folders to be siblings under `public_html/`.
3. In hPanel, set the `superadmin.reddiph.com` document root to
   `/home/u672637579/domains/reddiph.com/public_html/superadmin/`. Do not point
   it at the application folder or an `index.php` file.
4. In hPanel, select PHP 8.1 or newer and ensure the PDO MySQL extension is
   enabled. The app requires a MySQL-compatible database such as MariaDB.
5. Super Admin's default host, database name, and username now match the
   neighboring `reddiph_admin` configuration. In `config/database.local.php`,
   set the same database password used by `reddiph_admin/config/database.local.php`.
   You can copy `config/database.local.php.example` to
   `config/database.local.php`; the local file is Git-ignored. Do not copy the
   Mobile API's TomTom key or signup code into Super Admin. If Hostinger uses
   different database credentials, set all connection values in this local
   file instead. Environment variables `DB_HOST`, `DB_PORT`, `DB_NAME`,
   `DB_USER`, and `DB_PASS` override the local file.
7. Use the same database as the website only if it already contains the
   Super Admin's required tables and columns. Otherwise, review and apply the
   appropriate additive migrations after taking a backup. Never import a
   data-bearing backup into the live database as a setup shortcut.
   The supplied `reddiph_admin/config/Reddiph_backup.sql` already contains the
   shared application tables and `super_admin` role value, but it does not
   contain the shared `audit_logs` table or facility archive/active columns.
   It includes a nurse-only legacy audit table. After taking
   a database backup, import
   `reddiph_superadmin/config/migrations/20261022_portal_pages.sql` using
   phpMyAdmin to enable audit logging and facility archive/visibility actions.
   Import
   `reddiph_superadmin/config/migrations/20261023_login_disabled_reason.sql`
   as well to store and show administrator-provided login-disable reasons in
   the Admin website and Mobile app. Do not apply these migrations until the
   database backup has been taken and the SQL reviewed. The Users page and
   login controls require the login-reason and account-archive migrations; the
   Facilities page can still display existing facilities before its support
   migration is imported.
   Import
   `reddiph_superadmin/config/migrations/20261024_user_account_archive.sql`
   to enable account archiving and restoration. Archiving blocks sign-in and
   preserves linked records; archived accounts can be restored from the
   Archived filter.
   Review and apply
   `reddiph_superadmin/config/migrations/20261029_shared_platform_audit.sql`
   after verifying the current migration state. It retains `platform_scope`,
   adds the canonical `platform` and original `action_code`, converts existing
   action values to exactly three words, and imports legacy nurse audit entries
   idempotently without deleting their source records. Website and Mobile
   application versions must both be deployed with their audit writers for
   their events to appear in the shared log. The migration restores the full
   supported `user_type.role` enum; review the deployed values before applying.
   Role values are read directly from `user_type.role`; no separate role table
   is required.
   Confirm the intended administrator has the exact `super_admin` role value.
   The configured database account needs `SELECT` access to `user_type` for
   login and `UPDATE` access to record the optional last-login timestamp. Grant
   only the database privileges required by the Overview, Users, Facilities,
   and Audit logs pages.
8. Create the Super Admin account using the SQL example above in Hostinger's
   database tool, with a newly generated password hash. Do not reuse a sample
   or development password.
9. Enable SSL for the subdomain in hPanel, then open
   `https://superadmin.reddiph.com`.
   Test the login page, sign in, and verify each Manage page. If a route returns a 404, check that both `.htaccess` files are
   uploaded, Apache rewrite rules are enabled, and the two folders are siblings.

The public launcher serves `/` as the MVC login page. Clean URLs such as
`/login` and `/overview` are passed to the application front controller. CSS
and JavaScript requests are served from the sibling application's
`public/assets/` directory through the launcher.
