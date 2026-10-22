// =========================================================
// REDDI PH / PULSE ALERT
// GLOBAL PAGE SCRIPT
// =========================================================
//
// Contains:
// 1. Global back button behavior
// 2. Forgot password modals
// 3. Form prototypes
// 4. Signup interactions
// 5. Incoming Dispatches behavior
// 6. Emergency Alerts behavior
//
// No database or business logic belongs here.
// =========================================================


// =========================================================
// GLOBAL PAGE INITIALIZATION
// =========================================================

function isProtectedPage() {
    return !/portalController|loginController|signupController|logoutController/.test(
        window.location.pathname
    );
}

function getBackLogoutDrawer() {
    var overlay = document.getElementById('settings-logout-overlay');

    if (!overlay) {
        overlay = document.createElement('div');
        overlay.className = 'settings-logout-overlay';
        overlay.id = 'settings-logout-overlay';
        overlay.setAttribute('aria-hidden', 'true');
        overlay.innerHTML =
            '<section class="settings-logout-drawer" role="dialog" aria-modal="true" ' +
            'aria-labelledby="settings-logout-title" aria-describedby="settings-logout-message">' +
            '<h2 id="settings-logout-title">Log out of Pulse Alert?</h2>' +
            '<p id="settings-logout-message">You\'ll need to sign back in to view dispatches, alerts,<br>' +
            'and staff schedules for DSB District Hospital.</p>' +
            '<div class="settings-logout-actions">' +
            '<button type="button" class="settings-logout-cancel" id="settings-logout-cancel">Cancel</button>' +
            '<a class="settings-logout-confirm" id="settings-logout-confirm" href="logoutController.php">Log out</a>' +
            '</div></section>';
        document.body.appendChild(overlay);
    }

    if (document.body.classList.contains('doctor-settings-page')) {
        var logoutTitle = document.getElementById('settings-logout-title');
        if (logoutTitle) {
            logoutTitle.textContent = 'Log out of Reddi PH?';
        }
    }

    if (!document.getElementById('pulse-back-logout-styles')) {
        var style = document.createElement('style');
        style.id = 'pulse-back-logout-styles';
        style.textContent =
            '.settings-logout-overlay{position:fixed;z-index:1000;inset:0;display:flex;align-items:center;justify-content:center;padding:18px;background:rgba(22,10,12,.48);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);opacity:0;visibility:hidden;pointer-events:none;transition:opacity .2s ease,visibility .2s ease}' +
            '.settings-logout-overlay--open{opacity:1;visibility:visible;pointer-events:auto}' +
            '.settings-logout-drawer{width:min(100%,526px);padding:48px 52px 50px;border-radius:8px;background:#790004;color:#fff;box-shadow:0 18px 48px rgba(35,0,2,.28);transform:translateY(18px) scale(.98);transition:transform .25s ease}' +
            '.settings-logout-overlay--open .settings-logout-drawer{transform:translateY(0) scale(1)}' +
            '.settings-logout-drawer h2{margin:0 0 6px;font-size:17px;line-height:1.2;font-weight:800}' +
            '.settings-logout-drawer p{margin:0;max-width:330px;font-size:12px;line-height:1.22;font-weight:500}' +
            '.settings-logout-actions{display:grid;grid-template-columns:1fr 1fr;gap:17px;margin-top:22px}' +
            '.settings-logout-cancel,.settings-logout-confirm{min-height:31px;display:flex;align-items:center;justify-content:center;border:0;border-radius:9px;background:rgba(255,255,255,.16);color:#fff;font-family:inherit;font-size:12px;line-height:1;font-weight:800;text-decoration:none;cursor:pointer}' +
            '.settings-logout-cancel:hover,.settings-logout-confirm:hover{background:rgba(255,255,255,.25)}' +
            '@media(max-width:700px){.settings-logout-drawer{padding:34px 28px 30px}.settings-logout-actions{gap:10px}}';
        document.head.appendChild(style);
    }

    var currentPath = window.location.pathname.toLowerCase();
    var currentRole = currentPath.indexOf('/doctor') !== -1
        ? 'doctor'
        : currentPath.indexOf('/nurse') !== -1
            ? 'nurse'
            : 'hospital_admin';
    var confirmLink = document.getElementById('settings-logout-confirm');
    if (confirmLink) {
        confirmLink.href = 'logoutController.php?role=' + encodeURIComponent(currentRole);
    }

    return {
        overlay: overlay,
        cancel: document.getElementById('settings-logout-cancel'),
        confirm: confirmLink
    };
}

function setupBrowserBackLogout() {
    if (!isProtectedPage() || document.body.dataset.backLogoutBound === 'true') {
        return;
    }

    document.body.dataset.backLogoutBound = 'true';

    var drawer = getBackLogoutDrawer();
    var lastFocusedElement = null;

    function closeDrawer() {
        drawer.overlay.classList.remove('settings-logout-overlay--open');
        drawer.overlay.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('settings-logout-open');

        if (lastFocusedElement) {
            lastFocusedElement.focus();
        }
    }

    function openDrawer() {
        lastFocusedElement = document.activeElement;
        drawer.overlay.classList.add('settings-logout-overlay--open');
        drawer.overlay.setAttribute('aria-hidden', 'false');
        document.body.classList.add('settings-logout-open');
        drawer.cancel.focus();
    }

    drawer.cancel.addEventListener('click', closeDrawer);
    drawer.overlay.addEventListener('click', function (event) {
        if (event.target === drawer.overlay) {
            closeDrawer();
        }
    });

    window.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && drawer.overlay.classList.contains('settings-logout-overlay--open')) {
            closeDrawer();
        }
    });

    window.history.pushState({ pulseBackLogout: true }, '', window.location.href);
    window.addEventListener('popstate', function () {
        window.history.pushState({ pulseBackLogout: true }, '', window.location.href);
        openDrawer();
    });

    var navigationEntries = window.performance && window.performance.getEntriesByType
        ? window.performance.getEntriesByType('navigation')
        : [];
    if (navigationEntries.length && navigationEntries[0].type === 'back_forward') {
        openDrawer();
    }
}

window.addEventListener('pageshow', function (event) {
    document.querySelectorAll(
        '#hospitaladminLoginForm input[name="work_email"], ' +
        '#hospitaladminLoginForm input[name="password"], ' +
        '#doctorLoginForm input[name="work_email"], ' +
        '#doctorLoginForm input[name="password"], ' +
        '#nurseLoginForm input[name="work_email"], ' +
        '#nurseLoginForm input[name="password"]'
    ).forEach(function (input) {
        input.value = '';
    });

    if (event.persisted) {
        setupBrowserBackLogout();
        if (isProtectedPage()) {
            var logoutOverlay = document.getElementById('settings-logout-overlay');
            logoutOverlay.classList.add('settings-logout-overlay--open');
            logoutOverlay.setAttribute('aria-hidden', 'false');
            document.body.classList.add('settings-logout-open');
        }
    }
});

function setupPasswordControls() {
    if (!document.getElementById('password-control-styles')) {
        var style = document.createElement('style');
        style.id = 'password-control-styles';
        style.textContent =
            '.password-field-control{position:relative;width:100%}' +
            '.password-field-control>input{padding-right:3.2rem!important}' +
            '.password-visibility-toggle{position:absolute;right:.65rem;top:50%;transform:translateY(-50%);border:0;background:transparent;color:#555;padding:.35rem;font:inherit;font-size:.8rem;cursor:pointer}' +
            '.password-policy-hint{display:block;margin-top:.35rem;color:#555;font-size:.75rem;line-height:1.4}';
        document.head.appendChild(style);
    }

    document.querySelectorAll('input[type="password"]').forEach(function (input) {
        if (input.dataset.passwordControlsBound === 'true') return;
        input.dataset.passwordControlsBound = 'true';
        var wrapper = document.createElement('span');
        wrapper.className = 'password-field-control';
        input.parentNode.insertBefore(wrapper, input);
        wrapper.appendChild(input);
        var toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'password-visibility-toggle';
        toggle.textContent = 'Show';
        toggle.setAttribute('aria-label', 'Show password');
        toggle.setAttribute('aria-pressed', 'false');
        toggle.addEventListener('click', function () {
            var visible = input.type === 'password';
            input.type = visible ? 'text' : 'password';
            toggle.textContent = visible ? 'Hide' : 'Show';
            toggle.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
            toggle.setAttribute('aria-pressed', visible ? 'true' : 'false');
            input.focus();
        });
        wrapper.appendChild(toggle);

        var name = input.name.toLowerCase();
        var form = input.closest('form');
        var signupPassword = form &&
            /signup/i.test(form.id) &&
            name !== 'confirm_password';
        var isNewPassword = name === 'new_password' ||
            input.autocomplete === 'new-password' ||
            signupPassword ||
            input.id.toLowerCase().includes('new-password') ||
            input.id.toLowerCase().includes('admin_password') ||
            input.id.toLowerCase().includes('doctor_password');
        if (isNewPassword) {
            var hint = document.createElement('small');
            hint.className = 'password-policy-hint';
            hint.textContent = '8–72 characters, with an uppercase letter, a lowercase letter, and a special character.';
            wrapper.appendChild(hint);
            input.addEventListener('input', function () {
                var valid = input.value.length >= 8 && input.value.length <= 72 &&
                    /[A-Z]/.test(input.value) && /[a-z]/.test(input.value) &&
                    /[^A-Za-z0-9]/.test(input.value);
                input.setCustomValidity(input.value === '' && !input.required
                    ? ''
                    : valid ? '' : 'Use 8–72 characters, including uppercase, lowercase, and a special character.');
            });
        }
    });
}

function setupAccountStatusPolling() {
    if (!isProtectedPage()) return;

    var script = Array.prototype.find.call(document.scripts, function (item) {
        return item.src.indexOf('/public/js/script.js') !== -1;
    });
    if (!script) {
        console.warn('[Session] Could not determine the account-status endpoint.');
        return;
    }

    var path = window.location.pathname.toLowerCase();
    var role = path.indexOf('/doctor') !== -1
        ? 'doctor'
        : path.indexOf('/nurse') !== -1
            ? 'nurse'
            : 'hospital_admin';
    var endpoint = new URL('../../api/session-status.php', script.src);
    endpoint.searchParams.set('role', role);
    var checking = false;

    function checkAccountStatus() {
        if (checking || document.visibilityState === 'hidden') return;
        checking = true;
        window.fetch(endpoint.toString(), {
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { Accept: 'application/json' }
        }).then(function (response) {
            if (response.redirected) {
                window.location.assign(response.url);
                return;
            }
            if (!response.ok) {
                console.warn('[Session] Account status check failed with HTTP ' + response.status + '.');
            }
        }).catch(function (error) {
            console.warn('[Session] Account status check could not reach the server.', error);
        }).finally(function () {
            checking = false;
        });
    }

    window.setInterval(checkAccountStatus, 60000);
    window.addEventListener('focus', checkAccountStatus);
    document.addEventListener('visibilitychange', checkAccountStatus);
}

document.addEventListener('DOMContentLoaded', function () {

    setupPasswordControls();
    setupAccountStatusPolling();
    setupBrowserBackLogout();
    incomingdispatchBindIncidentPopups();

    // =====================================================
    // GLOBAL BACK BUTTON
    // =====================================================

    try {

        var backButtons = document.querySelectorAll(
            '#backBtn, ' +
            '#hospitaladminBackBtn, ' +
            '#doctorBackBtn, ' +
            '#nurseBackBtn, ' +
            '.back-btn, ' +
            '.login-back-btn, ' +
            '.hospitaladmin-login-back-btn, ' +
            '.doctor-login-back-btn, ' +
            '.nurse-login-back-btn'
        );

        backButtons.forEach(function (btn) {

            btn.addEventListener('click', function (event) {

                event.preventDefault();

                if (window.history.length > 1) {
                    window.history.back();
                } else {
                    window.location.href = window.APP_BASE_URL || '/';
                }

            });

        });

    } catch (error) {

        console.error(
            '[PulseAlert] Global back-button initialization failed.',
            error
        );

    }


    // =====================================================
    // FORGOT PASSWORD MODALS
    // =====================================================

    try {

        function setupForgotPasswordModal(role) {

            var modalId =
                role === 'admin'
                    ? 'adminForgotPassModal'
                    : role + 'ForgotPassModal';

            var linkId =
                role === 'admin'
                    ? 'hospitaladminForgotPassLink'
                    : role + 'ForgotPassLink';

            var closeId =
                role === 'admin'
                    ? 'adminForgotPassBackBtn'
                    : role + 'ForgotPassBackBtn';

            var emailId =
                role === 'admin'
                    ? 'adminforgotpass-email'
                    : role + 'forgotpass-email';


            var modal =
                document.getElementById(modalId);

            var link =
                document.getElementById(linkId);

            var closeBtn =
                document.getElementById(closeId);


            function openModal() {

                if (!modal) {
                    return;
                }

                modal.classList.add('is-open');

                modal.setAttribute(
                    'aria-hidden',
                    'false'
                );


                var emailInput =
                    document.getElementById(emailId);

                if (emailInput) {

                    window.setTimeout(function () {
                        emailInput.focus();
                    }, 150);

                }

            }


            function closeModal() {

                if (!modal) {
                    return;
                }

                modal.classList.remove('is-open');

                modal.setAttribute(
                    'aria-hidden',
                    'true'
                );

            }


            if (link) {

                link.addEventListener(
                    'click',
                    function (event) {

                        event.preventDefault();

                        openModal();

                    }
                );

            }


            if (closeBtn) {

                closeBtn.addEventListener(
                    'click',
                    function (event) {

                        event.preventDefault();

                        closeModal();

                    }
                );

            }


            if (modal) {

                modal.addEventListener(
                    'click',
                    function (event) {

                        if (event.target === modal) {
                            closeModal();
                        }

                    }
                );

            }


            return {
                open: openModal,
                close: closeModal
            };

        }


        var adminForgotModal =
            setupForgotPasswordModal('admin');

        var doctorForgotModal =
            setupForgotPasswordModal('doctor');

        var nurseForgotModal =
            setupForgotPasswordModal('nurse');


        document.addEventListener(
            'keydown',
            function (event) {

                if (
                    event.key === 'Escape' ||
                    event.key === 'Esc'
                ) {

                    adminForgotModal.close();
                    doctorForgotModal.close();
                    nurseForgotModal.close();

                }

            }
        );


        function setupForgotPasswordForm(
            role,
            modalHandler
        ) {

            var formId =
                role === 'admin'
                    ? 'adminForgotPassForm'
                    : role + 'ForgotPassForm';

            var emailId =
                role === 'admin'
                    ? 'adminforgotpass-email'
                    : role + 'forgotpass-email';


            var form =
                document.getElementById(formId);


            if (!form) {
                return;
            }


            form.addEventListener(
                'submit',
                function (event) {

                    var action =
                        form.getAttribute('action');


                    if (
                        !action ||
                        action === '#'
                    ) {

                        event.preventDefault();


                        var emailInput =
                            document.getElementById(emailId);


                        var email =
                            emailInput
                                ? emailInput.value
                                : '';


                        alert(
                            'Reset link request sent for ' +
                            email +
                            ' (prototype).'
                        );


                        modalHandler.close();

                    }

                }
            );

        }


        setupForgotPasswordForm(
            'admin',
            adminForgotModal
        );

        setupForgotPasswordForm(
            'doctor',
            doctorForgotModal
        );

        setupForgotPasswordForm(
            'nurse',
            nurseForgotModal
        );

    } catch (error) {

        console.error(
            '[PulseAlert] Forgot-password initialization failed.',
            error
        );

    }


    // =====================================================
    // CONTACT FORM PROTOTYPE
    // =====================================================

    try {

        var contactForm =
            document.querySelector('.contact-form');


        if (contactForm) {

            contactForm.addEventListener(
                'submit',
                function (event) {

                    event.preventDefault();

                    alert(
                        'Thanks! This is a static prototype — ' +
                        'hook this form up to your backend.'
                    );

                }
            );

        }

    } catch (error) {

        console.error(
            '[PulseAlert] Contact form initialization failed.',
            error
        );

    }


    // =====================================================
    // LOGIN FORM PROTOTYPE
    // =====================================================

    try {

        var loginForms =
            document.querySelectorAll(
                '#loginForm, ' +
                '#hospitaladminLoginForm, ' +
                '#doctorLoginForm, ' +
                '#nurseLoginForm, ' +
                '.login-form, ' +
                '.hospitaladmin-login-form, ' +
                '.doctor-login-form, ' +
                '.nurse-login-form'
            );


        loginForms.forEach(function (form) {

            form.addEventListener(
                'submit',
                function (event) {

                    var action =
                        form.getAttribute('action');


                    if (
                        !action ||
                        action === '#'
                    ) {

                        event.preventDefault();

                        console.log(
                            'Login form submitted'
                        );

                    }

                }
            );

        });

    } catch (error) {

        console.error(
            '[PulseAlert] Login form initialization failed.',
            error
        );

    }


    // =====================================================
    // SIGNUP INTERACTIONS
    // =====================================================

    try {

        function setupSignupForm(role) {

            var formId =
                role + 'SignupForm';

            var shellSelector =
                '.' + role + '-signup-shell';

            var buttonSelector =
                '.' + role + '-signup-submit';

            var fieldSelector =
                '.' + role + '-signup-field input';


            var shell =
                document.querySelector(shellSelector);


            if (shell) {

                shell.classList.add(
                    'is-visible'
                );

            }


            var form =
                document.getElementById(formId);


            if (form) {

                form.addEventListener(
                    'submit',
                    function () {

                        var submitButton =
                            form.querySelector(
                                buttonSelector
                            );


                        if (submitButton) {

                            submitButton.disabled =
                                true;

                            submitButton.textContent =
                                'Creating account...';

                            submitButton.style.opacity =
                                '0.9';

                        }

                    }
                );

            }


            var inputs =
                document.querySelectorAll(
                    fieldSelector
                );


            inputs.forEach(function (input) {

                input.addEventListener(
                    'focus',
                    function () {

                        if (input.parentElement) {

                            input.parentElement.classList.add(
                                'is-focused'
                            );

                        }

                    }
                );


                input.addEventListener(
                    'blur',
                    function () {

                        if (input.parentElement) {

                            input.parentElement.classList.remove(
                                'is-focused'
                            );

                        }

                    }
                );

            });

        }


        setupSignupForm('nurse');
        setupSignupForm('doctor');
        setupSignupForm('hospitaladmin');

    } catch (error) {

        console.error(
            '[PulseAlert] Signup initialization failed.',
            error
        );

    }


    // =====================================================
    // INCOMING DISPATCHES
    // =====================================================

    try {

        if (
            document.getElementById(
                'incomingdispatch-barangay-grid'
            ) ||
            document.getElementById(
                'incomingdispatch-search-input'
            )
        ) {

            incomingdispatchBindStaffPagination();
            incomingdispatchAnimateFacilityBars();
            incomingdispatchAnimateCards();
            incomingdispatchPulseActiveCards();
            incomingdispatchBindSearch();
            incomingdispatchBindSearchFocus();
            incomingdispatchBindBell();
            incomingdispatchBindBellShake();
            incomingdispatchBindDownload();
            incomingdispatchBindCardClicks();

        }

    } catch (error) {

        console.error(
            '[PulseAlert] Incoming Dispatches initialization failed.',
            error
        );

    }


    // =====================================================
    // EMERGENCY ALERTS
    // =====================================================

    try {

        if (
            document.getElementById(
                'emergency_alert_shell'
            ) ||
            document.getElementById(
                'emergency_alert_table'
            )
        ) {

            emergencyAlertInit();

        }

    } catch (error) {

        console.error(
            '[PulseAlert] Emergency Alerts initialization failed.',
            error
        );

    }


    // =====================================================
    // LIVE MAPPING
    // =====================================================

    try {

        if (document.getElementById('livemapping-map')) {
            livemappingInit();
        }

    } catch (error) {

        console.error('[PulseAlert] Live Mapping initialization failed.', error);

    }


    // =====================================================
    // STAFF SCHEDULE
    // =====================================================

    try {

        if (document.getElementById('hospitalsched-table-body')) {
            hospitalschedInit();
        }

    } catch (error) {

        console.error('[PulseAlert] Staff Schedule initialization failed.', error);

    }


    // =====================================================
    // RESOURCES
    // =====================================================

    try {

        if (
            document.getElementById('resources-table-body') ||
            document.getElementById('resources-grid') ||
            document.getElementById('resources-search-input')
        ) {
            initResourcesPage();
        }

    } catch (error) {

        console.error('[PulseAlert] Resources initialization failed.', error);

    }


    // =====================================================
    // HOSPITAL NETWORK
    // =====================================================

    try {

        if (document.getElementById('hospitalnetwork-grid')) {
            hospitalnetworkInit();
        }

    } catch (error) {

        console.error('[PulseAlert] Hospital Network initialization failed.', error);

    }


    // =====================================================
    // ANALYTICS
    // =====================================================

    try {

        if (document.getElementById('analytics-trend-chart')) {
            analyticsInit();
        }

    } catch (error) {

        console.error('[PulseAlert] Analytics initialization failed.', error);

    }


    // =====================================================
    // DEMOGRAPHICS
    // =====================================================

    try {

        if (document.getElementById('demographics-page')) {
            demographicsInit();
        }

    } catch (error) {

        console.error('[PulseAlert] Demographics initialization failed.', error);

    }

});


// =========================================================
// LIVE MAPPING
// =========================================================

function livemappingInit() {

    if (!window.L) {
        throw new Error('Leaflet did not load.');
    }

    livemappingBindHeaderControls();

    var dataElement = document.getElementById('livemapping-initial-data');
    var initialData = { ambulances: [], incidents: [], hospitals: [] };

    try {
        initialData = JSON.parse(dataElement.textContent || '{}');
    } catch (error) {
        console.warn('[Live Mapping] Initial map data could not be parsed.', error);
    }

    var primaryHospital = initialData.hospitals && initialData.hospitals[0];
    var hospitalLatitude = primaryHospital ? Number(primaryHospital.latitude) : NaN;
    var hospitalLongitude = primaryHospital ? Number(primaryHospital.longitude) : NaN;
    var hasHospitalCoordinates = Number.isFinite(hospitalLatitude) && Number.isFinite(hospitalLongitude);
    if (!hasHospitalCoordinates) {
        hospitalLatitude = 0;
        hospitalLongitude = 0;
    }
    var initialZoom = hasHospitalCoordinates ? 12 : 2;
    var map = L.map('livemapping-map', { zoomControl: false }).setView([hospitalLatitude, hospitalLongitude], initialZoom);
    L.control.zoom({ position: 'bottomright' }).addTo(map);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    window.livemappingMarkers = L.layerGroup().addTo(map);
    window.livemappingMap = map;
    window.livemappingData = initialData;
    window.livemappingFleetFilter = 'all';
    window.livemappingSeenIncidentIds = Object.create(null);
    window.livemappingRouteStatuses = Object.create(null);
    window.livemappingRouteAutoFitIncidentId =
        new URLSearchParams(window.location.search).get('incident_id') || '';
    window.livemappingRouteAutoFitted = false;
    (initialData.incidents || []).forEach(function (incident) {
        window.livemappingSeenIncidentIds[String(incident.id)] = true;
    });
    livemappingBindFleetFilters();
    livemappingBindAlertDrawer(initialData);
    livemappingRender(map, initialData);
    livemappingRefreshRoadRoutes(initialData);

    var requestedIncidentId = Number(new URLSearchParams(window.location.search).get('incident_id'));
    if (requestedIncidentId > 0) {
        var requestedAmbulance = (initialData.ambulances || []).find(function (ambulance) {
            return Number(ambulance.incident_id) === requestedIncidentId;
        });
        if (requestedAmbulance) {
            livemappingFocusVehicle(map, requestedAmbulance, livemappingRouteState(requestedAmbulance));
        } else {
            var requestedIncident = (initialData.incidents || []).find(function (incident) {
                return Number(incident.id) === requestedIncidentId;
            });
            if (requestedIncident) map.setView([Number(requestedIncident.latitude), Number(requestedIncident.longitude)], 16);
        }
    }

    if (!hasHospitalCoordinates && navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function (position) {
            if (!map._livemappingUserLocation) {
                map._livemappingUserLocation = true;
                map.setView([position.coords.latitude, position.coords.longitude], 14);
            }
        }, function () { /* Map data remains usable without location permission. */ }, { enableHighAccuracy: true, timeout: 5000, maximumAge: 30000 });
    }

    var selectedBarangay = new URLSearchParams(window.location.search).get('barangay');
    var refreshUrl = (window.APP_BASE_URL || '') + '/api/livemapping-data.php' +
        (selectedBarangay ? '?barangay=' + encodeURIComponent(selectedBarangay) : '');

    function refreshMapData() {
        fetch(refreshUrl, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (response) {
                if (!response.ok) throw new Error('Map refresh returned HTTP ' + response.status + '.');
                return response.json();
            })
            .then(function (response) {
                if (!response.success || !response.data) throw new Error(response.message || 'Map refresh returned invalid data.');
                if (livemappingAnnounceIncomingIncidents(map, response.data.incidents || []) > 0) {
                    window.livemappingIncidentPage = 0;
                }
                window.livemappingData = response.data;
                livemappingRender(map, response.data);
                livemappingRefreshRoadRoutes(response.data);
            })
            .catch(function (error) {
                console.warn('[Live Mapping] Refresh failed.', error);
            })
            .finally(function () {
                window.setTimeout(refreshMapData, 5000);
            });
    }
    window.setTimeout(refreshMapData, 5000);
}

function livemappingBindHeaderControls() {

    var input = document.getElementById('incomingdispatch-search-input');
    var bell = document.getElementById('incomingdispatch-bell-btn');

    if (input && input.getAttribute('data-livemapping-search-bound') !== '1') {
        input.setAttribute('data-livemapping-search-bound', '1');

        input.addEventListener('focus', function () {
            this.style.transition = 'width 0.2s ease';
            this.style.width = '340px';
        });

        input.addEventListener('blur', function () {
            this.style.width = '300px';
        });
    }

    pulseAlertShakeBell(bell);
}

function pulseAlertShakeBell(bell) {

    if (!bell || bell.getAttribute('data-pulse-alert-bell-bound') === '1') {
        return;
    }

    bell.setAttribute('data-pulse-alert-bell-bound', '1');
    bell.style.transition = 'transform 0.15s ease';

    window.setTimeout(function () {
        var ticks = 0;
        var shake = window.setInterval(function () {
            bell.style.transform = ticks % 2 === 0 ? 'rotate(-10deg)' : 'rotate(10deg)';
            ticks++;

            if (ticks > 3) {
                window.clearInterval(shake);
                bell.style.transform = 'rotate(0deg)';
            }
        }, 90);
    }, 700);
}

function hospitalschedInit() {

    var input = document.getElementById('hospitalsched-search-input');
    var bell = document.getElementById('hospitalsched-bell-btn');
    var panel = document.getElementById('hospitalsched-notification-panel');
    var list = document.getElementById('hospitalsched-notification-list');
    var wrapper = document.querySelector('.emergency_alert_notification_wrap');
    var refreshing = false;

    if (input) {
        input.addEventListener('focus', function () {
            this.style.transition = 'width 0.2s ease';
            this.style.width = '340px';
        });

        input.addEventListener('blur', function () {
            this.style.width = '300px';
        });

        input.addEventListener('input', hospitalschedFilterRows);
    }

    if (bell && panel) {
        bell.addEventListener('click', function () {
            panel.hidden = !panel.hidden;
            bell.setAttribute('aria-expanded', panel.hidden ? 'false' : 'true');
            var dot = document.getElementById('hospitalsched-bell-dot');
            if (!panel.hidden && dot) dot.hidden = true;
        });
    }

    if (list && input) {
        list.addEventListener('click', function (event) {
            var notification = event.target.closest('.hospitalsched-notification-item');
            if (!notification) return;

            var allFilter = document.querySelector('.hospitalsched-filter[data-role="all"]');
            document.querySelectorAll('.hospitalsched-filter').forEach(function (button) {
                button.classList.toggle('hospitalsched-filter--active', button === allFilter);
            });
            input.value = notification.getAttribute('data-staff-name') || '';
            hospitalschedFilterRows();
            if (panel) panel.hidden = true;
            if (bell) bell.setAttribute('aria-expanded', 'false');
            input.focus();
        });
    }

    document.addEventListener('click', function (event) {
        if (wrapper && !wrapper.contains(event.target) && panel && !panel.hidden) {
            panel.hidden = true;
            if (bell) bell.setAttribute('aria-expanded', 'false');
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && panel && !panel.hidden) {
            panel.hidden = true;
            if (bell) bell.setAttribute('aria-expanded', 'false');
        }
    });

    document.querySelectorAll('.hospitalsched-filter').forEach(function (button) {
        button.addEventListener('click', function () {
            document.querySelectorAll('.hospitalsched-filter').forEach(function (item) {
                item.classList.remove('hospitalsched-filter--active');
            });

            this.classList.add('hospitalsched-filter--active');
            hospitalschedFilterRows();
        });
    });

    function refreshSchedule() {
        if (refreshing || document.visibilityState !== 'visible') return;
        refreshing = true;
        var url = new URL(window.location.href);
        url.searchParams.set('action', 'status');
        fetch(url.toString(), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (response) {
            return response.json().then(function (result) {
                if (!response.ok || !result.success) {
                    throw new Error(result.message || 'Staff schedule could not be refreshed.');
                }
                return result;
            });
        }).then(function (result) {
            hospitalschedRenderSchedule(result.schedules || []);
            var onDutyCount = document.getElementById('hospitalsched-on-duty-count');
            var doctorCount = document.getElementById('hospitalsched-doctor-count');
            var nurseCount = document.getElementById('hospitalsched-nurse-count');
            if (onDutyCount) onDutyCount.textContent = String(result.onDutyCount || 0);
            if (doctorCount) doctorCount.textContent = String(result.doctorCount || 0);
            if (nurseCount) nurseCount.textContent = String(result.nurseCount || 0);
        }).catch(function (error) {
            console.error('[PulseAlert] Staff schedule refresh failed.', error);
        }).finally(function () {
            refreshing = false;
        });
    }

    refreshSchedule();
    window.setInterval(refreshSchedule, 1000);
    pulseAlertShakeBell(bell);
}

function hospitalschedRenderSchedule(schedules) {
    var tbody = document.getElementById('hospitalsched-table-body');
    if (!tbody) return;

    var rows = (schedules || []).map(function (schedule) {
        var row = document.createElement('tr');
        row.dataset.role = String(schedule.role || '').toLowerCase();
        row.dataset.status = String(schedule.status || 'off_duty');

        var staffCell = document.createElement('td');
        var initials = document.createElement('span');
        initials.className = 'hospitalsched-initials';
        initials.textContent = schedule.initials || '';
        var name = document.createElement('strong');
        name.textContent = schedule.name || '';
        staffCell.appendChild(initials);
        staffCell.appendChild(name);

        var roleCell = document.createElement('td');
        roleCell.textContent = schedule.role || '';
        var shiftCell = document.createElement('td');
        shiftCell.textContent = schedule.shift || 'No shift scheduled';
        var statusCell = document.createElement('td');
        var status = document.createElement('span');
        var online = Boolean(schedule.online);
        status.className = 'hospitalsched-status hospitalsched-status--' + (online ? 'online' : 'offline');
        status.textContent = schedule.statusLabel || 'Off duty';
        status.title = online ? 'Online in the system' : 'Offline in the system';
        status.setAttribute('aria-label', status.textContent + ', ' + (online ? 'online' : 'offline'));
        statusCell.appendChild(status);

        row.appendChild(staffCell);
        row.appendChild(roleCell);
        row.appendChild(shiftCell);
        row.appendChild(statusCell);
        return row;
    });
    tbody.replaceChildren.apply(tbody, rows);
    hospitalschedFilterRows();
}

function hospitalschedFilterRows() {

    var input = document.getElementById('hospitalsched-search-input');
    var activeFilter = document.querySelector('.hospitalsched-filter--active');
    var query = input ? input.value.trim().toLowerCase() : '';
    var filter = activeFilter ? activeFilter.getAttribute('data-role') : 'all';

    document.querySelectorAll('#hospitalsched-table-body tr').forEach(function (row) {
        var role = row.getAttribute('data-role');
        var status = row.getAttribute('data-status');
        var filterMatches = filter === 'all' || (filter === 'on_duty' ? status === 'on_duty' : role === filter);
        var searchMatches = query === '' || row.textContent.toLowerCase().indexOf(query) !== -1;

        row.hidden = !(filterMatches && searchMatches);
    });
}

