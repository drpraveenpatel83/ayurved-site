/* ============================================================
   app.js — Global helpers, API client, Auth, Toast, Navbar
   ============================================================ */

// Auto-detect base path (works for both /  and  /test-site/)
const _pathParts = window.location.pathname.split('/');
const _subdir    = _pathParts[1] && !_pathParts[1].includes('.') &&
                   _pathParts[1] !== 'api' ? '/' + _pathParts[1] : '';
const API_BASE   = _subdir + '/api';

// ── Token storage ────────────────────────────────────────────
const Auth = {
  getToken: () => localStorage.getItem('aq_token'),
  setToken: (t) => localStorage.setItem('aq_token', t),
  removeToken: () => localStorage.removeItem('aq_token'),
  getUser: () => {
    try { return JSON.parse(localStorage.getItem('aq_user') || 'null'); }
    catch { return null; }
  },
  setUser: (u) => localStorage.setItem('aq_user', JSON.stringify(u)),
  removeUser: () => localStorage.removeItem('aq_user'),
  isLoggedIn: () => !!localStorage.getItem('aq_token'),
  isAdmin: () => { const u = Auth.getUser(); return u && u.role === 'admin'; },
  logout: async () => {
    try { await api('public/auth/logout.php', 'POST'); } catch {}
    Auth.removeToken();
    Auth.removeUser();
    window.location.href = '/pages/login.html';
  }
};

// ── API Client ────────────────────────────────────────────────
async function api(endpoint, method = 'GET', body = null) {
  const opts = {
    method,
    headers: { 'Content-Type': 'application/json' }
  };
  const token = Auth.getToken();
  if (token) opts.headers['Authorization'] = `Bearer ${token}`;
  if (body && method !== 'GET') opts.body = JSON.stringify(body);

  const url = `${API_BASE}/${endpoint}`;
  const res = await fetch(url, opts);
  const json = await res.json().catch(() => ({ success: false, message: 'Server error' }));

  if (res.status === 401) {
    Auth.removeToken();
    Auth.removeUser();
    if (!window.location.pathname.includes('login')) {
      sessionStorage.setItem('aq_redirect', window.location.href);
      window.location.href = '/pages/login.html';
    }
    return json;
  }
  return json;
}

async function apiGet(endpoint) { return api(endpoint, 'GET'); }
async function apiPost(endpoint, body) { return api(endpoint, 'POST', body); }
async function apiDelete(endpoint, body) { return api(endpoint, 'DELETE', body); }

// ── Toast Notifications ────────────────────────────────────────
const Toast = (() => {
  let container;
  function init() {
    container = document.getElementById('toast-container');
    if (!container) {
      container = document.createElement('div');
      container.className = 'toast-container';
      container.id = 'toast-container';
      document.body.appendChild(container);
    }
  }
  function show(msg, type = 'success') {
    init();
    const icons = { success: '✅', error: '❌', warn: '⚠️', info: 'ℹ️' };
    const t = document.createElement('div');
    t.className = `toast ${type}`;
    t.innerHTML = `<span>${icons[type] || ''}</span><span>${msg}</span>`;
    container.appendChild(t);
    setTimeout(() => t.remove(), 3200);
  }
  return { show, success: (m) => show(m, 'success'), error: (m) => show(m, 'error'), warn: (m) => show(m, 'warn'), info: (m) => show(m, 'info') };
})();

// ── Navbar ─────────────────────────────────────────────────────
function initNavbar() {
  const burger = document.getElementById('nav-burger');
  const menu   = document.getElementById('nav-menu');
  const loginBtn  = document.getElementById('nav-login-btn');
  const userBtn   = document.getElementById('nav-user-btn');
  const userName  = document.getElementById('nav-user-name');
  const logoutBtn = document.getElementById('nav-logout-btn');

  if (burger && menu) {
    burger.addEventListener('click', () => menu.classList.toggle('open'));
    document.addEventListener('click', (e) => {
      if (!burger.contains(e.target) && !menu.contains(e.target)) menu.classList.remove('open');
    });
  }

  // Highlight active nav link
  const links = document.querySelectorAll('.navbar-menu a');
  links.forEach(a => {
    if (a.href === window.location.href || window.location.pathname.startsWith(new URL(a.href, location.origin).pathname)) {
      a.classList.add('active');
    }
  });

  // Auth state
  if (Auth.isLoggedIn()) {
    const user = Auth.getUser();
    if (loginBtn)  loginBtn.classList.add('hidden');
    if (userBtn)   userBtn.classList.remove('hidden');
    if (userName)  userName.textContent = user?.name?.split(' ')[0] || 'User';
  } else {
    if (loginBtn)  loginBtn.classList.remove('hidden');
    if (userBtn)   userBtn.classList.add('hidden');
  }

  if (logoutBtn) logoutBtn.addEventListener('click', Auth.logout);
}

// ── Auth Guard (require login for protected pages) ─────────────
function requireLogin(redirectBack = true) {
  if (!Auth.isLoggedIn()) {
    if (redirectBack) sessionStorage.setItem('aq_redirect', window.location.href);
    window.location.href = '/pages/login.html';
    return false;
  }
  return true;
}

// ── Show login gate instead of content ─────────────────────────
function showLoginGate(container, message = 'Quiz attempt karne ke liye login karein') {
  container.innerHTML = `
    <div class="login-gate">
      <div class="icon">🔐</div>
      <h3>Login Required</h3>
      <p>${message}</p>
      <a href="/pages/login.html" class="btn btn-primary btn-lg">Login / Register</a>
    </div>`;
}

// ── Loading spinner ─────────────────────────────────────────────
function showSpinner(container) {
  container.innerHTML = '<div class="spinner-wrap"><div class="spinner"></div></div>';
}

// ── Params helper ───────────────────────────────────────────────
const Params = {
  get: (key) => new URLSearchParams(window.location.search).get(key),
  all: () => Object.fromEntries(new URLSearchParams(window.location.search))
};

// ── Date helpers ────────────────────────────────────────────────
function todayStr() {
  return new Date().toISOString().split('T')[0];
}
function formatDate(dateStr) {
  return new Date(dateStr).toLocaleDateString('hi-IN', { day: 'numeric', month: 'long', year: 'numeric' });
}

// ── Share helpers ───────────────────────────────────────────────
function shareResult(score, total, shareToken) {
  const pct  = Math.round((score / total) * 100);
  const msg  = `🌿 Ayurveda Quiz Result\n✅ Score: ${score}/${total} (${pct}%)\n📊 ${pct >= 70 ? 'बहुत अच्छा! 🎉' : 'अभ्यास जारी रखें 💪'}\n\nResult देखें: ${location.origin}/pages/result.html?share=${shareToken}`;
  return {
    whatsapp: `https://api.whatsapp.com/send?text=${encodeURIComponent(msg)}`,
    twitter:  `https://twitter.com/intent/tweet?text=${encodeURIComponent(msg)}`,
    copy:     msg
  };
}

// ── Copy to clipboard ───────────────────────────────────────────
async function copyToClipboard(text) {
  try {
    await navigator.clipboard.writeText(text);
    Toast.success('Link copied!');
  } catch {
    Toast.error('Copy failed');
  }
}

// ── Init on DOM ready ───────────────────────────────────────────
document.addEventListener('DOMContentLoaded', initNavbar);
