/* ============================================================
   blog.js — Blog Posts listing page
   ============================================================ */

const BLOG_LIMIT = 12;
let _blogOffset   = 0;
let _blogCategory = '';
let _blogSearch   = '';
let _blogTotal    = 0;

document.addEventListener('DOMContentLoaded', () => {
  // Read ?category from URL if present
  const urlCat = new URLSearchParams(location.search).get('category') || '';
  if (urlCat) {
    _blogCategory = urlCat;
    const tab = document.querySelector(`.blog-filter-tab[data-cat="${urlCat}"]`);
    if (tab) {
      document.querySelectorAll('.blog-filter-tab').forEach(t => t.classList.remove('active'));
      tab.classList.add('active');
    }
  }

  loadBlogPosts(true);
  initBlogSearch();
  initBlogMobileNav();
});

// ── Category Filter ───────────────────────────────────────────
function setCatFilter(btn) {
  document.querySelectorAll('.blog-filter-tab').forEach(t => t.classList.remove('active'));
  btn.classList.add('active');
  _blogCategory = btn.dataset.cat;
  _blogOffset   = 0;
  loadBlogPosts(true);
}

// ── Search ────────────────────────────────────────────────────
function initBlogSearch() {
  const input = document.getElementById('blog-search');
  if (!input) return;
  let timer;
  input.addEventListener('input', () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
      _blogSearch = input.value.trim();
      _blogOffset = 0;
      loadBlogPosts(true);
    }, 380);
  });
  input.addEventListener('keydown', e => {
    if (e.key === 'Enter') {
      clearTimeout(timer);
      _blogSearch = input.value.trim();
      _blogOffset = 0;
      loadBlogPosts(true);
    }
  });
}

// ── Load Posts ────────────────────────────────────────────────
async function loadBlogPosts(reset = false) {
  const grid       = document.getElementById('blog-posts-grid');
  const countEl    = document.getElementById('blog-count');
  if (!grid) return;

  if (reset) {
    _blogOffset = 0;
    grid.innerHTML = '<div style="grid-column:1/-1" class="spinner-wrap"><div class="spinner"></div></div>';
  }

  let url = `public/posts.php?limit=${BLOG_LIMIT}&offset=${_blogOffset}`;
  if (_blogCategory) url += `&category=${encodeURIComponent(_blogCategory)}`;
  if (_blogSearch)   url += `&search=${encodeURIComponent(_blogSearch)}`;

  try {
    const res = await apiGet(url);
    const { posts = [], total = 0 } = res?.data || {};
    _blogTotal = total;

    if (reset) grid.innerHTML = '';

    // Remove any existing "load more" row before re-render
    const oldMore = document.getElementById('blog-load-more-row');
    if (oldMore) oldMore.remove();

    if (!posts.length && reset) {
      grid.innerHTML = `
        <div class="blog-empty" style="grid-column:1/-1">
          <div class="icon">📭</div>
          <p>No posts found${_blogSearch ? ` for "<strong>${_esc(_blogSearch)}</strong>"` : ''}.
          ${_blogCategory ? '<br>Try selecting a different category.' : ''}</p>
        </div>`;
      if (countEl) countEl.textContent = '0 results';
      return;
    }

    posts.forEach(p => {
      const card = document.createElement('a');
      card.href = `/${_esc(p.slug)}/`;
      card.className = 'post-card';
      const thumb = p.thumbnail
        ? `<img src="${_esc(p.thumbnail)}" alt="${_esc(p.title)}" loading="lazy">`
        : `<div style="background:#eef2f6;height:100%;display:flex;align-items:center;justify-content:center;font-size:2.4rem">📝</div>`;
      card.innerHTML = `
        <div class="post-card-img">${thumb}</div>
        <div class="post-card-body">
          ${p.category ? `<div class="post-card-cat">${_esc(p.category)}</div>` : ''}
          <span class="post-card-title">${_esc(p.title)}</span>
          ${p.excerpt ? `<p class="post-card-excerpt">${_esc(_trunc(p.excerpt, 90))}</p>` : ''}
          <div class="post-card-meta">
            <span class="date">📅 ${_fmtDate(p.published_at)}</span>
            <span class="post-card-read">Read →</span>
          </div>
        </div>`;
      grid.appendChild(card);
    });

    _blogOffset += posts.length;
    if (countEl) countEl.textContent = `${total} post${total !== 1 ? 's' : ''}`;

    // Load more button
    if (_blogOffset < total) {
      const moreRow = document.createElement('div');
      moreRow.id = 'blog-load-more-row';
      moreRow.style.cssText = 'grid-column:1/-1;text-align:center;margin-top:16px';
      moreRow.innerHTML = `<button class="btn btn-outline" onclick="loadBlogPosts(false)">Load More Posts</button>`;
      grid.appendChild(moreRow);
    }

  } catch {
    if (reset) grid.innerHTML = '<div class="blog-empty" style="grid-column:1/-1"><div class="icon">⚠️</div><p>Failed to load posts. Please refresh.</p></div>';
  }
}

// ── Mobile Nav ────────────────────────────────────────────────
function initBlogMobileNav() {
  const burger  = document.getElementById('nav-burger');
  const navMenu = document.getElementById('nav-menu');
  if (burger && navMenu) {
    burger.addEventListener('click', () => navMenu.classList.toggle('open'));
  }
  document.querySelectorAll('.nav-dropdown-trigger').forEach(trigger => {
    trigger.addEventListener('click', e => {
      if (window.innerWidth > 960) return;
      e.preventDefault();
      const menu = trigger.nextElementSibling;
      const isOpen = menu.classList.contains('mob-open');
      document.querySelectorAll('.nav-dropdown-menu').forEach(m => m.classList.remove('mob-open'));
      if (!isOpen) menu.classList.add('mob-open');
    });
  });
  document.addEventListener('click', e => {
    if (!e.target.closest('.navbar')) navMenu?.classList.remove('open');
  });
}

// ── Helpers ───────────────────────────────────────────────────
function _esc(str) {
  if (!str) return '';
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function _trunc(str, n) {
  return str && str.length > n ? str.slice(0, n).trimEnd() + '…' : (str || '');
}
function _fmtDate(dt) {
  if (!dt) return '';
  return new Date(dt).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
}
