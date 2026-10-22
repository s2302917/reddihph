# Super Admin (PHP MVC)

Run locally with XAMPP:
1. Start Apache and MySQL.
2. The Super Admin and website connect to the same database (`u672637579_reddi_ph` by default). The Super Admin uses the same `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASS` environment variables as the website; these override its own `config/database.local.php`, which in turn overrides `config/config.php`. A local config file should return an array with `host`, `port`, `database`, `username`, and `password` keys. No website files need to be changed.
3. `config/reddiph_schema.sql` is the canonical shared schema for the website, mobile app, and Super Admin. It includes shared roles/permissions, audit logs, facility archive state, and shared/web/mobile branding and settings. For a new empty database, import this file. It is a data-bearing full dump; do not re-import it over an existing database because its table/data statements can replace existing records. For an existing website database, apply a reviewed migration instead of importing the dump.
4. Create a Super Admin row in `user_type` only if one does not already exist. Generate a password hash with `php -r "echo password_hash('your-password', PASSWORD_DEFAULT), PHP_EOL;"`, then insert a row using that hash:
   ```sql
   INSERT INTO user_type (role, full_name, hospital_name, work_email, password_hash)
   VALUES ('super_admin', 'Super Admin', 'Administration', 'admin@example.com', '<generated-password-hash>');
   ```
   The login rejects every other role.
5. Open http://localhost/superadmin/app/controller/LoginController.php
6. Sign in, then use the sidebar to visit Overview, Users, Roles and permissions, Hospitals and barangays, UI and branding, Audit logs, and System settings. The management pages provide database-backed create/edit/archive or restore actions, permission assignment, scoped settings and branding, audit search, and account suspension/password resets. Changes are CSRF-protected and validated; all CRUD reads and writes use the shared database, schema mismatches are reported, and successful writes are recorded in the shared audit log in the same database transaction.

Controllers, models, and views are stored in `app/controller/`, `app/models/`, and `app/views/`. View files are kept directly in `app/views/` with names such as `LoginView.php`; model files use names such as `LoginModel.php`. The one schema is stored in `config/reddiph_schema.sql`, page-specific CSS remains under `public/assets/css/`, and all page JavaScript is in `public/assets/js/script.js`. Shared, website, and mobile settings and branding are stored in this same database. The website and mobile applications must read their relevant scopes to apply those values at runtime; their code is intentionally unchanged by this setup.
