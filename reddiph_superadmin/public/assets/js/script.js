(() => {
  const sessionTimeout = Number(document.body.dataset.sessionIdleTimeout);
  const activityUrl = document.body.dataset.sessionActivityUrl;
  const logoutUrl = document.body.dataset.sessionLogoutUrl;
  const loginUrl = document.body.dataset.sessionLoginUrl;
  const logoutForm = document.querySelector('.sidebar-footer form');
  const csrfInput = logoutForm?.querySelector('input[name="csrf"]');
  const bindDebouncedSearch = (form, input) => {
    let searchTimer;
    input.addEventListener('input', () => {
      window.clearTimeout(searchTimer);
      searchTimer = window.setTimeout(() => form.requestSubmit(), 350);
    });
    form.addEventListener('submit', () => window.clearTimeout(searchTimer));
  };

  if (
    Number.isFinite(sessionTimeout) &&
    sessionTimeout > 0 &&
    activityUrl &&
    logoutUrl &&
    loginUrl &&
    csrfInput instanceof HTMLInputElement
  ) {
    const timeoutMs = sessionTimeout * 1000;
    const heartbeatIntervalMs = 60_000;
    let idleTimer;
    let lastHeartbeat = Date.now();
    let navigationExpected = false;
    let logoutStarted = false;

    const csrfBody = () => new URLSearchParams({ csrf: csrfInput.value });
    const redirectToLogin = () => window.location.assign(loginUrl);

    const logOut = async () => {
      if (logoutStarted) return;
      logoutStarted = true;
      try {
        await fetch(logoutUrl, {
          method: 'POST',
          credentials: 'same-origin',
          keepalive: true,
          body: csrfBody(),
        });
      } catch (error) {
        console.error('Could not confirm Super Admin logout with the server.', error);
      }
      redirectToLogin();
    };

    const refreshSession = async () => {
      try {
        const response = await fetch(activityUrl, {
          method: 'POST',
          credentials: 'same-origin',
          body: csrfBody(),
        });
        if (!response.ok || response.redirected) redirectToLogin();
      } catch (error) {
        console.error('Could not refresh the Super Admin session.', error);
      }
    };

    const markActivity = () => {
      if (logoutStarted) return;
      window.clearTimeout(idleTimer);
      idleTimer = window.setTimeout(logOut, timeoutMs);

      const now = Date.now();
      if (now - lastHeartbeat >= heartbeatIntervalMs) {
        lastHeartbeat = now;
        void refreshSession();
      }
    };

    ['pointerdown', 'pointermove', 'keydown', 'scroll', 'touchstart'].forEach((eventName) => {
      document.addEventListener(eventName, markActivity, { passive: true });
    });
    idleTimer = window.setTimeout(logOut, timeoutMs);

    document.addEventListener('click', (event) => {
      if (!(event.target instanceof Element)) return;
      const link = event.target.closest('a[href]');
      if (!(link instanceof HTMLAnchorElement) || link.target === '_blank' || link.hasAttribute('download')) return;
      if (event instanceof MouseEvent && (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey)) return;
      if (new URL(link.href, window.location.href).origin === window.location.origin) {
        navigationExpected = true;
      }
    });

    document.addEventListener('submit', (event) => {
      if (!(event.target instanceof HTMLFormElement)) return;
      const destination = new URL(event.target.action, window.location.href);
      if (destination.origin === window.location.origin) navigationExpected = true;
    });

    window.addEventListener('pagehide', (event) => {
      if (navigationExpected || event.persisted || logoutStarted) return;
      const sent = navigator.sendBeacon(logoutUrl, csrfBody());
      if (!sent) {
        console.warn('The browser could not send the Super Admin tab-close logout request.');
      }
    });
  }

  const loginForm = document.querySelector('[data-login-form]');
  loginForm?.addEventListener('submit', () => {
    const submitButton = loginForm.querySelector('[data-login-submit]');
    if (!(submitButton instanceof HTMLButtonElement)) return;

    submitButton.disabled = true;
    submitButton.textContent = 'Signing in...';
    loginForm.setAttribute('aria-busy', 'true');
  });

  const passwordToggle = document.querySelector('[data-password-toggle]');
  const loginPasswordToggle = document.querySelector('.toggle');
  const activePasswordToggle = passwordToggle || loginPasswordToggle;
  if (activePasswordToggle instanceof HTMLButtonElement) {
    activePasswordToggle.addEventListener('click', () => {
      const inputId = activePasswordToggle.getAttribute('aria-controls');
      const input = inputId ? document.getElementById(inputId) : null;
      if (!(input instanceof HTMLInputElement)) return;
      const show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      const label = activePasswordToggle.querySelector('span');
      if (label) {
        label.textContent = show ? 'Hide' : 'Show';
      } else {
        activePasswordToggle.textContent = show ? 'Hide' : 'Show';
      }
      activePasswordToggle.setAttribute('aria-pressed', String(show));
      activePasswordToggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    });
  }

  document.querySelectorAll('.incident-chart circle').forEach((point) => {
    const tooltip = document.querySelector('.chart-tooltip');
    if (!tooltip) return;

    const show = () => {
      const date = point.dataset.chartDate || point.dataset.chartLabel || 'Day';
      const count = Number(point.dataset.chartCount);
      const safeCount = Number.isFinite(count) ? count : 0;
      tooltip.textContent = `${date}: ${safeCount.toLocaleString()} incident ${safeCount === 1 ? 'report' : 'reports'}`;
    };
    const clear = () => {
      tooltip.textContent = '';
    };
    point.addEventListener('focus', show);
    point.addEventListener('mouseenter', show);
    point.addEventListener('blur', clear);
    point.addEventListener('mouseleave', clear);
  });

  const addAccountToggle = document.querySelector('[data-toggle-create-account]');
  const addAccountCard = document.getElementById('create-account-card');
  const closeAddAccount = document.querySelector('[data-close-create-account]');
  if (
    addAccountToggle instanceof HTMLButtonElement &&
    addAccountCard instanceof HTMLElement
  ) {
    const setAddAccountVisible = (visible) => {
      addAccountCard.hidden = !visible;
      addAccountToggle.setAttribute('aria-expanded', String(visible));
      if (visible) {
        addAccountCard.querySelector('input[name="name"]')?.focus();
      } else {
        addAccountToggle.focus();
      }
    };
    addAccountToggle.addEventListener('click', () => {
      setAddAccountVisible(addAccountCard.hidden);
    });
    closeAddAccount?.addEventListener('click', () => {
      setAddAccountVisible(false);
    });
  }

  const createAccountForm = document.querySelector('.create-account-form');
  const accountHospitalSelect = createAccountForm?.querySelector('[data-account-hospital]');
  const accountBarangaySelect = createAccountForm?.querySelector('[data-account-barangay]');
  const accountBarangayStatus = createAccountForm?.querySelector('[data-account-barangay-status]');
  const accountRoleSelect = createAccountForm?.querySelector('select[name="role"]');
  if (
    createAccountForm instanceof HTMLFormElement &&
    accountHospitalSelect instanceof HTMLSelectElement &&
    accountBarangaySelect instanceof HTMLSelectElement &&
    accountBarangayStatus instanceof HTMLElement
  ) {
    let barangayRequest = 0;
    const setBarangayStatus = (message) => {
      accountBarangayStatus.textContent = message;
    };
    const resetBarangays = (placeholder) => {
      accountBarangaySelect.replaceChildren(new Option(placeholder, ''));
      accountBarangaySelect.disabled = true;
    };
    const updateBarangayRequirement = () => {
      if (accountRoleSelect instanceof HTMLSelectElement) {
        accountBarangaySelect.required = accountRoleSelect.value === 'barangay_admin';
      }
    };

    const loadAccountBarangays = async () => {
      const requestId = ++barangayRequest;
      const hospitalId = accountHospitalSelect.value;
      resetBarangays(hospitalId ? 'Loading barangays…' : 'Select a hospital first');
      updateBarangayRequirement();

      if (!hospitalId) {
        setBarangayStatus('Choose a hospital to load its barangays.');
        return;
      }

      setBarangayStatus('Loading barangays…');
      let failureMessage = 'Barangays could not be loaded. Change the hospital and select it again to retry.';
      try {
        const url = new URL(createAccountForm.dataset.barangaysUrl, window.location.href);
        url.searchParams.set('action', 'barangays');
        url.searchParams.set('hospital_id', hospitalId);
        const response = await fetch(url, {
          headers: { Accept: 'application/json' },
          credentials: 'same-origin',
          cache: 'no-store',
        });
        let result;
        try {
          result = await response.json();
        } catch {
          failureMessage = 'The barangay service returned an invalid response. Refresh the page and try again.';
          throw new Error(failureMessage);
        }
        if (!response.ok || !result.success || !Array.isArray(result.barangays)) {
          failureMessage = result.message || failureMessage;
          throw new Error(failureMessage);
        }
        if (requestId !== barangayRequest || hospitalId !== accountHospitalSelect.value) return;

        resetBarangays('Not assigned');
        result.barangays.forEach((barangay) => {
          const id = Number(barangay.id);
          if (!Number.isSafeInteger(id) || id < 1 || typeof barangay.name !== 'string') return;
          accountBarangaySelect.add(new Option(barangay.name, String(id)));
        });
        accountBarangaySelect.disabled = false;
        const barangayCount = accountBarangaySelect.options.length - 1;
        setBarangayStatus(
          barangayCount === 0
            ? 'No barangays available for this hospital.'
            : `${barangayCount} barangay${barangayCount === 1 ? '' : 's'} available.`
        );
      } catch (error) {
        if (requestId !== barangayRequest) return;
        resetBarangays('Barangays unavailable');
        setBarangayStatus(failureMessage);
        console.error('Could not load barangays for the selected hospital.', error);
      }
    };

    accountHospitalSelect.addEventListener('change', loadAccountBarangays);
    accountRoleSelect?.addEventListener('change', updateBarangayRequirement);
    updateBarangayRequirement();
  }

  const userSearchForm = document.querySelector('[data-user-search-form]');
  const userSearchInput = userSearchForm?.querySelector('[data-user-search]');
  const userStatusInput = userSearchForm?.querySelector('[data-user-status]');
  const userFilters = document.querySelector('.user-filters');
  if (userFilters instanceof HTMLElement) {
    let dragStartX = 0;
    let dragStartScrollLeft = 0;
    let activePointerId = null;
    let dragged = false;
    let suppressNextClick = false;
    let suppressClickTimer;

    userFilters.addEventListener('pointerdown', (event) => {
      if (event.pointerType !== 'mouse' || event.button !== 0) return;
      activePointerId = event.pointerId;
      dragStartX = event.clientX;
      dragStartScrollLeft = userFilters.scrollLeft;
      dragged = false;
    });

    window.addEventListener('pointermove', (event) => {
      if (event.pointerId !== activePointerId) return;
      const distance = event.clientX - dragStartX;
      if (Math.abs(distance) > 4) {
        dragged = true;
        userFilters.classList.add('is-dragging');
        event.preventDefault();
      }
      if (dragged) userFilters.scrollLeft = dragStartScrollLeft - distance;
    });

    const finishFilterDrag = (event) => {
      if (event.pointerId !== activePointerId) return;
      activePointerId = null;
      userFilters.classList.remove('is-dragging');
      if (dragged) {
        suppressNextClick = true;
        window.clearTimeout(suppressClickTimer);
        suppressClickTimer = window.setTimeout(() => {
          suppressNextClick = false;
        }, 300);
      }
    };

    window.addEventListener('pointerup', finishFilterDrag);
    window.addEventListener('pointercancel', finishFilterDrag);
    userFilters.addEventListener('click', (event) => {
      if (!suppressNextClick) return;
      event.preventDefault();
      event.stopPropagation();
      suppressNextClick = false;
      window.clearTimeout(suppressClickTimer);
    }, true);
  }
  if (
    userSearchForm instanceof HTMLFormElement &&
    userSearchInput instanceof HTMLInputElement &&
    userStatusInput instanceof HTMLInputElement
  ) {
    bindDebouncedSearch(userSearchForm, userSearchInput);

    userSearchForm.querySelectorAll('[data-user-status-button]').forEach((button) => {
      button.addEventListener('click', () => {
        const status = button.dataset.userStatusButton;
        if (status !== 'active' && status !== 'archived') return;
        userStatusInput.value = status;
        userSearchForm.requestSubmit();
      });
    });
  }

  const userMenuTriggers = [...document.querySelectorAll('[data-user-menu-trigger]')];
  const positionUserMenu = (trigger, menu) => {
    const anchor = trigger.getBoundingClientRect();
    const bounds = menu.getBoundingClientRect();
    const margin = 8;
    const left = Math.min(
      Math.max(margin, anchor.right - bounds.width),
      window.innerWidth - bounds.width - margin
    );
    const below = anchor.bottom + 4;
    const above = anchor.top - bounds.height - 4;
    const top = below + bounds.height <= window.innerHeight - margin
      ? below
      : above >= margin
        ? above
        : Math.max(margin, window.innerHeight - bounds.height - margin);
    menu.style.left = `${left}px`;
    menu.style.top = `${top}px`;
  };
  const repositionOpenUserMenus = () => {
    userMenuTriggers.forEach((trigger) => {
      const menuId = trigger.getAttribute('popovertarget');
      const menu = menuId ? document.getElementById(menuId) : null;
      if (menu instanceof HTMLElement && menu.matches(':popover-open')) {
        positionUserMenu(trigger, menu);
      }
    });
  };
  userMenuTriggers.forEach((trigger) => {
    const menuId = trigger.getAttribute('popovertarget');
    const menu = menuId ? document.getElementById(menuId) : null;
    if (!(menu instanceof HTMLElement)) return;
    menu.addEventListener('toggle', (event) => {
      if (event.newState === 'open') {
        requestAnimationFrame(() => positionUserMenu(trigger, menu));
      }
    });
  });
  window.addEventListener('resize', repositionOpenUserMenus);
  window.addEventListener('scroll', repositionOpenUserMenus, true);

  const facilityFilterForm = document.querySelector('[data-facility-filter-form]');
  const facilitySearch = facilityFilterForm?.querySelector('[data-facility-search]');
  if (facilityFilterForm instanceof HTMLFormElement) {
    facilityFilterForm.querySelectorAll('[data-facility-auto-submit]').forEach((control) => {
      control.addEventListener('change', () => facilityFilterForm.requestSubmit());
    });
    if (facilitySearch instanceof HTMLInputElement) {
      bindDebouncedSearch(facilityFilterForm, facilitySearch);
    }
  }

  const auditPlatformFilter = document.querySelector('[data-audit-platform-filter]');
  if (auditPlatformFilter instanceof HTMLSelectElement) {
    auditPlatformFilter.addEventListener('change', () => {
      const form = auditPlatformFilter.closest('form');
      if (form instanceof HTMLFormElement) form.requestSubmit();
    });
  }

  const facilityFormCard = document.getElementById('facility-form-card');
  const facilityFormToggle = document.querySelector('[data-toggle-facility-form]');
  const facilityFormClose = document.querySelector('[data-close-facility-form]');
  const facilityCreateType = document.querySelector('[data-facility-create-type]');
  const setFacilityFormVisible = (visible) => {
    if (!(facilityFormCard instanceof HTMLElement)) return;
    facilityFormCard.hidden = !visible;
    if (facilityFormToggle instanceof HTMLButtonElement) {
      facilityFormToggle.setAttribute('aria-expanded', String(visible));
      facilityFormToggle.textContent = visible ? 'Close form' : 'Add record';
    }
  };
  if (facilityFormToggle instanceof HTMLButtonElement && facilityFormCard instanceof HTMLElement) {
    facilityFormToggle.addEventListener('click', () => setFacilityFormVisible(facilityFormCard.hidden));
    facilityFormClose?.addEventListener('click', () => {
      setFacilityFormVisible(false);
      facilityFormToggle.focus();
    });
  }
  if (facilityCreateType instanceof HTMLSelectElement) {
    const facilityForms = [...document.querySelectorAll('[data-facility-record-form]')];
    const updateFacilityFormType = () => {
      facilityForms.forEach((form) => {
        if (!(form instanceof HTMLElement)) return;
        form.hidden = form.dataset.facilityRecordForm !== facilityCreateType.value;
      });
    };
    facilityCreateType.addEventListener('change', updateFacilityFormType);
    updateFacilityFormType();
  }

  document.querySelector('[data-recheck-audit]')?.addEventListener('click', () => location.reload());
})();
