(() => {
  const passwordToggle = document.querySelector('.toggle');
  if (passwordToggle) {
    passwordToggle.addEventListener('click', () => {
      const input = document.getElementById(passwordToggle.getAttribute('aria-controls'));
      const show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      passwordToggle.textContent = show ? 'Hide' : 'Show';
      passwordToggle.setAttribute('aria-pressed', String(show));
    });
  }

  document.querySelectorAll('.incident-chart circle').forEach((point) => {
    const tooltip = document.querySelector('.chart-tooltip');
    if (!tooltip) return;

    const show = () => {
      tooltip.textContent = `${point.dataset.chartLabel}: ${point.dataset.chartCount} incident reports`;
    };
    const clear = () => {
      tooltip.textContent = '';
    };
    point.addEventListener('focus', show);
    point.addEventListener('mouseenter', show);
    point.addEventListener('blur', clear);
    point.addEventListener('mouseleave', clear);
  });

  const userSearch = document.querySelector('[data-user-search]');
  if (userSearch) {
    const rows = [...document.querySelectorAll('[data-user-row]')];
    const noResults = document.querySelector('.users-no-results');

    userSearch.addEventListener('input', () => {
      const query = userSearch.value.trim().toLowerCase();
      let visibleCount = 0;
      rows.forEach((row) => {
        const visible = row.dataset.search.includes(query);
        row.hidden = !visible;
        if (visible) visibleCount += 1;
      });
      if (noResults) noResults.hidden = visibleCount !== 0;
    });
  }

  const roleButtons = document.querySelectorAll('[data-role-select]');
  const roleCells = document.querySelectorAll('[data-role-column]');
  roleButtons.forEach((button) => {
    button.addEventListener('click', () => {
      const selectedRole = button.dataset.roleSelect;
      roleButtons.forEach((item) => item.setAttribute('aria-pressed', String(item === button)));
      roleCells.forEach((cell) => {
        cell.dataset.selected = String(cell.dataset.roleColumn === selectedRole);
      });
    });
  });

  document.querySelectorAll('[data-facility-group]').forEach((group) => {
    const search = group.querySelector('[data-barangay-search]');
    if (!search) return;
    const cards = [...group.querySelectorAll('[data-barangay-card]')];
    const noResults = group.querySelector('.barangay-no-results');

    search.addEventListener('input', () => {
      const query = search.value.trim().toLowerCase();
      let visibleCount = 0;
      cards.forEach((card) => {
        const visible = card.dataset.search.includes(query);
        card.hidden = !visible;
        if (visible) visibleCount += 1;
      });
      if (noResults) noResults.hidden = visibleCount !== 0;
    });
  });

  const brandingPreview = document.querySelector('[data-branding-preview-base]');
  const websitePreview = document.querySelector('[data-branding-website-preview]');
  const websitePreviewPage = document.querySelector('[data-branding-preview-page]');
  const primaryColor = document.querySelector('[data-primary-color]');
  const secondaryColor = document.querySelector('[data-secondary-color]');
  const nameInput = document.querySelector('[data-brand-name-input]');
  const logoInput = document.querySelector('[data-brand-logo-input]');
  const faviconInput = document.querySelector('[data-brand-favicon-input]');
  const originalWebsiteBranding = {};
  const resetPreviewFields = new Set();
  let websiteBrandingReady = false;

  const previewAssetUrl = (path) => {
    if (!path) return null;
    if (/^(https?:)?\/\//i.test(path) || path.startsWith('/')) return path;
    if (/^[a-z][a-z\d+.-]*:/i.test(path)) return null;
    return new URL(path.replace(/^\/+/, ''), new URL(brandingPreview.dataset.brandingPreviewBase, window.location.origin)).href;
  };

  const updateWebsitePreview = () => {
    if (!websitePreview || !brandingPreview || !websiteBrandingReady) return;

    const previewDocument = websitePreview.contentDocument;
    const previewWindow = websitePreview.contentWindow;
    const scope = brandingPreview.dataset.brandingScope;
    if (!previewDocument || !previewWindow || scope === 'mobile') return;

    const inputs = {
      app_name: nameInput,
      logo_path: logoInput,
      favicon_path: faviconInput,
      primary_color: primaryColor,
      secondary_color: secondaryColor,
    };
    const values = {};
    Object.entries(inputs).forEach(([key, input]) => {
      const editedValue = input?.value.trim() ?? '';
      if (resetPreviewFields.has(key)) {
        values[key] = editedValue;
      } else {
        values[key] = editedValue || originalWebsiteBranding[key];
      }
    });

    const appName = values.app_name || originalWebsiteBranding.app_name;
    const logo = previewDocument.querySelector('.brand-logo img, .application-brand-logo');
    if (logo) {
      logo.src = previewAssetUrl(values.logo_path) || originalWebsiteBranding.logo_path;
      logo.alt = appName;
    }

    previewDocument.title = `${originalWebsiteBranding.titlePrefix} — ${appName}`;
    previewDocument.documentElement.style.setProperty('--brand-primary-color', values.primary_color || originalWebsiteBranding.primary_color);
    previewDocument.documentElement.style.setProperty('--brand-secondary-color', values.secondary_color || originalWebsiteBranding.secondary_color);

    let favicon = previewDocument.querySelector('link[rel~="icon"]');
    const faviconUrl = previewAssetUrl(values.favicon_path);
    if (faviconUrl) {
      if (!favicon) {
        favicon = previewDocument.createElement('link');
        favicon.rel = 'icon';
        previewDocument.head.appendChild(favicon);
      }
      favicon.href = faviconUrl;
    } else if (favicon && originalWebsiteBranding.favicon_path) {
      favicon.href = originalWebsiteBranding.favicon_path;
    } else if (favicon) {
      favicon.remove();
    }
  };

  websitePreview?.addEventListener('load', () => {
    const previewDocument = websitePreview.contentDocument;
    if (!previewDocument) return;

    const logo = previewDocument.querySelector('.brand-logo img, .application-brand-logo');
    const favicon = previewDocument.querySelector('link[rel~="icon"]');
    const titleParts = previewDocument.title.split(' — ');
    originalWebsiteBranding.app_name = titleParts.slice(1).join(' — ') || 'Reddiph';
    originalWebsiteBranding.titlePrefix = titleParts[0] || 'Reddiph';
    originalWebsiteBranding.logo_path = logo?.getAttribute('src') ?? '';
    originalWebsiteBranding.favicon_path = favicon?.getAttribute('href') ?? '';
    originalWebsiteBranding.primary_color = previewWindowColor(previewDocument, '--brand-primary-color', '#8d1018');
    originalWebsiteBranding.secondary_color = previewWindowColor(previewDocument, '--brand-secondary-color', '#d4803a');
    websiteBrandingReady = true;
    updateWebsitePreview();
  });

  function previewWindowColor(previewDocument, property, fallback) {
    return previewDocument.defaultView.getComputedStyle(previewDocument.documentElement).getPropertyValue(property).trim() || fallback;
  }

  websitePreviewPage?.addEventListener('change', () => {
    if (!websitePreview || !brandingPreview) return;
    const previewUrl = new URL(
      `app/controllers/${websitePreviewPage.value}`,
      new URL(brandingPreview.dataset.brandingPreviewBase, window.location.origin)
    );
    websitePreview.src = previewUrl.href;
  });

  const brandingInputs = {
    app_name: nameInput,
    logo_path: logoInput,
    favicon_path: faviconInput,
    primary_color: primaryColor,
    secondary_color: secondaryColor,
  };

  Object.entries(brandingInputs).forEach(([key, input]) => {
    const updateFieldPreview = () => {
      resetPreviewFields.delete(key);
      updateWebsitePreview();
    };
    input?.addEventListener('input', updateFieldPreview);
    input?.addEventListener('change', updateFieldPreview);
  });

  document.querySelector('[data-reset-branding]')?.addEventListener('click', () => {
    const defaults = {
      app_name: brandingPreview.dataset.defaultAppName,
      logo_path: brandingPreview.dataset.defaultLogoPath,
      favicon_path: brandingPreview.dataset.defaultFaviconPath,
      primary_color: brandingPreview.dataset.defaultPrimaryColor,
      secondary_color: brandingPreview.dataset.defaultSecondaryColor,
    };

    Object.entries(brandingInputs).forEach(([key, input]) => {
      if (input) input.value = defaults[key];
      resetPreviewFields.add(key);
    });
    updateWebsitePreview();
  });

  document.querySelector('[data-recheck-audit]')?.addEventListener('click', () => location.reload());

  document.querySelectorAll('[data-copy-value]').forEach((button) => {
    button.addEventListener('click', async () => {
      try {
        await navigator.clipboard.writeText(button.dataset.copyValue);
        button.textContent = 'Copied';
        window.setTimeout(() => { button.textContent = 'Copy'; }, 1500);
      } catch (error) {
        button.textContent = 'Copy unavailable';
        button.setAttribute('aria-label', 'Copy to clipboard is unavailable in this browser context.');
      }
    });
  });
})();