function initResourcesPage() {

    var input = document.getElementById('resources-search-input');
    var bell = document.getElementById('resources-bell-btn');
    var panel = document.getElementById('resources-notification-panel');
    var resourceWrap = document.querySelector('.emergency_alert_notification_wrap');
    var filterButtons = document.querySelectorAll('.resources-filter');
    var cards = document.querySelectorAll('.resources-card');
    var rows = document.querySelectorAll('#resources-table-body tr');
    var toast = document.getElementById('resources-notification-toast');

    if (toast) {
        window.requestAnimationFrame(function () {
            toast.classList.add('is-visible');
        });
        window.setTimeout(function () {
            toast.classList.remove('is-visible');
        }, 3200);
    }

    function applyResourcesFilters() {
        var query = input ? input.value.trim().toLowerCase() : '';
        var activeFilter = document.querySelector('.resources-filter--active');
        var filter = activeFilter ? activeFilter.getAttribute('data-resource-filter') : 'all';

        cards.forEach(function (card) {
            var name = (card.getAttribute('data-resource-name') || '').toLowerCase();
            var type = (card.getAttribute('data-resource-type') || '').toLowerCase();
            var status = (card.getAttribute('data-resource-status') || '').toLowerCase();
            var matchesFilter = filter === 'all' || status === filter;
            var matchesQuery = query === '' || name.indexOf(query) !== -1 || type.indexOf(query) !== -1;
            card.hidden = !(matchesFilter && matchesQuery);
        });

        rows.forEach(function (row) {
            var name = (row.getAttribute('data-resource-name') || '').toLowerCase();
            var type = (row.getAttribute('data-resource-type') || '').toLowerCase();
            var status = (row.getAttribute('data-resource-status') || '').toLowerCase();
            var matchesFilter = filter === 'all' || status === filter;
            var matchesQuery = query === '' || name.indexOf(query) !== -1 || type.indexOf(query) !== -1;
            row.hidden = !(matchesFilter && matchesQuery);
        });
    }

    if (input) {
        input.addEventListener('focus', function () {
            this.style.transition = 'width 0.2s ease';
            this.style.width = '340px';
        });

        input.addEventListener('blur', function () {
            this.style.width = '300px';
        });

        input.addEventListener('input', applyResourcesFilters);
    }

    filterButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            filterButtons.forEach(function (item) {
                item.classList.toggle('resources-filter--active', item === button);
            });
            applyResourcesFilters();
        });
    });

    if (bell && panel) {
        bell.addEventListener('click', function () {
            panel.hidden = !panel.hidden;
            bell.setAttribute('aria-expanded', panel.hidden ? 'false' : 'true');
            var dot = document.getElementById('resources-bell-dot');
            if (!panel.hidden && dot) {
                dot.hidden = true;
            }
        });
    }

    var vehicleDialog = document.getElementById('resources-vehicle-dialog');
    var vehicleForm = document.getElementById('resources-vehicle-form');
    if (vehicleDialog && vehicleForm) {
        var vehicleId = document.getElementById('resources-vehicle-id');
        var vehicleType = document.getElementById('resources-vehicle-type');
        var callSign = document.getElementById('resources-call-sign');
        var plateNumber = document.getElementById('resources-plate-number');
        var driverName = document.getElementById('resources-driver-name');
        var vehicleStatus = document.getElementById('resources-vehicle-status');
        var vehicleTitle = document.getElementById('resources-vehicle-dialog-title');

        function openVehicleDialog(vehicle) {
            vehicleForm.reset();
            vehicleId.value = '';
            vehicleStatus.value = 'available';
            if (vehicle) {
                vehicleId.value = vehicle.dataset.vehicleId || '';
                vehicleType.value = vehicle.dataset.vehicleType || 'Ambulance';
                callSign.value = vehicle.dataset.callSign || '';
                plateNumber.value = vehicle.dataset.plateNumber || '';
                driverName.value = vehicle.dataset.driverName || '';
                vehicleStatus.value = vehicle.dataset.vehicleStatus || 'available';
                vehicleTitle.textContent = 'Edit vehicle';
            } else {
                vehicleType.value = 'Ambulance';
                vehicleTitle.textContent = 'Add vehicle';
            }
            vehicleDialog.showModal();
            callSign.focus();
        }

        document.querySelectorAll('[data-resource-vehicle-add]').forEach(function (button) {
            button.addEventListener('click', function () {
                openVehicleDialog(null);
                vehicleType.value = button.dataset.resourceVehicleAdd || 'Ambulance';
            });
        });

        document.querySelectorAll('[data-resource-vehicle-edit]').forEach(function (button) {
            button.addEventListener('click', function () {
                openVehicleDialog(button);
            });
        });

        vehicleDialog.querySelectorAll('[data-resource-vehicle-close]').forEach(function (button) {
            button.addEventListener('click', function () {
                vehicleDialog.close();
            });
        });

        vehicleDialog.addEventListener('click', function (event) {
            if (event.target === vehicleDialog) {
                vehicleDialog.close();
            }
        });
    }

    var bedDialog = document.getElementById('resources-bed-dialog');
    var bedEditButton = document.getElementById('resources-bed-edit');
    var bedMaximumInput = document.getElementById('resources-bed-maximum');
    if (bedDialog && bedEditButton && bedMaximumInput) {
        bedEditButton.addEventListener('click', function () {
            bedMaximumInput.value = bedEditButton.dataset.currentMaximum || '0';
            bedDialog.showModal();
            bedMaximumInput.focus();
        });

        bedDialog.querySelectorAll('[data-resource-bed-close]').forEach(function (button) {
            button.addEventListener('click', function () {
                bedDialog.close();
            });
        });

        bedDialog.addEventListener('click', function (event) {
            if (event.target === bedDialog) {
                bedDialog.close();
            }
        });
    }

    document.addEventListener('click', function (event) {
        if (resourceWrap && !resourceWrap.contains(event.target) && panel && !panel.hidden) {
            panel.hidden = true;
            if (bell) bell.setAttribute('aria-expanded', 'false');
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && panel && !panel.hidden) {
            panel.hidden = true;
            if (bell) bell.setAttribute('aria-expanded', 'false');
        }
    });

    applyResourcesFilters();
    pulseAlertShakeBell(bell);
}

function hospitalnetworkInit() {

    var input = document.getElementById('hospitalnetwork-search-input');
    var bell = document.getElementById('hospitalnetwork-bell-btn');
    var notificationPanel = document.getElementById('hospitalnetwork-notification-panel');
    var notificationList = document.getElementById('hospitalnetwork-notification-list');
    var notificationWrap = document.querySelector('.emergency_alert_notification_wrap');
    var modal = document.getElementById('hospitalnetwork-modal');
    var form = document.getElementById('hospitalnetwork-referral-form');
    var incidentSearch = document.getElementById('hospitalnetwork-incident-search');
    var incidentSearchButton = document.getElementById('hospitalnetwork-incident-search-btn');
    var incidentSelect = document.getElementById('hospitalnetwork-incident-select');
    var incidentSearchResult = document.getElementById('hospitalnetwork-incident-search-result');
    var hospitalSelect = document.getElementById('hospitalnetwork-hospital-id');
    var successPanel = document.getElementById('hospitalnetwork-referral-success');
    var toast = document.getElementById('hospitalnetwork-toast');

    function showReferralToast(message, kind) {
        if (!toast) return;
        toast.textContent = message;
        toast.classList.remove('is-success', 'is-error');
        toast.classList.add(kind === 'error' ? 'is-error' : 'is-success', 'is-visible');
        window.clearTimeout(window.hospitalnetworkToastTimer);
        window.hospitalnetworkToastTimer = window.setTimeout(function () {
            toast.classList.remove('is-visible');
        }, 3200);
    }

    function updateReferralHospital() {
        if (!hospitalSelect) return;
        var selectedHospital = hospitalSelect.selectedOptions[0];
        var name = selectedHospital && selectedHospital.value ? selectedHospital.dataset.hospitalName : '';
        var address = selectedHospital ? selectedHospital.dataset.hospitalAddress || '' : '';
        var specialties = selectedHospital ? selectedHospital.dataset.hospitalSpecialties || '' : '';
        document.getElementById('hospitalnetwork-modal-hospital').textContent = name ? 'Send to ' + name : 'Choose a receiving hospital';
        document.getElementById('hospitalnetwork-modal-details').textContent = name
            ? [address, specialties].filter(Boolean).join(' - ')
            : 'Select the receiving hospital for this referral.';
    }

    function resetIncidentSearch() {
        if (incidentSearch) incidentSearch.value = '';
        if (incidentSelect) {
            Array.prototype.forEach.call(incidentSelect.options, function (option) { option.hidden = false; });
        }
        if (incidentSearchResult && incidentSelect) {
            var activeIncidentCount = Array.prototype.filter.call(incidentSelect.options, function (option) { return Boolean(option.value); }).length;
            incidentSearchResult.textContent = activeIncidentCount
                ? activeIncidentCount + ' active incidents'
                : 'No active incidents available';
        }
    }

    function openReferralModal(hospitalId) {
        if (!modal) return;
        if (form) form.hidden = false;
        if (successPanel) successPanel.hidden = true;
        if (hospitalSelect) hospitalSelect.value = hospitalId || '';
        updateReferralHospital();
        resetIncidentSearch();
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
    }

    function filterReferralIncidents() {
        if (!incidentSearch || !incidentSelect) return;

        var query = incidentSearch.value.trim().toLowerCase();
        var matchCount = 0;
        Array.prototype.forEach.call(incidentSelect.options, function (option) {
            if (!option.value) return;
            var searchText = (option.dataset.search || option.textContent).toLowerCase();
            var matches = query === '' || searchText.indexOf(query) !== -1;
            option.hidden = !matches;
            if (matches) matchCount++;
        });

        if (incidentSelect.selectedOptions.length && incidentSelect.selectedOptions[0].hidden) {
            incidentSelect.value = '';
        }

        if (incidentSearchResult) {
            incidentSearchResult.textContent = matchCount
                ? matchCount + ' matching incident' + (matchCount === 1 ? '' : 's')
                : 'No matching incidents';
        }
    }

    if (incidentSearchButton) {
        incidentSearchButton.addEventListener('click', filterReferralIncidents);
    }

    if (incidentSearch) {
        incidentSearch.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                filterReferralIncidents();
            }
        });
    }

    if (hospitalSelect) {
        hospitalSelect.addEventListener('change', updateReferralHospital);
    }

    if (input) {
        input.addEventListener('focus', function () {
            this.style.transition = 'width 0.2s ease';
            this.style.width = '340px';
        });

        input.addEventListener('blur', function () {
            this.style.width = '300px';
        });

        input.addEventListener('input', function () {
            var query = this.value.trim().toLowerCase();
            document.querySelectorAll('.hospitalnetwork-card').forEach(function (card) {
                card.hidden = query !== '' && card.getAttribute('data-name').indexOf(query) === -1;
            });
        });
        Object.keys(window.livemappingRouteStatuses || {}).forEach(function (key) {
            if (!activeIncidentIds[key]) {
                livemappingSetRoadRouteStatus(key, null);
            }
        });
    }

    if (bell && notificationPanel) {
        bell.addEventListener('click', function () {
            notificationPanel.hidden = !notificationPanel.hidden;
            bell.setAttribute('aria-expanded', notificationPanel.hidden ? 'false' : 'true');
            var dot = document.getElementById('hospitalnetwork-bell-dot');
            if (!notificationPanel.hidden && dot) dot.hidden = true;
        });
    }

    if (notificationList && input) {
        notificationList.addEventListener('click', function (event) {
            var item = event.target.closest('.hospitalnetwork-notification-item');
            if (!item) return;

            input.value = item.getAttribute('data-hospital-name') || '';
            input.dispatchEvent(new Event('input', { bubbles: true }));
            if (notificationPanel) notificationPanel.hidden = true;
            if (bell) bell.setAttribute('aria-expanded', 'false');

            var card = document.querySelector('.hospitalnetwork-card[data-hospital-id="' + item.getAttribute('data-hospital-id') + '"]');
            if (card) card.scrollIntoView({ behavior: 'smooth', block: 'center' });
            input.focus();
        });
    }

    document.addEventListener('click', function (event) {
        if (notificationWrap && !notificationWrap.contains(event.target) && notificationPanel && !notificationPanel.hidden) {
            notificationPanel.hidden = true;
            if (bell) bell.setAttribute('aria-expanded', 'false');
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && notificationPanel && !notificationPanel.hidden) {
            notificationPanel.hidden = true;
            if (bell) bell.setAttribute('aria-expanded', 'false');
        }
    });

    document.querySelectorAll('.hospitalnetwork-refer-btn').forEach(function (button) {
        button.addEventListener('click', function () {
            openReferralModal(this.getAttribute('data-hospital-id'));
        });
    });

    var referralParams = new URLSearchParams(window.location.search);
    if (referralParams.get('open_referral') === '1') {
        openReferralModal(referralParams.get('hospital_id') || '');
    }

    if (modal) {
        modal.addEventListener('click', function (event) {
            if (event.target === modal || event.target.closest('.hospitalnetwork-modal-close, .hospitalnetwork-success-close')) {
                modal.classList.remove('is-open');
                modal.setAttribute('aria-hidden', 'true');
            }
        });
    }

    if (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            var submit = form.querySelector('button[type="submit"]');
            submit.disabled = true;

            fetch(new URL('hospitalnetworkController.php', window.location.href).toString(), {
                method: 'POST',
                body: new FormData(form),
                headers: { 'Accept': 'application/json' }
            })
                .then(function (response) {
                    if (!response.ok || !(response.headers.get('content-type') || '').includes('application/json')) {
                        throw new Error('Unable to send referral.');
                    }

                    return response.json();
                })
                .then(function (data) {
                    if (!data.success) {
                        throw new Error(data.message || 'Unable to send referral.');
                    }
                    var referral = data.referral || {};
                    var incidentId = Number(referral.incident_id);
                    document.getElementById('hospitalnetwork-referral-success-patient').textContent =
                        (referral.patient_name || 'Patient') + ' · Incident #' + incidentId + (referral.incident_type ? ' · ' + referral.incident_type : '');
                    document.getElementById('hospitalnetwork-referral-success-destination').textContent = referral.receiving_hospital || '';
                    document.getElementById('hospitalnetwork-referral-success-status').textContent = referral.status || 'pending';
                    document.getElementById('hospitalnetwork-referral-success-reason').textContent = referral.referral_reason || '';
                    document.getElementById('hospitalnetwork-referral-map-link').href =
                        new URL('livemappingController.php?incident_id=' + encodeURIComponent(incidentId), window.location.href).toString();
                    form.hidden = true;
                    if (successPanel) successPanel.hidden = false;
                    submit.disabled = false;
                    showReferralToast(data.message || 'Referral sent successfully.', 'success');
                })
                .catch(function (error) {
                    submit.disabled = false;
                    showReferralToast(error.message || 'Unable to send referral.', 'error');
                });
        });
    }

    pulseAlertShakeBell(bell);
}

function analyticsInit() {

    var canvas = document.getElementById('analytics-trend-chart');
    var dataElement = document.getElementById('analytics-trend-data');
    var range = document.getElementById('analytics-range');
    var customDates = document.querySelector('.analytics-custom-dates');
    var rangeForm = range ? range.form : null;
    var fromDate = document.getElementById('analytics-from-date');
    var toDate = document.getElementById('analytics-to-date');

    incomingdispatchBindBell();
    incomingdispatchBindBellShake();

    if (range) {
        function updateAnalyticsDateInputs() {
            var isCustom = range.value === 'custom';
            if (customDates) customDates.hidden = !isCustom;
            if (fromDate) fromDate.required = isCustom;
            if (toDate) {
                toDate.required = isCustom;
                toDate.min = fromDate && fromDate.value ? fromDate.value : '';
                if (fromDate && fromDate.value && toDate.value && toDate.value < fromDate.value) {
                    toDate.value = fromDate.value;
                }
            }
        }

        updateAnalyticsDateInputs();
        range.addEventListener('change', function () {
            if (this.value !== 'custom') {
                this.form.submit();
            } else {
                updateAnalyticsDateInputs();
            }
        });

        if (fromDate) fromDate.addEventListener('change', updateAnalyticsDateInputs);
        if (rangeForm) {
            rangeForm.addEventListener('submit', function (event) {
                if (range.value === 'custom' && fromDate && toDate && fromDate.value > toDate.value) {
                    event.preventDefault();
                    toDate.setCustomValidity('End date must be on or after start date.');
                    toDate.reportValidity();
                    return;
                }
                if (toDate) toDate.setCustomValidity('');
            });
        }
    }

    if (!canvas || !dataElement) {
        return;
    }

    try {
        analyticsDrawTrend(canvas, JSON.parse(dataElement.textContent || '[]'));
    } catch (error) {
        console.warn('[Analytics] Trend chart could not be drawn.', error);
    }
}

function analyticsDrawTrend(canvas, rows) {

    var context = canvas.getContext('2d');
    var rect = canvas.getBoundingClientRect();
    var width = Math.max(260, Math.round(rect.width));
    var height = Math.max(180, Math.round(rect.height));
    var ratio = window.devicePixelRatio || 1;
    var values = rows.map(function (row) { return Number(row.value) || 0; });
    var labels = rows.map(function (row) { return row.label || ''; });
    var max = Math.max(5, Math.ceil(Math.max.apply(null, values.concat([0])) / 5) * 5);
    var padding = { top: 18, right: 14, bottom: 28, left: 30 };

    canvas.width = width * ratio;
    canvas.height = height * ratio;
    context.setTransform(ratio, 0, 0, ratio, 0, 0);
    context.clearRect(0, 0, width, height);

    context.strokeStyle = '#eee1de';
    context.lineWidth = 1;
    for (var row = 0; row < 5; row++) {
        var y = padding.top + ((height - padding.top - padding.bottom) * row / 4);
        context.beginPath(); context.moveTo(padding.left, y); context.lineTo(width - padding.right, y); context.stroke();
    }

    if (!values.length) {
        return;
    }

    var chartWidth = width - padding.left - padding.right;
    var chartHeight = height - padding.top - padding.bottom;
    context.beginPath();
    values.forEach(function (value, index) {
        var x = padding.left + chartWidth * index / Math.max(1, values.length - 1);
        var y = padding.top + chartHeight - (value / max * chartHeight);
        index === 0 ? context.moveTo(x, y) : context.lineTo(x, y);
    });
    context.strokeStyle = '#8b0000'; context.lineWidth = 2.5; context.stroke();

    context.fillStyle = '#8b0000';
    values.forEach(function (value, index) {
        var x = padding.left + chartWidth * index / Math.max(1, values.length - 1);
        var y = padding.top + chartHeight - (value / max * chartHeight);
        context.beginPath(); context.arc(x, y, 3, 0, Math.PI * 2); context.fill();
    });

    context.fillStyle = '#7d7774'; context.font = '9px Inter, sans-serif'; context.textAlign = 'center';
    labels.forEach(function (label, index) {
        var x = padding.left + chartWidth * index / Math.max(1, labels.length - 1);
        context.fillText(label, x, height - 8);
    });
}

function demographicsInit() {

    var filterButtons = document.querySelectorAll('.demographics-filter');
    var statValues = document.querySelectorAll('.demographics-stat-value');
    var rows = document.querySelectorAll('.demographics-log-table tbody tr');
    var previousLogsButton = document.getElementById('demographics-log-previous');
    var nextLogsButton = document.getElementById('demographics-log-next');
    var logPageLabel = document.getElementById('demographics-log-page');
    var range = document.getElementById('demographics-range');
    var customDates = document.querySelector('.demographics-custom-dates');
    var rangeForm = range ? range.form : null;
    var fromDate = document.getElementById('demographics-from-date');
    var toDate = document.getElementById('demographics-to-date');
    var currentLogPage = 0;
    var logPageSize = 4;
    var activeLogFilter = 'all';

    function renderDemographicsLogPage() {
        var matchingRows = Array.prototype.filter.call(rows, function (row) {
            return activeLogFilter === 'all' || (row.getAttribute('data-status') || 'pending') === activeLogFilter;
        });
        var pageCount = Math.max(1, Math.ceil(matchingRows.length / logPageSize));
        currentLogPage = Math.min(currentLogPage, pageCount - 1);
        var firstVisibleIndex = currentLogPage * logPageSize;
        var visibleRows = matchingRows.slice(firstVisibleIndex, firstVisibleIndex + logPageSize);

        Array.prototype.forEach.call(rows, function (row) {
            row.hidden = visibleRows.indexOf(row) === -1;
        });

        if (logPageLabel) {
            logPageLabel.textContent = matchingRows.length
                ? 'Page ' + (currentLogPage + 1) + ' of ' + pageCount + ' · ' + matchingRows.length + ' logs'
                : 'No matching logs';
        }
        if (previousLogsButton) previousLogsButton.disabled = currentLogPage === 0 || matchingRows.length === 0;
        if (nextLogsButton) nextLogsButton.disabled = currentLogPage >= pageCount - 1 || matchingRows.length === 0;
    }

    incomingdispatchBindBell();
    incomingdispatchBindBellShake();

    if (range) {
        function updateDemographicsDateInputs() {
            var isCustom = range.value === 'custom';
            if (customDates) customDates.hidden = !isCustom;
            if (fromDate) fromDate.required = isCustom;
            if (toDate) {
                toDate.required = isCustom;
                toDate.min = fromDate && fromDate.value ? fromDate.value : '';
                if (fromDate && fromDate.value && toDate.value && toDate.value < fromDate.value) {
                    toDate.value = fromDate.value;
                }
                toDate.setCustomValidity('');
            }
        }

        updateDemographicsDateInputs();
        range.addEventListener('change', function () {
            if (this.value !== 'custom') {
                this.form.submit();
            } else {
                updateDemographicsDateInputs();
            }
        });

        if (fromDate) fromDate.addEventListener('change', updateDemographicsDateInputs);
        if (rangeForm) {
            rangeForm.addEventListener('submit', function (event) {
                if (range.value === 'custom' && fromDate && toDate && fromDate.value > toDate.value) {
                    event.preventDefault();
                    toDate.setCustomValidity('End date must be on or after start date.');
                    toDate.reportValidity();
                    return;
                }
                if (toDate) toDate.setCustomValidity('');
            });
        }
    }

    if (previousLogsButton) {
        previousLogsButton.addEventListener('click', function () {
            if (currentLogPage > 0) {
                currentLogPage--;
                renderDemographicsLogPage();
            }
        });
    }

    if (nextLogsButton) {
        nextLogsButton.addEventListener('click', function () {
            currentLogPage++;
            renderDemographicsLogPage();
        });
    }

    filterButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            filterButtons.forEach(function (btn) {
                btn.classList.remove('demographics-filter--active');
            });

            this.classList.add('demographics-filter--active');

            activeLogFilter = this.getAttribute('data-filter') || 'all';
            currentLogPage = 0;
            renderDemographicsLogPage();
        });
    });

    renderDemographicsLogPage();

    statValues.forEach(function (valueNode) {
        var target = Number(valueNode.getAttribute('data-target') || 0);
        var duration = 850;
        var start = null;
        var current = 0;

        function animateNumber(timestamp) {
            if (!start) {
                start = timestamp;
            }

            var elapsed = timestamp - start;
            var progress = Math.min(elapsed / duration, 1);
            var eased = 1 - Math.pow(1 - progress, 3);
            current = Math.round(target * eased);
            valueNode.textContent = current;

            if (progress < 1) {
                window.requestAnimationFrame(animateNumber);
            } else {
                valueNode.textContent = target;
            }
        }

        window.requestAnimationFrame(animateNumber);
    });
}

function livemappingRender(map, data) {

    var markers = window.livemappingMarkers;
    var fleetList = document.getElementById('livemapping-fleet-list');
    window.livemappingStaffAlertSent = window.livemappingStaffAlertSent || Object.create(null);
    window.livemappingIncidentPage = window.livemappingIncidentPage || 0;
    // Clearing the layers closes the open popup; the flag keeps that from forgetting which one was open.
    window.livemappingRedrawing = true;
    markers.clearLayers();
    window.livemappingRedrawing = false;
    fleetList.innerHTML = '';
    livemappingRenderIncidentRoster(map, fleetList, data.incidents || [], data.ambulances || []);
    livemappingRenderDatabaseFleet(
        data.fleet_ambulances || [],
        data.fleet_incidents || []
    );
    window.requestAnimationFrame(function () {
        map.invalidateSize({ pan: false });
    });

    (data.hospitals || []).forEach(function (hospital) {
        livemappingAddMarker(markers, hospital.latitude, hospital.longitude, 'hospital', '&#127973;', livemappingEscapeHtml(hospital.name), 'hospital-' + hospital.id);
    });

    (data.incidents || []).forEach(function (incident) {
        var movementStatus = livemappingIncidentStatus(incident);
        var route = movementStatus === 'en_route'
            ? livemappingIncidentRouteState(incident)
            : null;
        var destination = incident.destination_name || 'Hospital destination unavailable';
        var destinationLabel = movementStatus === 'arrived'
            ? 'Arrived at ' + destination
            : Number(incident.is_referred) === 1
                ? 'En route to referred hospital: ' + destination
                : 'En route to ' + destination;
        if (route) {
            livemappingAddRoute(markers, route, destination, destinationLabel);
        }
        var etaMinutes = route && route.etaMinutes !== null && route.etaMinutes !== undefined
            ? route.etaMinutes
            : incident.eta_minutes;
        var incidentEta = etaMinutes !== null && etaMinutes !== undefined ? etaMinutes + ' min' : 'Unavailable';
        var incidentStatusLabel = livemappingIncidentStatusLabel(incident);
        livemappingAddMarker(markers, incident.latitude, incident.longitude, 'incident', '!', livemappingEscapeHtml(incident.incident_type) + '<br>Status: ' + livemappingEscapeHtml(incidentStatusLabel) + '<br>' + livemappingEscapeHtml(incident.location) + '<br>' + livemappingEscapeHtml(destinationLabel) + '<br>ETA: ' + livemappingEscapeHtml(incidentEta), 'incident-' + incident.id);
    });

    var fleet = (data.ambulances || []).map(function (ambulance) {
        var status = livemappingTransportStatus(ambulance) || 'offline';
        var route = livemappingRouteState(ambulance);
        return { ambulance: ambulance, status: status, route: route };
    });
    var statusOrder = { en_route: 0, arrived: 1, available: 2, offline: 3 };
    fleet.sort(function (left, right) {
        return (statusOrder[left.status] ?? 4) - (statusOrder[right.status] ?? 4);
    });

    fleet.forEach(function (entry) {
        var ambulance = entry.ambulance;
        var status = entry.status;
        var route = entry.route;
        if (window.livemappingFleetFilter !== 'all' && status !== window.livemappingFleetFilter) return;
        var latitude = route ? route.latitude : ambulance.latitude;
        var longitude = route ? route.longitude : ambulance.longitude;
        var detail = ambulance.vehicle_type + ' - ' + (ambulance.assignment_note || 'No current assignment');
        if (ambulance.patient_name) {
            detail += ' - Patient: ' + ambulance.patient_name;
        }
        if (route) {
            detail += ' - ' + route.distanceKm.toFixed(1) + ' km to ' + ambulance.destination_name;
            detail += route.etaMinutes !== null ? ' - ETA ' + route.etaMinutes + ' min' : ' - ETA pending location update';
        } else if (ambulance.eta_minutes !== null && ambulance.eta_minutes !== undefined) {
            detail += ' - ETA ' + ambulance.eta_minutes + ' min';
        }
        livemappingAddMarker(markers, latitude, longitude, status, ambulance.vehicle_type === 'PTV' ? '&#128663;' : '&#128657;', livemappingEscapeHtml(ambulance.call_sign) + '<br>' + livemappingEscapeHtml(detail), 'vehicle-' + ambulance.id);
    });

    livemappingBindUnitAlerts();
}

