# Reddiph - Original UI + Emergency Flow

This version keeps the original Reddiph interface and adds the emergency flow without replacing the original dashboard design.

## Original UI preserved
- Portal role selection
- Resident login
- Resident dashboard and SOS circle
- Responder dashboard
- Doctor dashboard
- Nurse dashboard
- Volunteer dashboard
- Original colors, spacing, cards, typography and navigation

## Resident emergency flow
Resident Dashboard
-> hold SOS
-> responder map with responder name and ETA
-> Responder Arrived
-> Rate responder and close report

Resident SOS, dispatch, and case interactions are simulated locally. Responder
sign-in uses the PHP API described below; other roles and backend workflows
have not yet been integrated.

## Run
flutter pub get
flutter run

If Flutter says Android is not a supported platform in this project, run this once inside the project folder:
flutter create .

Then:
flutter pub get
flutter run -d emulator-5554

## PHP API
Keep `sos_api` separate from the Flutter project under XAMPP htdocs.

## Mobile responder sign-in

The mobile app signs up and authenticates responders against the existing
`user_type`, `hospitals`, and `barangays` tables through
`Reddiph_mobile_api/api/auth`; it does not connect directly to MySQL. Signup
loads hospitals and their barangays from the database, then validates that the
selected barangay belongs to the selected hospital before creating an active
`responder` account. The assigned numeric `user_type.id` is shown as the
responder ID to use for login.

Signup for every role stores the selected hospital and barangay in
`user_type.hospital_id` and `user_type.barangay_id` (migration 004).

`Reddiph_mobile_api` follows a plain-PHP MVC layout (no framework):
`config/` (PDO `Database`), `app/models/` (`SignupModel`,
`UserTypeModel`, `AccountValidator`, `LocationModel`, `IncidentModel`),
`app/controllers/` (`SignupController`,
`SosController`), and `api/auth/` (thin endpoints: `login.php`, `signup.php`,
`signup_options.php`). Endpoints return JSON with
`success` and, on errors, `message`.

Every role signs in with email and password through one endpoint,
`POST api/auth/login.php` with `role` (`resident`, `responder`, `doctor`,
`nurse`, `volunteer`, `barangay_admin`), `email` and `password`. It checks
`user_type.work_email` and `password_hash` for that role and returns the profile
plus a signed `token`.
Responder signup now collects an email (stored in `work_email`) instead of using a
generated ID.

New passwords on signup and password changes must be 8–72 characters and include
at least one uppercase letter, one lowercase letter, and one special character.
The mobile app checks the session on resume and every minute while active, and
the web portal polls protected sessions every minute. The API also rejects
requests immediately once an account is banned. Banned mobile users are returned
to the role portal when the app detects the rejected session.

**Forgot password (all six roles).** `POST api/auth/forgot_password.php`
(`role`, `email`) emails a 12-character temporary password (valid 24 hours, one
request per 60 seconds) and always answers with the same message so account
existence is not revealed; it follows the admin's `requestPasswordReset`. The
temporary password sets `user_type.must_change_password = 1`, so login returns
`must_change_password: true` and the app shows a mandatory "new password" card.
`POST api/auth/change_password.php` (`token`, `password`, `confirm_password`)
saves it and clears the flag. Mail is sent with PHP `mail()`; set the `MAIL_FROM`
environment variable if the sender must differ from `noreply@reddiph.com`.
No migration is needed (columns already exist in `user_type`).

Signup for all six roles is one endpoint, `POST api/auth/signup.php`, with `role`
(`resident`, `responder`, `doctor`, `nurse`, `volunteer`, `barangay_admin`), `full_name`,
`work_email`, `phone_number`, `age`, `gender`, `hospital_id`,
`barangay_id`, `password`, `confirm_password` and, for doctors only,
`license_number`; Barangay Admin signup also requires `signup_code`. It follows
the admin signup rules (unique work email, unique
doctor license) and checks that the barangay belongs to the hospital. Hospitals,
barangays and genders come from `signup_options.php`.

Upload the standalone `Reddiph_mobile_api` folder to Hostinger `public_html`.

### SOS incident reports

Pressing SOS asks for device location, then calls `POST api/incidents/sos.php`
with `latitude`, `longitude` and the login `token`. The reporter's name, age and
gender are read from `user_type` on the server (never trusted from the client).
Signup collects age (1-120) and gender; the gender choices, hospitals
and barangays all come from the database via `signup_options.php`. The server inserts a row in the shared
`incidents` table (type default "SOS emergency alert", status `critical`),
using the reporter's hospital or the nearest hospital, and returns
`{"success": true, "incident_id": 12}`. The app asks for location permission on
every open until it is allowed.

The SOS button must be held for 3 seconds (a ring fills while held). The server
(`DispatchModel`) then fills the dispatch columns from the database:
`barangay_id`/`location` (nearest barangay of the hospital that has coordinates, else the GPS
text), `eta_minutes` (first ETA hospital to incident, `ceil(km * 1.3 / 35 * 60)`, min 1,
same as the admin live map), `transport_name`/`transport_type`/`driver_name` (an
available `ambulances` row of the hospital not on an open incident) and
`responder_name` (most recently signed-in responder of the hospital who is not the
reporter and not on an open incident). Columns with no match stay empty.

