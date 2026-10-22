# Reddiph Mobile API deployment notes

## Barangay Admin signup

Barangay Admin signup is enabled only when the server has a private signup code
with at least 32 characters. The code is checked by the API and is not included
in the Flutter app.

1. Generate a private code on the server, for example:

   ```powershell
   E:\xampp\php\php.exe -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
   ```

2. Add it to the existing `Reddiph_mobile_api/config/database.local.php`
   return array. Keep the database settings already in that file:

   ```php
   'barangay_admin_signup_code' => 'PASTE_THE_GENERATED_CODE_HERE',
   ```

   `database.local.php` is git-ignored. Do not put the code in the mobile app,
   a checked-in file, screenshots, or a shared default account.

   Alternatively, configure the API process environment variable
   `REDDIPH_BARANGAY_ADMIN_SIGNUP_CODE`.

3. Apply `reddiph_admin/config/migrations/20261024_barangay_admin_portal.sql`
   to the same database used by the mobile API, after the existing false-report
   and incident-timeline migrations. Use phpMyAdmin or your normal deployment
   process; do not run the migration against a different database.

4. Apply `reddiph_admin/config/migrations/20261025_barangay_admin_log_export_indexes.sql`
   after the portal and incident hospital-timeline migrations. It adds indexes
   for date-filtered barangay exports and does not change existing records.

5. Apply `reddiph_admin/config/migrations/20261026_barangay_admin_signup_review_index.sql`
   after `20261024_barangay_admin_portal.sql` to optimize status-filtered
   approval lists.

6. Deploy the updated PHP API and Flutter app. Each Barangay Admin registers
   their own email and password. New responder and volunteer registrations stay
   pending until an admin assigned to the same barangay approves them. Existing
   accounts remain approved by the migration default.

## Barangay Admin log exports

The Logs screen can export SOS reports, hospital updates, volunteer sign-ups,
and false-report records for today, the current Monday-to-Sunday week, or the
current month. The API uses the signed-in admin's barangay and Manila time for
all date ranges. Exported PDF and CSV files are passed to the device share
sheet, where the user can save or send them.