function livemappingRenderIncidentRoster(map, list, incidents, ambulances) {
    var count = document.getElementById('livemapping-incident-count');
    if (!list) return;

    var filter = window.livemappingFleetFilter || 'all';
    var incidentRecords = incidents
        .filter(function (incident) {
            return filter === 'all' || livemappingIncidentStatus(incident) === filter;
        })
        .map(function (incident) {
            return {
                kind: 'incident',
                data: incident,
                time: Date.parse(incident.reported_at || incident.created_at || '') || 0
            };
        });
    var vehicleRecords = ambulances
        .filter(function (ambulance) {
            var status = livemappingTransportStatus(ambulance);
            var isAssignedIncidentListed = ambulance.incident_id &&
                incidents.some(function (incident) {
                    return String(incident.id) === String(ambulance.incident_id);
                });
            return !isAssignedIncidentListed && (filter === 'all' || status === filter);
        })
        .map(function (ambulance) {
            return {
                kind: 'vehicle',
                data: ambulance,
                time: Date.parse(
                    ambulance.arrived_at || ambulance.recorded_at ||
                    ambulance.updated_at || ambulance.created_at || ''
                ) || 0
            };
        });
    var records = incidentRecords.concat(vehicleRecords).sort(function (left, right) {
        return right.time - left.time ||
            Number(right.data.id || right.data.incident_id) -
            Number(left.data.id || left.data.incident_id);
    });
    if (count) {
        count.textContent = records.length + ' record' + (records.length === 1 ? '' : 's');
    }
    var pageSize = 3;
    var pageCount = Math.ceil(records.length / pageSize);
    window.livemappingIncidentPage = Math.min(window.livemappingIncidentPage, Math.max(0, pageCount - 1));
    var page = window.livemappingIncidentPage;
    var pageRecords = records.slice(page * pageSize, (page + 1) * pageSize);
    var pageKey = filter + ':' + page + ':' + pageRecords.map(function (record) {
        return record.kind + ':' + String(record.data.id || record.data.incident_id);
    }).join(',');

    if (pageKey !== window.livemappingMapPageKey) {
        var pageBounds = [];
        pageRecords.forEach(function (record) {
            var item = record.data;
            livemappingAddBoundsPoint(pageBounds, item.latitude, item.longitude);
        });
        if (pageBounds.length) {
            map.fitBounds(pageBounds, { padding: [55, 55], maxZoom: 15 });
        }
        window.livemappingMapPageKey = pageKey;
    }

    if (!pageRecords.length) {
        list.insertAdjacentHTML('beforeend', '<p class="livemapping-incident-empty">No ' + livemappingEscapeHtml(filter.replace('_', ' ')) + ' records to display.</p>');
    }

    pageRecords.forEach(function (record) {
        if (record.kind === 'vehicle') {
            var vehicle = record.data;
            var vehicleType = vehicle.vehicle_type || 'Ambulance';
            var isPtv = String(vehicleType).toLowerCase().indexOf('ptv') !== -1;
            var status = livemappingTransportStatus(vehicle);
            var coordinates = livemappingCoordinates(vehicle.latitude, vehicle.longitude);
            var hasCoordinates = coordinates !== 'Coordinates unavailable';
            list.insertAdjacentHTML('beforeend',
                '<article class="livemapping-incident-card">' +
                '<div class="livemapping-incident-card-heading">' +
                '<span class="livemapping-incident-transport"><i class="livemapping-incident-transport-icon' + (isPtv ? ' livemapping-incident-transport-icon--ptv' : '') + '" aria-hidden="true">' + (isPtv ? '&#128663;' : '&#128657;') + '</i>' +
                '<strong>' + livemappingEscapeHtml(vehicle.call_sign || 'Unnamed vehicle') + ' · ' + livemappingEscapeHtml(vehicleType) + '</strong></span>' +
                '<span class="livemapping-incident-status livemapping-incident-status--' + livemappingEscapeClass(status) + '">' + livemappingEscapeHtml(status.replace('_', ' ')) + '</span>' +
                '</div>' +
                '<p>Hospital: ' + livemappingEscapeHtml(vehicle.hospital_name || 'Hospital unavailable') + '</p>' +
                '<p>Driver: ' + livemappingEscapeHtml(vehicle.driver_name || 'Not assigned') + '</p>' +
                '<p>' + livemappingEscapeHtml(vehicle.assignment_note || 'No current assignment') + ' · ' + livemappingEscapeHtml(coordinates) + '</p>' +
                (hasCoordinates ? '<div class="livemapping-incident-actions"><button type="button" class="livemapping-incident-focus">Focus on map</button></div>' : '') +
                '</article>');
            var vehicleFocus = list.lastElementChild.querySelector('.livemapping-incident-focus');
            if (vehicleFocus) {
                vehicleFocus.addEventListener('click', function () {
                    map.setView([Number(vehicle.latitude), Number(vehicle.longitude)], 16);
                });
            }
            return;
        }

        var incident = record.data;
        var movementStatus = livemappingIncidentStatus(incident);
        var statusLabel = livemappingIncidentStatusLabel(incident);
        var location = incident.location || 'Location unavailable';
        var barangay = incident.barangay_name || 'Barangay unavailable';
        var patient = incident.patient_name ? 'Patient: ' + incident.patient_name : '';
        var destination = incident.destination_name || 'Hospital destination unavailable';
        var vehicle = ambulances.find(function (item) {
            return String(item.incident_id || '') === String(incident.id);
        });
        var eta = incident.eta_minutes !== null && incident.eta_minutes !== undefined
            ? 'ETA: ' + incident.eta_minutes + ' min'
            : '';
        var incidentLatitude = Number(incident.latitude);
        var incidentLongitude = Number(incident.longitude);
        var canFocus = incident.latitude !== null && incident.latitude !== '' &&
            incident.longitude !== null && incident.longitude !== '' &&
            Number.isFinite(incidentLatitude) && Number.isFinite(incidentLongitude) &&
            Math.abs(incidentLatitude) <= 90 && Math.abs(incidentLongitude) <= 180;
        var transportName = incident.transport_name || (vehicle && vehicle.call_sign) || '';
        var transportType = String(incident.transport_type || (vehicle && vehicle.vehicle_type) || '').toLowerCase();
        var isPtv = transportType.indexOf('ptv') !== -1;
        var hasTransport = Boolean(transportName);
        var transportIcon = hasTransport
            ? (isPtv ? '&#128663;' : '&#128657;')
            : '!';
        var transportLabel = hasTransport
            ? transportName + ' · ' + (incident.transport_type || (vehicle && vehicle.vehicle_type) || (isPtv ? 'PTV' : 'Ambulance'))
            : 'Transport not assigned';
        var driver = incident.driver_name || (vehicle && vehicle.driver_name);
        var alreadyAlerted = window.livemappingStaffAlertSent[String(incident.id)];
        var alertButton = alreadyAlerted
            ? '<button type="button" class="livemapping-incident-alert" disabled>Staff notified</button>'
            : '<button type="button" class="livemapping-incident-alert" data-incident-id="' + livemappingEscapeHtml(String(incident.id)) + '" data-transport-name="' + livemappingEscapeHtml(String(transportName)) + '">Alert staff</button>';

        list.insertAdjacentHTML('beforeend', '<article class="livemapping-incident-card">' +
            '<div class="livemapping-incident-card-heading">' +
            '<span class="livemapping-incident-transport"><i class="livemapping-incident-transport-icon' + (isPtv ? ' livemapping-incident-transport-icon--ptv' : '') + '" aria-hidden="true">' + transportIcon + '</i>' +
            '<strong>#' + livemappingEscapeHtml(String(incident.id)) + ' · ' + livemappingEscapeHtml(incident.incident_type || 'Emergency incident') + '</strong></span>' +
            '<span class="livemapping-incident-status livemapping-incident-status--' + livemappingEscapeClass(movementStatus) + '">' + livemappingEscapeHtml(statusLabel) + '</span>' +
            '</div>' +
            '<p>' + livemappingEscapeHtml(transportLabel) + '</p>' +
            '<p>Barangay: ' + livemappingEscapeHtml(barangay) + '</p>' +
            '<p>' + livemappingEscapeHtml(location) + '</p>' +
            (driver ? '<p>Driver: ' + livemappingEscapeHtml(driver) + '</p>' : '') +
            (patient ? '<p>' + livemappingEscapeHtml(patient) + '</p>' : '') +
            '<p>Hospital: ' + livemappingEscapeHtml(destination) + (eta ? ' · ' + livemappingEscapeHtml(eta) : '') + '</p>' +
            '<div class="livemapping-incident-actions">' +
            '<button type="button" class="livemapping-incident-focus" data-incident-id="' + livemappingEscapeHtml(String(incident.id)) + '"' + (canFocus ? '' : ' disabled') + '>Focus on map</button>' +
            alertButton + '</div></article>');
    });

    if (pageCount > 1) {
        list.insertAdjacentHTML('beforeend',
            '<nav class="livemapping-incident-pagination" aria-label="Fleet status pages">' +
            '<button type="button" class="livemapping-incident-page-button" data-incident-page="previous"' + (page === 0 ? ' disabled' : '') + '>Previous</button>' +
            '<span class="livemapping-incident-page-label">Page ' + (page + 1) + ' of ' + pageCount + '</span>' +
            '<button type="button" class="livemapping-incident-page-button" data-incident-page="next"' + (page + 1 >= pageCount ? ' disabled' : '') + '>Next</button>' +
            '</nav>');
    }

    list.querySelectorAll('.livemapping-incident-focus:not(:disabled)').forEach(function (button) {
        button.addEventListener('click', function () {
            var incident = pageRecords.find(function (record) {
                return record.kind === 'incident' &&
                    String(record.data.id) === button.dataset.incidentId;
            });
            if (!incident) return;
            map.setView([Number(incident.data.latitude), Number(incident.data.longitude)], 16);
        });
    });
    list.querySelectorAll('.livemapping-incident-page-button:not(:disabled)').forEach(function (button) {
        button.addEventListener('click', function () {
            window.livemappingIncidentPage += button.dataset.incidentPage === 'next' ? 1 : -1;
            livemappingRender(map, window.livemappingData || { incidents: [], ambulances: [], hospitals: [] });
        });
    });
    livemappingBindUnitAlerts();
}

function livemappingRenderDatabaseFleet(ambulances, incidents) {
    var list = document.getElementById('livemapping-ambulance-directory-list');
    var count = document.getElementById('livemapping-ambulance-count');
    if (!list) return;

    if (count) {
        count.textContent = ambulances.length + ' vehicles · ' + incidents.length + ' incidents';
    }
    list.innerHTML = '';
    var linkedVehicleIds = Object.create(null);
    var records = incidents.map(function (incident) {
        var linkedVehicle = ambulances.find(function (vehicle) {
            return String(vehicle.incident_id || '') === String(incident.id);
        });
        var activityTime = Date.parse(incident.reported_at || incident.created_at || '') || 0;
        if (linkedVehicle) {
            activityTime = Math.max(
                activityTime,
                Date.parse(linkedVehicle.arrived_at || '') || 0,
                Date.parse(linkedVehicle.location_recorded_at || '') || 0,
                Date.parse(linkedVehicle.updated_at || '') || 0,
                Date.parse(linkedVehicle.created_at || '') || 0
            );
        }
        return {
            kind: 'incident',
            data: incident,
            time: activityTime
        };
    });
    ambulances.forEach(function (vehicle) {
        var incident = incidents.find(function (item) {
            return String(item.id) === String(vehicle.incident_id || '');
        });
        if (incident) {
            linkedVehicleIds[String(vehicle.id)] = true;
            return;
        }
        records.push({
            kind: 'vehicle',
            data: vehicle,
            time: Date.parse(
                vehicle.arrived_at || vehicle.location_recorded_at ||
                vehicle.updated_at || vehicle.created_at || ''
            ) || 0
        });
    });
    records.sort(function (left, right) {
        return right.time - left.time ||
            Number(right.data.id || right.data.incident_id) -
            Number(left.data.id || left.data.incident_id);
    });

    var pageSize = 6;
    var pageCount = Math.ceil(records.length / pageSize);
    window.livemappingDatabaseFleetPage = Math.min(
        window.livemappingDatabaseFleetPage || 0,
        Math.max(0, pageCount - 1)
    );
    var page = window.livemappingDatabaseFleetPage;
    var pageRecords = records.slice(page * pageSize, (page + 1) * pageSize);

    pageRecords.forEach(function (record) {
        if (record.kind === 'vehicle') {
            var vehicle = record.data;
            if (linkedVehicleIds[String(vehicle.id)]) return;
            var vehicleStatus = livemappingTransportStatus(vehicle);
            var vehicleIsPtv = String(vehicle.vehicle_type || '').toLowerCase().indexOf('ptv') !== -1;
            list.insertAdjacentHTML('beforeend',
                '<article class="livemapping-incident-card livemapping-vehicle-card">' +
                '<div class="livemapping-incident-card-heading">' +
                '<span class="livemapping-incident-transport"><i class="livemapping-incident-transport-icon' + (vehicleIsPtv ? ' livemapping-incident-transport-icon--ptv' : '') + '" aria-hidden="true">' + (vehicleIsPtv ? '&#128663;' : '&#128657;') + '</i>' +
                '<strong>' + livemappingEscapeHtml(vehicle.call_sign || 'Unnamed vehicle') + ' · ' + livemappingEscapeHtml(vehicle.vehicle_type || 'Ambulance') + '</strong></span>' +
                '<span class="livemapping-incident-status livemapping-incident-status--' + livemappingEscapeClass(vehicleStatus) + '">' + livemappingEscapeHtml(vehicleStatus.replace('_', ' ')) + '</span>' +
                '</div>' +
                '<p>Hospital: ' + livemappingEscapeHtml(vehicle.hospital_name || 'Hospital unavailable') + '</p>' +
                '<p>Driver: ' + livemappingEscapeHtml(vehicle.driver_name || 'Not assigned') + '</p>' +
                '<p>' + livemappingEscapeHtml(vehicle.assignment_note || 'No current assignment') + '</p>' +
                '</article>');
            return;
        }

        var incident = record.data;
        var vehicle = ambulances.find(function (item) {
            return String(item.incident_id || '') === String(incident.id);
        });
        var transportName = incident.transport_name || (vehicle && vehicle.call_sign);
        var transportType = incident.transport_type || (vehicle && vehicle.vehicle_type) || '';
        var isPtv = String(transportType).toLowerCase().indexOf('ptv') !== -1;
        var movementStatus = String(incident.movement_status || incident.status || 'unknown').toLowerCase();
        var severity = String(incident.status || 'unknown').replace('_', ' ');
        severity = severity.charAt(0).toUpperCase() + severity.slice(1);
        var statusLabel = movementStatus === 'en_route'
            ? 'En-route'
            : movementStatus === 'arrived'
                ? 'Arrived'
                : severity;
        var etaMinutes = vehicle && vehicle.eta_minutes !== null && vehicle.eta_minutes !== undefined
            ? vehicle.eta_minutes
            : incident.eta_minutes;
        var eta = etaMinutes !== null && etaMinutes !== undefined
            ? ' · ETA ' + etaMinutes + ' min'
            : '';

        list.insertAdjacentHTML('beforeend',
            '<article class="livemapping-incident-card">' +
            '<div class="livemapping-incident-card-heading">' +
            '<span class="livemapping-incident-transport"><i class="livemapping-incident-transport-icon' + (isPtv ? ' livemapping-incident-transport-icon--ptv' : '') + '" aria-hidden="true">' + (transportName ? (isPtv ? '&#128663;' : '&#128657;') : '!') + '</i>' +
            '<strong>#' + livemappingEscapeHtml(String(incident.id)) + ' · ' + livemappingEscapeHtml(incident.incident_type || 'Emergency incident') + '</strong></span>' +
            '<span class="livemapping-incident-status livemapping-incident-status--' + livemappingEscapeClass(movementStatus) + '">' + livemappingEscapeHtml(statusLabel) + '</span>' +
            '</div>' +
            '<p>' + livemappingEscapeHtml(transportName ? transportName + ' · ' + transportType : 'Transport not assigned') + '</p>' +
            '<p>Barangay: ' + livemappingEscapeHtml(incident.barangay_name || 'Barangay unavailable') + '</p>' +
            '<p>' + livemappingEscapeHtml(incident.location || 'Location unavailable') + '</p>' +
            '<p>Hospital: ' + livemappingEscapeHtml(incident.destination_name || incident.owner_hospital_name || 'Hospital unavailable') + eta + '</p>' +
            '</article>');
    });

    if (pageRecords.length === 0) {
        list.innerHTML = '<p class="livemapping-incident-empty">No fleet or incident records found.</p>';
    }
    if (pageCount > 1) {
        list.insertAdjacentHTML('beforeend',
            '<nav class="livemapping-database-pagination" aria-label="All-hospital fleet pages">' +
            '<button type="button" data-database-page="previous"' + (page === 0 ? ' disabled' : '') + '>Previous</button>' +
            '<span>Page ' + (page + 1) + ' of ' + pageCount + '</span>' +
            '<button type="button" data-database-page="next"' + (page + 1 >= pageCount ? ' disabled' : '') + '>Next</button>' +
            '</nav>');
        list.querySelectorAll('[data-database-page]:not(:disabled)').forEach(function (button) {
            button.addEventListener('click', function () {
                window.livemappingDatabaseFleetPage += button.dataset.databasePage === 'next' ? 1 : -1;
                livemappingRenderDatabaseFleet(ambulances, incidents);
            });
        });
    }
}

function livemappingIncidentStatus(incident) {
    var transportStatus = String(incident.transport_status || '').toLowerCase();
    if (transportStatus === 'arrived' || incident.transport_arrived_at || incident.hospital_arrived_at) {
        return 'arrived';
    }
    var incidentIsActive = ['critical', 'urgent', 'dispatched', 'stable']
        .indexOf(String(incident.status || '').toLowerCase()) !== -1;
    if (transportStatus === 'en_route' || (incidentIsActive && (
        Number(incident.sent_to_hospital) === 1 ||
        String(incident.reported_by || '').toLowerCase() === 'on_scene'
    ))) {
        return 'en_route';
    }
    return String(incident.status || 'unknown').toLowerCase();
}

function livemappingIncidentStatusLabel(incident) {
    var severity = String(incident.status || 'unknown').replace('_', ' ');
    severity = severity.charAt(0).toUpperCase() + severity.slice(1);
    var movementStatus = livemappingIncidentStatus(incident);
    if (movementStatus === 'en_route') return 'En-route';
    if (movementStatus === 'arrived') return 'Arrived';
    return severity;
}

function livemappingTransportStatus(ambulance) {
    if (ambulance.arrived_at || String(ambulance.status || '').toLowerCase() === 'arrived') {
        return 'arrived';
    }
    return String(ambulance.status || 'offline').toLowerCase();
}

function livemappingAddBoundsPoint(bounds, latitude, longitude) {
    if (latitude === null || latitude === undefined || latitude === '' ||
        longitude === null || longitude === undefined || longitude === '') return;
    var point = [Number(latitude), Number(longitude)];
    if (point.every(Number.isFinite) && Math.abs(point[0]) <= 90 && Math.abs(point[1]) <= 180) {
        bounds.push(point);
    }
}

function livemappingCoordinates(latitude, longitude) {
    if (latitude === null || latitude === undefined || longitude === null || longitude === undefined) {
        return 'Coordinates unavailable';
    }

    var parsedLatitude = Number(latitude);
    var parsedLongitude = Number(longitude);
    if (!Number.isFinite(parsedLatitude) || !Number.isFinite(parsedLongitude)) {
        return 'Coordinates unavailable';
    }

    return parsedLatitude.toFixed(6) + ', ' + parsedLongitude.toFixed(6);
}

function livemappingFocusVehicle(map, vehicle, route) {
    var points = [];
    var addPoint = function (latitude, longitude) {
        if (latitude !== null && latitude !== undefined && longitude !== null && longitude !== undefined) {
            var point = [Number(latitude), Number(longitude)];
            if (point.every(Number.isFinite)) points.push(point);
        }
    };

    addPoint(route ? route.latitude : vehicle.latitude, route ? route.longitude : vehicle.longitude);
    addPoint(vehicle.incident_latitude, vehicle.incident_longitude);
    addPoint(vehicle.destination_latitude, vehicle.destination_longitude);

    if (points.length > 1) {
        map.fitBounds(points, { padding: [70, 70], maxZoom: 16 });
    } else if (points.length === 1) {
        map.setView(points[0], 16);
    }
}

function livemappingRouteState(vehicle) {
    if (String(vehicle.status || '').toLowerCase() !== 'en_route') return null;
    if (!vehicle.incident_id || !vehicle.destination_name) return null;

    var currentLat = Number(vehicle.latitude);
    var currentLng = Number(vehicle.longitude);
    if (!vehicle.destination_name || !vehicle.incident_id) return null;
    var endLat = Number(vehicle.destination_latitude);
    var endLng = Number(vehicle.destination_longitude);
    if (![currentLat, currentLng, endLat, endLng].every(Number.isFinite)) return null;

    var etaMinutes = vehicle.eta_minutes !== null && vehicle.eta_minutes !== undefined && vehicle.eta_minutes !== ''
        ? Number(vehicle.eta_minutes)
        : null;
    return {
        latitude: currentLat,
        longitude: currentLng,
        endLatitude: endLat,
        endLongitude: endLng,
        distanceKm: livemappingDistanceKm(currentLat, currentLng, endLat, endLng),
        etaMinutes: etaMinutes,
    };
}

function livemappingBindFleetFilters() {
    document.querySelectorAll('.livemapping-fleet-filter').forEach(function (button) {
        button.addEventListener('click', function () {
            document.querySelectorAll('.livemapping-fleet-filter').forEach(function (item) { item.classList.remove('livemapping-fleet-filter--active'); });
            button.classList.add('livemapping-fleet-filter--active');
            window.livemappingFleetFilter = button.dataset.fleetFilter || 'all';
            window.livemappingIncidentPage = 0;
            if (window.livemappingMap && window.livemappingData) {
                livemappingRender(window.livemappingMap, window.livemappingData);
            }
        });
    });
}

function livemappingBindAlertDrawer(data) {
    var overlay = document.getElementById('livemapping-alert-overlay');
    var form = document.getElementById('livemapping-alert-form');
    var close = document.getElementById('livemapping-alert-close');
    var recipients = document.getElementById('livemapping-alert-recipients');
    var specialization = document.getElementById('livemapping-alert-specialization');
    var statusNode = document.getElementById('livemapping-alert-status');
    var alertIncidentName = document.getElementById('livemapping-alert-incident-name');
    var alertIncidentId = document.getElementById('livemapping-alert-incident-id');
    var alertIncidentStatus = document.getElementById('livemapping-alert-incident-status');
    var alertIncidentLocation = document.getElementById('livemapping-alert-incident-location');
    var alertIncidentPatient = document.getElementById('livemapping-alert-incident-patient');
    var alertIncidentEta = document.getElementById('livemapping-alert-incident-eta');
    var alertIncidentHospital = document.getElementById('livemapping-alert-incident-hospital');
    var people = Array.isArray(data.recipients) ? data.recipients : [];
    var allSpecializations = Array.from(new Set(people.map(function (person) { return person.specialization; }).filter(Boolean))).sort();
    allSpecializations.forEach(function (value) {
        var option = document.createElement('option');
        option.value = value;
        option.textContent = value;
        specialization.appendChild(option);
    });

    function setStatus(message, kind) {
        if (!statusNode) return;
        statusNode.textContent = message;
        statusNode.classList.remove('is-success', 'is-error');
        if (kind) {
            statusNode.classList.add(kind === 'success' ? 'is-success' : 'is-error');
        }
    }

    function renderRecipients(list) {
        if (!recipients) return;
        var filter = specialization.value;
        var rows = (list || []).filter(function (person) {
            return (!filter || person.specialization === filter);
        });

        if (!rows.length) {
            recipients.innerHTML = '<p class="livemapping-alert-empty">No doctors or nurses are currently on duty or logged in.</p>';
            return;
        }

        recipients.innerHTML = rows.map(function (person) {
            var name = person.full_name || 'Unassigned staff';
            var role = person.role || 'staff';
            var spec = person.specialization || 'General care';
            var dutyStart = livemappingFormatDutyTime(person.shift_start);
            var dutyEnd = livemappingFormatDutyTime(person.shift_end);
            var dutyDetails = person.duty_status || 'Logged in';
            if (person.duty_status === 'On duty' || person.duty_status === 'On duty · logged in') {
                dutyDetails += ': ' + dutyStart + ' - ' + dutyEnd + ' (Asia/Manila)';
            }
            return '<label class="livemapping-alert-recipient"><input type="checkbox" name="recipient_ids[]" value="' + person.id + '" checked><span class="livemapping-alert-recipient-copy"><strong>' + livemappingEscapeHtml(name) + ' · ' + livemappingEscapeHtml(role) + '</strong><small>' + livemappingEscapeHtml(spec) + ' · ' + livemappingEscapeHtml(dutyDetails) + '</small></span></label>';
        }).join('');
    }

    function livemappingFormatDutyTime(value) {
        var match = /^(\d{1,2}):(\d{2})/.exec(String(value || ''));
        if (!match) return 'Not scheduled';
        var hours = Number(match[1]);
        var minutes = match[2];
        var period = hours >= 12 ? 'PM' : 'AM';
        return (hours % 12 || 12) + ':' + minutes + ' ' + period;
    }

    function populateIncidentFields(incident) {
        if (!incident) return;
        if (alertIncidentName) alertIncidentName.textContent = incident.incident_type || 'Emergency incident';
        if (alertIncidentId) alertIncidentId.textContent = incident.incident_id ? '#' + incident.incident_id : '—';
        if (alertIncidentStatus) alertIncidentStatus.textContent = incident.status || 'CRITICAL';
        if (alertIncidentLocation) alertIncidentLocation.textContent = incident.location || 'Location unavailable';
        if (alertIncidentPatient) {
            var patient = incident.patient_name || 'Patient name unavailable';
            if (incident.patient_age !== null && incident.patient_age !== undefined) patient += ' · ' + incident.patient_age + ' yrs';
            if (incident.patient_gender) patient += ' · ' + incident.patient_gender;
            alertIncidentPatient.textContent = patient;
        }
        if (alertIncidentEta) alertIncidentEta.textContent = incident.eta !== null && incident.eta !== undefined ? incident.eta + ' min' : 'Not available';
        if (alertIncidentHospital) alertIncidentHospital.textContent = incident.hospital_name || 'Hospital not available';
    }

    function refreshAlertOptions(incidentId) {
        if (!incidentId) {
            setStatus('The selected incident could not be found. Please refresh the page and try again.', 'error');
            return;
        }

        setStatus('Loading eligible staff...', '');
        fetch('livemappingController.php?action=alert_staff_options&incident_id=' + encodeURIComponent(incidentId), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Unable to load staff.');
                }
                return response.json();
            })
            .then(function (result) {
                if (!result.success) {
                    setStatus(result.message || 'The selected incident could not be found. Please refresh the page and try again.', 'error');
                    return;
                }
                populateIncidentFields(result.incident || null);
                var currentPeople = Array.isArray(result.recipients) ? result.recipients : [];
                people = currentPeople;
                specialization.innerHTML = '<option value="">All specializations</option>';
                var options = Array.from(new Set(people.map(function (person) { return person.specialization; }).filter(Boolean))).sort();
                options.forEach(function (value) {
                    var option = document.createElement('option');
                    option.value = value;
                    option.textContent = value;
                    specialization.appendChild(option);
                });
                renderRecipients(people);
                if (!people.length) {
                    setStatus(result.message || 'No doctors or nurses are currently on duty or logged in.', 'error');
                } else {
                    setStatus('Eligible staff loaded. Select on-duty or logged-in recipients and send the alert.', '');
                }
            })
            .catch(function (error) {
                console.warn('[Live Mapping] Alert staff options failed.', error);
                setStatus('Unable to load eligible staff right now. Please try again.', 'error');
            });
    }

    specialization.addEventListener('change', function () {
        renderRecipients(people);
    });

    window.livemappingOpenAlert = function (button) {
        var incidentId = button.dataset.incidentId || '';
        var transportName = button.dataset.transportName || '';
        overlay.hidden = false;
        document.getElementById('livemapping-alert-transport').textContent = 'Transport: ' + (transportName || 'Not assigned');
        form.dataset.incidentId = incidentId;
        form.dataset.transportName = transportName;
        form.reset();
        setStatus('Loading eligible staff...', '');
        refreshAlertOptions(incidentId);
    };

    close.addEventListener('click', function () {
        overlay.hidden = true;
        setStatus('', '');
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        var incidentId = form.dataset.incidentId || '';
        var transportName = form.dataset.transportName || '';
        var selected = Array.from(form.querySelectorAll('input[name="recipient_ids[]"]:checked')).map(function (input) { return input.value; });
        var submitButton = form.querySelector('.livemapping-alert-submit');
        if (!incidentId || !selected.length) {
            setStatus('Select at least one on-duty or logged-in staff member before sending the alert.', 'error');
            return;
        }

        if (submitButton) {
            submitButton.disabled = true;
        }
        setStatus('Sending alert...', '');

        var payload = {
            incident_id: incidentId,
            transport_name: transportName,
            message: form.elements.message.value || '',
            specialization: specialization.value || '',
            recipient_ids: selected
        };

        fetch('livemappingController.php?action=send_alert', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        }).then(function (response) { return response.json(); })
            .then(function (result) {
                if (!result.success) {
                    throw new Error(result.message || 'Unable to send alert.');
                }

                var count = Number(result.count || 0);
                var names = Array.isArray(result.sent_to) ? result.sent_to : [];
                var summary = count + ' active staff member' + (count === 1 ? '' : 's');
                if (names.length) {
                    summary += '\n• ' + names.join('\n• ');
                }
                window.livemappingStaffAlertSent[String(result.incident_id || incidentId)] = true;
                var alertedIncidentId = String(result.incident_id || incidentId);
                document.querySelectorAll('.livemapping-incident-alert, .livemapping-unit-alert').forEach(function (button) {
                    if (button.dataset.incidentId === alertedIncidentId) {
                        button.disabled = true;
                        button.textContent = 'Staff notified';
                    }
                });
                setStatus('Alert Sent Successfully\nIncident #' + (result.incident_id || incidentId) + ' has been sent to:\n' + summary, 'success');
                form.reset();
                setTimeout(function () {
                    overlay.hidden = true;
                }, 2000);
            })
            .catch(function (error) {
                console.warn('[Live Mapping] Send alert failed.', error);
                setStatus(error.message || 'Unable to send alert. Please try again.', 'error');
            })
            .finally(function () {
                if (submitButton) {
                    submitButton.disabled = false;
                }
            });
    });

    renderRecipients(people);
}

function livemappingBindUnitAlerts() {
    document.querySelectorAll('.livemapping-unit-alert, .livemapping-incident-alert').forEach(function (button) {
        if (button.dataset.bound) return;
        button.dataset.bound = '1';
        button.addEventListener('click', function (event) {
            event.stopPropagation();
            if (button.disabled) return;
            if (typeof window.livemappingOpenAlert === 'function') {
                window.livemappingOpenAlert(button);
            }
        });
    });
}

function livemappingShowNotificationToast(message, kind) {
    var toast = document.getElementById('livemapping-notification-toast');
    if (!toast) return;

    toast.textContent = message;
    toast.classList.remove('is-success', 'is-error');
    toast.classList.add(kind === 'error' ? 'is-error' : 'is-success', 'is-visible');
    window.clearTimeout(window.livemappingNotificationToastTimer);
    window.livemappingNotificationToastTimer = window.setTimeout(function () {
        toast.classList.remove('is-visible');
    }, 3200);
}

function livemappingAnnounceIncomingIncidents(map, incidents) {
    var seen = window.livemappingSeenIncidentIds || Object.create(null);
    var newIncidents = [];
    (incidents || []).forEach(function (incident) {
        var id = String(incident.id || '');
        if (!id) return;
        if (!seen[id]) newIncidents.push(incident);
        seen[id] = true;
    });
    window.livemappingSeenIncidentIds = seen;
    if (!newIncidents.length) return 0;

    var incident = newIncidents[0];
    var toast = document.getElementById('livemapping-notification-toast');
    if (!toast) {
        livemappingShowNotificationToast(
            'New hospital incident #' + incident.id + ': ' +
            (incident.incident_type || 'Emergency incident'),
            'success'
        );
        return newIncidents.length;
    }

    toast.textContent = '';
    var message = document.createElement('span');
    message.className = 'livemapping-notification-toast-message';
    var patient = incident.patient_name ? ' · Patient: ' + incident.patient_name : '';
    var eta = incident.eta_minutes !== null && incident.eta_minutes !== undefined
        ? ' · ETA ' + incident.eta_minutes + ' min'
        : '';
    message.textContent = newIncidents.length === 1
        ? 'New incident #' + incident.id + ': ' +
            (incident.incident_type || 'Emergency incident') +
            ' · ' + (incident.location || 'Location unavailable') + patient + eta
        : newIncidents.length + ' new incidents; latest is #' + incident.id +
            ' · ' + (incident.location || 'Location unavailable') + patient + eta;
    toast.appendChild(message);

    var latitude = Number(incident.latitude);
    var longitude = Number(incident.longitude);
    if (
        incident.latitude !== null && incident.latitude !== '' &&
        incident.longitude !== null && incident.longitude !== '' &&
        Number.isFinite(latitude) && Number.isFinite(longitude) &&
        Math.abs(latitude) <= 90 && Math.abs(longitude) <= 180
    ) {
        var focusButton = document.createElement('button');
        focusButton.type = 'button';
        focusButton.className = 'livemapping-notification-toast-focus';
        focusButton.textContent = 'Focus on map';
        focusButton.setAttribute('aria-label', 'Focus incident on map');
        focusButton.addEventListener('click', function () {
            map.setView([latitude, longitude], 16);
            toast.classList.remove('is-visible');
        });
        toast.appendChild(focusButton);
    }

    toast.classList.remove('is-error');
    toast.classList.add('is-success', 'is-visible');
    window.clearTimeout(window.livemappingNotificationToastTimer);
    window.livemappingNotificationToastTimer = window.setTimeout(function () {
        toast.classList.remove('is-visible');
    }, 8000);
    return newIncidents.length;
}

