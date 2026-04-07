/* ============================================================
   home.js — Homepage: Banner Slider + News Tabs + Mobile Nav
   ============================================================ */

document.addEventListener('DOMContentLoaded', () => {
  loadBannerSlider();
  loadNewsPosts('latest-news');
  initMobileNav();
});

// ── Banner / Image Slider ─────────────────────────────────────
let _sliderIdx   = 0;
let _sliderItems = [];
let _sliderTimer = null;

async function loadBannerSlider() {
  const outer = document.getElementById('banner-slider-outer');
  if (!outer) return;

  try {
    const res = await apiGet('public/banners.php');
    _sliderItems = res?.data || [];
  } catch { _sliderItems = []; }

  // Fallback default banner if no banners added yet
  if (!_sliderItems.length) {
    _sliderItems = [
      {
        title: 'Welcome to Ayurved Studies',
        subtitle: 'Free BAMS Notes · AIAPGET MCQ · NCISM Updates · Daily Quiz',
        image_url: '',
        link_url: '/pages/quiz.html',
        color: '#1a6e3c'
      },
      {
        title: 'AIAPGET Preparation 2025',
        subtitle: 'Topic-wise MCQs, Previous Year Papers and Mock Tests',
        image_url: '',
        link_url: '/category/aiapget',
        color: '#0170B9'
      },
      {
        title: 'Daily Quiz — 10 MCQs Every Day',
        subtitle: 'Practice daily to crack BAMS & AYUSH competitive exams',
        image_url: '',
        link_url: '/pages/quiz.html?type=daily',
        color: '#E67E22'
      }
    ];
  }

  _buildSlider(outer);
  _startSliderAuto();
}

function _buildSlider(outer) {
  // Clear placeholder
  outer.innerHTML = '';

  // Track
  const track = document.createElement('div');
  track.className = 'slider-track';
  track.id = 'slider-track';

  _sliderItems.forEach((b, i) => {
    const slide = document.createElement('div');
    slide.className = 'slide';

    if (b.image_url) {
      // Image-only slide with optional link
      const img = `<img src="${esc(b.image_url)}" alt="${esc(b.title || 'Banner')}" class="slide-img" loading="${i === 0 ? 'eager' : 'lazy'}">`;
      slide.innerHTML = b.link_url
        ? `<a href="${esc(b.link_url)}" ${b.link_url.startsWith('http') ? 'target="_blank" rel="noopener"' : ''}>${img}</a>`
        : img;
    } else {
      // Fallback: colored placeholder (only shown if no image uploaded)
      slide.innerHTML = `<div class="slide-no-img" style="background:${esc(b.color || '#1a6e3c')}"></div>`;
    }

    track.appendChild(slide);
  });

  outer.appendChild(track);

  // Prev / Next buttons (only if more than one slide)
  if (_sliderItems.length > 1) {
    const prev = document.createElement('button');
    prev.className = 'slider-btn slider-btn-prev';
    prev.setAttribute('aria-label', 'Previous');
    prev.innerHTML = '&#8249;';
    prev.onclick = () => { _sliderGo(_sliderIdx - 1); _resetAuto(); };

    const next = document.createElement('button');
    next.className = 'slider-btn slider-btn-next';
    next.setAttribute('aria-label', 'Next');
    next.innerHTML = '&#8250;';
    next.onclick = () => { _sliderGo(_sliderIdx + 1); _resetAuto(); };

    outer.appendChild(prev);
    outer.appendChild(next);

    // Dots
    const dotsWrap = document.createElement('div');
    dotsWrap.className = 'slider-dots';
    dotsWrap.id = 'slider-dots';
    _sliderItems.forEach((_, i) => {
      const dot = document.createElement('button');
      dot.className = 'slider-dot' + (i === 0 ? ' active' : '');
      dot.setAttribute('aria-label', `Slide ${i + 1}`);
      dot.onclick = () => { _sliderGo(i); _resetAuto(); };
      dotsWrap.appendChild(dot);
    });
    outer.appendChild(dotsWrap);
  }

  // Touch / swipe
  let touchStartX = 0;
  outer.addEventListener('touchstart', e => { touchStartX = e.touches[0].clientX; }, { passive: true });
  outer.addEventListener('touchend', e => {
    const diff = touchStartX - e.changedTouches[0].clientX;
    if (Math.abs(diff) > 40) { _sliderGo(_sliderIdx + (diff > 0 ? 1 : -1)); _resetAuto(); }
  });
}

