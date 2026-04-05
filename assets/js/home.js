/* ============================================================
   home.js — Homepage: Banner Slider + Daily Quiz + Categories
   ============================================================ */

document.addEventListener('DOMContentLoaded', async () => {
  initBannerSlider();
  await Promise.all([loadDailyQuiz(), loadCategories()]);
});

// ── Banner Slider ─────────────────────────────────────────────
async function initBannerSlider() {
  const wrap    = document.getElementById('banner-slides');
  const dotsWrap= document.getElementById('banner-dots');
  if (!wrap) return;

  // Load banners from API
  let banners = [];
  try {
    const res = await apiGet('public/banners.php');
    if (res.success && res.data.length) banners = res.data;
  } catch {}

  // Fallback default banner
  if (!banners.length) {
    banners = [{
      title: 'Ayurveda Quiz & Notes',
      subtitle: 'BAMS, AIAPGET, Govt Exams ke liye daily practice',
      image_url: '',
      link_url: ''
    }];
  }

  let current = 0;

  function render() {
    wrap.innerHTML = banners.map((b, i) => `
      <div class="banner-slide" style="${b.color ? `background:${b.color}` : ''}">
        ${b.image_url ? `<img src="${b.image_url}" alt="${b.title||''}" loading="${i===0?'eager':'lazy'}">` : ''}
        <div class="banner-content">
          ${b.title    ? `<h2>${b.title}</h2>` : ''}
          ${b.subtitle ? `<p>${b.subtitle}</p>` : ''}
          ${b.link_url ? `<a href="${b.link_url}" class="btn btn-white btn-sm mt-2">Explore →</a>` : ''}
        </div>
      </div>`).join('');

    if (dotsWrap) {
      dotsWrap.innerHTML = banners.map((_, i) =>
        `<div class="banner-dot ${i===0?'active':''}" data-i="${i}"></div>`).join('');
      dotsWrap.querySelectorAll('.banner-dot').forEach(d =>
        d.addEventListener('click', () => goTo(+d.dataset.i)));
    }
  }

  function goTo(n) {
    current = (n + banners.length) % banners.length;
    wrap.style.transform = `translateX(-${current * 100}%)`;
    document.querySelectorAll('.banner-dot').forEach((d, i) =>
      d.classList.toggle('active', i === current));
  }

  render();

  // Arrow buttons
  document.querySelector('.banner-arrow.prev')?.addEventListener('click', () => goTo(current - 1));
  document.querySelector('.banner-arrow.next')?.addEventListener('click', () => goTo(current + 1));

  // Auto-play
  if (banners.length > 1) setInterval(() => goTo(current + 1), 4500);

  // Touch swipe support
  let startX = 0;
  const slider = document.getElementById('banner-slider');
  if (slider) {
    slider.addEventListener('touchstart', e => { startX = e.touches[0].clientX; }, { passive: true });
    slider.addEventListener('touchend', e => {
      const diff = startX - e.changedTouches[0].clientX;
      if (Math.abs(diff) > 40) goTo(diff > 0 ? current + 1 : current - 1);
    });
  }
}

// ── Daily Quiz Box ─────────────────────────────────────────────
async function loadDailyQuiz() {
  const box = document.getElementById('daily-quiz-box');
  if (!box) return;

  const today = todayStr();

  try {
    const res = await apiGet(`public/daily-quiz.php?date=${today}`);
    if (!res.success || !res.data) {
      box.innerHTML = `<div class="daily-quiz-box">
        <div class="info"><h3>📅 Daily Quiz</h3><p>Aaj ka quiz abhi available nahi hai</p></div>
      </div>`;
      return;
    }

    const quiz  = res.data;
    const done  = quiz.attempted;
    const score = quiz.last_score;

    if (done) {
      box.innerHTML = `
        <div class="daily-quiz-box daily-quiz-completed">
          <div class="info">
            <h3>✅ Aaj ka Quiz Complete!</h3>
            <p>${formatDate(today)} ka daily quiz aapne complete kar liya</p>
            <div class="meta">
              <span>Score: ${score?.score ?? '-'}/${score?.total ?? 10}</span>
              <span>🔥 Streak jari rakhein</span>
            </div>
          </div>
          <div>
            <a href="/pages/result.html?attempt=${score?.attempt_id}" class="btn btn-white btn-sm">Result Dekhein →</a>
          </div>
        </div>`;
    } else {
      box.innerHTML = `
        <div class="daily-quiz-box">
          <div class="info">
            <h3>📅 Aaj ka Daily Quiz</h3>
            <p>${formatDate(today)} — ${quiz.title || '10 Important MCQs'}</p>
            <div class="meta">
              <span>10 Questions</span>
              <span>~10 Minutes</span>
            </div>
          </div>
          <div>
            <a href="/pages/quiz.html?type=daily&date=${today}" class="btn btn-white btn-lg">
              Attempt Now →
            </a>
          </div>
        </div>`;
    }
  } catch {
    box.innerHTML = `<div class="daily-quiz-box"><div class="info"><h3>📅 Daily Quiz</h3><p>Load hone mein error aaya</p></div></div>`;
  }
}

// ── Categories Grid ────────────────────────────────────────────
async function loadCategories() {
  const grid = document.getElementById('categories-grid');
  if (!grid) return;

  showSpinner(grid);

  try {
    const res = await apiGet('public/categories.php?root=1');
    if (!res.success || !res.data.length) {
      grid.innerHTML = '<div class="empty-state"><div class="icon">📚</div><p>Categories load nahi hue</p></div>';
      return;
    }

    grid.innerHTML = res.data.map(cat => `
      <a href="${getCategoryUrl(cat)}" class="category-card" style="--card-color:${cat.color}">
        <span class="icon">${cat.icon || '📚'}</span>
        <div class="name">${cat.name}</div>
        ${cat.question_count ? `<div class="count">${cat.question_count} Questions</div>` : ''}
        <div class="color-bar"></div>
      </a>`).join('');
  } catch {
    grid.innerHTML = '<div class="empty-state"><div class="icon">⚠️</div><p>Error loading categories</p></div>';
  }
}

function getCategoryUrl(cat) {
  switch (cat.type) {
    case 'bams_year':  return `/pages/bams.html?year=${cat.bams_year}&id=${cat.id}`;
    case 'samhita':    return `/pages/samhita.html`;
    case 'aiapget':    return `/pages/aiapget.html`;
    case 'govt_exam':  return `/pages/govt-exam.html`;
    default:           return `/pages/subject.html?id=${cat.id}`;
  }
}