function livemappingDistanceKm(latitude1, longitude1, latitude2, longitude2) {
    var radians = Math.PI / 180;
    var deltaLatitude = (latitude2 - latitude1) * radians;
    var deltaLongitude = (longitude2 - longitude1) * radians;
    var a = Math.sin(deltaLatitude / 2) ** 2 + Math.cos(latitude1 * radians) * Math.cos(latitude2 * radians) * Math.sin(deltaLongitude / 2) ** 2;
    return 6371 * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

function livemappingIncidentRouteState(incident) {
    if (incident.route_start_latitude === null || incident.route_start_longitude === null ||
        incident.route_start_latitude === '' || incident.route_start_longitude === '' ||
        incident.destination_latitude === null || incident.destination_longitude === null ||
        incident.destination_latitude === '' || incident.destination_longitude === '') return null;
    var latitude = Number(incident.route_start_latitude ?? incident.latitude);
    var longitude = Number(incident.route_start_longitude ?? incident.longitude);
    var endLatitude = Number(incident.destination_latitude);
    var endLongitude = Number(incident.destination_longitude);
    if (![latitude, longitude, endLatitude, endLongitude].every(Number.isFinite) ||
        Math.abs(latitude) > 90 || Math.abs(endLatitude) > 90 ||
        Math.abs(longitude) > 180 || Math.abs(endLongitude) > 180) return null;

    var routeCache = window.livemappingRoadRoutes &&
        window.livemappingRoadRoutes[String(incident.id)];
    if (!routeCache || !Array.isArray(routeCache.geometry)) return null;

    return {
        latitude: latitude,
        longitude: longitude,
        endLatitude: endLatitude,
        endLongitude: endLongitude,
        geometry: routeCache.geometry,
        etaMinutes: routeCache.etaMinutes
    };
}

function livemappingAddRoute(markers, route, destinationName, label) {
    if (!Array.isArray(route.geometry) || route.geometry.length < 2) return;
    var points = route.geometry.map(function (point) {
        return [Number(point.latitude), Number(point.longitude)];
    });
    if (points.some(function (point) {
        return !Number.isFinite(point[0]) || !Number.isFinite(point[1]) ||
            Math.abs(point[0]) > 90 || Math.abs(point[1]) > 180;
    })) return;
    var routeLabel = label || 'Destination: ' + destinationName;
    L.polyline(points, {
        color: '#ffffff',
        weight: 7,
        opacity: 0.9,
        lineCap: 'round',
        lineJoin: 'round',
        interactive: false
    }).addTo(markers);
    L.polyline(
        points,
        {
            color: '#171516',
            weight: 4,
            opacity: 0.95,
            lineCap: 'round',
            lineJoin: 'round'
        }
    ).bindPopup(livemappingEscapeHtml(routeLabel)).addTo(markers);
}

function livemappingSetRoadRouteStatus(incidentId, status, message) {
    var statuses = window.livemappingRouteStatuses || (window.livemappingRouteStatuses = Object.create(null));
    if (status) {
        statuses[String(incidentId)] = { status: status, message: message };
    } else {
        delete statuses[String(incidentId)];
    }

    var statusElement = document.getElementById('livemapping-route-status');
    if (!statusElement) return;
    var entries = Object.keys(statuses).map(function (key) {
        return statuses[key];
    });
    var messageToShow = '';
    if (entries.some(function (entry) { return entry.status === 'pending'; })) {
        messageToShow = 'Calculating road routes from live responder locations…';
    } else {
        var failed = entries.find(function (entry) { return entry.status === 'error'; });
        var waiting = entries.find(function (entry) { return entry.status === 'waiting'; });
        messageToShow = failed ? failed.message : waiting ? waiting.message : '';
    }
    statusElement.textContent = messageToShow;
    statusElement.hidden = messageToShow === '';
    statusElement.classList.toggle(
        'is-error',
        entries.some(function (entry) { return entry.status === 'error'; }) &&
            !entries.some(function (entry) { return entry.status === 'pending'; })
    );
}

function livemappingRefreshRoadRoutes(data) {
    window.livemappingRoadRoutes = window.livemappingRoadRoutes || Object.create(null);
    var activeIncidentIds = Object.create(null);
    (data.incidents || []).forEach(function (incident) {
        var key = String(incident.id);
        activeIncidentIds[key] = true;
        var incidentStatus = livemappingIncidentStatus(incident);
        if (incidentStatus !== 'en_route') {
            var sentToHospital = incident.sent_to_hospital === true ||
                Number(incident.sent_to_hospital) === 1;
            livemappingSetRoadRouteStatus(
                key,
                sentToHospital && incidentStatus !== 'arrived' ? 'waiting' : null,
                sentToHospital && incidentStatus !== 'arrived'
                    ? 'Waiting for an assigned responder or vehicle to provide fresh GPS before routing to the hospital.'
                    : ''
            );
            return;
        }
        var hasOriginCoordinates =
            incident.route_start_latitude !== null &&
            incident.route_start_latitude !== undefined &&
            incident.route_start_latitude !== '' &&
            incident.route_start_longitude !== null &&
            incident.route_start_longitude !== undefined &&
            incident.route_start_longitude !== '';
        var hasDestinationCoordinates =
            incident.destination_latitude !== null &&
            incident.destination_latitude !== undefined &&
            incident.destination_latitude !== '' &&
            incident.destination_longitude !== null &&
            incident.destination_longitude !== undefined &&
            incident.destination_longitude !== '';
        if (!hasDestinationCoordinates) {
            livemappingSetRoadRouteStatus(
                key,
                'error',
                'A road route is unavailable because the destination hospital has no valid map coordinates.'
            );
            return;
        }
        if (!hasOriginCoordinates) {
            livemappingSetRoadRouteStatus(
                key,
                'waiting',
                'Waiting for a fresh responder GPS location before drawing the hospital route.'
            );
            return;
        }
        var latitude = Number(incident.route_start_latitude);
        var longitude = Number(incident.route_start_longitude);
        var destinationLatitude = Number(incident.destination_latitude);
        var destinationLongitude = Number(incident.destination_longitude);
        if (![destinationLatitude, destinationLongitude].every(Number.isFinite) ||
            Math.abs(destinationLatitude) > 90 ||
            Math.abs(destinationLongitude) > 180) {
            livemappingSetRoadRouteStatus(
                key,
                'error',
                'A road route is unavailable because the destination hospital has no valid map coordinates.'
            );
            return;
        }
        if (![latitude, longitude].every(Number.isFinite) ||
            Math.abs(latitude) > 90 || Math.abs(longitude) > 180) {
            livemappingSetRoadRouteStatus(
                key,
                'waiting',
                'Waiting for a fresh responder GPS location before drawing the hospital route.'
            );
            return;
        }

        var cached = window.livemappingRoadRoutes[key];
        var destinationChanged = !cached ||
            cached.destinationLatitude !== destinationLatitude ||
            cached.destinationLongitude !== destinationLongitude;
        var moved = !cached || livemappingDistanceKm(
            cached.originLatitude,
            cached.originLongitude,
            latitude,
            longitude
        ) >= 0.1;
        if (cached && cached.pending) return;
        if (cached && !destinationChanged && !moved &&
            (!cached.retryAt || Date.now() < cached.retryAt)) return;

        var routeState = cached || {};
        routeState.pending = true;
        window.livemappingRoadRoutes[key] = routeState;
        livemappingSetRoadRouteStatus(key, 'pending', '');
        var routeUrl = (window.APP_BASE_URL || '') + '/api/livemapping-route.php?incident_id=' +
            encodeURIComponent(key);
        fetch(routeUrl, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' }
        }).then(function (response) {
            return response.json().then(function (result) {
                if (!response.ok) {
                    throw new Error(
                        result.message || 'Road route request returned HTTP ' + response.status + '.'
                    );
                }
                return result;
            });
        }).then(function (result) {
            if (!result.success || !result.route ||
                !Array.isArray(result.route.geometry) ||
                result.route.geometry.length < 2) {
                throw new Error(result.message || 'Road route response was invalid.');
            }
            var geometry = result.route.geometry.map(function (point) {
                return [Number(point.latitude), Number(point.longitude)];
            });
            if (geometry.some(function (point) {
                return !Number.isFinite(point[0]) || !Number.isFinite(point[1]) ||
                    Math.abs(point[0]) > 90 || Math.abs(point[1]) > 180;
            })) {
                throw new Error('The road route contained invalid map coordinates.');
            }
            routeState.originLatitude = Number(result.route.origin.latitude);
            routeState.originLongitude = Number(result.route.origin.longitude);
            routeState.destinationLatitude = Number(result.route.destination.latitude);
            routeState.destinationLongitude = Number(result.route.destination.longitude);
            routeState.geometry = result.route.geometry;
            routeState.etaMinutes = result.route.eta_minutes;
            routeState.pending = false;
            routeState.retryAt = 0;
            livemappingSetRoadRouteStatus(key, null);
            if (window.livemappingMap && window.livemappingData) {
                livemappingRender(window.livemappingMap, window.livemappingData);
                var shouldFitRoute = window.livemappingRouteAutoFitIncidentId
                    ? key === String(window.livemappingRouteAutoFitIncidentId)
                    : !window.livemappingRouteAutoFitted;
                if (shouldFitRoute) {
                    window.livemappingMap.fitBounds(geometry, {
                        padding: [55, 55],
                        maxZoom: 15
                    });
                    window.livemappingRouteAutoFitted = true;
                }
            }
        }).catch(function (error) {
            routeState.pending = false;
            routeState.retryAt = Date.now() + 60000;
            livemappingSetRoadRouteStatus(
                key,
                'error',
                'Road route unavailable for incident #' + key + ': ' +
                    (error.message || 'the route service returned an unknown error.')
            );
            console.warn('[Live Mapping] Road route unavailable for incident #' + key + '.', error);
        });
    });
}

// `popup` is HTML the caller has already escaped; `key` identifies the marker across the 5-second redraws.
function livemappingAddMarker(markers, latitude, longitude, type, icon, popup, key) {
    if (latitude === null || latitude === undefined || latitude === '' ||
        longitude === null || longitude === undefined || longitude === '') return;
    var parsedLatitude = Number(latitude);
    var parsedLongitude = Number(longitude);
    if (!Number.isFinite(parsedLatitude) || !Number.isFinite(parsedLongitude) ||
        Math.abs(parsedLatitude) > 90 || Math.abs(parsedLongitude) > 180) return;
    var marker = L.marker([Number(latitude), Number(longitude)], {
        icon: L.divIcon({ className: '', html: '<span class="livemapping-marker livemapping-marker--' + livemappingEscapeClass(type) + '">' + icon + '</span>', iconSize: [24, 24], iconAnchor: [12, 12] }),
        // Incidents sit on top so a vehicle at the same spot cannot hide their popup.
        zIndexOffset: type === 'incident' ? 1000 : 0,
    }).bindPopup(popup).addTo(markers);

    if (!key) return;
    marker.on('popupopen', function () { window.livemappingOpenPopupKey = key; });
    marker.on('popupclose', function () {
        if (!window.livemappingRedrawing && window.livemappingOpenPopupKey === key) window.livemappingOpenPopupKey = null;
    });
    if (window.livemappingOpenPopupKey === key) marker.openPopup();
}

function livemappingEscapeClass(value) {
    return String(value || 'offline').replace(/[^a-z_]/g, '');
}

function livemappingEscapeHtml(value) {
    var element = document.createElement('div');
    element.textContent = String(value || '');
    return element.innerHTML;
}


// =========================================================
// INCOMING DISPATCHES
// =========================================================


// =========================================================
// FACILITY STATUS BARS
// =========================================================

function incomingdispatchAnimateFacilityBars() {

    var bars =
        document.querySelectorAll(
            '.incomingdispatch-status-bar-fill[data-percent], ' +
            '.incomingdispatch-metric-bar-fill[data-percent]'
        );


    bars.forEach(function (bar) {

        var percent =
            parseFloat(
                bar.getAttribute('data-percent')
            );


        if (isNaN(percent)) {
            percent = 0;
        }


        percent =
            Math.max(
                0,
                Math.min(100, percent)
            );


        // Apply immediately first.
        // This prevents an invisible bar if the animation
        // callback is interrupted.

        bar.style.width =
            percent + '%';


        // Restart the animation after the browser paints.

        window.requestAnimationFrame(function () {

            bar.style.width =
                '0%';


            window.requestAnimationFrame(function () {

                bar.style.width =
                    percent + '%';

            });

        });

    });

}


// =========================================================
// CARD ENTRANCE ANIMATION
// =========================================================

function incomingdispatchAnimateCards() {

    var cards =
        document.querySelectorAll(
            '#incomingdispatch-barangay-grid ' +
            '.incomingdispatch-card'
        );


    cards.forEach(
        function (card, index) {

            card.classList.add(
                'incomingdispatch-fade-in'
            );


            card.style.animationDelay =
                (index * 35) + 'ms';

        }
    );

}


// =========================================================
// ACTIVE CARD PULSE
// =========================================================

function incomingdispatchPulseActiveCards() {

    var cards =
        document.querySelectorAll(
            '#incomingdispatch-barangay-grid ' +
            '.incomingdispatch-card--active'
        );


    cards.forEach(function (card) {

        card.classList.add(
            'incomingdispatch-card--pulse'
        );

    });

}


// =========================================================
// SEARCH FILTER
// =========================================================

function incomingdispatchBindSearch() {

    var input =
        document.getElementById(
            'incomingdispatch-search-input'
        );


    if (!input) {
        return;
    }


    input.addEventListener(
        'input',
        function () {

            var query =
                input.value
                    .trim()
                    .toLowerCase();


            var cards =
                document.querySelectorAll(
                    '#incomingdispatch-barangay-grid ' +
                    '.incomingdispatch-card'
                );


            cards.forEach(
                function (card) {

                    var nameElement =
                        card.querySelector(
                            '.incomingdispatch-card-name'
                        );


                    if (!nameElement) {
                        return;
                    }


                    var name =
                        nameElement.textContent
                            .trim()
                            .toLowerCase();


                    var matches =
                        query === '' ||
                        name.indexOf(query) !== -1;


                    card.style.display =
                        matches ? '' : 'none';

                }
            );

        }
    );

}


// =========================================================
// SEARCH FOCUS
// =========================================================

function incomingdispatchBindSearchFocus() {

    var input =
        document.getElementById(
            'incomingdispatch-search-input'
        );


    if (!input) {
        return;
    }


    input.addEventListener(
        'focus',
        function () {

            this.style.transition =
                'width 0.2s ease';

            this.style.width =
                '340px';

        }
    );


    input.addEventListener(
        'blur',
        function () {

            this.style.width =
                '300px';

        }
    );

}


// =========================================================
// NOTIFICATION BELL
// =========================================================

function incomingdispatchBindIncidentPopups() {
    var path = window.location.pathname.toLowerCase();
    if (
        path.indexOf('/reddiph_admin/app/controllers/') === -1 ||
        /doctor|nurse|login|signup|portal|logout/.test(path)
    ) {
        return;
    }

    var container = document.createElement('aside');
    container.className = 'incomingdispatch-incident-popups';
    container.setAttribute('aria-label', 'New emergency incidents');
    container.setAttribute('aria-live', 'polite');
    container.setAttribute('aria-relevant', 'additions');
    document.body.appendChild(container);

    if (!document.getElementById('incomingdispatch-incident-popup-styles')) {
        var style = document.createElement('style');
        style.id = 'incomingdispatch-incident-popup-styles';
        style.textContent =
            '.incomingdispatch-incident-popups{position:fixed;z-index:12000;right:20px;bottom:20px;display:flex;flex-direction:column;gap:12px;width:min(390px,calc(100vw - 32px));max-height:calc(100vh - 40px);overflow:auto;pointer-events:none}' +
            '.incomingdispatch-incident-popup{position:relative;padding:18px 42px 16px 18px;border:1px solid #e2c5c1;border-left:5px solid #a32025;border-radius:10px;background:#fff;box-shadow:0 12px 36px rgba(27,14,12,.22);color:#211b1a;pointer-events:auto;animation:incomingdispatch-incident-popup-in .22s ease-out}' +
            '.incomingdispatch-incident-popup h2{margin:0 0 7px;color:#8b171d;font-size:14px;font-weight:800}' +
            '.incomingdispatch-incident-popup p{margin:4px 0;color:#4d4542;font-size:12px;line-height:1.45;overflow-wrap:anywhere}' +
            '.incomingdispatch-incident-popup .incomingdispatch-incident-popup-meta{color:#79706d;font-size:11px}' +
            '.incomingdispatch-incident-popup a{display:inline-flex;margin-top:10px;padding:8px 11px;border-radius:6px;background:#841d22;color:#fff;font-size:11px;font-weight:700;text-decoration:none}' +
            '.incomingdispatch-incident-popup a:focus-visible,.incomingdispatch-incident-popup button:focus-visible{outline:2px solid #16161a;outline-offset:2px}' +
            '.incomingdispatch-incident-popup-close{position:absolute;top:9px;right:10px;width:28px;height:28px;border:0;border-radius:50%;background:#f4eeec;color:#403735;font-size:20px;line-height:1;cursor:pointer}' +
            '@keyframes incomingdispatch-incident-popup-in{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}' +
            '@media(max-width:600px){.incomingdispatch-incident-popups{right:12px;bottom:12px;width:calc(100vw - 24px);max-height:55vh}}' +
            '@media(prefers-reduced-motion:reduce){.incomingdispatch-incident-popup{animation:none}}';
        document.head.appendChild(style);
    }

    var storageKey = 'reddiph-admin-known-incident-notifications';
    var knownIds = [];
    var hasBaseline = false;
    try {
        var storedValue = sessionStorage.getItem(storageKey);
        hasBaseline = storedValue !== null;
        var storedIds = JSON.parse(storedValue || '[]');
        if (Array.isArray(storedIds)) knownIds = storedIds.map(String);
    } catch (error) {
        console.warn('[PulseAlert] Incident notification history could not be read.', error);
    }

    function showIncident(incident) {
        var incidentId = String(incident.id || '');
        var role = String(incident.reporterRole || '').toLowerCase();
        var reportedBy = String(incident.reportedBy || '').toLowerCase();
        var source = role === 'barangay_admin' || reportedBy === 'barangay_admin'
            ? 'Barangay administrator'
            : 'Responder';
        var baseUrl = (window.APP_BASE_URL || '').replace(/\/$/, '');
        var mapUrl = baseUrl + '/app/controllers/livemappingController.php?incident_id=' +
            encodeURIComponent(incidentId);
        if (incident.barangaySlug) {
            mapUrl += '&barangay=' + encodeURIComponent(incident.barangaySlug);
        }

        var card = document.createElement('section');
        card.className = 'incomingdispatch-incident-popup';
        card.setAttribute('role', 'status');
        var close = document.createElement('button');
        close.type = 'button';
        close.className = 'incomingdispatch-incident-popup-close';
        close.setAttribute('aria-label', 'Dismiss new incident notification');
        close.textContent = '×';
        close.addEventListener('click', function () {
            card.remove();
        });

        var heading = document.createElement('h2');
        heading.textContent = 'New incident from ' + source;
        var summary = document.createElement('p');
        summary.textContent = (incident.incidentType || 'Emergency incident') +
            ' · ' + (incident.patientName || 'Patient details unavailable');
        var location = document.createElement('p');
        location.textContent = (incident.barangay || 'Barangay unavailable') +
            ' · ' + (incident.location || 'Location unavailable');
        var meta = document.createElement('p');
        meta.className = 'incomingdispatch-incident-popup-meta';
        meta.textContent = 'Incident #' + incidentId + ' · ' +
            String(incident.status || 'new').replace('_', ' ');
        var link = document.createElement('a');
        link.href = mapUrl;
        link.textContent = 'View incident on Live Mapping';

        card.append(close, heading, summary, location, meta, link);
        container.prepend(card);
    }

    function refreshIncidentNotifications() {
        fetch('dispatchController.php?action=incident_notifications', {
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { Accept: 'application/json' }
        }).then(function (response) {
            if (!response.ok) throw new Error('Incident notification refresh returned HTTP ' + response.status + '.');
            return response.json();
        }).then(function (result) {
            if (!result.success || !Array.isArray(result.incidentNotifications)) {
                throw new Error(result.message || 'Incident notification response was invalid.');
            }

            var notifications = result.incidentNotifications;
            var newNotifications = hasBaseline
                ? notifications.filter(function (incident) {
                    return incident.id && knownIds.indexOf(String(incident.id)) === -1;
                })
                : [];
            notifications.forEach(function (incident) {
                if (incident.id) knownIds.push(String(incident.id));
            });
            knownIds = knownIds.filter(function (id, index, ids) {
                return ids.indexOf(id) === index;
            }).slice(-250);
            hasBaseline = true;
            try {
                sessionStorage.setItem(storageKey, JSON.stringify(knownIds));
            } catch (error) {
                console.warn('[PulseAlert] Incident notification history could not be saved.', error);
            }
            newNotifications.reverse().forEach(showIncident);
        }).catch(function (error) {
            console.error('[PulseAlert] New incident notifications could not be refreshed.', error);
        });
    }

    refreshIncidentNotifications();
    window.setInterval(function () {
        if (document.visibilityState === 'visible') refreshIncidentNotifications();
    }, 5000);
}

function incomingdispatchBindBell() {
    var bell = document.getElementById('incomingdispatch-bell-btn');
    var panel = document.getElementById('incomingdispatch-notification-panel');
    var list = document.getElementById('incomingdispatch-notification-list');
    var staffAlertList = document.getElementById('incomingdispatch-staff-alert-list');
    var dot = document.getElementById('incomingdispatch-bell-dot');
    var acknowledgedIds = JSON.parse(sessionStorage.getItem('incomingdispatch-alert-ids') || '[]');
    var hasNewDispatches = false;
    var hasUnreadStaffAlerts = false;

    if (!bell) return;

    function updateBellIndicator() {
        var hasAlerts = hasNewDispatches || hasUnreadStaffAlerts;
        if (!dot && hasAlerts) {
            dot = document.createElement('span');
            dot.id = 'incomingdispatch-bell-dot';
            dot.className = 'incomingdispatch-bell-dot';
            bell.appendChild(dot);
        }
        if (dot) dot.hidden = !hasAlerts;
        bell.classList.toggle('incomingdispatch-bell--alert', hasAlerts);
    }

    function refreshStaffAlertReceipts() {
        if (!staffAlertList) return;
        fetch('livemappingController.php?action=staff_alert_receipts', {
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { Accept: 'application/json' }
        }).then(function (response) {
            return response.json().then(function (result) {
                if (!response.ok || !result.success) {
                    throw new Error(result.message || 'Staff alert status could not be loaded.');
                }
                return result;
            });
        }).then(function (result) {
            var alerts = Array.isArray(result.alerts) ? result.alerts : [];
            hasUnreadStaffAlerts = alerts.some(function (alert) {
                return alert.status !== 'read';
            });
            updateBellIndicator();

            var groups = new Map();
            alerts.forEach(function (alert) {
                var key = String(alert.incidentId || '') + '|' + String(alert.createdAt || '');
                if (!groups.has(key)) {
                    groups.set(key, {
                        incidentId: alert.incidentId,
                        incident: alert.incident,
                        patient: alert.patient,
                        createdAt: alert.createdAt,
                        recipients: []
                    });
                }
                groups.get(key).recipients.push(alert);
            });

            staffAlertList.innerHTML = groups.size ? Array.from(groups.values()).map(function (group) {
                var label = group.incidentId ? 'Incident #' + group.incidentId : 'Staff alert';
                var details = [group.incident, group.patient, group.createdAt].filter(Boolean).join(' · ');
                var recipients = group.recipients.map(function (alert) {
                    var isRead = alert.status === 'read';
                    var status = isRead ? 'Read' : 'Unread';
                    return '<small class="incomingdispatch-staff-alert-recipient ' +
                        (isRead ? 'is-read' : 'is-unread') + '">' +
                        escapeIncomingDispatchText(alert.recipient) + ' · ' +
                        escapeIncomingDispatchText(alert.role) + ' · ' + status + '</small>';
                }).join('');
                return '<div class="incomingdispatch-staff-alert-item"><strong>' +
                    escapeIncomingDispatchText(label) + '</strong><small>' +
                    escapeIncomingDispatchText(details) + '</small>' + recipients + '</div>';
            }).join('') : '<span class="incomingdispatch-notification-empty">No staff alerts sent</span>';
        }).catch(function (error) {
            staffAlertList.innerHTML = '<span class="incomingdispatch-notification-empty">Staff alert status unavailable</span>';
            console.error('[PulseAlert] Staff alert receipts could not be loaded.', error);
        });
    }

    bell.addEventListener('click', function () {
        if (panel) panel.hidden = !panel.hidden;
        acknowledgedIds = window.incomingdispatchLastIds || acknowledgedIds;
        sessionStorage.setItem('incomingdispatch-alert-ids', JSON.stringify(acknowledgedIds));
        hasNewDispatches = false;
        updateBellIndicator();
    });

    function refresh() {
        fetch('dispatchController.php?action=status', { credentials: 'same-origin', cache: 'no-store' })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (!data.success) return;
                var ids = data.dispatches.map(function (item) { return String(item.id); });
                hasNewDispatches = ids.some(function (id) { return acknowledgedIds.indexOf(id) === -1; });
                window.incomingdispatchLastIds = ids;
                hasNewDispatches = hasNewDispatches && data.count > 0;
                updateBellIndicator();
                refreshStaffAlertReceipts();
                incomingdispatchRefreshAcknowledgedCards(data.dispatches);
                incomingdispatchRefreshStaff(data.staffOnDuty || []);
                incomingdispatchRefreshFacilityStatus(data.facilityStatus || {});
                if (list) {
                    list.innerHTML = data.dispatches.length ? data.dispatches.map(function (item) {
                        var patientInfo = escapeIncomingDispatchText(item.patientName || 'Patient name not provided');
                        var mappingBaseUrl = (window.APP_BASE_URL || '').replace(/\/$/, '');
                        var mappingUrl = mappingBaseUrl + '/app/controllers/livemappingController.php?barangay=' +
                            encodeURIComponent(item.barangaySlug || '');
                        if (item.patientGender || item.patientAge !== null && item.patientAge !== undefined) {
                            patientInfo += ' · ' + escapeIncomingDispatchText(item.patientGender || '');
                            if (item.patientAge !== null && item.patientAge !== undefined) {
                                patientInfo += ', ' + escapeIncomingDispatchText(String(item.patientAge));
                            }
                        }
                        return '<a href="' + escapeIncomingDispatchText(mappingUrl) + '" data-dispatch-id="' + item.id + '" data-barangay-slug="' + escapeIncomingDispatchText(item.barangaySlug) + '">' +
                            escapeIncomingDispatchText(item.dispatchPurpose) + '<br><small>' + patientInfo + '</small><br><small>' + escapeIncomingDispatchText(item.barangay) + ' · ' + escapeIncomingDispatchText(item.vehicleName) + ' · ' + escapeIncomingDispatchText(item.driverName) + '</small></a>';
                    }).join('') : '<span class="incomingdispatch-notification-empty">No active emergencies</span>';
                }
                incomingdispatchRefreshTable(data.dispatches);
            }).catch(function (error) {
                console.error('[PulseAlert] Dashboard refresh failed.', error);
            });
    }

    refresh();
    window.setInterval(function () {
        if (document.visibilityState === 'visible') refresh();
    }, 5000);
    if (panel) panel.addEventListener('click', function (event) {
        var link = event.target.closest('a');
        if (link) {
            incomingdispatchAcknowledgeSlug(link.getAttribute('data-barangay-slug'), [link.getAttribute('data-dispatch-id')]);
            panel.hidden = true;
        }
    });

}

function incomingdispatchRefreshTable(dispatches) {
    var table = document.getElementById('incomingdispatch-active-list');
    var count = document.querySelector('.incomingdispatch-dispatch-table-count');
    if (!table) return;
    if (count) count.textContent = dispatches.length + (dispatches.length === 1 ? ' active' : ' active');
    table.innerHTML = dispatches.length ? dispatches.map(function (item) {
        var patientMeta = '';
        if (item.patientAge !== null && item.patientAge !== undefined) patientMeta += escapeIncomingDispatchText(String(item.patientAge)) + ' years';
        if (item.patientGender) patientMeta += (patientMeta ? ' · ' : '') + escapeIncomingDispatchText(String(item.patientGender).charAt(0).toUpperCase() + String(item.patientGender).slice(1));
        return '<tr data-dispatch-id="' + item.id + '">' +
            '<td><strong>' + escapeIncomingDispatchText(item.patientName) + '</strong>' + (patientMeta ? '<small>' + patientMeta + '</small>' : '') + '</td>' +
            '<td>' + escapeIncomingDispatchText(item.barangay) + '</td>' +
            '<td>' + escapeIncomingDispatchText(item.dispatchPurpose) + '</td>' +
            '<td>' + escapeIncomingDispatchText(item.vehicleName) + '<small>' + escapeIncomingDispatchText(item.transportType) + '</small></td>' +
            '<td>' + escapeIncomingDispatchText(item.driverName) + '</td>' +
            '<td><span class="incomingdispatch-status-pill">' + escapeIncomingDispatchText(item.status) + '</span></td>' +
            '</tr>';
    }).join('') : '<tr><td colspan="6" class="incomingdispatch-dispatch-table-empty">No active dispatches</td></tr>';
}

function incomingdispatchReadAcknowledgements() {
    try {
        return JSON.parse(localStorage.getItem('incomingdispatch-acknowledged-dispatches') || '{}');
    } catch (error) {
        return {};
    }
}

function incomingdispatchAcknowledgeSlug(slug, dispatchIds) {
    if (!slug) return;
    var acknowledgements = incomingdispatchReadAcknowledgements();
    var existing = Array.isArray(acknowledgements[slug]) ? acknowledgements[slug] : [];
    acknowledgements[slug] = existing.concat(dispatchIds || []).map(String).filter(function (id, index, ids) {
        return id && ids.indexOf(id) === index;
    });
    localStorage.setItem('incomingdispatch-acknowledged-dispatches', JSON.stringify(acknowledgements));
}

function incomingdispatchApplyAcknowledgement(card) {
    var slug = card.getAttribute('data-barangay-slug');
    var dispatchIds;
    try {
        dispatchIds = JSON.parse(card.getAttribute('data-dispatch-ids') || '[]').map(String);
    } catch (error) {
        dispatchIds = [];
    }
    var acknowledgements = incomingdispatchReadAcknowledgements();
    var viewedIds = acknowledgements[slug] || [];
    var isViewed = dispatchIds.length > 0 && dispatchIds.every(function (id) {
        return viewedIds.indexOf(String(id)) !== -1;
    });
    var badge = card.querySelector('.incomingdispatch-card-badge');
    card.classList.toggle('incomingdispatch-card--acknowledged', isViewed);
    card.classList.toggle('incomingdispatch-card--pulse', !isViewed && card.classList.contains('incomingdispatch-card--active'));
    if (isViewed && badge) {
        badge.remove();
    }
}

function incomingdispatchRefreshAcknowledgedCards(dispatches) {
    var dispatchesByBarangay = {};
    (dispatches || []).forEach(function (dispatch) {
        if (dispatch.barangayId === null || dispatch.barangayId === undefined) return;
        if (!dispatchesByBarangay[dispatch.barangayId]) dispatchesByBarangay[dispatch.barangayId] = [];
        dispatchesByBarangay[dispatch.barangayId].push(String(dispatch.id));
    });
    document.querySelectorAll('#incomingdispatch-barangay-grid .incomingdispatch-card[data-barangay-id]').forEach(function (card) {
        var currentIds = dispatchesByBarangay[card.getAttribute('data-barangay-id')] || [];
        card.setAttribute('data-dispatch-ids', JSON.stringify(currentIds));
        card.setAttribute('data-count', String(currentIds.length));
        card.classList.toggle('incomingdispatch-card--active', currentIds.length > 0);
        var statusLabel = card.querySelector('.incomingdispatch-card-status');
        if (statusLabel) {
            statusLabel.textContent = currentIds.length > 0
                ? currentIds.length + ' active ' + (currentIds.length === 1 ? 'dispatch' : 'dispatches')
                : 'No active dispatch';
            statusLabel.classList.toggle('incomingdispatch-card-status--active', currentIds.length > 0);
        }
        var badge = card.querySelector('.incomingdispatch-card-badge');
        if (currentIds.length > 0 && !badge) {
            badge = document.createElement('span');
            badge.className = 'incomingdispatch-card-badge';
            badge.setAttribute('aria-label', 'New active dispatch');
            card.appendChild(badge);
        } else if (currentIds.length === 0 && badge) {
            badge.remove();
        }
        incomingdispatchApplyAcknowledgement(card);
    });
}

function escapeIncomingDispatchText(value) {
    var element = document.createElement('span');
    element.textContent = value == null ? '' : String(value);
    return element.innerHTML;
}

function incomingdispatchBindDownload() {
    var button = document.getElementById('incomingdispatch-download-btn');
    var overlay = document.getElementById('incomingdispatch-download-overlay');
    var cancel = document.getElementById('incomingdispatch-download-cancel');

    if (!button || !overlay) {
        return;
    }

    function close() {
        overlay.setAttribute('aria-hidden', 'true');
        overlay.classList.remove('incomingdispatch-download-overlay--open');
    }

    button.addEventListener('click', function () {
        overlay.setAttribute('aria-hidden', 'false');
        overlay.classList.add('incomingdispatch-download-overlay--open');
    });

    if (cancel) {
        cancel.addEventListener('click', close);
    }

    overlay.addEventListener('click', function (event) {
        if (event.target === overlay) {
            close();
        }
    });
}

function incomingdispatchBindStaffPagination() {
    var rows = Array.prototype.slice.call(document.querySelectorAll('.incomingdispatch-staff-row'));
    var previous = document.getElementById('incomingdispatch-staff-prev');
    var next = document.getElementById('incomingdispatch-staff-next');
    var pageLabel = document.getElementById('incomingdispatch-staff-page');
    var pageSize = 4;

    if (!previous || !next || !pageLabel || previous.dataset.staffPagerBound === 'true') {
        return;
    }

    previous.dataset.staffPage = '0';
    previous.dataset.staffPagerBound = 'true';
    next.dataset.staffPagerBound = 'true';

    function render() {
        var rows = Array.prototype.slice.call(document.querySelectorAll('.incomingdispatch-staff-row'));
        var page = Number(previous.dataset.staffPage || 0);
        var pageCount = Math.max(1, Math.ceil(rows.length / pageSize));
        page = Math.min(page, pageCount - 1);
        previous.dataset.staffPage = String(page);

        rows.forEach(function (row, index) {
            row.hidden = index < page * pageSize || index >= (page + 1) * pageSize;
        });
        pageLabel.textContent = String(page + 1) + ' / ' + String(pageCount);
        previous.disabled = page === 0;
        next.disabled = page >= pageCount - 1;
        previous.setAttribute('aria-disabled', String(previous.disabled));
        next.setAttribute('aria-disabled', String(next.disabled));
    }

    previous.addEventListener('click', function () {
        var page = Number(previous.dataset.staffPage || 0);
        if (page > 0) {
            previous.dataset.staffPage = String(page - 1);
            render();
        }
    });
    next.addEventListener('click', function () {
        var page = Number(previous.dataset.staffPage || 0);
        var rows = document.querySelectorAll('.incomingdispatch-staff-row');
        if ((page + 1) * pageSize < rows.length) {
            previous.dataset.staffPage = String(page + 1);
            render();
        }
    });
    render();
}

