(function () {
  const ROLE_HOME = {
    CLIENT: 'client-dashboard.html',
    IB: 'ib-dashboard.html',
    MIB: 'mib-dashboard.html',
    ADMIN: 'admin-overview.html'
  };

  let meCache = null;

  function appBasePath() {
    const path = window.location.pathname || '/';
    const parts = path.split('/').filter(Boolean);

    if (!parts.length) {
      return '';
    }

    const last = parts[parts.length - 1];
    const looksLikeFile = last.includes('.');
    const dirParts = looksLikeFile ? parts.slice(0, -1) : parts;

    return dirParts.length ? `/${dirParts.join('/')}` : '';
  }

  function resolveUrl(url) {
    if (/^https?:\/\//i.test(url)) {
      return url;
    }

    if (url.startsWith('/')) {
      return `${appBasePath()}${url}`;
    }

    return url;
  }

  function escapeHtml(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  async function api(url, options = {}) {
    const init = {
      method: options.method || 'GET',
      headers: { ...(options.headers || {}) },
      credentials: 'same-origin'
    };

    if (options.formData) {
      init.body = options.formData;
    } else if (options.data !== undefined) {
      init.headers['Content-Type'] = 'application/json';
      init.body = JSON.stringify(options.data);
    }

    if (options.idempotencyKey) {
      init.headers['Idempotency-Key'] = options.idempotencyKey;
    }

    const response = await fetch(resolveUrl(url), init);
    const json = await response.json().catch(() => ({
      ok: false,
      message: 'Invalid server response'
    }));

    if (!response.ok || json.ok === false) {
      const error = new Error(json.message || 'Request failed');
      error.status = response.status;
      error.payload = json;
      throw error;
    }

    return json;
  }

  async function getMe(force = false) {
    if (!force && meCache) {
      return meCache;
    }

    const response = await api('api/auth/me.php');
    meCache = response.data;
    return meCache;
  }

  async function requireAuth(roles) {
    try {
      const me = await getMe();
      if (Array.isArray(roles) && roles.length && !roles.includes(me.role)) {
        window.location.href = ROLE_HOME[me.role] || 'login.html';
        return null;
      }
      return me;
    } catch (error) {
      window.location.href = 'login.html';
      return null;
    }
  }

  function setMessage(target, message, type = 'info') {
    if (!target) {
      return;
    }

    const palette = {
      info: { bg: 'rgba(79,123,255,.08)', border: 'rgba(79,123,255,.2)', color: '#9fb7ff' },
      success: { bg: 'rgba(46,204,138,.08)', border: 'rgba(46,204,138,.2)', color: '#7fe3ad' },
      error: { bg: 'rgba(239,83,80,.08)', border: 'rgba(239,83,80,.2)', color: '#ff9b99' }
    };

    const colors = palette[type] || palette.info;
    target.textContent = message;
    target.style.display = message ? 'block' : 'none';
    target.style.background = colors.bg;
    target.style.border = `1px solid ${colors.border}`;
    target.style.color = colors.color;
    target.style.padding = '12px 14px';
    target.style.borderRadius = '10px';
    target.style.marginBottom = '16px';
    target.style.fontSize = '13px';
  }

  function currency(value) {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 2 }).format(Number(value || 0));
  }

  function number(value, decimals = 0) {
    return new Intl.NumberFormat('en-US', {
      minimumFractionDigits: decimals,
      maximumFractionDigits: decimals
    }).format(Number(value || 0));
  }

  function shortDate(value) {
    if (!value) return '-';
    return new Intl.DateTimeFormat('en-US', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
  }

  function roleHome(role) {
    return ROLE_HOME[role] || 'login.html';
  }

  window.Madocks = {
    api,
    getMe,
    requireAuth,
    setMessage,
    currency,
    number,
    shortDate,
    roleHome,
    escapeHtml
  };
})();
