/**
 * trading-incentive-ajax.js
 * Complete AJAX layer for the Trading Incentive Ecosystem.
 * Drop this before </body> in Lucky_draw.html and call TI.init()
 *
 * API base URL — change to match your server
 */

const TI = (() => {
  'use strict';

  const BASE = '/Lucky_draw/php/api';
  const ADMIN = '/Lucky_draw/php/admin';

  // ─── Core fetch wrapper ──────────────────────────────────────────────────
  async function api(url, { method = 'GET', body = null, isAdmin = false } = {}) {
    const opts = {
      method,
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
    };
    if (body) opts.body = JSON.stringify(body);

    const res = await fetch(url, opts);
    const json = await res.json();
    if (!json.success) throw new Error(json.error || 'Request failed');
    return json.data;
  }

  const get  = (url)          => api(url, { method: 'GET' });
  const post = (url, body)    => api(url, { method: 'POST', body });

  // ─── Auth ─────────────────────────────────────────────────────────────────
  const Auth = {
    login: (email, password) =>
      post(`${BASE}/auth.php?action=login`, { email, password }),

    logout: () =>
      get(`${BASE}/auth.php?action=logout`),
  };

  // ─── Dashboard ───────────────────────────────────────────────────────────
  const Dashboard = {
    load: () => get(`${BASE}/dashboard.php`),
  };

  // ─── Spin ─────────────────────────────────────────────────────────────────
  const Spin = {
    /**
     * Execute a spin.
     * @param {string} spinType - 'x1' or 'x10'
     * @param {boolean} isFree  - true = use free spin wallet
     */
    execute: (spinType = 'x1', isFree = false) =>
      post(`${BASE}/spin.php`, { spin_type: spinType, is_free: isFree }),
  };

  // ─── Sure Win ─────────────────────────────────────────────────────────────
  const SureWin = {
    claim: (milestoneId) =>
      post(`${BASE}/sure_win_claim.php`, { milestone_id: milestoneId }),
  };

  // ─── Admin ────────────────────────────────────────────────────────────────
  const Admin = {
    /** Sync trades from broker payload */
    syncTrades: (trades) =>
      post(`${BASE}/sync_trades.php`, { trades }),

    /** Get exposure + config */
    getExposure: () =>
      get(`${ADMIN}/exposure.php`),

    /** Update a config key */
    updateConfig: (key, value) =>
      post(`${ADMIN}/exposure.php`, { key, value }),

    /** Get audit logs */
    auditLog: (page = 1) =>
      get(`${ADMIN}/admin_actions.php?action=audit_log&page=${page}`),

    /** Get jobs list */
    jobs: () =>
      get(`${ADMIN}/admin_actions.php?action=jobs`),

    /** Run monthly expiry for a given month */
    runExpiry: (month) =>
      post(`${ADMIN}/admin_actions.php?action=run_expiry`, { month }),

    /** Pick lucky draw winners */
    pickWinners: (month, numWinners) =>
      post(`${ADMIN}/admin_actions.php?action=pick_winners`, { month, num_winners: numWinners }),

    /** Fulfill a Sure Win claim */
    fulfillClaim: (claimId) =>
      post(`${ADMIN}/admin_actions.php?action=fulfill_claim`, { claim_id: claimId }),
  };

  // ─── UI State ─────────────────────────────────────────────────────────────
  let _state = {
    user: null,
    dashboard: null,
    spinning: false,
  };

  // ─── UI Helpers ────────────────────────────────────────────────────────────
  function fmt(n, dp = 2) {
    return Number(n).toLocaleString(undefined, { minimumFractionDigits: dp, maximumFractionDigits: dp });
  }

  function setEl(id, html) {
    const el = document.getElementById(id);
    if (el) el.innerHTML = html;
  }

  function setText(id, text) {
    const el = document.getElementById(id);
    if (el) el.textContent = text;
  }

  function showToast(title, msg, type = 'info') {
    // Reuses existing toast() from the HTML prototype if available
    if (typeof toast === 'function') {
      toast(title, msg);
      return;
    }
    console.log(`[${type.toUpperCase()}] ${title}: ${msg}`);
  }

  // ─── Renderers ────────────────────────────────────────────────────────────
  function renderDashboard(data) {
    _state.dashboard = data;

    // KPI cards
    setText('kpi-spin-balance',   fmt(data.spin_wallet?.balance ?? 0, 0));
    setText('kpi-free-balance',   fmt(data.spin_wallet?.free_balance ?? 0, 0));
    setText('kpi-lambo-balance',  '$' + fmt(data.lambo_wallet?.balance ?? 0));
    setText('kpi-sw-points',      fmt(data.sure_win?.wallet?.points ?? 0, 0));
    setText('kpi-monthly-lots',   fmt(data.monthly_lots ?? 0));

    // User tier badge
    const tierEl = document.getElementById('user-tier');
    if (tierEl) {
      const t = data.user?.tier ?? 'bronze';
      const cls = { diamond: 'good', gold: 'warn', silver: '', bronze: '' }[t] ?? '';
      tierEl.innerHTML = `<span class="badge ${cls}">${t.charAt(0).toUpperCase() + t.slice(1)}</span>`;
    }

    // Spin credits text
    setText('spin-credit-count', fmt(data.spin_wallet?.balance ?? 0, 0));
    setText('free-spin-count',   fmt(data.spin_wallet?.free_balance ?? 0, 0));

    // Lambo flow display
    setText('lambo-balance-display', '$' + fmt(data.lambo_wallet?.balance ?? 0));

    // Leaderboard
    renderLeaderboard(data.leaderboard ?? []);
    renderIbLeaderboard(data.ib_leaderboard ?? []);

    // Sure Win milestones
    renderMilestones(data.sure_win?.milestones ?? [], data.sure_win?.wallet?.points ?? 0);

    // Recent spins
    renderRecentSpins(data.recent_spins ?? []);
  }

  function renderLeaderboard(rows) {
    const tbody = document.querySelector('#leaderboard-table tbody');
    if (!tbody) return;
    tbody.innerHTML = rows.map((r, i) => {
      const tierClass = { diamond: 'good', gold: 'warn' }[r.tier] ?? '';
      return `<tr>
        <td class="muted">${i + 1}</td>
        <td>${r.username}</td>
        <td class="muted">${fmt(r.total_lots)}</td>
        <td><span class="badge ${tierClass}">${capitalize(r.tier)}</span></td>
      </tr>`;
    }).join('');
  }

  function renderIbLeaderboard(rows) {
    const tbody = document.querySelector('#ib-leaderboard-table tbody');
    if (!tbody) return;
    tbody.innerHTML = rows.map((r, i) => `<tr>
      <td class="muted">${i + 1}</td>
      <td>${r.username}</td>
      <td><span class="badge">${r.role.toUpperCase()}</span></td>
      <td class="muted">${fmt(r.network_lots)}</td>
    </tr>`).join('');
  }

  function renderMilestones(milestones, currentPts) {
    const tbody = document.getElementById('milestones');
    if (!tbody) return;
    tbody.innerHTML = milestones.map(m => {
      const pct = Math.min(100, (currentPts / m.points_req) * 100);
      let statusHtml, claimBtn = '';
      if (m.claim_status === 'pending' || m.claim_status === 'fulfilled') {
        statusHtml = `<span class="badge ${m.claim_status === 'fulfilled' ? 'good' : 'warn'}">${capitalize(m.claim_status)}</span>`;
      } else if (currentPts >= m.points_req) {
        statusHtml = `<span class="badge good">Unlocked</span>`;
        claimBtn   = `<button class="btn" style="margin-top:4px" onclick="TI.claimMilestone(${m.id})">Claim</button>`;
      } else {
        statusHtml = `<span class="badge">Locked (${fmt(pct, 0)}%)</span>`;
      }
      return `<tr>
        <td class="muted">${fmt(m.points_req, 0)}</td>
        <td>${m.reward_name}</td>
        <td>${statusHtml}${claimBtn}</td>
      </tr>`;
    }).join('');
  }

  function renderRecentSpins(spins) {
    const tbody = document.querySelector('#recent-spins-table tbody');
    if (!tbody) return;
    tbody.innerHTML = spins.map(s => `<tr>
      <td class="muted">${s.created_at.slice(0, 10)}</td>
      <td>${s.is_free ? 'Free' : 'Trade'} ${s.spin_type}</td>
      <td>${s.prize_won ? s.prize_won : '<span class="muted">No prize</span>'}</td>
      <td class="muted">${s.credits_used}</td>
    </tr>`).join('');
  }

  function renderExposure(data) {
    setText('exp-total-lots',      fmt(data.total_lots));
    setText('exp-spin-allocated',  '$' + fmt(data.spin?.allocated ?? 0));
    setText('exp-spin-paid',       '$' + fmt(data.spin?.paid ?? 0));
    setText('exp-spin-pending',    '$' + fmt(data.spin?.pending_liability ?? 0));
    setText('exp-lambo-fund',      '$' + fmt(data.lambo?.total_fund ?? 0));
    setText('exp-lambo-paid',      '$' + fmt(data.lambo?.paid_out ?? 0));
    setText('exp-reserve',         '$' + fmt(data.company_reserve ?? 0));

    const ratioEl = document.getElementById('exp-liability-ratio');
    if (ratioEl) {
      const cls = data.alert ? 'bad' : data.liability_ratio > 50 ? 'warn' : 'good';
      ratioEl.innerHTML = `<span class="badge ${cls}">${data.liability_ratio}%</span>`;
    }

    const bar = document.getElementById('exp-liability-bar');
    if (bar) bar.querySelector('div').style.width = Math.min(100, data.liability_ratio) + '%';

    // Config table
    const cfgBody = document.querySelector('#config-table tbody');
    if (cfgBody && data.config) {
      cfgBody.innerHTML = Object.entries(data.config).map(([k, v]) =>
        `<tr>
          <td>${k}</td>
          <td><input class="config-input" data-key="${k}" value="${v}" style="background:transparent;border:none;color:inherit;width:80px;"></td>
          <td><button class="btn ghost" onclick="TI.saveConfig('${k}')">Save</button></td>
        </tr>`
      ).join('');
    }
  }

  // ─── Public API ───────────────────────────────────────────────────────────
  function capitalize(s) { return s ? s.charAt(0).toUpperCase() + s.slice(1) : ''; }

  function handleLogin(event) {
    event.preventDefault();
    const email = document.getElementById('email').value;
    const password = document.getElementById('password').value;

    Auth.login(email, password)
      .then(user => {
        showToast('Login successful', `Welcome!`);
        _state.user = user;
        go('how'); // Or 'dash'
        init(true); // Reload data, force authenticated view
      })
      .catch(err => {
        showToast('Login failed', err.message, 'error');
      });
  }

  async function init(isAuthenticated = false) {
    console.log('[TI] Initialising Trading Incentive AJAX layer');
    const loginForm = document.getElementById('login-form');
    if (loginForm) {
        loginForm.addEventListener('submit', handleLogin);
    }

    if (isAuthenticated) {
        try {
            const data = await Dashboard.load();
            _state.user = data.user;
            renderDashboard(data);
            go('how');
        } catch (e) {
             console.warn('[TI] Dashboard load failed after login:', e.message);
             go('login');
        }
        return;
    }

    try {
      const data = await Dashboard.load();
      _state.user = data.user;
      renderDashboard(data);
      go('how');
    } catch (e) {
      console.warn('[TI] Dashboard load failed (user may not be logged in):', e.message);
      go('login');
    }
  }


  async function doSpin(spinType = 'x1', isFree = false) {
    if (_state.spinning) return;
    _state.spinning = true;

    const btn = document.getElementById(isFree ? 'btn-free-spin' : `btn-spin-${spinType}`);
    if (btn) { btn.disabled = true; btn.textContent = 'Spinning...'; }

    try {
      const result = await Spin.execute(spinType, isFree);

      // Animate the wheel if the prototype has a spinWheel() function
      if (typeof spinWheel === 'function') spinWheel(result.prize);

      if (result.prize) {
        showToast('🎉 You Won!', `${result.prize.name} (value: $${fmt(result.prize.value)})`);
      } else {
        showToast('No prize this time', 'Better luck next spin!');
      }

      // Refresh wallet numbers
      setText('spin-credit-count', fmt(result.wallet.balance, 0));
      setText('free-spin-count',   fmt(result.wallet.free_balance, 0));
      setText('kpi-spin-balance',  fmt(result.wallet.balance, 0));

      // Reload full dashboard to refresh ledger / recent spins
      const fresh = await Dashboard.load();
      renderDashboard(fresh);
    } catch (e) {
      showToast('Spin Error', e.message, 'error');
    } finally {
      _state.spinning = false;
      if (btn) { btn.disabled = false; btn.textContent = spinType === 'x10' ? 'Spin ×10' : 'Spin ×1'; }
    }
  }

  async function claimMilestone(milestoneId) {
    try {
      const r = await SureWin.claim(milestoneId);
      showToast('Claim submitted!', `${r.milestone} — status: ${r.status}`);
      const fresh = await Dashboard.load();
      renderDashboard(fresh);
    } catch (e) {
      showToast('Claim Error', e.message, 'error');
    }
  }

  async function loadAdmin() {
    try {
      const exp = await Admin.getExposure();
      renderExposure(exp);
    } catch (e) {
      showToast('Admin Error', e.message, 'error');
    }
  }

  async function saveConfig(key) {
    const input = document.querySelector(`.config-input[data-key="${key}"]`);
    if (!input) return;
    try {
      await Admin.updateConfig(key, input.value);
      showToast('Config saved', `${key} = ${input.value}`);
    } catch (e) {
      showToast('Save Error', e.message, 'error');
    }
  }

  async function runExpiry() {
    const month = prompt('Run expiry for month (YYYY-MM):', new Date().toISOString().slice(0, 7));
    if (!month) return;
    try {
      const r = await Admin.runExpiry(month);
      showToast('Expiry complete', r.output ?? `Month: ${month}`);
      loadAdmin();
    } catch (e) {
      showToast('Expiry Error', e.message, 'error');
    }
  }

  async function pickWinners() {
    const month = prompt('Select winners for month (YYYY-MM):', new Date().toISOString().slice(0, 7));
    if (!month) return;
    const num = parseInt(prompt('Number of winners:', '3'), 10);
    if (isNaN(num)) return;

    try {
      const r = await Admin.pickWinners(month, num);
      const names = r.winners.map((w, i) => `${i+1}. ${w.user.username} (${fmt(w.contribution)} USD)`).join('\n');
      showToast('Winners Selected!', `Job #${r.job_id}\nSeed: ${r.rng_seed.slice(0, 12)}...\n\n${names}`);
    } catch (e) {
      showToast('Error', e.message, 'error');
    }
  }

  async function syncTrades(trades) {
    try {
      const r = await Admin.syncTrades(trades);
      showToast('Sync complete', `Inserted: ${r.inserted}, Skipped: ${r.skipped}`);
      if (r.errors?.length) console.warn('Sync errors:', r.errors);
    } catch (e) {
      showToast('Sync Error', e.message, 'error');
    }
  }

  // Wire up tab navigation to load data contextually
    function wireNavigation() {
      document.querySelectorAll('.tab').forEach(tab => {
        tab.addEventListener('click', async () => {
          const target = tab.dataset.tab ?? tab.dataset.page ?? tab.getAttribute('onclick')?.match(/show\('([^']+)'\)/)?.[1];
          if (!target) return;

          if (target.includes('admin') || target.includes('exposure')) {
            loadAdmin();
          } else if (target.includes('leader') || target.includes('spin')) {
            const d = await Dashboard.load().catch(() => null);
            if (d) renderDashboard(d);
          }
        });
      });
    }

  // ─── Auto-hook existing buttons from Lucky_draw.html prototype ──────────
  function hookPrototypeButtons() {
    // Spin x1
    const spinBtn = document.getElementById('btn-spin-x1') ?? document.querySelector('[onclick*="spinWheel"]');
    if (spinBtn && !spinBtn._tiHooked) {
      spinBtn._tiHooked = true;
      spinBtn.addEventListener('click', (e) => { e.stopImmediatePropagation(); doSpin('x1', false); });
    }

    // Spin x10
    const spin10Btn = document.getElementById('btn-spin-x10');
    if (spin10Btn && !spin10Btn._tiHooked) {
      spin10Btn._tiHooked = true;
      spin10Btn.addEventListener('click', (e) => { e.stopImmediatePropagation(); doSpin('x10', false); });
    }

    // Sync trades admin button
    const syncBtn = document.querySelector('[onclick*="sync-trades"], [onclick*="syncTrades"]');
    if (syncBtn && !syncBtn._tiHooked) {
      syncBtn._tiHooked = true;
      syncBtn.addEventListener('click', (e) => {
        e.stopImmediatePropagation();
        showToast('Trade Sync', 'Call TI.syncTrades(tradesArray) with data from your broker API.');
      });
    }

    // Monthly calc button
    const calcBtn = document.querySelector('[onclick*="monthly"]');
    if (calcBtn && !calcBtn._tiHooked) {
      calcBtn._tiHooked = true;
      calcBtn.addEventListener('click', (e) => { e.stopImmediatePropagation(); runExpiry(); });
    }

    // Pick winners button
    const winnersBtn = document.querySelector('[onclick*="winner"], [onclick*="Winner"]');
    if (winnersBtn && !winnersBtn._tiHooked) {
      winnersBtn._tiHooked = true;
      winnersBtn.addEventListener('click', (e) => { e.stopImmediatePropagation(); pickWinners(); });
    }

    // Admin exposure tab
    const adminTab = document.querySelector('[data-tab="admin"], [onclick*="admin"]');
    if (adminTab && !adminTab._tiHooked) {
      adminTab._tiHooked = true;
      adminTab.addEventListener('click', loadAdmin);
    }
  }

  return {
    // Lifecycle
    init,
    wireNavigation,
    hookPrototypeButtons,

    // User actions
    doSpin,
    claimMilestone,

    // Admin actions
    loadAdmin,
    saveConfig,
    runExpiry,
    pickWinners,
    syncTrades,

    // Direct API access
    Auth,
    Dashboard,
    Spin,
    SureWin,
    Admin,

    // State (read-only)
    get state() { return { ..._state }; },
  };
})();

// Auto-init on DOM ready
document.addEventListener('DOMContentLoaded', () => {
  TI.init();
  TI.wireNavigation();
  TI.hookPrototypeButtons();
});