function incomingdispatchRefreshStaff(staff) {
    var list = document.getElementById('incomingdispatch-staff-list');
    if (!list) return;

    list.innerHTML = (staff || []).map(function (member, index) {
        var avatar = member.image
            ? '<img src="' + escapeIncomingDispatchText(member.image) + '" alt="">'
            : escapeIncomingDispatchText(member.initials || '');
        var dutyHours = member.dutyHours
            ? '<p class="incomingdispatch-staff-dept">' + escapeIncomingDispatchText(member.dutyHours) + '</p>'
            : '';
        var availabilityClass = member.isAvailable
            ? 'incomingdispatch-staff-dot--on'
            : 'incomingdispatch-staff-dot--off';

        return '<div class="incomingdispatch-staff-row" id="incomingdispatch-staff-' + index + '">' +
            '<span class="incomingdispatch-staff-avatar">' + avatar + '</span>' +
            '<div class="incomingdispatch-staff-text">' +
            '<p class="incomingdispatch-staff-name">' + escapeIncomingDispatchText(member.name) + '</p>' +
            '<p class="incomingdispatch-staff-dept">' + escapeIncomingDispatchText(member.department) + '</p>' +
            dutyHours +
            '</div><span class="incomingdispatch-staff-dot ' + availabilityClass + '"></span></div>';
    }).join('');

    incomingdispatchBindStaffPagination();
}

function incomingdispatchRefreshFacilityStatus(metrics) {
    Object.keys(metrics || {}).forEach(function (key) {
        var row = document.getElementById('incomingdispatch-metric-' + key);
        if (!row) return;

        var value = row.querySelector('.incomingdispatch-metric-value');
        var fill = row.querySelector('.incomingdispatch-metric-bar-fill');
        var metric = metrics[key];
        if (value) value.textContent = metric.display;
        if (fill && metric.percent !== undefined) {
            fill.dataset.percent = String(metric.percent);
            fill.style.width = metric.percent + '%';
            fill.classList.remove(
                'incomingdispatch-metric-bar-fill--high',
                'incomingdispatch-metric-bar-fill--medium',
                'incomingdispatch-metric-bar-fill--low'
            );
            fill.classList.add(metric.capacityClass);
        }
    });
}


// =========================================================
// BELL SHAKE
// =========================================================

function incomingdispatchBindBellShake() {

    var bell =
        document.getElementById(
            'incomingdispatch-bell-btn'
        );

    var dot =
        document.getElementById(
            'incomingdispatch-bell-dot'
        );


    if (!bell) {
        return;
    }


    bell.style.transition =
        'transform 0.15s ease';


    window.setTimeout(
        function () {

            var ticks = 0;


            var shake =
                window.setInterval(
                    function () {

                        bell.style.transform =
                            ticks % 2 === 0
                                ? 'rotate(-10deg)'
                                : 'rotate(10deg)';


                        ticks++;


                        if (ticks > 3) {

                            window.clearInterval(
                                shake
                            );


                            bell.style.transform =
                                'rotate(0deg)';

                        }

                    },
                    90
                );

        },
        700
    );

}


// =========================================================
// QUICK ACTIONS
// =========================================================

function incomingdispatchBindQuickActions() {

    var broadcastButton =
        document.getElementById(
            'incomingdispatch-btn-broadcast'
        );

    var exportButton =
        document.getElementById(
            'incomingdispatch-btn-export'
        );


    if (broadcastButton) {

        broadcastButton.addEventListener(
            'click',
            function () {

                broadcastButton.classList.add(
                    'incomingdispatch-card--pulse'
                );


                window.setTimeout(
                    function () {

                        broadcastButton.classList.remove(
                            'incomingdispatch-card--pulse'
                        );

                    },
                    1400
                );

            }
        );

    }


    if (exportButton) {

        exportButton.addEventListener(
            'click',
            function () {

                exportButton.style.transform =
                    'scale(0.97)';


                window.setTimeout(
                    function () {

                        exportButton.style.transform =
                            '';

                    },
                    150
                );

            }
        );

    }

}


// =========================================================
// BARANGAY CARD CLICK
// =========================================================

function incomingdispatchBindCardClicks() {

    var grid = document.getElementById('incomingdispatch-barangay-grid');
    if (grid && !grid.dataset.mapBackgroundBound) {
        grid.dataset.mapBackgroundBound = 'true';
        grid.addEventListener('click', function (event) {
            if (event.target !== grid) return;
            var allMapCard = document.getElementById('incomingdispatch-card-allbrgys');
            var allMapUrl = allMapCard && allMapCard.getAttribute('data-map-url');
            if (allMapUrl) window.location.href = allMapUrl;
        });
    }

    var cards =
        document.querySelectorAll(
            '#incomingdispatch-barangay-grid ' +
            '.incomingdispatch-card'
        );


    cards.forEach(function (card) {

        card.addEventListener(
            'click',
            function () {

                var dispatchIds = [];
                try {
                    dispatchIds = JSON.parse(card.getAttribute('data-dispatch-ids') || '[]');
                } catch (error) {
                    dispatchIds = [];
                }
                incomingdispatchAcknowledgeSlug(card.getAttribute('data-barangay-slug'), dispatchIds);
                incomingdispatchApplyAcknowledgement(card);

                card.style.transform =
                    'scale(0.97)';


                window.setTimeout(
                    function () {

                        card.style.transform =
                            '';

                    },
                    120
                );


                var nameElement =
                    card.querySelector(
                        '.incomingdispatch-card-name'
                    );


                var name =
                    nameElement
                        ? nameElement.textContent.trim()
                        : '';


                var count =
                    card.getAttribute(
                        'data-count'
                    );


                console.log(
                    '[incomingdispatch]',
                    'Barangay:',
                    name,
                    '| Active dispatches:',
                    count
                );

                var mapUrl = card.getAttribute('data-map-url');
                if (mapUrl) window.location.href = mapUrl;

            }
        );

    });

}


// =========================================================
// EMERGENCY ALERTS
// =========================================================

var emergencyAlertToastTimer = null;
var emergencyAlertSearchTimer = null;
var emergencyAlertCurrentPage = 1;
var emergencyAlertCurrentRows = [];


// =========================================================
// EMERGENCY ALERT INITIALIZATION
// =========================================================

function emergencyAlertInit() {

    emergencyAlertBindSearch();
    emergencyAlertBindFilters();
    emergencyAlertBindSummaryCards();
    emergencyAlertBindBell();
    pulseAlertShakeBell(document.getElementById('emergency_alert_bell_btn'));
    emergencyAlertBindModal();
    emergencyAlertBindNavigation();
    emergencyAlertBindActions();
    emergencyAlertBindPagination();
    emergencyAlertLoadData();
    window.setInterval(function () {
        if (document.visibilityState === 'visible') {
            emergencyAlertLoadData(true, true);
        }
    }, 5000);

}


// =========================================================
// CONTROLLER URL
// =========================================================

function emergencyAlertControllerUrl() {
    return new URL(
        './emergency_alertController.php',
        window.location.href
    ).toString();
}


// =========================================================
// CONTROLLER REQUEST URL
// =========================================================

function emergencyAlertRequestUrl(
    action,
    params
) {

    var url =
        new URL(
            emergencyAlertControllerUrl(),
            window.location.href
        );


    url.searchParams.set(
        'action',
        action
    );


    if (params) {

        Object.keys(params).forEach(
            function (key) {

                url.searchParams.set(
                    key,
                    params[key]
                );

            }
        );

    }


    return url;

}


// =========================================================
// LOAD EMERGENCY ALERT DATA
// =========================================================

function emergencyAlertLoadData(forceRefresh, quiet) {

    var initialDataElement =
        document.getElementById(
            'emergency_alert_initial_data'
        );


    if (!forceRefresh && initialDataElement) {

        try {

            var initialData =
                JSON.parse(
                    initialDataElement.textContent || '{}'
                );


            if (
                initialData &&
                Array.isArray(initialData.alerts)
            ) {

                window.emergency_alert_data =
                    initialData;


                emergencyAlertUpdateHeader(
                    initialData
                );


                emergencyAlertUpdateSummary(
                    initialData.summary || {}
                );


                emergencyAlertRender(
                    emergencyAlertCurrentStatus()
                );


                return;

            }

        } catch (error) {

            console.warn(
                '[Emergency Alerts] Could not parse embedded data.',
                error
            );

        }

    }


    var url =
        emergencyAlertRequestUrl(
            'data',
            {
                status: 'all'
            }
        );


    fetch(
        url.toString(),
        {
            headers: {
                'X-Requested-With':
                    'XMLHttpRequest'
            },
            cache: 'no-store'
        }
    )
        .then(function (response) {

            if (!response.ok) {

                throw new Error(
                    'Unable to load emergency alerts.'
                );

            }

            return response.json();

        })
        .then(function (data) {

            if (!data.success) {

                throw new Error(
                    data.message ||
                    'Unable to load emergency alerts.'
                );

            }


            window.emergency_alert_data =
                data;


            emergencyAlertUpdateHeader(
                data
            );


            emergencyAlertUpdateSummary(
                data.summary || {}
            );


            emergencyAlertRender(
                emergencyAlertCurrentStatus()
            );

        })
        .catch(function (error) {

            console.error(
                '[Emergency Alerts]',
                error
            );


            if (!quiet) {
                emergencyAlertShowToast('Showing current emergency alert data.');
                emergencyAlertSetActive(emergencyAlertCurrentStatus());
                emergencyAlertStaggerRows();
            }

        });

}


// =========================================================
// UPDATE EMERGENCY ALERT HEADER
// =========================================================

function emergencyAlertUpdateHeader(data) {

    var subtitle =
        document.getElementById(
            'emergency_alert_subtitle'
        );

    var name =
        document.getElementById(
            'emergency_alert_user_name'
        );

    var role =
        document.getElementById(
            'emergency_alert_user_role'
        );


    if (
        subtitle &&
        data.pageTitle
    ) {

        subtitle.textContent =
            data.pageTitle;

    }


    if (
        name &&
        data.coordinatorName
    ) {

        name.textContent =
            data.coordinatorName;

    }


    if (
        role &&
        data.coordinatorRole
    ) {

        role.textContent =
            data.coordinatorRole;

    }

    var avatar = document.getElementById('emergency_alert_avatar');
    if (avatar && data.coordinatorImage) {
        var avatarImage = document.createElement('img');
        avatarImage.src = data.coordinatorImage;
        avatarImage.alt = '';
        avatar.replaceChildren(avatarImage);
    } else if (avatar && data.coordinatorInitials) {
        avatar.textContent = data.coordinatorInitials;
    }

    emergencyAlertRenderNotifications(data.alerts || []);

}


// =========================================================
// UPDATE EMERGENCY ALERT SUMMARY
// =========================================================

function emergencyAlertUpdateSummary(summary) {

    var values = {
        all:
            Number(summary.all) || 0,

        critical:
            Number(summary.critical) || 0,

        urgent:
            Number(summary.urgent) || 0,

        stable:
            Number(summary.stable) || 0
    };


    Object.keys(values).forEach(
        function (key) {

            var element =
                document.getElementById(
                    'emergency_alert_summary_' +
                    key +
                    '_value'
                );


            if (element) {

                element.textContent =
                    values[key];

            }

        }
    );


    var dot =
        document.getElementById(
            'emergency_alert_bell_dot'
        );


    if (dot) {
        dot.hidden = values.critical + values.urgent <= 0;
    }


    emergencyAlertAnimateSummaryCards();


    if (values.critical > 0) {

        emergencyAlertPulseBell();

    }

}


// =========================================================
// EMERGENCY ALERT SEARCH
// =========================================================

function emergencyAlertBindSearch() {

    var input =
        document.getElementById(
            'emergency_alert_search_input'
        );

    var wrap =
        document.getElementById(
            'emergency_alert_search_wrap'
        );


    if (!input) {
        return;
    }


    input.addEventListener(
        'input',
        function () {

            window.clearTimeout(
                emergencyAlertSearchTimer
            );


            emergencyAlertSearchTimer =
                window.setTimeout(
                    function () {

                        emergencyAlertRender(
                            emergencyAlertCurrentStatus()
                        );

                    },
                    60
                );

        }
    );


    input.addEventListener(
        'focus',
        function () {

            if (wrap) {

                wrap.classList.add(
                    'emergency_alert_search_focused',
                    'emergency_alert_search--focused'
                );

            }

        }
    );


    input.addEventListener(
        'blur',
        function () {

            if (wrap) {

                wrap.classList.remove(
                    'emergency_alert_search_focused',
                    'emergency_alert_search--focused'
                );

            }

        }
    );

}


// =========================================================
// EMERGENCY ALERT FILTERS
// =========================================================

function emergencyAlertBindFilters() {

    document
        .querySelectorAll(
            '.emergency_alert_filter'
        )
        .forEach(
            function (button) {

                button.addEventListener(
                    'click',
                    function () {

                        emergencyAlertRender(
                            this.getAttribute(
                                'data-status'
                            ) || 'all'
                        );

                    }
                );

            }
        );

}


// =========================================================
// EMERGENCY SUMMARY CARDS
// =========================================================

function emergencyAlertBindSummaryCards() {

    var cards =
        document.querySelectorAll(
            '.emergency_alert_summary_card'
        );


    if (!cards.length) {
        return;
    }


    cards.forEach(
        function (card) {

            // Explicitly make sure the card behaves
            // like an interactive control.

            card.style.cursor =
                'pointer';


            card.addEventListener(
                'click',
                function (event) {

                    event.preventDefault();


                    var status =
                        this.getAttribute(
                            'data-status'
                        ) || 'all';


                    console.log(
                        '[Emergency Alerts] Summary selected:',
                        status
                    );


                    emergencyAlertRender(
                        status
                    );

                }
            );

        }
    );

}


// =========================================================
// CURRENT EMERGENCY ALERT STATUS
// =========================================================

function emergencyAlertCurrentStatus() {

    var active =
        document.querySelector(
            '.emergency_alert_filter_active'
        );


    return active
        ? (
            active.getAttribute(
                'data-status'
            ) || 'all'
        )
        : 'all';

}


// =========================================================
// SET EMERGENCY ACTIVE STATE
// =========================================================

function emergencyAlertSetActive(status) {

    status =
        String(status || 'all')
            .toLowerCase()
            .trim();


    // -----------------------------------------------------
    // FILTER BUTTONS
    // -----------------------------------------------------

    document
        .querySelectorAll(
            '.emergency_alert_filter'
        )
        .forEach(
            function (button) {

                var buttonStatus =
                    button.getAttribute(
                        'data-status'
                    );


                var active =
                    buttonStatus === status;


                button.classList.toggle(
                    'emergency_alert_filter_active',
                    active
                );


                button.classList.toggle(
                    'emergency_alert_filter_active_critical',
                    active &&
                    status === 'critical'
                );


                button.classList.toggle(
                    'emergency_alert_filter_active_urgent',
                    active &&
                    status === 'urgent'
                );


                button.classList.toggle(
                    'emergency_alert_filter_active_stable',
                    active &&
                    status === 'stable'
                );

            }
        );


    // -----------------------------------------------------
    // SUMMARY CARDS
    // -----------------------------------------------------

    document
        .querySelectorAll(
            '.emergency_alert_summary_card'
        )
        .forEach(
            function (card) {

                var cardStatus =
                    card.getAttribute(
                        'data-status'
                    );


                var active =
                    status !== 'all' &&
                    cardStatus === status;


                card.classList.remove(
                    'emergency_alert_summary_card_active',
                    'emergency_alert_summary_card_critical_active',
                    'emergency_alert_summary_card_urgent_active',
                    'emergency_alert_summary_card_stable_active'
                );


                if (active) {

                    card.classList.add(
                        'emergency_alert_summary_card_active'
                    );


                    if (
                        status === 'critical'
                    ) {

                        card.classList.add(
                            'emergency_alert_summary_card_critical_active'
                        );

                    }


                    if (status === 'urgent') {

                        card.classList.add(
                            'emergency_alert_summary_card_urgent_active'
                        );

                    }


                    if (
                        status === 'stable'
                    ) {

                        card.classList.add(
                            'emergency_alert_summary_card_stable_active'
                        );

                    }

                }

            }
        );

}


// =========================================================
// RENDER EMERGENCY ALERTS
// =========================================================

function emergencyAlertRender(status) {

    status =
        String(status || 'all')
            .toLowerCase()
            .trim();


    if (
        [
            'all',
            'critical',
            'urgent',
            'stable'
        ].indexOf(status) === -1
    ) {

        status = 'all';

    }


    // IMPORTANT:
    // Only call this once.
    emergencyAlertSetActive(status);


    var notice =
        document.getElementById(
            'emergency_alert_critical_notice'
        );


    if (notice) {

        notice.hidden =
            status !== 'critical';

    }


    var data =
        window.emergency_alert_data;


    if (
        !data ||
        !Array.isArray(data.alerts)
    ) {

        return;

    }


    var input =
        document.getElementById(
            'emergency_alert_search_input'
        );


    var query =
        input
            ? input.value.trim().toLowerCase()
            : '';


    var alerts =
        data.alerts.filter(
            function (alert) {

                var alertStatus =
                    String(
                        alert.status || ''
                    )
                        .toLowerCase()
                        .trim();


                var statusMatch =
                    status === 'all' ||
                    alertStatus === status;


                if (!statusMatch) {
                    return false;
                }


                if (!query) {
                    return true;
                }


                var haystack = [
                    alert.status,
                    alert.incidentType,
                    alert.patientInfo,
                    alert.patientName,
                    alert.location,
                    alert.barangay,
                    alert.responder,
                    alert.transport,
                    alert.driver,
                    alert.hospital,
                    alert.notes,
                    alert.eta,
                    alert.timeAgo
                ]
                    .join(' ')
                    .toLowerCase();


                return (
                    haystack.indexOf(query) !== -1
                );

            }
        );


    emergencyAlertRenderTable(
        status,
        alerts
    );

}


// =========================================================
// RENDER EMERGENCY TABLE
// =========================================================

function emergencyAlertRenderTable(
    status,
    alerts
) {

    var table =
        document.getElementById(
            'emergency_alert_table'
        );

    var head =
        document.getElementById(
            'emergency_alert_table_head'
        );

    var body =
        document.getElementById(
            'emergency_alert_table_body'
        );

    var empty =
        document.getElementById(
            'emergency_alert_empty'
        );


    if (
        !table ||
        !head ||
        !body ||
        !empty
    ) {

        return;

    }


    table.className =
        'emergency_alert_table ' +
        'emergency_alert_table_' +
        status;


    table.setAttribute(
        'data-view',
        status
    );


    head.innerHTML =
        '<tr id="emergency_alert_table_head_row">' +
        '<th>STATUS</th><th>INCIDENT / PATIENT</th><th>LOCATION</th>' +
        '<th>RESPONDER / TRANSPORT</th><th>REPORTED</th><th>ACTIONS</th></tr>';


    var html = '';
    var pageSize = 10;
    var pageCount = Math.max(1, Math.ceil(alerts.length / pageSize));
    emergencyAlertCurrentPage = Math.min(emergencyAlertCurrentPage, pageCount);
    var firstIndex = (emergencyAlertCurrentPage - 1) * pageSize;
    var pageRows = alerts.slice(firstIndex, firstIndex + pageSize);


    for (
        var i = 0;
        i < pageRows.length;
        i++
    ) {

        var alert =
            pageRows[i];


        var incident =
            emergencyAlertEscapeHtml(
                alert.incidentType ||
                'Unknown Incident'
            );


        var patient = alert.patientName
            ? '<div class="emergency_alert_patient_info">' +
            '<strong class="emergency_alert_patient_name">' + emergencyAlertEscapeHtml(alert.patientName) + '</strong>' +
            '</div>'
            : '';

        var location = '<span>' + emergencyAlertEscapeHtml(alert.location || 'Unknown Location') + '</span>' +
            (alert.barangay ? '<small>' + emergencyAlertEscapeHtml(alert.barangay) + '</small>' : '');

        var time =
            emergencyAlertEscapeHtml(
                alert.timeAgo ||
                'Just now'
            );

        var reportedParts = String(alert.reportedAt || '').trim().split(/[ T]/, 2);
        var reportedHtml = reportedParts[0]
            ? '<div class="emergency_alert_reported"><span class="emergency_alert_reported_date">' + emergencyAlertEscapeHtml(reportedParts[0]) + '</span>' +
            (reportedParts[1] ? '<span class="emergency_alert_reported_clock">' + emergencyAlertEscapeHtml(reportedParts[1]) + '</span>' : '') +
            '</div>'
            : '<div class="emergency_alert_reported"><span class="emergency_alert_reported_clock">' + time + '</span></div>';


        var alertStatus =
            String(
                alert.status || ''
            )
                .toLowerCase();


        html +=
            '<tr class="emergency_alert_row" ' +
            'id="emergency_alert_row_' +
            Number(alert.id) +
            '" data-alert-id="' +
            Number(alert.id) +
            '" data-status="' +
            emergencyAlertEscapeHtml(
                alertStatus
            ) +
            '">';


        var statusSelect = '<select class="emergency_alert_status_select" data-action="status" data-alert-id="' + Number(alert.id) + '" aria-label="Change incident status">' +
            ['critical', 'urgent', 'stable'].map(function (choice) {
                return '<option value="' + choice + '"' + (choice === alertStatus ? ' selected' : '') + '>' + choice.charAt(0).toUpperCase() + choice.slice(1) + '</option>';
            }).join('') + '</select>';
        var responseDetails = '<div class="emergency_alert_response_info"><strong>' + emergencyAlertEscapeHtml(alert.responder || '—') + '</strong><span>' + emergencyAlertEscapeHtml(alert.transport || 'No transport assigned') + '</span></div>';

        html += '<td>' + statusSelect + '</td>' +
            '<td class="emergency_alert_incident_cell"><strong class="emergency_alert_incident">' + incident + '</strong>' + patient + '</td>' +
            '<td class="emergency_alert_location">' + location + '</td>' +
            '<td>' + responseDetails + '</td>' +
            '<td class="emergency_alert_time">' + reportedHtml + '</td>' +
            '<td class="emergency_alert_actions_cell">' + emergencyAlertActionHtml(alert) + '</td>';


        html +=
            '</tr>';

    }


    body.innerHTML =
        html;


    empty.hidden = alerts.length !== 0;

    var pageInfo = document.getElementById('emergency_alert_page_info');
    var previous = document.getElementById('emergency_alert_page_previous');
    var next = document.getElementById('emergency_alert_page_next');
    if (pageInfo) {
        pageInfo.textContent = 'Showing ' + (alerts.length ? firstIndex + 1 : 0) + '–' + Math.min(firstIndex + pageSize, alerts.length) + ' of ' + alerts.length + ' incidents';
    }
    if (previous) previous.disabled = emergencyAlertCurrentPage <= 1;
    if (next) next.disabled = emergencyAlertCurrentPage >= pageCount;


    emergencyAlertStaggerRows();

}


// =========================================================
// EMERGENCY ALERT ACTION BUTTON
// =========================================================

function emergencyAlertActionHtml(alert) {
    return '<button type="button" class="emergency_alert_action emergency_alert_action_details" data-action="details" data-alert-id="' + Number(alert.id) + '">VIEW DETAILS <span>&rarr;</span></button>';

}


// =========================================================
// EMERGENCY ROW ANIMATION
// =========================================================

function emergencyAlertStaggerRows() {

    var rows =
        document.querySelectorAll(
            '#emergency_alert_table_body ' +
            '.emergency_alert_row'
        );


    for (
        var i = 0;
        i < rows.length;
        i++
    ) {

        rows[i].style.animationDelay =
            (i * 45) + 'ms';

    }

}


// =========================================================
// EMERGENCY ACTION DELEGATION
// =========================================================

function emergencyAlertBindActions() {

    var body =
        document.getElementById(
            'emergency_alert_table_body'
        );


    if (!body) {
        return;
    }


    if (
        body.getAttribute(
            'data-delegated'
        ) === '1'
    ) {

        return;

    }


    body.setAttribute(
        'data-delegated',
        '1'
    );


    body.addEventListener(
        'click',
        function (event) {

            var button =
                event.target.closest(
                    '.emergency_alert_action'
                );


            if (!button) {
                return;
            }


            var action =
                button.getAttribute(
                    'data-action'
                );


            var id =
                button.getAttribute(
                    'data-alert-id'
                );


            if (!id) {
                return;
            }


            if (action === 'notify') {

                emergencyAlertNotifyStaff(
                    id,
                    button
                );

            } else if (
                action === 'details'
            ) {

                emergencyAlertLoadDetails(
                    id
                );

            }

        }
    );

    body.addEventListener('change', function (event) {
        var select = event.target.closest('.emergency_alert_status_select');
        if (select) emergencyAlertUpdateStatus(select);
    });

}

function emergencyAlertUpdateStatus(select) {
    var formData = new FormData();
    formData.append('id', select.getAttribute('data-alert-id'));
    formData.append('status', select.value);
    select.disabled = true;

    fetch(emergencyAlertRequestUrl('status').toString(), {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    }).then(function (response) {
        return response.json().then(function (data) {
            if (!response.ok || !data.success) throw new Error(data.message || 'Status update failed.');
            emergencyAlertShowToast(data.message);
            emergencyAlertLoadData(true);
        });
    }).catch(function (error) {
        emergencyAlertShowToast(error.message);
        emergencyAlertRender(emergencyAlertCurrentStatus());
    }).finally(function () {
        select.disabled = false;
    });
}

function emergencyAlertBindPagination() {
    var previous = document.getElementById('emergency_alert_page_previous');
    var next = document.getElementById('emergency_alert_page_next');
    if (previous) previous.addEventListener('click', function () {
        if (emergencyAlertCurrentPage > 1) {
            emergencyAlertCurrentPage--;
            emergencyAlertRenderTable(emergencyAlertCurrentStatus(), emergencyAlertCurrentRows);
        }
    });
    if (next) next.addEventListener('click', function () {
        if (emergencyAlertCurrentPage * 10 < emergencyAlertCurrentRows.length) {
            emergencyAlertCurrentPage++;
            emergencyAlertRenderTable(emergencyAlertCurrentStatus(), emergencyAlertCurrentRows);
        }
    });
}


// =========================================================
// NOTIFY STAFF
// =========================================================

function emergencyAlertNotifyStaff(
    id,
    button
) {

    button.disabled =
        true;


    var originalText =
        button.innerHTML;


    button.innerHTML =
        'NOTIFYING...';


    var formData =
        new FormData();


    formData.append(
        'id',
        id
    );


    var url =
        emergencyAlertRequestUrl(
            'notify'
        );


    fetch(
        url.toString(),
        {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With':
                    'XMLHttpRequest'
            }
        }
    )
        .then(function (response) {

            return response
                .json()
                .then(function (data) {

                    return {
                        ok: response.ok,
                        data: data
                    };

                });

        })
        .then(function (result) {

            if (
                !result.ok ||
                !result.data.success
            ) {

                throw new Error(
                    result.data.message ||
                    'Notification failed.'
                );

            }


            button.innerHTML =
                'STAFF NOTIFIED';


            button.classList.add(
                'emergency_alert_action_notified'
            );


            emergencyAlertShowToast(
                result.data.message ||
                'Staff notification sent successfully.'
            );

        })
        .catch(function (error) {

            button.disabled =
                false;


            button.innerHTML =
                originalText;


            emergencyAlertShowToast(
                error.message
            );

        });

}


// =========================================================
// LOAD ALERT DETAILS
// =========================================================

function emergencyAlertLoadDetails(id) {

    var url =
        emergencyAlertRequestUrl(
            'details',
            {
                id: id
            }
        );


    fetch(
        url.toString(),
        {
            headers: {
                'X-Requested-With':
                    'XMLHttpRequest'
            }
        }
    )
        .then(function (response) {

            return response
                .json()
                .then(function (data) {

                    return {
                        ok: response.ok,
                        data: data
                    };

                });

        })
        .then(function (result) {

            if (
                !result.ok ||
                !result.data.success
            ) {

                throw new Error(
                    result.data.message ||
                    'Unable to load alert details.'
                );

            }


            emergencyAlertOpenDetails(
                result.data.alert
            );

        })
        .catch(function (error) {

            emergencyAlertShowToast(
                error.message
            );

        });

}


// =========================================================
// OPEN ALERT DETAILS MODAL
// =========================================================

function emergencyAlertOpenDetails(alert) {

    var modal =
        document.getElementById(
            'emergency_alert_details_modal'
        );

    var body =
        document.getElementById(
            'emergency_alert_modal_body'
        );
    var actions = document.getElementById('emergency_alert_modal_actions');


    if (
        !modal ||
        !body || !actions
    ) {

        return;

    }


    var details = [
        [
            'Status',
            alert.status
        ],
        [
            'Patient',
            alert.patientName || alert.patientInfo || '—'
        ],
        [
            'Location',
            alert.location || '—'
        ],
        [
            'Barangay',
            alert.barangay || '—'
        ],
        [
            'Gender / Age',
            [alert.gender, alert.age].filter(function (value) { return value !== null && value !== ''; }).join(' / ') || '—'
        ],
        [
            'Assigned responder',
            alert.responder || '—'
        ],
        ['Transport', alert.transport || '—'],
        ['Transport type', alert.transportType || '—'],
        ['Driver', alert.driver || '—'],
        ['Hospital', alert.hospital || '—'],
        [
            'ETA', alert.eta || '—'
        ],
        ['Reported', alert.reportedAt || '—'],
        ['Resolved', alert.resolvedAt || '—'],
        ['Last updated', alert.updatedAt || '—'],
        ['Nurse vitals', alert.vitals || '—'],
        ['Nurse preparation', alert.nursePrep || '—'],
        ['Nurse notes', alert.nurseNotes || '—'],
        ['Coordinates', alert.latitude && alert.longitude ? alert.latitude + ', ' + alert.longitude : '—'],
        ['Notes', alert.notes || '—']
    ];


    body.innerHTML =
        details.map(
            function (item) {
                var modifier = item[0] === 'Notes' || item[0] === 'Nurse notes' ? ' emergency_alert_detail_item_notes' : '';

                return (
                    '<div class="emergency_alert_detail_item' + modifier + '">' +
                    '<span class="emergency_alert_detail_label">' +
                    emergencyAlertEscapeHtml(
                        item[0]
                    ) +
                    '</span>' +
                    '<span class="emergency_alert_detail_value">' +
                    emergencyAlertEscapeHtml(
                        item[1]
                    ) +
                    '</span>' +
                    '</div>'
                );

            }
        ).join('');

    document.getElementById('emergency_alert_modal_title').textContent = alert.incidentType || 'Alert Details';

    actions.innerHTML = '<a class="emergency_alert_drawer_action emergency_alert_drawer_action_primary" href="hospitalnetworkController.php?incident_id=' + Number(alert.id) + '&open_referral=1">Refer patient <span>&rarr;</span></a>' +
        (String(alert.status || '').toLowerCase() === 'critical'
            ? '<button type="button" class="emergency_alert_drawer_action" data-action="notify" data-alert-id="' + Number(alert.id) + '">Notify staff</button>'
            : '');


    modal.classList.add(
        'emergency_alert_modal_active'
    );


    modal.classList.remove(
        'emergency_alert_modal_open'
    );


    modal.setAttribute(
        'aria-hidden',
        'false'
    );

}


// =========================================================
// MODAL EVENTS
// =========================================================

function emergencyAlertBindModal() {

    var modal =
        document.getElementById(
            'emergency_alert_details_modal'
        );

    var close =
        document.getElementById(
            'emergency_alert_modal_close'
        );

    var backdrop =
        document.getElementById(
            'emergency_alert_modal_backdrop'
        );


    if (!modal) {
        return;
    }


    if (close) {

        close.addEventListener(
            'click',
            emergencyAlertCloseModal
        );

    }


    if (backdrop) {

        backdrop.addEventListener(
            'click',
            emergencyAlertCloseModal
        );

    }

    var actions = document.getElementById('emergency_alert_modal_actions');
    if (actions) {
        actions.addEventListener('click', function (event) {
            var button = event.target.closest('[data-action="notify"]');
            if (button) emergencyAlertNotifyStaff(button.getAttribute('data-alert-id'), button);
        });
    }


    document.addEventListener(
        'keydown',
        function (event) {

            if (
                event.key === 'Escape'
            ) {

                emergencyAlertCloseModal();

            }

        }
    );

}


// =========================================================
// CLOSE ALERT DETAILS MODAL
// =========================================================

function emergencyAlertCloseModal() {

    var modal =
        document.getElementById(
            'emergency_alert_details_modal'
        );


    if (!modal) {
        return;
    }


    // FIX:
    // The modal is opened with
    // emergency_alert_modal_active.
    //
    // Therefore that exact class must be removed.

    modal.classList.remove(
        'emergency_alert_modal_active'
    );


    // Also remove the old class in case
    // another page/version added it.

    modal.classList.remove(
        'emergency_alert_modal_open'
    );


    modal.setAttribute(
        'aria-hidden',
        'true'
    );

}


