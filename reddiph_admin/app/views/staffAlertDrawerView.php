<div class="livemapping-alert-overlay" id="livemapping-alert-overlay" aria-hidden="true" hidden>
    <form class="livemapping-alert-drawer" id="livemapping-alert-form" autocomplete="off"
        role="dialog" aria-modal="true" aria-labelledby="livemapping-alert-title" tabindex="-1">
        <button type="button" class="livemapping-alert-close" id="livemapping-alert-close"
            aria-label="Close alert drawer">×</button>
        <h2 id="livemapping-alert-title">Send alert to staff</h2>
        <p id="livemapping-alert-transport" class="livemapping-alert-transport"></p>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($staffAlertCsrf ?? '', ENT_QUOTES, 'UTF-8') ?>">

        <div class="livemapping-alert-incident" aria-live="polite">
            <p class="livemapping-alert-section-label">Selected incident</p>
            <strong id="livemapping-alert-incident-name">Select an incident</strong>
            <dl class="livemapping-alert-incident-grid">
                <div><dt>Incident ID</dt><dd id="livemapping-alert-incident-id">—</dd></div>
                <div><dt>Status</dt><dd id="livemapping-alert-incident-status">—</dd></div>
                <div><dt>Location</dt><dd id="livemapping-alert-incident-location">—</dd></div>
                <div><dt>Patient</dt><dd id="livemapping-alert-incident-patient">—</dd></div>
                <div><dt>ETA</dt><dd id="livemapping-alert-incident-eta">—</dd></div>
                <div><dt>Hospital</dt><dd id="livemapping-alert-incident-hospital">—</dd></div>
            </dl>
        </div>

        <label for="livemapping-alert-search">Search staff</label>
        <input id="livemapping-alert-search" name="staff_search" type="search"
            placeholder="Name, role, or specialty" maxlength="100">

        <label for="livemapping-alert-specialization">Specialty</label>
        <select id="livemapping-alert-specialization" name="specialization">
            <option value="">All specialties</option>
        </select>

        <div class="livemapping-alert-status-filters">
            <div>
                <label for="livemapping-alert-online-status">Online Status</label>
                <select id="livemapping-alert-online-status" name="online_status">
                    <option value="all">All</option>
                    <option value="online">Online</option>
                    <option value="offline">Offline</option>
                </select>
            </div>
            <div>
                <label for="livemapping-alert-account-status">Account Status</label>
                <select id="livemapping-alert-account-status" name="account_status">
                    <option value="all">All</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <div>
                <label for="livemapping-alert-duty-status">Duty Status</label>
                <select id="livemapping-alert-duty-status" name="duty_status">
                    <option value="all">All</option>
                    <option value="on_duty">On Duty</option>
                    <option value="off_duty">Off Duty</option>
                </select>
            </div>
        </div>

        <p class="livemapping-alert-selection-count" id="livemapping-alert-selection-count" aria-live="polite">
            0 staff selected
        </p>
        <div id="livemapping-alert-selected" class="livemapping-alert-selected" aria-live="polite"></div>
        <div id="livemapping-alert-recipients" class="livemapping-alert-recipients" aria-live="polite"></div>
        <nav class="livemapping-alert-pagination" aria-label="Staff results pagination">
            <button type="button" id="livemapping-alert-previous" aria-label="Previous staff page">Previous</button>
            <span id="livemapping-alert-page" aria-live="polite">Page 1 of 1</span>
            <button type="button" id="livemapping-alert-next" aria-label="Next staff page">Next</button>
        </nav>

        <label for="livemapping-alert-message">Message (optional)</label>
        <textarea id="livemapping-alert-message" name="message" maxlength="2000"
            placeholder="Optional message for the response team"></textarea>
        <button type="submit" class="livemapping-alert-submit" disabled>Send alert</button>
        <output id="livemapping-alert-status" class="livemapping-alert-status" aria-live="polite"></output>
    </form>
</div>