function _sliderGo(n) {
  _sliderIdx = ((n % _sliderItems.length) + _sliderItems.length) % _sliderItems.length;
  const track = document.getElementById('slider-track');
  if (track) track.style.transform = `translateX(-${_sliderIdx * 100}%)`;
  document.querySelectorAll('.slider-dot').forEach((d, i) => d.classList.toggle('active', i === _sliderIdx));
}

function _startSliderAuto() {
  if (_sliderItems.length < 2) return;
  _sliderTimer = setInterval(() => _sliderGo(_sliderIdx + 1), 5000);
}

function _resetAuto() {
  clearInterval(_sliderTimer);
  _startSliderAuto();
}

// ── News Tabs ─────────────────────────────────────────────────
let _activeNewsSlug = 'latest-news';

function switchNewsTab(btn) {
  document.querySelectorAll('.news-tab').forEach(t => t.classList.remove('active'));
  btn.classList.add('active');
  _activeNewsSlug = btn.dataset.cat;
  const name = btn.dataset.name;
  loadNewsPosts(_activeNewsSlug);
  const linkEl = document.getElementById('news-view-all-link');
  const catEl  = document.getElementById('news-view-all-cat');
  if (linkEl) linkEl.href = `/category/${_activeNewsSlug}`;
  if (catEl)  catEl.textContent = name;
}

async function loadNewsPosts(category) {
  const grid = document.getElementById('news-posts-list');
  if (!grid) return;

  grid.innerHTML = '<div class="spinner-wrap" style="grid-column:1/-1;padding:48px"><div class="spinner"></div></div>';

  try {
    const res = await apiGet(`public/posts.php?category=${encodeURIComponent(category)}&limit=6`);
    const posts = res?.data?.posts || [];

    if (!posts.length) {
      grid.innerHTML = '<div class="news-empty">No posts found in this category yet.</div>';
      return;
    }
    grid.innerHTML = posts.map((p, i) => _newsCardHtml(p, i)).join('');
  } catch {
    grid.innerHTML = '<div class="news-empty">Failed to load posts. Please refresh.</div>';
  }
}

function _newsCardHtml(p, index) {
  const newBadge  = index < 2 ? '<span class="badge-new">🔴 New</span>' : '';
  const mustBadge = p.featured ? '<span class="badge-must">📋 Must Read</span>' : '';
  const catBadge  = p.category ? `<span class="news-card-cat-badge">${esc(p.category)}</span>` : '';
  const imgHtml   = p.image_url
    ? `<div class="news-card-img"><img src="${esc(p.image_url)}" alt="${esc(p.title)}" loading="lazy"></div>`
    : '';

  return `
    <a href="/${esc(p.slug)}/" class="news-card${p.image_url ? ' has-img' : ''}">
      ${imgHtml}
      <div class="news-card-body">
        <div class="news-card-top">
          <div class="news-card-badges">${catBadge}${newBadge}${mustBadge}</div>
          <span class="news-card-arrow">→</span>
        </div>
        <span class="news-card-title">${esc(p.title)}</span>
        <div class="news-card-meta">${fmtDate(p.published_at)} · By ${esc(p.author || 'Admin')}</div>
      </div>
    </a>`;
}

// ── Mobile Nav ────────────────────────────────────────────────
function initMobileNav() {
  const burger  = document.getElementById('nav-burger');
  const navMenu = document.getElementById('nav-menu');

  if (burger && navMenu) {
    burger.addEventListener('click', () => navMenu.classList.toggle('open'));
  }

  // Mobile dropdown toggles
  document.querySelectorAll('.nav-dropdown-trigger').forEach(trigger => {
    trigger.addEventListener('click', e => {
      if (window.innerWidth > 960) return;
      e.preventDefault();
      const menu   = trigger.nextElementSibling;
      const isOpen = menu.classList.contains('mob-open');
      document.querySelectorAll('.nav-dropdown-menu').forEach(m => m.classList.remove('mob-open'));
      document.querySelectorAll('.nav-caret').forEach(c => c.style.transform = '');
      if (!isOpen) {
        menu.classList.add('mob-open');
        trigger.querySelector('.nav-caret').style.transform = 'rotate(180deg)';
      }
    });
  });

  // Close nav on outside click
  document.addEventListener('click', e => {
    if (!e.target.closest('.navbar')) navMenu?.classList.remove('open');
  });
}

// ── Helpers ───────────────────────────────────────────────────
function esc(str) {
  if (!str) return '';
  return String(str)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;')
    .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
function fmtDate(dt) {
  if (!dt) return '';
  return new Date(dt).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
}