// =========================================================
// EMERGENCY ALERT BELL
// =========================================================

function emergencyAlertBindBell() {

    var bell =
        document.getElementById(
            'emergency_alert_bell_btn'
        );

    var wrapper = document.querySelector('.emergency_alert_notification_wrap');
    var panel = document.getElementById('emergency_alert_notification_panel');
    var list = document.getElementById('emergency_alert_notification_list');


    if (!bell) {
        return;
    }


    bell.addEventListener(
        'click',
        function () {
            if (!panel) return;
            panel.hidden = !panel.hidden;
            bell.setAttribute('aria-expanded', panel.hidden ? 'false' : 'true');
            var dot = document.getElementById('emergency_alert_bell_dot');
            if (!panel.hidden && dot) dot.hidden = true;
        }
    );

    if (list) {
        list.addEventListener('click', function (event) {
            var item = event.target.closest('[data-notification-id]');
            if (!item) return;
            if (panel) panel.hidden = true;
            bell.setAttribute('aria-expanded', 'false');
            emergencyAlertLoadDetails(item.getAttribute('data-notification-id'));
        });
    }

    document.addEventListener('click', function (event) {
        if (wrapper && !wrapper.contains(event.target) && panel && !panel.hidden) {
            panel.hidden = true;
            bell.setAttribute('aria-expanded', 'false');
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && panel && !panel.hidden) {
            panel.hidden = true;
            bell.setAttribute('aria-expanded', 'false');
        }
    });

}

function emergencyAlertRenderNotifications(alerts) {
    var list = document.getElementById('emergency_alert_notification_list');
    if (!list) return;

    var active = (alerts || []).filter(function (alert) {
        return ['critical', 'urgent'].indexOf(String(alert.status || '').toLowerCase()) !== -1;
    });

    if (!active.length) {
        list.innerHTML = '<span class="emergency_alert_notification_empty">No active emergency cases</span>';
        return;
    }

    list.innerHTML = active.slice(0, 8).map(function (alert) {
        return '<button type="button" class="emergency_alert_notification_item" data-notification-id="' + Number(alert.id) + '">' +
            '<span class="emergency_alert_notification_status emergency_alert_notification_status_' + String(alert.status).toLowerCase() + '">' + emergencyAlertEscapeHtml(alert.status) + '</span>' +
            '<strong>' + emergencyAlertEscapeHtml(alert.incidentType || 'Emergency case') + '</strong>' +
            '<small>' + emergencyAlertEscapeHtml(alert.location || 'Unknown location') + ' · ' + emergencyAlertEscapeHtml(alert.timeAgo || '') + '</small>' +
            '</button>';
    }).join('');
}


// =========================================================
// EMERGENCY BELL PULSE
// =========================================================

function emergencyAlertPulseBell() {

    var bell =
        document.getElementById(
            'emergency_alert_bell_btn'
        );


    if (!bell) {
        return;
    }


    bell.classList.remove(
        'emergency_alert_bell_pulse'
    );


    void bell.offsetWidth;


    bell.classList.add(
        'emergency_alert_bell_pulse'
    );


    window.setTimeout(
        function () {

            bell.classList.remove(
                'emergency_alert_bell_pulse'
            );

        },
        1500
    );

}


// =========================================================
// SUMMARY CARD ANIMATION
// =========================================================

function emergencyAlertAnimateSummaryCards() {

    var cards =
        document.querySelectorAll(
            '.emergency_alert_summary_card'
        );


    cards.forEach(
        function (card, index) {

            card.style.opacity =
                '0';

            card.style.transform =
                'translateY(8px)';


            window.setTimeout(
                function () {

                    card.style.transition =
                        'opacity .35s ease, transform .35s ease';

                    card.style.opacity =
                        '1';

                    card.style.transform =
                        'translateY(0)';

                },
                index * 60
            );

        }
    );

}


// =========================================================
// EMERGENCY NAVIGATION
// =========================================================

function emergencyAlertBindNavigation() {

    document
        .querySelectorAll(
            '.emergency_alert_nav_item'
        )
        .forEach(
            function (link) {

                link.addEventListener(
                    'click',
                    function () {

                        this.style.transform =
                            'scale(.99)';


                        var item =
                            this;


                        window.setTimeout(
                            function () {

                                item.style.transform =
                                    '';

                            },
                            120
                        );

                    }
                );

            }
        );

}


// =========================================================
// EMERGENCY TOAST
// =========================================================

function emergencyAlertShowToast(
    message
) {

    var toast =
        document.getElementById(
            'emergency_alert_toast'
        );


    if (!toast) {
        return;
    }


    toast.textContent =
        message;


    toast.classList.add(
        'emergency_alert_toast_active'
    );


    window.clearTimeout(
        emergencyAlertToastTimer
    );


    emergencyAlertToastTimer =
        window.setTimeout(
            function () {

                toast.classList.remove(
                    'emergency_alert_toast_active'
                );

            },
            2800
        );

}


// =========================================================
// ESCAPE HTML
// =========================================================

function emergencyAlertEscapeHtml(
    value
) {

    return String(
        value == null
            ? ''
            : value
    )
        .replace(
            /&/g,
            '&amp;'
        )
        .replace(
            /</g,
            '&lt;'
        )
        .replace(
            />/g,
            '&gt;'
        )
        .replace(
            /"/g,
            '&quot;'
        )
        .replace(
            /'/g,
            '&#039;'
        );

}


// =========================================================
// COMPATIBILITY ALIASES
// =========================================================

window.emergency_alert_init =
    emergencyAlertInit;

window.emergencyAlertInit =
    emergencyAlertInit;
// =========================================================
// SETTINGS PAGE
// =========================================================
// UI animation and interaction only.
// No SQL.
// No database connection.
// No database logic.
// =========================================================

function initSettingsPage() {

    var settingsCard =
        document.getElementById('settings-card');

    if (!settingsCard) {
        return;
    }

    incomingdispatchBindBell();
    incomingdispatchBindBellShake();


    // -------------------------------------------------------
    // Page entrance animation
    // -------------------------------------------------------

    window.setTimeout(function () {

        settingsCard.classList.add(
            'settings-card--visible'
        );

    }, 60);


    // -------------------------------------------------------
    // Editable account fields
    // -------------------------------------------------------

    var editButtons =
        document.querySelectorAll(
            '[data-settings-edit]'
        );


    editButtons.forEach(function (button) {

        button.addEventListener(
            'click',
            function () {

                var targetId =
                    button.getAttribute(
                        'data-settings-edit'
                    );

                var input =
                    document.getElementById(
                        targetId
                    );


                if (!input) {
                    return;
                }


                var currentlyReadonly =
                    input.hasAttribute('readonly');


                if (currentlyReadonly) {

                    input.removeAttribute(
                        'readonly'
                    );

                    input.classList.add(
                        'settings-input--editable'
                    );

                    input.focus();

                    var length =
                        input.value.length;

                    input.setSelectionRange(
                        length,
                        length
                    );

                } else {

                    input.setAttribute(
                        'readonly',
                        'readonly'
                    );

                    input.classList.remove(
                        'settings-input--editable'
                    );

                }

            }
        );

    });


    // -------------------------------------------------------
    // Change password
    // -------------------------------------------------------

    // -------------------------------------------------------
    // Two-factor authentication
    // -------------------------------------------------------

    var twoFactorButton =
        document.getElementById(
            'settings-two-factor'
        );


    if (twoFactorButton) {

        twoFactorButton.addEventListener(
            'click',
            function () {

                alert(
                    'Two-factor authentication settings will be available here.'
                );

            }
        );

    }


    // -------------------------------------------------------
    // Hospital profile
    // -------------------------------------------------------

    var hospitalButton =
        document.getElementById(
            'settings-edit-hospital'
        );


    if (hospitalButton) {

        hospitalButton.addEventListener(
            'click',
            function () {

                var hospitalInput =
                    document.getElementById(
                        'settings-hospital-name'
                    );

                if (!hospitalInput) {
                    return;
                }

                hospitalInput.removeAttribute('readonly');
                hospitalInput.classList.add(
                    'settings-input--editable'
                );
                hospitalInput.focus();

            }
        );

    }


    // -------------------------------------------------------
    // Submit settings through the MVC controller.
    // -------------------------------------------------------

    var saveButton =
        document.getElementById(
            'settings-save-button'
        );


    if (saveButton) {

        saveButton.form.addEventListener(
            'submit',
            function () {

                var editableInputs =
                    document.querySelectorAll(
                        '.settings-input:not([readonly])'
                    );


                editableInputs.forEach(
                    function (input) {

                        input.setAttribute(
                            'readonly',
                            'readonly'
                        );

                        input.classList.remove(
                            'settings-input--editable'
                        );

                    }
                );


                saveButton.classList.add(
                    'settings-save-button--saved'
                );

            }
        );

    }


    // -------------------------------------------------------
    // Logout confirmation drawer
    // -------------------------------------------------------

    var logoutButton =
        document.getElementById(
            'settings-logout-button'
        );


    if (logoutButton) {

        var logoutOverlay =
            document.getElementById(
                'settings-logout-overlay'
            );

        var logoutCancelButton =
            document.getElementById(
                'settings-logout-cancel'
            );

        var logoutConfirmLink =
            document.getElementById(
                'settings-logout-confirm'
            );

        var lastFocusedElement = null;

        function closeLogoutDrawer() {

            if (!logoutOverlay) {
                return;
            }

            logoutOverlay.classList.remove(
                'settings-logout-overlay--open'
            );

            logoutOverlay.setAttribute(
                'aria-hidden',
                'true'
            );

            document.body.classList.remove(
                'settings-logout-open'
            );

            if (lastFocusedElement) {
                lastFocusedElement.focus();
            }

        }

        logoutButton.addEventListener(
            'click',
            function () {

                if (!logoutOverlay) {
                    return;
                }

                lastFocusedElement = document.activeElement;

                logoutOverlay.classList.add(
                    'settings-logout-overlay--open'
                );

                logoutOverlay.setAttribute(
                    'aria-hidden',
                    'false'
                );

                document.body.classList.add(
                    'settings-logout-open'
                );

                if (logoutCancelButton) {
                    logoutCancelButton.focus();
                }

            }
        );

        if (logoutCancelButton) {

            logoutCancelButton.addEventListener(
                'click',
                closeLogoutDrawer
            );

        }

        if (logoutOverlay) {

            logoutOverlay.addEventListener(
                'click',
                function (event) {

                    if (event.target === logoutOverlay) {
                        closeLogoutDrawer();
                    }

                }
            );

        }

        document.addEventListener(
            'keydown',
            function (event) {

                if (
                    event.key === 'Escape' &&
                    logoutOverlay &&
                    logoutOverlay.classList.contains(
                        'settings-logout-overlay--open'
                    )
                ) {
                    closeLogoutDrawer();
                }

            }
        );

    }

}


// =========================================================
// RUN SETTINGS INITIALIZATION
// =========================================================

document.addEventListener(
    'DOMContentLoaded',
    function () {

        try {

            initSettingsPage();

        } catch (error) {

            console.error(
                '[PulseAlert] Settings initialization failed.',
                error
            );

        }

    }
);

// =========================================================
// DOCTOR AND NURSE MODULE PAGES
// =========================================================

function initDoctorNurseModulePages() {
    var drawer = document.getElementById('caseDrawer');
    var backdrop = document.getElementById('drawerBackdrop');
    var closeButton = document.getElementById('closeDrawer');
    var doctorCases = window.doctorCases || [];

    function setCaseDetail(id) {
        var selectedCase = doctorCases.find(function (item) {
            return Number(item.id) === Number(id);
        });
        if (!selectedCase) return;

        var values = {
            drawerCaseTitle: selectedCase.title,
            drawerCaseLocation: selectedCase.location,
            drawerCaseStatus: selectedCase.statusLabel,
            drawerCasePatient: selectedCase.patient,
            drawerCaseHospital: selectedCase.hospital,
            drawerCaseResponder: selectedCase.responder || 'Not recorded',
            drawerCaseEta: selectedCase.eta === null ? 'Not available' : selectedCase.eta + ' min',
            drawerCaseReported: selectedCase.reportedAt,
            drawerCaseNotes: selectedCase.notes || 'No incident notes recorded.'
        };
        Object.keys(values).forEach(function (elementId) {
            var element = document.getElementById(elementId);
            if (element) element.textContent = values[elementId];
        });
        var statusElement = document.getElementById('drawerCaseStatus');
        if (statusElement) {
            statusElement.classList.remove('status-critical', 'status-urgent', 'status-stable');
            var statusClass = selectedCase.status === 'dispatched' ? 'urgent' : selectedCase.status;
            if (['critical', 'urgent', 'stable'].indexOf(statusClass) !== -1) {
                statusElement.classList.add('status-' + statusClass);
            }
        }
    }

    var doctorCaseList = document.getElementById('doctor-case-list');
    if (doctorCaseList) {
        doctorCaseList.addEventListener('click', function (event) {
            var button = event.target.closest('.details-btn');
            if (!button || !drawer) return;
            setCaseDetail(button.dataset.caseId);
            drawer.classList.add('open');
            drawer.setAttribute('aria-hidden', 'false');
            if (backdrop) backdrop.classList.add('open');
            document.body.classList.add('drawer-open');
        });
    }

    function closeCaseDrawer() {
        if (drawer) drawer.classList.remove('open');
        if (drawer) drawer.setAttribute('aria-hidden', 'true');
        if (backdrop) backdrop.classList.remove('open');
        document.body.classList.remove('drawer-open');
    }

    if (closeButton) closeButton.addEventListener('click', closeCaseDrawer);
    if (backdrop) backdrop.addEventListener('click', closeCaseDrawer);
    if (drawer) {
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && drawer.classList.contains('open')) closeCaseDrawer();
        });
    }

    var dutyForm = document.getElementById('doctor-duty-form');
    var dutyValue = document.getElementById('doctor-duty-value');
    var dutyButton = document.getElementById('doctor-duty-toggle');
    var dutyFeedback = document.getElementById('doctor-duty-feedback');
    if (dutyForm && dutyValue && dutyButton) {
        dutyForm.addEventListener('submit', function (event) {
            event.preventDefault();
            dutyButton.disabled = true;
            dutyButton.setAttribute('aria-busy', 'true');
            if (dutyFeedback) dutyFeedback.textContent = '';

            fetch(dutyForm.action, {
                method: 'POST',
                body: new FormData(dutyForm),
                headers: { Accept: 'application/json' }
            }).then(function (response) {
                return response.json().then(function (result) {
                    if (!response.ok || !result.success) {
                        throw new Error(result.message || 'Duty status could not be updated.');
                    }
                    return result;
                });
            }).then(function (result) {
                dutyValue.value = result.available ? 'off' : 'on';
                dutyButton.textContent = result.available ? 'On Duty' : 'Off Duty';
                dutyButton.classList.toggle('is-on-duty', result.available);
                dutyButton.classList.toggle('is-off-duty', !result.available);
                dutyButton.setAttribute('aria-pressed', result.available ? 'true' : 'false');
                if (dutyFeedback) dutyFeedback.textContent = result.message;
            }).catch(function (error) {
                if (dutyFeedback) dutyFeedback.textContent = error.message;
                console.error('[PulseAlert] Doctor duty status update failed.', error);
            }).finally(function () {
                dutyButton.disabled = false;
                dutyButton.removeAttribute('aria-busy');
            });
        });
    }

    var caseFilterButtons = document.querySelectorAll('[data-case-filter]');
    var doctorCaseCards = Array.prototype.slice.call(document.querySelectorAll('.case-card[data-case-status]'));
    var doctorCasesEmpty = document.getElementById('doctor-cases-empty');
    var casePagination = document.getElementById('doctor-case-pagination');
    var previousCasesButton = document.getElementById('doctor-cases-previous');
    var nextCasesButton = document.getElementById('doctor-cases-next');
    var casePageStatus = document.getElementById('doctor-cases-page-status');
    var activeCaseFilter = 'all';
    var currentCasePage = 1;
    var casesPerPage = 6;

    function renderDoctorCases() {
        var matchingCards = Array.prototype.filter.call(doctorCaseCards, function (card) {
            return activeCaseFilter === 'all' || card.dataset.caseStatus === activeCaseFilter;
        });
        var pageCount = Math.max(1, Math.ceil(matchingCards.length / casesPerPage));
        currentCasePage = Math.min(currentCasePage, pageCount);
        var firstVisibleIndex = (currentCasePage - 1) * casesPerPage;
        var lastVisibleIndex = firstVisibleIndex + casesPerPage;

        doctorCaseCards.forEach(function (card) {
            var matchingIndex = matchingCards.indexOf(card);
            card.hidden = matchingIndex < firstVisibleIndex || matchingIndex >= lastVisibleIndex;
        });
        if (doctorCasesEmpty) {
            doctorCasesEmpty.hidden = matchingCards.length > 0;
            doctorCasesEmpty.textContent = doctorCaseCards.length
                ? 'No ' + activeCaseFilter + ' cases are currently active.'
                : 'No active cases have been sent to your account.';
        }
        if (casePageStatus) casePageStatus.textContent = 'Page ' + currentCasePage + ' of ' + pageCount;
        if (previousCasesButton) previousCasesButton.disabled = currentCasePage <= 1;
        if (nextCasesButton) nextCasesButton.disabled = currentCasePage >= pageCount;
        if (casePagination) casePagination.hidden = matchingCards.length === 0;
    }

    function refreshDoctorCases(cases, summary) {
        doctorCases.length = 0;
        cases.forEach(function (item) {
            doctorCases.push(item);
        });
        var caseList = document.getElementById('doctor-case-list');
        if (!caseList) return;

        caseList.querySelectorAll('.case-card').forEach(function (card) {
            card.remove();
        });
        doctorCases.forEach(function (item) {
            var status = item.status === 'dispatched' ? 'urgent' : item.status;
            var reportedAt = new Date(String(item.reportedAt).replace(' ', 'T') + '+08:00');
            var reportedLabel = Number.isNaN(reportedAt.getTime())
                ? item.reportedAt
                : reportedAt.toLocaleString('en-US', {
                    timeZone: 'Asia/Manila',
                    month: 'short',
                    day: 'numeric',
                    year: 'numeric',
                    hour: 'numeric',
                    minute: '2-digit'
                });
            var patient = item.patient === 'Patient details unavailable' ? '' : ' · ' + item.patient;
            var eta = item.eta === null ? 'ETA unavailable' : 'ETA ' + item.eta + ' min';
            var card = document.createElement('article');
            card.className = 'case-card';
            card.dataset.caseStatus = status;
            card.innerHTML = '<div class="case-card-top"><h2>' + escapeIncomingDispatchText(item.title) + '</h2>' +
                '<span class="severity ' + escapeIncomingDispatchText(status) + '">' + escapeIncomingDispatchText(item.statusLabel) + '</span></div>' +
                '<p>' + escapeIncomingDispatchText(item.location) + escapeIncomingDispatchText(patient) + '</p>' +
                '<small class="case-reported">Reported ' + escapeIncomingDispatchText(reportedLabel) + '</small>' +
                '<div class="case-card-bottom"><span>' + escapeIncomingDispatchText(eta + ' · ' + item.hospital) + '</span>' +
                '<button type="button" class="details-btn" data-case-id="' + Number(item.id) + '">View details</button></div>';
            caseList.insertBefore(card, doctorCasesEmpty);
        });

        Object.keys(summary || {}).forEach(function (statusKey) {
            var value = document.querySelector('[data-case-filter="' + statusKey + '"] .case-summary-value');
            if (value) value.textContent = String(summary[statusKey]);
        });
        doctorCaseCards = Array.prototype.slice.call(caseList.querySelectorAll('.case-card[data-case-status]'));
        renderDoctorCases();
    }

    caseFilterButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            activeCaseFilter = button.dataset.caseFilter;
            currentCasePage = 1;
            caseFilterButtons.forEach(function (filterButton) {
                var active = filterButton === button;
                filterButton.classList.toggle('is-active', active);
                filterButton.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
            renderDoctorCases();
        });
    });
    if (previousCasesButton) {
        previousCasesButton.addEventListener('click', function () {
            if (currentCasePage > 1) {
                currentCasePage -= 1;
                renderDoctorCases();
            }
        });
    }
    if (nextCasesButton) {
        nextCasesButton.addEventListener('click', function () {
            currentCasePage += 1;
            renderDoctorCases();
        });
    }
    renderDoctorCases();

    var incomingAlertBackdrop = document.getElementById('incoming-alert-backdrop');
    var incomingAlertClose = document.getElementById('incoming-alert-close');
    var incomingAlertError = document.getElementById('incoming-alert-error');
    if (incomingAlertBackdrop && incomingAlertClose && window.doctorAlertEndpoint) {
        var queuedDoctorAlerts = window.doctorInitialAlerts || [];
        var knownDoctorAlertIds = {};
        var activeDoctorAlert = null;
        queuedDoctorAlerts.forEach(function (alert) {
            knownDoctorAlertIds[String(alert.alertId)] = true;
        });

        function showNextDoctorAlert() {
            if (activeDoctorAlert || !queuedDoctorAlerts.length) return;
            activeDoctorAlert = queuedDoctorAlerts.shift();
            document.getElementById('incoming-alert-type').textContent = activeDoctorAlert.title;
            document.getElementById('incoming-alert-location').textContent = activeDoctorAlert.location;
            document.getElementById('incoming-alert-status').textContent = activeDoctorAlert.statusLabel;
            document.getElementById('incoming-alert-patient').textContent = activeDoctorAlert.patient;
            document.getElementById('incoming-alert-hospital').textContent = activeDoctorAlert.hospital;
            document.getElementById('incoming-alert-description').textContent =
                'Review the incident details, then close this alert to continue.';
            incomingAlertError.hidden = true;
            incomingAlertBackdrop.hidden = false;
            incomingAlertClose.focus();
        }

        function loadDoctorAlerts() {
            fetch(window.doctorAlertEndpoint + '?action=pending_alerts', {
                headers: { Accept: 'application/json' },
                cache: 'no-store'
            }).then(function (response) {
                return response.json().then(function (result) {
                    if (!response.ok || !result.success) {
                        throw new Error(result.message || 'Incoming alerts could not be loaded.');
                    }
                    return result;
                });
            }).then(function (result) {
                if (Array.isArray(result.cases)) refreshDoctorCases(result.cases, result.summary || {});
                result.alerts.forEach(function (alert) {
                    var id = String(alert.alertId);
                    if (!knownDoctorAlertIds[id]) {
                        knownDoctorAlertIds[id] = true;
                        queuedDoctorAlerts.push(alert);
                    }
                });
                showNextDoctorAlert();
            }).catch(function (error) {
                console.error('[PulseAlert] Incoming doctor alerts could not be loaded.', error);
            });
        }

        incomingAlertClose.addEventListener('click', function () {
            if (!activeDoctorAlert) return;
            incomingAlertClose.disabled = true;
            incomingAlertClose.setAttribute('aria-busy', 'true');
            incomingAlertError.hidden = true;

            var payload = new FormData();
            payload.append('csrf_token', window.doctorAlertCsrfToken || '');
            payload.append('alert_id', String(activeDoctorAlert.alertId));
            fetch(window.doctorAlertEndpoint + '?action=acknowledge_alert', {
                method: 'POST',
                body: payload,
                headers: { Accept: 'application/json' }
            }).then(function (response) {
                return response.json().then(function (result) {
                    if (!response.ok || !result.success) {
                        throw new Error(result.message || 'The alert could not be acknowledged.');
                    }
                    window.location.reload();
                });
            }).catch(function (error) {
                incomingAlertError.textContent = error.message;
                incomingAlertError.hidden = false;
                incomingAlertClose.disabled = false;
                incomingAlertClose.removeAttribute('aria-busy');
                console.error('[PulseAlert] Doctor incoming alert acknowledgement failed.', error);
            });
        });

        showNextDoctorAlert();
        loadDoctorAlerts();
        window.setInterval(function () {
            if (document.visibilityState === 'visible') loadDoctorAlerts();
        }, 5000);
    }

    var caseButtons = document.querySelectorAll('.case-filters button');
    var caseCards = document.querySelectorAll('.prep-case');
    var caseDetail = document.getElementById('caseDetail');
    var nurseCases = window.nurseCases || [];
    var detailSeverity = document.getElementById('detailSeverity');
    var detailArrival = document.getElementById('detailArrival');
    var detailTitle = document.getElementById('detailTitle');
    var detailPatient = document.getElementById('detailPatient');
    var detailHr = document.getElementById('detailHr');
    var detailBp = document.getElementById('detailBp');
    var detailSpo2 = document.getElementById('detailSpo2');
    var detailConscious = document.getElementById('detailConscious');
    var detailNote = document.getElementById('detailNote');
    var handoverSource = document.querySelector('.handover-head span');

    function showCase(id) {
        var selected = nurseCases.find(function (item) {
            return String(item.id) === String(id);
        });
        if (!selected) return;

        caseCards.forEach(function (card) {
            card.classList.toggle('selected', card.dataset.caseId === String(id));
        });

        if (detailSeverity) {
            detailSeverity.className = 'severity-label ' + selected.severity.toLowerCase();
            detailSeverity.innerHTML = '<i></i>' + selected.severity;
        }
        if (detailArrival) detailArrival.textContent = selected.arrival;
        if (detailTitle) detailTitle.textContent = selected.title;
        if (detailPatient) {
            var patient = selected.patient.replace(', going to', '');
            var destination = selected.patient.includes('going to')
                ? selected.patient.split('going to')[1]
                : ' the ER';
            detailPatient.textContent = patient + ', ' + selected.unit + ', going to' + destination;
        }
        if (detailHr) detailHr.innerHTML = selected.heartRate + '<small>bpm</small>';
        if (detailBp) detailBp.innerHTML = selected.bp + '<small>mmHg</small>';
        if (detailSpo2) detailSpo2.innerHTML = selected.spo2 + '<small>%</small>';
        if (detailConscious) detailConscious.innerHTML = selected.consciousness + '<small> GCS</small>';
        if (detailNote) detailNote.textContent = selected.note;
        if (handoverSource) handoverSource.textContent = 'Sent from ' + selected.unit;
    }

    function filterCases(filter) {
        var firstVisible = null;
        caseCards.forEach(function (card) {
            var visible = filter === 'all' || card.dataset.severity === filter;
            card.classList.toggle('hidden', !visible);
            if (visible && firstVisible === null) firstVisible = card.dataset.caseId;
        });
        caseButtons.forEach(function (button) {
            button.classList.toggle('active', button.dataset.filter === filter);
        });

        if (firstVisible !== null) {
            if (caseDetail) caseDetail.classList.remove('empty');
            showCase(firstVisible);
        } else if (caseDetail) {
            caseDetail.classList.add('empty');
        }
    }

    caseButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            filterCases(button.dataset.filter);
        });
    });
    document.querySelectorAll('.case-select').forEach(function (button) {
        button.addEventListener('click', function () {
            showCase(button.dataset.caseId);
        });
    });
    if (caseCards.length) showCase(caseCards[0].dataset.caseId);

    var scheduleModal = document.getElementById('scheduleModal');
    var scheduleForm = document.getElementById('scheduleForm');
    if (!scheduleModal || !scheduleForm) return;
    var deleteScheduleModal = document.getElementById('deleteScheduleModal');
    var confirmDeleteSchedule = document.getElementById('confirmDeleteSchedule');
    var pendingDeleteForm = null;
    var lastDeleteTrigger = null;

    var scheduleAction = document.getElementById('scheduleAction');
    var scheduleId = document.getElementById('scheduleId');
    var scheduleDate = document.getElementById('scheduleDate');
    var scheduleType = document.getElementById('scheduleType');
    var scheduleStart = document.getElementById('scheduleStart');
    var scheduleEnd = document.getElementById('scheduleEnd');
    var scheduleModalTitle = document.getElementById('scheduleModalTitle');
    var scheduleSubmit = document.getElementById('scheduleSubmit');
    var timeFields = document.querySelector('.schedule-time-fields');
    var addScheduleMode = document.getElementById('addScheduleMode');
    var calendarHint = document.getElementById('calendarHint');
    var selectedDateLabel = document.getElementById('scheduleSelectedDate');
    var dateButtons = document.querySelectorAll('.day-select');

    function setScheduleTimes() {
        var noTime = scheduleType.value === 'oncall' || scheduleType.value === 'leave';
        if (timeFields) timeFields.classList.toggle('hidden', noTime);
        scheduleStart.required = !noTime;
        scheduleEnd.required = !noTime;

        if (noTime) {
            scheduleStart.value = '';
            scheduleEnd.value = '';
            return;
        }
        if (!scheduleStart.value || !scheduleEnd.value) {
            if (scheduleType.value === 'evening') {
                scheduleStart.value = '15:00';
                scheduleEnd.value = '23:00';
            } else {
                scheduleStart.value = '07:00';
                scheduleEnd.value = '15:00';
            }
        }
    }

    function openScheduleModal(data) {
        var isEdit = data.action === 'update';
        scheduleAction.value = isEdit ? 'update' : 'add';
        scheduleId.value = isEdit ? data.id : '';
        scheduleDate.value = data.date;
        scheduleModalTitle.textContent = isEdit ? 'Edit schedule' : 'Add schedule';
        scheduleSubmit.textContent = isEdit ? 'Save changes' : 'Add schedule';
        scheduleType.value = data.type || 'morning';
        scheduleStart.value = data.start || (scheduleType.value === 'evening' ? '15:00' : '07:00');
        scheduleEnd.value = data.end || (scheduleType.value === 'evening' ? '23:00' : '15:00');
        selectedDateLabel.textContent = new Date(data.date + 'T00:00:00').toLocaleDateString(undefined, {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });

        setScheduleTimes();
        scheduleModal.classList.add('open');
        scheduleModal.setAttribute('aria-hidden', 'false');
        scheduleModal.removeAttribute('inert');
        scheduleType.focus();
    }

    function closeScheduleModal() {
        scheduleModal.classList.remove('open');
        scheduleModal.setAttribute('aria-hidden', 'true');
        scheduleModal.setAttribute('inert', '');
        if (addScheduleMode && addScheduleMode.getAttribute('aria-pressed') === 'true') {
            setAddMode(false);
        }
    }

    function openDeleteScheduleModal(form, trigger) {
        if (!deleteScheduleModal) {
            HTMLFormElement.prototype.submit.call(form);
            return;
        }
        pendingDeleteForm = form;
        lastDeleteTrigger = trigger;
        deleteScheduleModal.classList.add('open');
        deleteScheduleModal.setAttribute('aria-hidden', 'false');
        deleteScheduleModal.removeAttribute('inert');
        confirmDeleteSchedule.focus();
    }

    function closeDeleteScheduleModal() {
        if (!deleteScheduleModal) return;
        deleteScheduleModal.classList.remove('open');
        deleteScheduleModal.setAttribute('aria-hidden', 'true');
        deleteScheduleModal.setAttribute('inert', '');
        pendingDeleteForm = null;
        if (lastDeleteTrigger && document.contains(lastDeleteTrigger)) {
            lastDeleteTrigger.focus();
        }
    }

    function setAddMode(enabled) {
        if (!addScheduleMode) return;
        addScheduleMode.setAttribute('aria-pressed', enabled ? 'true' : 'false');
        addScheduleMode.classList.toggle('is-selecting-date', enabled);
        dateButtons.forEach(function (button) {
            button.disabled = !enabled || button.dataset.hasShift === 'true';
        });
        if (calendarHint) {
            calendarHint.textContent = enabled
                ? 'Choose an available date. Select an existing shift to edit it.'
                : 'Select “Add schedule”, then choose a day.';
        }
    }

    if (addScheduleMode) {
        addScheduleMode.addEventListener('click', function () {
            setAddMode(addScheduleMode.getAttribute('aria-pressed') !== 'true');
        });
    }
    dateButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            setAddMode(false);
            openScheduleModal({
                action: 'add',
                date: button.dataset.date,
                type: 'morning'
            });
        });
    });
    document.querySelectorAll('.shift[data-open-schedule="edit"]').forEach(function (button) {
        button.addEventListener('click', function () {
            setAddMode(false);
            openScheduleModal({
                action: 'update',
                id: button.dataset.id,
                date: button.dataset.date,
                type: button.dataset.type,
                start: button.dataset.start,
                end: button.dataset.end
            });
        });
    });
    document.querySelectorAll('.delete-schedule-form').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            openDeleteScheduleModal(form, event.submitter || form.querySelector('button[type="submit"]'));
        });
    });
    document.querySelectorAll('[data-close-delete-schedule]').forEach(function (button) {
        button.addEventListener('click', closeDeleteScheduleModal);
    });
    if (confirmDeleteSchedule) {
        confirmDeleteSchedule.addEventListener('click', function () {
            if (!pendingDeleteForm) return;
            HTMLFormElement.prototype.submit.call(pendingDeleteForm);
        });
    }
    document.querySelectorAll('[data-close-schedule]').forEach(function (button) {
        button.addEventListener('click', closeScheduleModal);
    });
    scheduleType.addEventListener('change', setScheduleTimes);
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            if (deleteScheduleModal && deleteScheduleModal.classList.contains('open')) {
                closeDeleteScheduleModal();
            } else {
                closeScheduleModal();
            }
        }
    });
}