Before first use, run `migrations/001_incidents_sos_defaults.sql` and then
`migrations/002_user_type_age_gender.sql` once in phpMyAdmin (and `migrations/003_backfill_sos_dispatch_fields.sql` to complete SOS rows sent earlier, and `migrations/004_user_type_barangay.sql`, which signup needs to save the chosen barangay) (until 002 runs,
the gender list is empty and signup shows a retry message). Keep the `migrations/` folder out of `public_html`.
Its own `config/config.php` connects to the shared database (host `localhost`
by default; override with `DB_HOST`). On the server, create
`config/database.local.php` from `database.local.php.example` (or set `DB_PASS`)
with the database password; never commit that file. Build the app with the HTTPS
URL of the folder, including its trailing slash, for example:

```text
flutter run --dart-define=REDDIPH_API_BASE_URL=https://your-domain.example/Reddiph_mobile_api/
```

Use the corresponding production HTTPS URL when building a release. The mobile responder login endpoint checks the existing password hash and
updates `last_login_at`. The nurse workflow is also API-backed as documented
below; other role-specific screens should be checked against their own API
coverage before treating them as production workflows.

### Nurse mobile portal

The nurse shell follows the five workflow areas Prep, Beds, Handoff, Schedule
and Settings. It uses the existing Flutter MVC structure and plain-PHP MVC API;
it does not add a UI framework or connect Flutter directly to MySQL.
`POST api/nurses/portal.php` loads the nurse's hospital profile, notified cases,
preparation checklist, bed inventory, handoffs, shifts and preferences. The
server verifies the signed session and nurse role, scopes case details and
actions to the nurse's hospital and nurse alert assignments, and audits state
changes. Mobile checklists and handoff records use the same database read by
the web nurse portal.

Before deploying these changes, take a database backup and apply
`reddiph_admin/config/migrations/20261028_nurse_mobile_workflow.sql` once to
the shared MariaDB database. This is an additive migration for nurse task
templates and completion, arrival/handoff state, stored handoff details,
preferences, shift-change requests and audit history. It has **not** been
applied by this code change. Then upload the updated `Reddiph_mobile_api`
controller, model and `api/nurses/portal.php` endpoint. The SQL migration
should remain outside `public_html`; the app's API uses the existing shared
database configuration.

When the API cannot be reached, the nurse screens display an explicit
read-only/offline state and disable updates. Pull-to-refresh retries the server;
there is no new persistent offline queue.

## Maps
Map screens use interactive Leaflet maps in the shared mobile WebView widget in
`lib/views/widgets/open_street_map.dart`. Tile endpoint, Leaflet CDN URLs,
user-agent, attribution, and demo coordinates are kept separately in
`lib/services/open_street_map_api.dart`. Internet access is required to load
Leaflet and map tiles, and © OpenStreetMap contributors attribution is
displayed on maps. The current markers are demo coordinates near Alfonso,
Cavite; replace them with verified GPS coordinates when connecting live
emergency locations.

## Updated Resident Flow

The resident flow has been updated to match the supplied Resident App Flow reference:

Resident Home -> hold SOS -> responder map with name and ETA -> Responder Arrived -> Rating/Close Report.

Resident bottom navigation is Home / Status / Profile. The Profile tab includes the SOS button. The resident SOS action opens the live-status map directly, with the demo responder name and ETA shown alongside it. Map coordinates and responder information are demo data until live GPS and dispatch integration is available.

The Sending screen remains available to the other roles that use it, with its existing 3-second countdown, cancel, and continue behavior. The resident SOS flow goes directly to tracking.

## Performance & animation pass

**Transitions**
- `core/theme.dart` defines a themed page transition (fade + short slide, light parallax on the page underneath; Cupertino swipe-back kept on iOS). All `MaterialPageRoute` pushes inherit it.
- `core/nav.dart` (`Nav.push / replace / resetTo`) is the single navigation entry point. Stack resets (login, logout, bottom-bar jumps in the SOS flow) cross-fade.
- The logo is a `Hero` between the portal and login screens.
- Tab changes in the shell cross-fade; the SOS FAB scales in/out instead of popping.

**Buttons**
- `Pressable` (in `views/widgets/common.dart`) gives every button a quick press-down scale. It uses a raw `Listener`, so it never interferes with taps, long-press or scrolling.
- The SOS circle pulses while idle, squishes while held and gives a haptic bump when the hold completes. The pulse stops if the OS "remove animations" setting is on.
- The portal role tiles now show their ripple (the old coloured `Container` was painting over the `InkWell`).

**Efficiency**
- One shared `ResidentBottomBar` replaces four copy-pasted bars; one SOS FAB replaces two.
- Screens only rebuild what changed: the SOS countdown uses a `ValueNotifier`, Home / Tracking / Status listen to `SosController` only in the section that needs it, star rating owns its own state.
- Static content is `const`, blurred shadows and the pulse live in `RepaintBoundary`s, `ThemeData` is built once, deprecated `withOpacity` calls removed.
- Fixed: `TrackingScreen` notified listeners during `initState` (setState-during-build); double-tapping CONTINUE/CANCEL could push two routes; the bottom bar ignored the system gesture inset.
"# Reddiph" 
"# Reddiph" 
# Reddiph
