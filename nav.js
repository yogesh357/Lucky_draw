/**
 * nav.js — Madocks shared navigation helper
 * Maps screen IDs to their respective HTML files.
 */

const SCREENS = {
  'how-it-works':    'how-it-works.html',
  'login':           'login.html',
  'client-dashboard':'client-dashboard.html',
  'spin':            'spin.html',
  'sure-win':        'sure-win.html',
  'lambo':           'lambo.html',
  'ib-dashboard':    'ib-dashboard.html',
  'mib-dashboard':   'mib-dashboard.html',
  'leaderboard':     'leaderboard.html',
  'rewards':         'rewards.html',
  'admin-overview':  'admin-overview.html',
  'admin-config':    'admin-config.html',
  'admin-trade-sync':'admin-trade-sync.html',
  'admin-exposure':  'admin-exposure.html',
  'admin-fraud':     'admin-fraud.html',
};

/**
 * Navigate to a screen by its ID.
 * Falls back gracefully if the ID is unknown.
 */
function navigate(id) {
  const file = SCREENS[id];
  if (file) {
    window.location.href = file;
  } else {
    console.warn('Unknown screen:', id);
  }
}

// Highlight active tab in the top screen-tabs bar
document.addEventListener('DOMContentLoaded', () => {
  const current = window.location.pathname.split('/').pop();
  document.querySelectorAll('.screen-tab[data-screen]').forEach(tab => {
    const target = SCREENS[tab.dataset.screen];
    if (target === current) tab.classList.add('active');
  });
});