function initNurseNotifiedCases() {
    var caseList = document.getElementById('nurse-case-list');
    var dutyForm = document.getElementById('nurse-duty-form');
    var prepForm = document.getElementById('patientPrepForm');
    if (!caseList || !prepForm) return;

    var cases = Array.isArray(window.nurseCases) ? window.nurseCases.slice() : [];
    var beds = Array.isArray(window.nurseBeds) ? window.nurseBeds.slice() : [];
    var endpoint = window.nurseNotifiedCasesEndpoint || '';
    var csrfToken = window.nurseCasesCsrfToken || '';
    var caseButtons = Array.prototype.slice.call(document.querySelectorAll('.case-filters button'));
    var emptyState = document.getElementById('nurse-cases-empty');
    var pagination = document.getElementById('nurse-case-pagination');
    var previousButton = document.getElementById('nurse-case-previous');
    var nextButton = document.getElementById('nurse-case-next');
    var pageStatus = document.getElementById('nurse-case-page-status');
    var dutyValue = document.getElementById('nurse-duty-value');
    var dutyButton = document.getElementById('nurse-duty-toggle');
    var dutyFeedback = document.getElementById('nurse-duty-feedback');
    var prepModal = document.getElementById('patientPrepModal');
    var prepIncidentId = document.getElementById('prepIncidentId');
    var prepIncidentTitle = document.getElementById('patientPrepIncident');
    var modalFeedback = document.getElementById('prepModalFeedback');
    var savePrepButton = document.getElementById('savePatientPrep');
    var selectedCaseId = null;
    var activeFilter = 'all';
    var currentPage = 1;
    var pageSize = 3;
    var refreshing = false;

    function createTextElement(tagName, className, text) {
        var element = document.createElement(tagName);
        if (className) element.className = className;
        element.textContent = text || '';
        return element;
    }

    function matchingCases() {
        return cases.filter(function (item) {
            return activeFilter === 'all' || item.severity === activeFilter;
        });
    }

    function updateSummary(summary, nextArrival, freeBeds) {
        var incoming = document.getElementById('nurse-summary-incoming');
        var prepared = document.getElementById('nurse-summary-prepared');
        var arrival = document.getElementById('nurse-summary-arrival');
        var arrivalCase = document.getElementById('nurse-summary-arrival-case');
        var erBeds = document.getElementById('nurse-summary-er');
        var pediatricBeds = document.getElementById('nurse-summary-pediatric');
        var incomingCount = Number(summary.incoming) || 0;
        if (incoming) {
            incoming.textContent = '';
            incoming.appendChild(document.createTextNode(String(incomingCount) + ' '));
            incoming.appendChild(createTextElement('small', '', 'cases'));
        }
        if (prepared) {
            prepared.textContent = '';
            prepared.appendChild(document.createTextNode(String(Number(summary.prepCompleted) || 0) + ' '));
            prepared.appendChild(createTextElement('small', '', 'of ' + incomingCount));
        }
        if (nextArrival !== undefined) {
            if (arrival) arrival.textContent = nextArrival ? nextArrival.arrival : 'No en-route cases';
            if (arrivalCase) arrivalCase.textContent = nextArrival ? nextArrival.incident : 'Waiting for transport update';
        }
        if (erBeds) erBeds.textContent = String(Number(freeBeds.er) || 0);
        if (pediatricBeds) pediatricBeds.textContent = String(Number(freeBeds.pediatric) || 0);
    }

    function updateBedOptions(selectedBedId) {
        var select = document.getElementById('prepBed');
        if (!select) return;
        var currentCase = cases.find(function (item) {
            return String(item.id) === String(selectedCaseId);
        });
        select.replaceChildren(createTextElement('option', '', 'Choose an available bed'));
        select.options[0].value = '';
        beds.forEach(function (bed) {
            var available = bed.status === 'ready' ||
                (currentCase && String(currentCase.bedId) === String(bed.id));
            if (!available) return;
            var option = createTextElement('option', '', bed.label);
            option.value = String(bed.id);
            select.appendChild(option);
        });
        select.value = selectedBedId === null || selectedBedId === undefined ? '' : String(selectedBedId);
    }

    function updateFilterCounts() {
        var counts = { all: cases.length, Critical: 0, Urgent: 0, Stable: 0 };
        cases.forEach(function (item) {
            counts[item.severity] = (counts[item.severity] || 0) + 1;
        });
        caseButtons.forEach(function (button) {
            var count = button.querySelector('b');
            if (count) count.textContent = String(counts[button.dataset.filter] || 0);
        });
    }

    function makeCaseCard(item) {
        var article = createTextElement('article', 'prep-case');
        article.dataset.severity = item.severity;
        article.dataset.caseId = String(item.id);

        var selectButton = document.createElement('button');
        selectButton.type = 'button';
        selectButton.className = 'case-select';
        selectButton.dataset.caseId = String(item.id);
        selectButton.setAttribute('aria-pressed', 'false');
        selectButton.setAttribute('aria-label', 'View ' + item.title);

        var severityLine = createTextElement('span', 'severity-line ' + item.severity.toLowerCase());
        var main = createTextElement('div', 'case-main');
        var meta = createTextElement('div', 'case-meta');
        var severity = createTextElement('span', 'severity-label ' + item.severity.toLowerCase());
        severity.appendChild(document.createElement('i'));
        severity.appendChild(document.createTextNode(item.severity));
        meta.appendChild(severity);
        meta.appendChild(createTextElement('span', '', item.unit));
        main.appendChild(meta);
        main.appendChild(createTextElement('h2', '', item.title));
        main.appendChild(createTextElement('p', '', item.patient));
        main.appendChild(createTextElement('strong', 'arriving-text', item.arrivalStatus));
        var prepState = createTextElement('small', 'prep-state' + (item.prepCompleted ? ' is-complete' : ''),
            item.prepCompleted ? 'Prep complete' : 'Preparation pending');
        main.appendChild(prepState);
        selectButton.appendChild(severityLine);
        selectButton.appendChild(main);
        article.appendChild(selectButton);
        return article;
    }

    function renderCases() {
        var matching = matchingCases();
        var pageCount = Math.max(1, Math.ceil(matching.length / pageSize));
        currentPage = Math.min(currentPage, pageCount);
        var start = (currentPage - 1) * pageSize;
        var visibleCases = matching.slice(start, start + pageSize);
        caseList.querySelectorAll('.prep-case').forEach(function (card) {
            card.remove();
        });
        visibleCases.forEach(function (item) {
            var card = makeCaseCard(item);
            card.classList.toggle('selected', String(item.id) === String(selectedCaseId));
            var button = card.querySelector('.case-select');
            button.setAttribute('aria-pressed', String(String(item.id) === String(selectedCaseId)));
            caseList.insertBefore(card, emptyState);
        });
        if (emptyState) {
            emptyState.hidden = matching.length > 0;
            emptyState.textContent = cases.length
                ? 'No ' + activeFilter.toLowerCase() + ' cases are currently active.'
                : 'No active cases have been notified to your account.';
        }
        if (pageStatus) pageStatus.textContent = 'Page ' + currentPage + ' of ' + pageCount;
        if (previousButton) previousButton.disabled = currentPage <= 1;
        if (nextButton) nextButton.disabled = currentPage >= pageCount;
        if (pagination) pagination.hidden = matching.length <= pageSize;

        if (visibleCases.length) {
            if (!visibleCases.some(function (item) { return String(item.id) === String(selectedCaseId); })) {
                selectedCaseId = visibleCases[0].id;
            }
            updateDetails(selectedCaseId);
            var selectedCard = caseList.querySelector('.prep-case.selected .case-select');
            if (selectedCard) selectedCard.setAttribute('aria-pressed', 'true');
        } else {
            selectedCaseId = null;
            showEmptyDetails();
        }
        updateFilterCounts();
    }

    function setMeasurement(elementId, value, unit) {
        var element = document.getElementById(elementId);
        if (!element) return;
        element.textContent = value === null || value === '' ? '—' : String(value);
        element.appendChild(createTextElement('small', '', unit));
    }

    function showEmptyDetails() {
        var detailContent = document.getElementById('detailContent');
        var detail = document.getElementById('caseDetail');
        var emptyMessage = document.getElementById('detailEmptyMessage');
        if (detailContent) detailContent.hidden = true;
        if (emptyMessage) {
            emptyMessage.hidden = false;
            emptyMessage.textContent = cases.length
                ? 'No cases match this filter.'
                : 'No incoming cases are currently assigned to your account.';
        }
        if (detail) detail.classList.add('empty');
    }

    function updateDetails(id) {
        var item = cases.find(function (entry) { return String(entry.id) === String(id); });
        if (!item) {
            showEmptyDetails();
            return;
        }
        selectedCaseId = item.id;
        caseList.querySelectorAll('.prep-case').forEach(function (card) {
            var selected = card.dataset.caseId === String(item.id);
            card.classList.toggle('selected', selected);
            var button = card.querySelector('.case-select');
            if (button) button.setAttribute('aria-pressed', selected ? 'true' : 'false');
        });

        var detailContent = document.getElementById('detailContent');
        var emptyMessage = document.getElementById('detailEmptyMessage');
        var detail = document.getElementById('caseDetail');
        if (detailContent) detailContent.hidden = false;
        if (emptyMessage) emptyMessage.hidden = true;
        if (detail) detail.classList.remove('empty');

        var severity = document.getElementById('detailSeverity');
        if (severity) {
            severity.className = 'severity-label ' + item.severity.toLowerCase();
            severity.textContent = '';
            severity.appendChild(document.createElement('i'));
            severity.appendChild(document.createTextNode(item.severity));
        }
        document.getElementById('detailArrivalStatus').textContent = item.arrivalStatus || 'Awaiting transport';
        document.getElementById('detailArrival').textContent = item.arrival || 'Not available';
        document.getElementById('detailTitle').textContent = item.title;
        document.getElementById('detailPatient').textContent = item.patient;
        document.getElementById('detailLocation').textContent = item.location + ' · ' + item.hospital;
        document.getElementById('handoverSource').textContent = 'Sent from ' + item.responder;
        setMeasurement('detailHr', item.heartRate, 'bpm');
        setMeasurement('detailBp', item.bp, 'mmHg');
        setMeasurement('detailSpo2', item.spo2, '%');
        setMeasurement('detailConscious', item.consciousness, ' GCS');
        document.getElementById('detailNote').textContent = item.note || 'No responder handover notes recorded.';
        document.getElementById('detailNurseNote').textContent = item.nurseNote || 'No nurse notes recorded.';
        var completeButton = document.getElementById('togglePrepComplete');
        completeButton.textContent = item.prepCompleted ? 'Mark prep incomplete' : 'Mark prep complete';
        completeButton.classList.toggle('is-complete', item.prepCompleted);
        var feedback = document.getElementById('nurse-prep-feedback');
        if (feedback) feedback.textContent = item.prepCompleted && item.prepCompletedByName
            ? 'Preparation completed by ' + item.prepCompletedByName + '.'
            : '';
    }

    function openPrepModal(item) {
        if (!item || !prepModal) return;
        prepIncidentId.value = String(item.id);
        prepIncidentTitle.textContent = item.title + ' · ' + item.patient;
        document.getElementById('prepHeartRate').value = item.heartRate;
        document.getElementById('prepBloodPressure').value = item.bp;
        document.getElementById('prepSpo2').value = item.spo2;
        document.getElementById('prepGcs').value = item.consciousness;
        document.getElementById('prepNurseNote').value = item.nurseNote || '';
        updateBedOptions(item.bedId);
        document.getElementById('prepCompleted').checked = item.prepCompleted;
        modalFeedback.textContent = '';
        prepModal.classList.add('open');
        prepModal.setAttribute('aria-hidden', 'false');
        prepModal.removeAttribute('inert');
        document.getElementById('prepHeartRate').focus();
    }

    function closePrepModal() {
        if (!prepModal) return;
        prepModal.classList.remove('open');
        prepModal.setAttribute('aria-hidden', 'true');
        prepModal.setAttribute('inert', '');
        var selectedButton = caseList.querySelector('.prep-case.selected .case-select');
        if (selectedButton) selectedButton.focus();
    }

    function submitPreparation(formData, button) {
        if (button) button.disabled = true;
        return fetch(prepForm.action, {
            method: 'POST',
            body: formData,
            headers: { Accept: 'application/json' }
        }).then(function (response) {
            return response.json().then(function (result) {
                if (!response.ok || !result.success) {
                    throw new Error(result.message || 'Patient preparation could not be saved.');
                }
                return result;
            });
        }).then(function (result) {
            cases = result.cases;
            beds = result.beds || beds;
            window.nurseCases = cases;
            window.nurseBeds = beds;
            updateSummary(result.summary, undefined, result.freeBeds);
            renderCases();
            var feedback = document.getElementById('nurse-prep-feedback');
            if (feedback) feedback.textContent = result.message;
            closePrepModal();
        }).catch(function (error) {
            modalFeedback.textContent = error.message;
            var detailFeedback = document.getElementById('nurse-prep-feedback');
            if (detailFeedback) detailFeedback.textContent = error.message;
            console.error('[PulseAlert] Nurse patient preparation update failed.', error);
        }).finally(function () {
            if (button) button.disabled = false;
        });
    }

    caseList.addEventListener('click', function (event) {
        var button = event.target.closest('.case-select');
        if (!button) return;
        updateDetails(button.dataset.caseId);
    });

    caseButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            activeFilter = button.dataset.filter;
            currentPage = 1;
            caseButtons.forEach(function (item) {
                var selected = item === button;
                item.classList.toggle('active', selected);
                item.setAttribute('aria-selected', selected ? 'true' : 'false');
            });
            renderCases();
        });
    });

    if (previousButton) previousButton.addEventListener('click', function () {
        if (currentPage > 1) {
            currentPage -= 1;
            renderCases();
        }
    });
    if (nextButton) nextButton.addEventListener('click', function () {
        currentPage += 1;
        renderCases();
    });

    document.getElementById('editPatientPrep').addEventListener('click', function () {
        openPrepModal(cases.find(function (item) { return String(item.id) === String(selectedCaseId); }));
    });

    document.getElementById('togglePrepComplete').addEventListener('click', function () {
        var item = cases.find(function (entry) { return String(entry.id) === String(selectedCaseId); });
        if (!item) return;
        if (!item.prepCompleted && (
            !item.heartRate ||
            !item.bp ||
            !item.spo2 ||
            !item.consciousness ||
            !item.bedId
        )) {
            openPrepModal(item);
            document.getElementById('prepCompleted').checked = true;
            modalFeedback.textContent = 'Enter all vital signs and assign a bed before completing preparation.';
            return;
        }
        var data = new FormData();
        data.append('csrf_token', csrfToken);
        data.append('incident_id', String(item.id));
        data.append('heart_rate', item.heartRate);
        data.append('blood_pressure', item.bp);
        data.append('spo2', item.spo2);
        data.append('gcs', item.consciousness);
        data.append('nurse_note', item.nurseNote || '');
        data.append('bed_id', item.bedId || '');
        data.append('prep_completed', item.prepCompleted ? '0' : '1');
        var feedback = document.getElementById('nurse-prep-feedback');
        feedback.textContent = '';
        submitPreparation(data, this);
    });

    prepForm.addEventListener('submit', function (event) {
        event.preventDefault();
        modalFeedback.textContent = '';
        var data = new FormData(prepForm);
        data.set('csrf_token', csrfToken);
        data.set('prep_completed', document.getElementById('prepCompleted').checked ? '1' : '0');
        submitPreparation(data, savePrepButton);
    });

    document.querySelectorAll('[data-close-prep-modal]').forEach(function (button) {
        button.addEventListener('click', closePrepModal);
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && prepModal.classList.contains('open')) closePrepModal();
    });

    if (dutyForm && dutyValue && dutyButton) {
        dutyForm.addEventListener('submit', function (event) {
            event.preventDefault();
            dutyButton.disabled = true;
            dutyButton.setAttribute('aria-busy', 'true');
            dutyFeedback.textContent = '';
            fetch(dutyForm.action, {
                method: 'POST',
                body: new FormData(dutyForm),
                headers: { Accept: 'application/json' }
            }).then(function (response) {
                return response.json().then(function (result) {
                    if (!response.ok || !result.success) {
                        throw new Error(result.message || 'Duty status could not be updated.');
                    }
                    return result;
                });
            }).then(function (result) {
                dutyValue.value = result.available ? 'off' : 'on';
                dutyButton.textContent = result.available ? 'On Duty' : 'Off Duty';
                dutyButton.classList.toggle('is-on-duty', result.available);
                dutyButton.classList.toggle('is-off-duty', !result.available);
                dutyButton.setAttribute('aria-pressed', result.available ? 'true' : 'false');
                dutyFeedback.textContent = result.message;
            }).catch(function (error) {
                dutyFeedback.textContent = error.message;
                console.error('[PulseAlert] Nurse duty status update failed.', error);
            }).finally(function () {
                dutyButton.disabled = false;
                dutyButton.removeAttribute('aria-busy');
            });
        });
    }

    function refreshCases() {
        if (refreshing || document.visibilityState !== 'visible') return;
        refreshing = true;
        fetch(endpoint + '?action=refresh', {
            headers: { Accept: 'application/json' },
            cache: 'no-store'
        }).then(function (response) {
            return response.json().then(function (result) {
                if (!response.ok || !result.success) {
                    throw new Error(result.message || 'Notified cases could not be refreshed.');
                }
                return result;
            });
        }).then(function (result) {
            cases = result.cases;
            beds = result.beds || beds;
            window.nurseCases = cases;
            window.nurseBeds = beds;
            updateSummary(result.summary, result.nextArrival, result.freeBeds);
            var refreshFeedback = document.getElementById('nurse-refresh-feedback');
            if (refreshFeedback) refreshFeedback.textContent = '';
            if (result.nurse && dutyButton && dutyValue) {
                var isAvailable = Boolean(result.nurse.is_available);
                dutyValue.value = isAvailable ? 'off' : 'on';
                dutyButton.textContent = isAvailable ? 'On Duty' : 'Off Duty';
                dutyButton.classList.toggle('is-on-duty', isAvailable);
                dutyButton.classList.toggle('is-off-duty', !isAvailable);
                dutyButton.setAttribute('aria-pressed', isAvailable ? 'true' : 'false');
            }
            renderCases();
        }).catch(function (error) {
            var feedback = document.getElementById('nurse-refresh-feedback');
            if (feedback) feedback.textContent = error.message;
            console.error('[PulseAlert] Nurse notified cases refresh failed.', error);
        }).finally(function () {
            refreshing = false;
        });
    }

    renderCases();
    refreshCases();
    window.setInterval(refreshCases, 15000);
}

function initNurseBedStatus() {
    var page = document.getElementById('nurse-bed-status-page');
    var form = document.getElementById('bedStatusForm');
    var modal = document.getElementById('bedStatusModal');
    if (!page || !form || !modal) return;

    var state = window.nurseBedStatus || {};
    var csrfToken = window.nurseBedStatusCsrf || '';
    var statusInput = document.getElementById('bedStatusValue');
    var bedIdInput = document.getElementById('bedStatusBedId');
    var incidentAssignment = document.getElementById('bedIncidentAssignment');
    var incidentInput = document.getElementById('bedStatusIncident');
    var bedLabel = document.getElementById('bedStatusBedLabel');
    var modalFeedback = document.getElementById('bedStatusModalFeedback');
    var pageFeedback = document.getElementById('bedStatusFeedback');
    var saveButton = document.getElementById('bedStatusSave');
    var activeFilter = 'all';
    var previousTrigger = null;
    var bedIcon = '<svg viewBox="0 0 32 32" fill="none" aria-hidden="true"><path d="M6 24V10M6 20H27V25M9 20V14H27C28.1 14 29 14.9 29 16V20M10 14V10H15C17.2 10 19 11.8 19 14" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>';

    function createOption(label, value) {
        var option = document.createElement('option');
        option.value = value;
        option.textContent = label;
        return option;
    }

    function statusLabel(status) {
        return status.charAt(0).toUpperCase() + status.slice(1);
    }

    function updateIncidentOptions(bedId, selectedIncidentId) {
        if (!incidentInput) return;
        var eligibleIncidents = (state.incidentOptions || []).filter(function (incident) {
            return incident.bedId === null || String(incident.bedId) === String(bedId);
        });
        incidentInput.replaceChildren(createOption(
            eligibleIncidents.length ? 'Choose an incident' : 'No active incidents available',
            ''
        ));
        eligibleIncidents.forEach(function (incident) {
            var label = incident.title + ' — ' + incident.patient + ' (' + statusLabel(incident.status) + ')';
            incidentInput.appendChild(createOption(label, String(incident.id)));
        });
        incidentInput.value = selectedIncidentId ? String(selectedIncidentId) : '';
    }

    function updateIncidentAssignment() {
        var isOccupied = statusInput.value === 'occupied';
        if (incidentAssignment) incidentAssignment.hidden = !isOccupied;
        if (incidentInput) {
            incidentInput.required = isOccupied;
            if (!isOccupied) incidentInput.value = '';
        }
    }

    function renderSummary() {
        document.querySelectorAll('.bed-stat').forEach(function (button) {
            var status = button.dataset.bedFilter;
            var count = status === 'all'
                ? Object.keys(state.counts || {}).reduce(function (total, key) {
                    return total + (Number(state.counts[key]) || 0);
                }, 0)
                : Number((state.counts || {})[status]) || 0;
            var value = button.querySelector('strong');
            if (value) value.textContent = String(count);
        });
    }

    function makeBedCard(bed) {
        var typeLabel = bed.type === 'er' ? 'ER' : 'Pediatric';
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'bed-card ' + bed.status;
        button.dataset.bedId = String(bed.id);
        button.dataset.bedType = bed.type;
        button.dataset.bedNumber = String(bed.number);
        button.dataset.bedStatus = bed.status;
        button.dataset.incidentId = bed.incidentId ? String(bed.incidentId) : '';
        button.setAttribute('aria-label', typeLabel + ' Bed ' + bed.number + ', ' + statusLabel(bed.status) + '. Edit status.');
        button.insertAdjacentHTML('afterbegin', bedIcon);
        button.querySelector('svg').className = 'bed-icon-svg';
        var name = document.createElement('strong');
        name.textContent = 'Bed ' + bed.number;
        var status = document.createElement('small');
        status.textContent = statusLabel(bed.status).toUpperCase();
        button.appendChild(name);
        button.appendChild(status);
        button.hidden = activeFilter !== 'all' && activeFilter !== bed.status;
        return button;
    }

    function renderBeds() {
        [
            { id: 'erBedGrid', beds: state.erBeds || [] },
            { id: 'pediatricBedGrid', beds: state.pediatricBeds || [] }
        ].forEach(function (gridData) {
            var grid = document.getElementById(gridData.id);
            if (!grid) return;
            grid.replaceChildren();
            gridData.beds.forEach(function (bed) {
                grid.appendChild(makeBedCard(bed));
            });
            var section = grid.closest('.bed-section');
            if (section) {
                section.hidden = activeFilter !== 'all' &&
                    !gridData.beds.some(function (bed) { return bed.status === activeFilter; });
            }
        });
    }

    function openModal(button) {
        previousTrigger = button;
        bedIdInput.value = button.dataset.bedId;
        statusInput.value = button.dataset.bedStatus;
        bedLabel.textContent = (button.dataset.bedType === 'er' ? 'ER' : 'Pediatric') +
            ' Bed ' + button.dataset.bedNumber;
        updateIncidentOptions(button.dataset.bedId, button.dataset.incidentId);
        updateIncidentAssignment();
        modalFeedback.textContent = '';
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        modal.removeAttribute('inert');
        statusInput.focus();
    }

    function closeModal() {
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
        modal.setAttribute('inert', '');
        if (previousTrigger && document.contains(previousTrigger)) previousTrigger.focus();
    }

    page.addEventListener('click', function (event) {
        var bed = event.target.closest('.bed-card');
        if (bed) {
            openModal(bed);
            return;
        }
        var filterButton = event.target.closest('.bed-stat');
        if (!filterButton) return;
        activeFilter = activeFilter === filterButton.dataset.bedFilter
            ? 'all'
            : filterButton.dataset.bedFilter;
        document.querySelectorAll('.bed-stat').forEach(function (button) {
            var selected = activeFilter === button.dataset.bedFilter;
            button.classList.toggle('active', selected);
            button.setAttribute('aria-pressed', selected ? 'true' : 'false');
        });
        renderBeds();
    });

    document.querySelectorAll('[data-close-bed-modal]').forEach(function (button) {
        button.addEventListener('click', closeModal);
    });
    statusInput.addEventListener('change', updateIncidentAssignment);
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal.classList.contains('open')) closeModal();
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        modalFeedback.textContent = '';
        pageFeedback.textContent = '';
        saveButton.disabled = true;
        var payload = new FormData(form);
        payload.set('csrf_token', csrfToken);
        fetch(form.action, {
            method: 'POST',
            body: payload,
            headers: { Accept: 'application/json' }
        }).then(function (response) {
            return response.json().then(function (result) {
                if (!response.ok || !result.success) {
                    throw new Error(result.message || 'Bed status could not be updated.');
                }
                return result;
            });
        }).then(function (result) {
            state = result;
            state.incidentOptions = result.incidentOptions || [];
            window.nurseBedStatus = state;
            renderSummary();
            renderBeds();
            closeModal();
            pageFeedback.textContent = result.message;
        }).catch(function (error) {
            modalFeedback.textContent = error.message;
            console.error('[PulseAlert] Nurse bed status update failed.', error);
        }).finally(function () {
            saveButton.disabled = false;
        });
    });

    renderSummary();
    renderBeds();
}

function initDoctorCaseHistory() {
    var searchForm = document.getElementById('doctor-history-search');
    var searchInput = document.getElementById('doctor-history-query');
    var tableBody = document.querySelector('.history-table tbody');
    if (!searchForm || !searchInput || !tableBody) return;

    var rows = Array.prototype.slice.call(tableBody.querySelectorAll('tr[data-incident-id]'));
    var emptyState = document.getElementById('doctor-history-empty');
    var pagination = document.getElementById('doctor-history-pagination');
    var previousButton = document.getElementById('doctor-history-previous');
    var nextButton = document.getElementById('doctor-history-next');
    var pageStatus = document.getElementById('doctor-history-page-status');
    var feedback = document.getElementById('history-status-feedback');
    var resolvedCount = document.querySelector('.history-stats > div:nth-child(3) strong');
    var resolvedRate = document.querySelector('.history-stats > div:nth-child(3) small');
    var currentPage = 1;
    var pageSize = 6;
    var historyRefreshInProgress = false;

    function matchingRows() {
        var query = searchInput.value.trim().toLocaleLowerCase();
        return rows.filter(function (row) {
            var searchableText = Array.prototype.filter.call(row.cells, function (_, index) {
                return index !== 3;
            }).map(function (cell) {
                return cell.textContent;
            }).join(' ').toLocaleLowerCase();
            return !query || searchableText.includes(query);
        });
    }

    function renderRows() {
        var matches = matchingRows();
        var pageCount = Math.max(1, Math.ceil(matches.length / pageSize));
        currentPage = Math.min(currentPage, pageCount);
        var firstIndex = (currentPage - 1) * pageSize;

        rows.forEach(function (row) {
            var rowIndex = matches.indexOf(row);
            row.hidden = rowIndex < firstIndex || rowIndex >= firstIndex + pageSize;
        });
        if (emptyState) {
            emptyState.hidden = matches.length > 0;
            emptyState.textContent = rows.length
                ? 'No incidents match your search.'
                : 'No cases were sent to your account for this period.';
        }
        if (pagination) pagination.hidden = matches.length === 0;
        if (pageStatus) pageStatus.textContent = 'Page ' + currentPage + ' of ' + pageCount;
        if (previousButton) previousButton.disabled = currentPage <= 1;
        if (nextButton) nextButton.disabled = currentPage >= pageCount;
    }

    function refreshHistory() {
        if (
            !window.doctorHistoryRefreshEndpoint ||
            historyRefreshInProgress ||
            tableBody.contains(document.activeElement)
        ) return;

        historyRefreshInProgress = true;
        fetch(window.doctorHistoryRefreshEndpoint, {
            headers: { Accept: 'application/json' },
            cache: 'no-store'
        }).then(function (response) {
            return response.json().then(function (result) {
                if (!response.ok || !result.success) {
                    throw new Error(result.message || 'Case history could not be refreshed.');
                }
                return result;
            });
        }).then(function (result) {
            var fragment = document.createDocumentFragment();
            result.cases.forEach(function (item) {
                var row = document.createElement('tr');
                row.dataset.incidentId = String(item.id);

                [item.date, item.case, item.patient].forEach(function (value) {
                    var cell = document.createElement('td');
                    cell.textContent = value;
                    row.appendChild(cell);
                });

                var statusCell = document.createElement('td');
                var statusSelect = document.createElement('select');
                statusSelect.className = 'history-status-select status-' + item.status;
                statusSelect.dataset.incidentId = String(item.id);
                statusSelect.setAttribute('aria-label', 'Change status for ' + item.case);

                if (item.status === 'urgent' || item.status === 'dispatched') {
                    var currentOption = document.createElement('option');
                    currentOption.value = item.status;
                    currentOption.textContent = item.status.charAt(0).toUpperCase() + item.status.slice(1) + ' (current)';
                    currentOption.disabled = true;
                    currentOption.selected = true;
                    statusSelect.appendChild(currentOption);
                }

                [
                    ['critical', 'Critical'],
                    ['stable', 'Stable'],
                    ['resolved', 'Resolved']
                ].forEach(function (optionData) {
                    var option = document.createElement('option');
                    option.value = optionData[0];
                    option.textContent = optionData[1];
                    option.selected = item.status === optionData[0];
                    statusSelect.appendChild(option);
                });
                statusSelect.dataset.savedStatus = item.status;
                statusCell.appendChild(statusSelect);
                row.appendChild(statusCell);

                var outcomeCell = document.createElement('td');
                var outcome = document.createElement('span');
                outcome.className = 'history-outcome status-' + item.status;
                outcome.textContent = item.status.charAt(0).toUpperCase() + item.status.slice(1);
                outcomeCell.appendChild(outcome);
                row.appendChild(outcomeCell);
                fragment.appendChild(row);
            });

            tableBody.replaceChildren(fragment);
            rows = Array.prototype.slice.call(tableBody.querySelectorAll('tr[data-incident-id]'));

            if (result.stats) {
                var statValues = document.querySelectorAll('.history-stats > div strong');
                if (statValues[0]) statValues[0].textContent = String(result.stats.received);
                if (statValues[1]) statValues[1].textContent = String(result.stats.totalNotified);
                if (statValues[2]) statValues[2].textContent = String(result.stats.resolved);
                if (resolvedRate) {
                    resolvedRate.textContent = result.stats.resolvedPercent + ' of cases in this date range';
                }
            }

            renderRows();
        }).catch(function (error) {
            console.error('[PulseAlert] Doctor case-history refresh failed.', error);
        }).finally(function () {
            historyRefreshInProgress = false;
        });
    }

    searchForm.addEventListener('submit', function (event) {
        event.preventDefault();
        currentPage = 1;
        renderRows();
    });
    searchInput.addEventListener('input', function () {
        currentPage = 1;
        renderRows();
    });
    if (previousButton) {
        previousButton.addEventListener('click', function () {
            if (currentPage > 1) {
                currentPage -= 1;
                renderRows();
            }
        });
    }
    if (nextButton) {
        nextButton.addEventListener('click', function () {
            currentPage += 1;
            renderRows();
        });
    }

    tableBody.addEventListener('change', function (event) {
        var select = event.target.closest('.history-status-select');
        if (!select) return;

        var previousStatus = select.dataset.savedStatus || select.value;
        var requestedStatus = select.value;
        var payload = new FormData();
        payload.append('id', select.dataset.incidentId || '');
        payload.append('status', requestedStatus);
        payload.append('csrf_token', window.doctorHistoryCsrfToken || '');
        select.disabled = true;
        if (feedback) feedback.textContent = '';

        fetch(window.doctorHistoryEndpoint, {
            method: 'POST',
            body: payload,
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        }).then(function (response) {
            return response.json().then(function (result) {
                if (!response.ok || !result.success) {
                    throw new Error(result.message || 'Could not save the selected status.');
                }
                return result;
            });
        }).then(function (result) {
            var row = select.closest('tr');
            var outcome = row ? row.querySelector('.history-outcome') : null;
            if (outcome) {
                outcome.classList.remove(
                    'status-critical',
                    'status-dispatched',
                    'status-urgent',
                    'status-stable',
                    'status-resolved'
                );
                outcome.classList.add('status-' + result.status);
                outcome.textContent = result.status.charAt(0).toUpperCase() + result.status.slice(1);
            }
            select.classList.remove(
                'status-critical',
                'status-dispatched',
                'status-urgent',
                'status-stable',
                'status-resolved'
            );
            select.classList.add('status-' + result.status);
            select.dataset.savedStatus = result.status;

            if (resolvedCount && previousStatus !== result.status) {
                var resolved = Number(resolvedCount.textContent) || 0;
                if (previousStatus === 'resolved') resolved -= 1;
                if (result.status === 'resolved') resolved += 1;
                resolvedCount.textContent = String(resolved);

                var received = Number(document.querySelector('.history-stats > div:first-child strong').textContent) || 0;
                if (resolvedRate) {
                    resolvedRate.textContent = (received > 0 ? Math.round((resolved / received) * 100) : 0) +
                        '% of cases in this date range';
                }
            }
        }).catch(function (error) {
            select.value = previousStatus;
            if (feedback) feedback.textContent = error.message;
            console.error('[PulseAlert] Doctor case-history status update failed.', error);
        }).finally(function () {
            select.disabled = false;
        });
    });

    rows.forEach(function (row) {
        var select = row.querySelector('.history-status-select');
        if (select) select.dataset.savedStatus = select.value;
    });
    renderRows();
    refreshHistory();
    window.setInterval(function () {
        if (document.visibilityState === 'visible') refreshHistory();
    }, 5000);
}

document.addEventListener('DOMContentLoaded', function () {
    try {
        initDoctorNurseModulePages();
    } catch (error) {
        console.error('[PulseAlert] Doctor/nurse page initialization failed.', error);
    }
    try {
        initNurseNotifiedCases();
    } catch (error) {
        console.error('[PulseAlert] Nurse notified-cases initialization failed.', error);
    }
    try {
        initNurseBedStatus();
    } catch (error) {
        console.error('[PulseAlert] Nurse bed-status initialization failed.', error);
    }
    try {
        initDoctorCaseHistory();
    } catch (error) {
        console.error('[PulseAlert] Doctor case-history initialization failed.', error);
    }
});

document.addEventListener('DOMContentLoaded', function () {
    var backdrop = document.getElementById('staff-alert-backdrop');
    var closeButton = document.getElementById('staff-alert-close');
    var errorMessage = document.getElementById('staff-alert-error');
    if (!backdrop || !closeButton || !window.staffIncomingAlertsEndpoint) return;

    var currentAlert = null;
    var checking = false;

    function setText(id, value) {
        var element = document.getElementById(id);
        if (element) element.textContent = value || 'Not provided';
    }

    function displayAlert(alert) {
        currentAlert = alert;
        setText('staff-alert-incident', alert.incident);
        setText('staff-alert-status', alert.status);
        setText('staff-alert-patient', alert.patient);
        setText('staff-alert-location', alert.location);
        setText('staff-alert-hospital', alert.hospital);
        setText('staff-alert-reported', alert.reportedAt);
        var details = document.getElementById('staff-alert-message');
        if (details) {
            details.textContent = alert.message || 'Review the incoming incident details.';
        }
        errorMessage.hidden = true;
        backdrop.hidden = false;
        closeButton.focus();
    }

    function poll() {
        if (checking || currentAlert || document.visibilityState !== 'visible') return;
        checking = true;
        fetch(window.staffIncomingAlertsEndpoint + '&action=pending', {
            headers: { Accept: 'application/json' },
            cache: 'no-store'
        }).then(function (response) {
            return response.json().then(function (result) {
                if (!response.ok || !result.success) {
                    throw new Error(result.message || 'Incoming alerts could not be loaded.');
                }
                return result;
            });
        }).then(function (result) {
            if (Array.isArray(result.alerts) && result.alerts.length) {
                displayAlert(result.alerts[0]);
            }
        }).catch(function (error) {
            console.error('[PulseAlert] Staff incoming alerts could not be loaded.', error);
        }).finally(function () {
            checking = false;
        });
    }

    closeButton.addEventListener('click', function () {
        if (!currentAlert) return;
        closeButton.disabled = true;
        errorMessage.hidden = true;
        var payload = new FormData();
        payload.append('alert_id', String(currentAlert.alertId));
        payload.append('csrf_token', window.staffIncomingAlertsCsrfToken || '');
        fetch(window.staffIncomingAlertsEndpoint + '&action=acknowledge', {
            method: 'POST',
            body: payload,
            headers: { Accept: 'application/json' }
        }).then(function (response) {
            return response.json().then(function (result) {
                if (!response.ok || !result.success) {
                    throw new Error(result.message || 'The notification could not be acknowledged.');
                }
            });
        }).then(function () {
            currentAlert = null;
            backdrop.hidden = true;
            poll();
        }).catch(function (error) {
            errorMessage.textContent = error.message;
            errorMessage.hidden = false;
            console.error('[PulseAlert] Staff incoming alert acknowledgement failed.', error);
        }).finally(function () {
            closeButton.disabled = false;
        });
    });

    poll();
    window.setInterval(poll, 5000);
});
// Settings profile, password, and hospital-location interactions.
document.addEventListener('DOMContentLoaded', function () {
    var settingsPage = document.getElementById('settings-card');
    if (!settingsPage) return;

    var toast = document.getElementById('settings-toast');
    var photoDrawer = document.getElementById('settings-photo-drawer');
    var passwordDrawer = document.getElementById('settings-password-drawer');
    var locationDrawer = document.getElementById('settings-location-drawer');
    var csrfField = document.querySelector('#settings-form [name="csrf_token"]');
    var settingsEndpoint = new URL(
        settingsPage.dataset.endpoint ||
            ((window.APP_BASE_URL || '') + '/app/controllers/settingsController.php'),
        window.location.origin
    ).toString();
    var avatarButton = document.getElementById('settings-profile-avatar');
    var photoInput = document.getElementById('settings-profile-image-input');
    var choosePhotoButton = document.getElementById('settings-choose-photo');
    var uploadPhotoButton = document.getElementById('settings-upload-photo');
    var previewImage = document.getElementById('settings-photo-preview-image');
    var previewInitials = document.getElementById('settings-photo-preview-initials');
    var passwordButton = document.getElementById('settings-change-password');
    var passwordForm = document.getElementById('settings-password-form');
    var openLocationButton = document.getElementById('settings-open-location');
    var confirmLocationButton = document.getElementById('settings-confirm-location');
    var useLocationButton = document.getElementById('settings-use-location');
    var latitudeInput = document.getElementById('settings-hospital-latitude');
    var longitudeInput = document.getElementById('settings-hospital-longitude');
    var selectedPhoto = null;
    var previewObjectUrl = null;
    var savedProfileImageUrl = avatarButton && avatarButton.querySelector('img')
        ? avatarButton.querySelector('img').src
        : null;
    var activeDrawer = null;
    var lastFocusedElement = null;
    var locationMap = null;
    var locationMarker = null;
    var pendingCoordinates = null;

    function showToast(message, kind) {
        if (!toast) return;
        toast.textContent = message;
        toast.classList.remove('is-success', 'is-error');
        toast.classList.add(kind === 'error' ? 'is-error' : 'is-success', 'is-visible');
        window.clearTimeout(window.settingsToastTimer);
        window.settingsToastTimer = window.setTimeout(function () {
            toast.classList.remove('is-visible');
        }, 3600);
    }

    if (toast && toast.dataset.message) {
        showToast(toast.dataset.message, toast.dataset.kind || 'success');
    }

    function resetPhotoPreview() {
        selectedPhoto = null;
        if (photoInput) photoInput.value = '';
        if (previewObjectUrl) {
            URL.revokeObjectURL(previewObjectUrl);
            previewObjectUrl = null;
        }
        if (!previewImage) return;
        if (savedProfileImageUrl) {
            previewImage.src = savedProfileImageUrl;
            previewImage.hidden = false;
            if (previewInitials) previewInitials.hidden = true;
        } else {
            previewImage.removeAttribute('src');
            previewImage.hidden = true;
            if (previewInitials) previewInitials.hidden = false;
        }
        if (uploadPhotoButton) uploadPhotoButton.disabled = true;
    }

    function openDrawer(drawer) {
        if (!drawer) return;
        lastFocusedElement = document.activeElement;
        activeDrawer = drawer;
        drawer.classList.add('settings-drawer-overlay--open');
        drawer.setAttribute('aria-hidden', 'false');
        document.body.classList.add('settings-drawer-open');
        var focusTarget = drawer.querySelector('input:not([type="hidden"]), button:not(.settings-drawer-close)');
        if (focusTarget) focusTarget.focus();
        if (drawer === locationDrawer) {
            window.setTimeout(function () {
                if (locationMap) locationMap.invalidateSize();
            }, 180);
        }
    }

    function closeDrawer(drawer) {
        if (!drawer) return;
        drawer.classList.remove('settings-drawer-overlay--open');
        drawer.setAttribute('aria-hidden', 'true');
        if (drawer === photoDrawer) resetPhotoPreview();
        activeDrawer = null;
        document.body.classList.remove('settings-drawer-open');
        if (lastFocusedElement && typeof lastFocusedElement.focus === 'function') {
            lastFocusedElement.focus();
        }
    }

    document.querySelectorAll('.settings-drawer-overlay').forEach(function (drawer) {
        drawer.addEventListener('click', function (event) {
            if (event.target === drawer || event.target.closest('[data-settings-close]')) {
                closeDrawer(drawer);
            }
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && activeDrawer) closeDrawer(activeDrawer);
    });

    function postSettingsAction(formData, submitButton) {
        if (submitButton) submitButton.disabled = true;
        return fetch(settingsEndpoint, {
            method: 'POST',
            body: formData,
            headers: { 'Accept': 'application/json' }
        }).then(function (response) {
            return response.json().then(function (result) {
                if (!response.ok || !result.success) {
                    throw new Error(result.message || 'The request could not be completed.');
                }
                return result;
            });
        }).finally(function () {
            if (submitButton) submitButton.disabled = false;
        });
    }

    if (avatarButton) {
        avatarButton.addEventListener('click', function () {
            resetPhotoPreview();
            openDrawer(photoDrawer);
        });
    }
    if (choosePhotoButton && photoInput) {
        choosePhotoButton.addEventListener('click', function () { photoInput.click(); });
    }
    if (photoInput) {
        photoInput.addEventListener('change', function () {
            selectedPhoto = this.files && this.files[0] ? this.files[0] : null;
            if (!selectedPhoto) return;
            if (!/^image\/(jpeg|png|webp)$/.test(selectedPhoto.type) || selectedPhoto.size > 5 * 1024 * 1024) {
                resetPhotoPreview();
                showToast('Choose a JPG, PNG, or WebP image smaller than 5 MB.', 'error');
                return;
            }
            if (previewObjectUrl) URL.revokeObjectURL(previewObjectUrl);
            previewObjectUrl = URL.createObjectURL(selectedPhoto);
            previewImage.src = previewObjectUrl;
            previewImage.hidden = false;
            if (previewInitials) previewInitials.hidden = true;
            uploadPhotoButton.disabled = false;
        });
    }
    if (uploadPhotoButton) {
        uploadPhotoButton.addEventListener('click', function () {
            if (!selectedPhoto || !csrfField) return;
            var formData = new FormData();
            formData.append('action', 'upload_avatar');
            formData.append('csrf_token', csrfField.value);
            formData.append('profile_image', selectedPhoto);
            var button = this;
            postSettingsAction(formData, button).then(function (result) {
                savedProfileImageUrl = result.imageUrl;
                document.querySelectorAll('.incomingdispatch-user-avatar, .emergency_alert_avatar, #settings-profile-avatar, .doctor-profile-avatar').forEach(function (avatar) {
                    avatar.querySelectorAll('img').forEach(function (image) { image.remove(); });
                    Array.prototype.forEach.call(avatar.childNodes, function (node) {
                        if (node.nodeType === Node.TEXT_NODE) node.remove();
                    });
                    var image = document.createElement('img');
                    image.src = result.imageUrl;
                    image.alt = '';
                    avatar.appendChild(image);
                    if (avatar.id === 'settings-profile-avatar' && !avatar.querySelector('.avatar-camera')) {
                        var camera = document.createElement('span');
                        camera.className = 'avatar-camera';
                        camera.setAttribute('aria-hidden', 'true');
                        camera.textContent = '+';
                        avatar.appendChild(camera);
                    }
                });
                showToast(result.message, 'success');
                closeDrawer(photoDrawer);
            }).catch(function (error) {
                showToast(error.message, 'error');
            });
        });
    }

    if (passwordButton) {
        passwordButton.addEventListener('click', function () { openDrawer(passwordDrawer); });
    }

    var settingsForm = document.getElementById('settings-form');
    if (settingsForm) {
        settingsForm.addEventListener('submit', function (event) {
            event.preventDefault();
            if (!settingsForm.reportValidity()) return;
            var submitButton = settingsForm.querySelector('button[type="submit"]');
            postSettingsAction(new FormData(settingsForm), submitButton).then(function (result) {
                showToast(result.message, 'success');
            }).catch(function (error) {
                showToast(error.message, 'error');
            });
        });
    }

    if (passwordForm) {
        passwordForm.addEventListener('submit', function (event) {
            event.preventDefault();
            if (!passwordForm.reportValidity()) return;
            if (passwordForm.elements.new_password.value !== passwordForm.elements.confirm_password.value) {
                passwordForm.elements.confirm_password.setCustomValidity('Passwords do not match.');
                passwordForm.elements.confirm_password.reportValidity();
                return;
            }
            passwordForm.elements.confirm_password.setCustomValidity('');
            var button = passwordForm.querySelector('button[type="submit"]');
            postSettingsAction(new FormData(passwordForm), button).then(function (result) {
                passwordForm.reset();
                showToast(result.message, 'success');
                closeDrawer(passwordDrawer);
            }).catch(function (error) {
                showToast(error.message, 'error');
            });
        });
        passwordForm.elements.confirm_password.addEventListener('input', function () {
            this.setCustomValidity('');
        });
    }

    function setMapLocation(latitude, longitude) {
        pendingCoordinates = { latitude: latitude, longitude: longitude };
        if (!locationMap || !window.L) return;
        var point = [latitude, longitude];
        if (!locationMarker) {
            locationMarker = window.L.marker(point, { draggable: true }).addTo(locationMap);
            locationMarker.on('dragend', function (event) {
                var position = event.target.getLatLng();
                pendingCoordinates = { latitude: position.lat, longitude: position.lng };
            });
        } else {
            locationMarker.setLatLng(point);
        }
        locationMap.setView(point, 15);
    }

    if (openLocationButton) {
        openLocationButton.addEventListener('click', function () {
            openDrawer(locationDrawer);
            var savedLatitude = Number(latitudeInput.value);
            var savedLongitude = Number(longitudeInput.value);
            var hasSavedCoordinates = latitudeInput.value !== '' && longitudeInput.value !== '' &&
                Number.isFinite(savedLatitude) && Number.isFinite(savedLongitude);
            pendingCoordinates = hasSavedCoordinates
                ? { latitude: savedLatitude, longitude: savedLongitude }
                : null;

            if (!locationMap && window.L) {
                var center = hasSavedCoordinates ? [savedLatitude, savedLongitude] : [10.5, 122.8];
                locationMap = window.L.map('settings-location-map').setView(center, hasSavedCoordinates ? 14 : 8);
                window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap contributors'
                }).addTo(locationMap);
                locationMap.on('click', function (event) {
                    setMapLocation(event.latlng.lat, event.latlng.lng);
                });
            }
            if (locationMap && hasSavedCoordinates) {
                setMapLocation(savedLatitude, savedLongitude);
            } else if (locationMarker) {
                locationMap.removeLayer(locationMarker);
                locationMarker = null;
            }
        });
    }
    if (useLocationButton) {
        useLocationButton.addEventListener('click', function () {
            if (!navigator.geolocation) {
                showToast('This browser does not support location.', 'error');
                return;
            }
            useLocationButton.disabled = true;
            navigator.geolocation.getCurrentPosition(function (position) {
                setMapLocation(position.coords.latitude, position.coords.longitude);
                useLocationButton.disabled = false;
            }, function () {
                useLocationButton.disabled = false;
                showToast('Location is unavailable. Allow access or pin the map manually.', 'error');
            }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 });
        });
    }
    if (confirmLocationButton) {
        confirmLocationButton.addEventListener('click', function () {
            if (!pendingCoordinates) {
                showToast('Click the map or locate this device to set a pin.', 'error');
                return;
            }
            latitudeInput.value = pendingCoordinates.latitude.toFixed(8);
            longitudeInput.value = pendingCoordinates.longitude.toFixed(8);
            closeDrawer(locationDrawer);
            showToast('Location pinned. Save changes to update the hospital profile.', 'success');
        });
    }
});

(function () {
    'use strict';

    function initNurseSchedule() {
        var modal = document.getElementById('nurseScheduleModal');
        var form = document.getElementById('nurseScheduleForm');
        if (!modal || !form) return;

        var deleteModal = document.getElementById('nurseDeleteScheduleModal');
        var confirmDelete = document.getElementById('nurseConfirmDeleteSchedule');
        var pendingDeleteForm = null;
        var lastDeleteTrigger = null;
        var action = document.getElementById('nurseScheduleAction');
        var scheduleId = document.getElementById('nurseScheduleId');
        var scheduleDate = document.getElementById('nurseScheduleDate');
        var scheduleType = document.getElementById('nurseScheduleType');
        var scheduleStart = document.getElementById('nurseScheduleStart');
        var scheduleEnd = document.getElementById('nurseScheduleEnd');
        var modalTitle = document.getElementById('nurseScheduleModalTitle');
        var submitButton = document.getElementById('nurseScheduleSubmit');
        var timeFields = form.querySelector('.nurse-schedule-time-fields');
        var addScheduleMode = document.getElementById('nurseAddScheduleMode');
        var calendarHint = document.getElementById('nurseCalendarHint');
        var selectedDateLabel = document.getElementById('nurseScheduleSelectedDate');
        var dateButtons = document.querySelectorAll('.nurse-day-select');

        function setScheduleTimes() {
            var noTime = scheduleType.value === 'oncall' || scheduleType.value === 'leave';
            timeFields.hidden = noTime;
            scheduleStart.required = !noTime;
            scheduleEnd.required = !noTime;
            if (noTime) {
                scheduleStart.value = '';
                scheduleEnd.value = '';
                return;
            }
            if (!scheduleStart.value || !scheduleEnd.value) {
                var evening = scheduleType.value === 'evening';
                scheduleStart.value = evening ? '15:00' : '07:00';
                scheduleEnd.value = evening ? '23:00' : '15:00';
            }
        }

        function setAddMode(enabled) {
            addScheduleMode.setAttribute('aria-pressed', enabled ? 'true' : 'false');
            addScheduleMode.classList.toggle('is-selecting-date', enabled);
            dateButtons.forEach(function (button) {
                button.disabled = !enabled || button.dataset.hasShift === 'true';
            });
            if (calendarHint) {
                calendarHint.textContent = enabled
                    ? 'Choose an empty day to add a schedule. Select the button again to cancel.'
                    : 'Select “Add schedule”, then choose a day.';
            }
        }

        function openSchedule(data) {
            var editing = data.action === 'update';
            action.value = editing ? 'update' : 'add';
            scheduleId.value = editing ? data.id : '';
            scheduleDate.value = data.date;
            modalTitle.textContent = editing ? 'Edit schedule' : 'Add schedule';
            submitButton.textContent = editing ? 'Save changes' : 'Add schedule';
            scheduleType.value = data.type || 'morning';
            scheduleStart.value = data.start || (scheduleType.value === 'evening' ? '15:00' : '07:00');
            scheduleEnd.value = data.end || (scheduleType.value === 'evening' ? '23:00' : '15:00');
            selectedDateLabel.textContent = new Date(data.date + 'T00:00:00').toLocaleDateString(undefined, {
                weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
            });
            setScheduleTimes();
            modal.classList.add('open');
            modal.setAttribute('aria-hidden', 'false');
            modal.removeAttribute('inert');
            scheduleType.focus();
        }

        function closeSchedule() {
            modal.classList.remove('open');
            modal.setAttribute('aria-hidden', 'true');
            modal.setAttribute('inert', '');
            if (addScheduleMode.getAttribute('aria-pressed') === 'true') setAddMode(false);
        }

        function closeDeleteModal() {
            if (!deleteModal) return;
            deleteModal.classList.remove('open');
            deleteModal.setAttribute('aria-hidden', 'true');
            deleteModal.setAttribute('inert', '');
            pendingDeleteForm = null;
            if (lastDeleteTrigger && document.contains(lastDeleteTrigger)) lastDeleteTrigger.focus();
        }

        addScheduleMode.addEventListener('click', function () {
            setAddMode(addScheduleMode.getAttribute('aria-pressed') !== 'true');
        });
        dateButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                if (!button.disabled) openSchedule({ action: 'add', date: button.dataset.date });
            });
        });
        document.querySelectorAll('[data-nurse-open-schedule="edit"]').forEach(function (button) {
            button.addEventListener('click', function () {
                openSchedule({
                    action: 'update', id: button.dataset.id, date: button.dataset.date,
                    type: button.dataset.type, start: button.dataset.start, end: button.dataset.end
                });
            });
        });
        document.querySelectorAll('[data-nurse-close-schedule]').forEach(function (button) {
            button.addEventListener('click', closeSchedule);
        });
        document.querySelectorAll('[data-nurse-delete-schedule]').forEach(function (button) {
            button.addEventListener('click', function (event) {
                event.stopPropagation();
                var deleteForm = button.closest('form');
                if (!deleteModal || !deleteForm) {
                    if (deleteForm) HTMLFormElement.prototype.submit.call(deleteForm);
                    return;
                }
                pendingDeleteForm = deleteForm;
                lastDeleteTrigger = button;
                deleteModal.classList.add('open');
                deleteModal.setAttribute('aria-hidden', 'false');
                deleteModal.removeAttribute('inert');
                confirmDelete.focus();
            });
        });
        document.querySelectorAll('[data-nurse-close-delete-schedule]').forEach(function (button) {
            button.addEventListener('click', closeDeleteModal);
        });
        confirmDelete.addEventListener('click', function () {
            if (pendingDeleteForm) HTMLFormElement.prototype.submit.call(pendingDeleteForm);
        });
        scheduleType.addEventListener('change', setScheduleTimes);
        form.addEventListener('submit', function () {
            submitButton.disabled = true;
            submitButton.setAttribute('aria-busy', 'true');
        });
        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') return;
            if (deleteModal && deleteModal.classList.contains('open')) closeDeleteModal();
            else if (modal.classList.contains('open')) closeSchedule();
        });
        setAddMode(false);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initNurseSchedule);
    else initNurseSchedule();
}());

(function () {
    'use strict';

    function initNurseSettings() {
        var card = document.getElementById('nurse-settings-card');
        if (!card) return;

        var toast = document.getElementById('nurse-settings-toast');
        var photoDrawer = document.getElementById('nurse-settings-photo-drawer');
        var passwordDrawer = document.getElementById('nurse-settings-password-drawer');
        var logoutOverlay = document.getElementById('nurse-settings-logout-overlay');
        var settingsForm = document.getElementById('nurse-settings-form');
        var passwordForm = document.getElementById('nurse-settings-password-form');
        var csrfField = settingsForm.querySelector('[name="csrf_token"]');
        var endpoint = card.dataset.endpoint;
        var avatarButton = document.getElementById('nurse-settings-profile-avatar');
        var photoInput = document.getElementById('nurse-settings-profile-image-input');
        var choosePhoto = document.getElementById('nurse-settings-choose-photo');
        var uploadPhoto = document.getElementById('nurse-settings-upload-photo');
        var previewImage = document.getElementById('nurse-settings-photo-preview-image');
        var previewInitials = document.getElementById('nurse-settings-photo-preview-initials');
        var passwordButton = document.getElementById('nurse-settings-change-password');
        var logoutButton = document.getElementById('nurse-settings-logout-button');
        var logoutCancel = document.getElementById('nurse-settings-logout-cancel');
        var selectedPhoto = null;
        var previewUrl = null;
        var savedImageUrl = avatarButton.querySelector('img') ? avatarButton.querySelector('img').src : null;
        var activeDrawer = null;
        var lastFocus = null;
        var toastTimer = null;

        function showToast(message, kind) {
            toast.textContent = message;
            toast.classList.remove('is-success', 'is-error');
            toast.classList.add(kind === 'error' ? 'is-error' : 'is-success', 'is-visible');
            window.clearTimeout(toastTimer);
            toastTimer = window.setTimeout(function () {
                toast.classList.remove('is-visible');
            }, 3600);
        }

        function openDrawer(drawer) {
            lastFocus = document.activeElement;
            activeDrawer = drawer;
            drawer.classList.add('nurse-settings-drawer-overlay--open');
            drawer.setAttribute('aria-hidden', 'false');
            drawer.removeAttribute('inert');
            var focusTarget = drawer.querySelector('input:not([type="hidden"]), button:not(.nurse-settings-drawer-close)');
            if (focusTarget) focusTarget.focus();
        }

        function closeDrawer(drawer) {
            drawer.classList.remove('nurse-settings-drawer-overlay--open');
            drawer.setAttribute('aria-hidden', 'true');
            drawer.setAttribute('inert', '');
            if (drawer === photoDrawer) resetPhotoPreview();
            activeDrawer = null;
            if (lastFocus && typeof lastFocus.focus === 'function') lastFocus.focus();
        }

        function resetPhotoPreview() {
            selectedPhoto = null;
            photoInput.value = '';
            if (previewUrl) {
                URL.revokeObjectURL(previewUrl);
                previewUrl = null;
            }
            if (savedImageUrl) {
                previewImage.src = savedImageUrl;
                previewImage.hidden = false;
                previewInitials.hidden = true;
            } else {
                previewImage.removeAttribute('src');
                previewImage.hidden = true;
                previewInitials.hidden = false;
            }
            uploadPhoto.disabled = true;
        }

        function postAction(formData, button) {
            if (button) button.disabled = true;
            return fetch(endpoint, {
                method: 'POST',
                body: formData,
                headers: { Accept: 'application/json' }
            }).then(function (response) {
                return response.json().then(function (result) {
                    if (!response.ok || !result.success) {
                        throw new Error(result.message || 'The request could not be completed.');
                    }
                    return result;
                });
            }).finally(function () {
                if (button) button.disabled = false;
            });
        }

        document.querySelectorAll('[data-nurse-settings-edit]').forEach(function (button) {
            button.addEventListener('click', function () {
                var field = document.getElementById(button.dataset.nurseSettingsEdit);
                if (!field) return;
                if (field.hasAttribute('readonly')) {
                    field.removeAttribute('readonly');
                    field.focus();
                } else {
                    field.setAttribute('readonly', 'readonly');
                }
            });
        });
        settingsForm.addEventListener('submit', function (event) {
            event.preventDefault();
            if (!settingsForm.reportValidity()) return;
            var button = settingsForm.querySelector('button[type="submit"]');
            postAction(new FormData(settingsForm), button).then(function (result) {
                settingsForm.querySelectorAll('input:not([type="hidden"])').forEach(function (field) {
                    field.setAttribute('readonly', 'readonly');
                });
                showToast(result.message, 'success');
            }).catch(function (error) {
                showToast(error.message, 'error');
                console.error('[PulseAlert] Nurse settings update failed.', error);
            });
        });
        avatarButton.addEventListener('click', function () {
            resetPhotoPreview();
            openDrawer(photoDrawer);
        });
        choosePhoto.addEventListener('click', function () { photoInput.click(); });
        photoInput.addEventListener('change', function () {
            selectedPhoto = this.files && this.files[0] ? this.files[0] : null;
            if (!selectedPhoto) return;
            if (!/^image\/(jpeg|png|webp)$/.test(selectedPhoto.type) || selectedPhoto.size > 5 * 1024 * 1024) {
                resetPhotoPreview();
                showToast('Choose a JPG, PNG, or WebP image smaller than 5 MB.', 'error');
                return;
            }
            if (previewUrl) URL.revokeObjectURL(previewUrl);
            previewUrl = URL.createObjectURL(selectedPhoto);
            previewImage.src = previewUrl;
            previewImage.hidden = false;
            previewInitials.hidden = true;
            uploadPhoto.disabled = false;
        });
        uploadPhoto.addEventListener('click', function () {
            if (!selectedPhoto || !csrfField) return;
            var uploadData = new FormData();
            uploadData.append('action', 'upload_avatar');
            uploadData.append('csrf_token', csrfField.value);
            uploadData.append('profile_image', selectedPhoto);
            postAction(uploadData, uploadPhoto).then(function (result) {
                savedImageUrl = result.imageUrl;
                document.querySelectorAll('.nurse-sidebar-avatar, #nurse-settings-profile-avatar').forEach(function (avatar) {
                    avatar.querySelectorAll('img').forEach(function (image) { image.remove(); });
                    Array.prototype.forEach.call(avatar.childNodes, function (node) {
                        if (node.nodeType === Node.TEXT_NODE) node.remove();
                    });
                    var image = document.createElement('img');
                    image.src = result.imageUrl;
                    image.alt = '';
                    avatar.insertBefore(image, avatar.querySelector('.nurse-settings-avatar-camera'));
                });
                showToast(result.message, 'success');
                closeDrawer(photoDrawer);
            }).catch(function (error) {
                showToast(error.message, 'error');
                console.error('[PulseAlert] Nurse profile photo update failed.', error);
            });
        });
        passwordButton.addEventListener('click', function () { openDrawer(passwordDrawer); });
        passwordForm.addEventListener('submit', function (event) {
            event.preventDefault();
            if (!passwordForm.reportValidity()) return;
            var newPassword = passwordForm.elements.new_password;
            var confirmation = passwordForm.elements.confirm_password;
            if (newPassword.value !== confirmation.value) {
                confirmation.setCustomValidity('Passwords do not match.');
                confirmation.reportValidity();
                return;
            }
            confirmation.setCustomValidity('');
            var button = passwordForm.querySelector('button[type="submit"]');
            postAction(new FormData(passwordForm), button).then(function (result) {
                passwordForm.reset();
                showToast(result.message, 'success');
                closeDrawer(passwordDrawer);
            }).catch(function (error) {
                showToast(error.message, 'error');
                console.error('[PulseAlert] Nurse password change failed.', error);
            });
        });
        passwordForm.elements.confirm_password.addEventListener('input', function () {
            this.setCustomValidity('');
        });
        document.querySelectorAll('[data-nurse-settings-close]').forEach(function (button) {
            button.addEventListener('click', function () {
                var drawer = button.closest('.nurse-settings-drawer-overlay');
                if (drawer) closeDrawer(drawer);
            });
        });
        document.querySelectorAll('.nurse-settings-drawer-overlay').forEach(function (drawer) {
            drawer.addEventListener('click', function (event) {
                if (event.target === drawer) closeDrawer(drawer);
            });
        });
        document.getElementById('nurse-settings-two-factor').addEventListener('click', function () {
            showToast('Two-factor authentication settings will be available here.', 'success');
        });
        logoutButton.addEventListener('click', function () {
            logoutOverlay.classList.add('nurse-settings-logout-overlay--open');
            logoutOverlay.setAttribute('aria-hidden', 'false');
            logoutOverlay.removeAttribute('inert');
            logoutCancel.focus();
        });

        function closeLogout() {
            logoutOverlay.classList.remove('nurse-settings-logout-overlay--open');
            logoutOverlay.setAttribute('aria-hidden', 'true');
            logoutOverlay.setAttribute('inert', '');
            logoutButton.focus();
        }

        logoutCancel.addEventListener('click', closeLogout);
        logoutOverlay.addEventListener('click', function (event) {
            if (event.target === logoutOverlay) closeLogout();
        });
        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') return;
            if (activeDrawer) closeDrawer(activeDrawer);
            else if (logoutOverlay.classList.contains('nurse-settings-logout-overlay--open')) closeLogout();
        });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initNurseSettings);
    else initNurseSettings();
}());
