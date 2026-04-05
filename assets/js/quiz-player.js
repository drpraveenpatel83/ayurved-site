/* ============================================================
   quiz-player.js — Full Quiz Engine with Anti-Cheat
   Flow: All questions one-by-one → Submit → Show all answers
   ============================================================ */

document.addEventListener('DOMContentLoaded', initQuizPage);

// State
let questions = [];
let answers   = {};   // { questionId: 'a'|'b'|'c'|'d'|null }
let current   = 0;
let attemptId = null;
let timerInterval = null;
let timeLeft  = 0;
let quizMeta  = {};

async function initQuizPage() {
  // Require login
  if (!Auth.isLoggedIn()) {
    const main = document.getElementById('quiz-main');
    if (main) showLoginGate(main);
    return;
  }

  const params = Params.all();
  quizMeta = params;

  // Build API params
  const body = buildStartBody(params);
  showSpinner(document.getElementById('quiz-main'));

  const res = await apiPost('public/quiz-start.php', body);

  if (!res.success) {
    document.getElementById('quiz-main').innerHTML = `
      <div class="empty-state"><div class="icon">⚠️</div>
      <h3>Quiz load nahi hua</h3><p>${res.message}</p>
      <a href="/" class="btn btn-primary mt-2">Home jao</a></div>`;
    return;
  }

  questions = res.data.questions;
  attemptId = res.data.attempt_id;
  timeLeft  = res.data.duration_secs || 0;

  // Initialize answer map
  questions.forEach(q => { answers[q.id] = null; });

  setupAntiCheat();
  renderQuiz();
  if (timeLeft > 0) startTimer();
}

function buildStartBody(p) {
  if (p.type === 'daily')  return { type: 'daily', date: p.date };
  if (p.type === 'random') return { type: 'random', category_id: p.category || null };
  return { type: p.type || 'practice', category_id: p.category || null };
}

// ── Anti-Cheat Setup ──────────────────────────────────────────
function setupAntiCheat() {
  document.body.classList.add('quiz-body');

  // Block right-click
  document.addEventListener('contextmenu', e => e.preventDefault());

  // Block copy/cut
  document.addEventListener('copy', e => { e.preventDefault(); Toast.warn('Content copy nahi kar sakte!'); });
  document.addEventListener('cut',  e => { e.preventDefault(); });

  // Block print screen warning (can't prevent but can warn)
  document.addEventListener('keydown', e => {
    if (e.key === 'PrintScreen' || (e.ctrlKey && (e.key === 'p' || e.key === 'P'))) {
      e.preventDefault();
      Toast.warn('Screenshot/Print allowed nahi hai!');
    }
    // Block ctrl+U (view source), ctrl+S
    if (e.ctrlKey && ['u', 'U', 's', 'S'].includes(e.key)) e.preventDefault();
  });

  // Tab switch warning
  let warnCount = 0;
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
      warnCount++;
      if (warnCount <= 2) {
        // Will show warning when user returns
      }
    } else if (warnCount > 0) {
      Toast.warn(`Tab switch detected (${warnCount}x). Exam mein dhyan rakhein!`);
    }
  });
}

// ── Render quiz UI ────────────────────────────────────────────
function renderQuiz() {
  const main = document.getElementById('quiz-main');
  if (!main) return;

  const q     = questions[current];
  const total = questions.length;
  const pct   = Math.round(((current) / total) * 100);
  const answered = Object.values(answers).filter(v => v !== null).length;

  main.innerHTML = `
    <!-- Progress -->
    <div class="quiz-progress-wrap">
      <div class="quiz-progress-bar">
        <div class="quiz-progress-fill" style="width:${pct}%"></div>
      </div>
      <div class="quiz-progress-text">Question ${current + 1} of ${total}</div>
    </div>

    <!-- Question Nav dots -->
    <div class="question-nav" id="q-nav">
      ${questions.map((qq, i) => {
        let cls = '';
        if (i === current) cls = 'current';
        else if (answers[qq.id] !== null) cls = 'answered';
        else if (i < current) cls = 'skipped';
        return `<button class="q-nav-dot ${cls}" data-i="${i}" title="Q${i+1}">${i+1}</button>`;
      }).join('')}
    </div>

    <!-- Question Card -->
    <div class="question-card" id="question-card">
      <div class="question-number">Q${current + 1} / ${total}</div>
      ${q.image_url ? `<img class="question-img" src="${q.image_url}" alt="Question image">` : ''}
      <div class="question-text">${q.question_text}</div>
      <div class="options-list" id="options-list">
        ${['a','b','c','d'].map(key => `
          <div class="option-item ${answers[q.id] === key ? 'selected' : ''}"
               data-key="${key}" onclick="selectOption('${key}')">
            <span class="option-key">${key.toUpperCase()}</span>
            <span class="option-text">${q['option_' + key]}</span>
          </div>`).join('')}
      </div>
    </div>

    <!-- Navigation -->
    <div class="quiz-nav">
      <button class="btn btn-outline btn-sm" onclick="goQuestion(${current - 1})" ${current === 0 ? 'disabled' : ''}>
        ← Prev
      </button>
      <div class="quiz-nav-center">
        <button class="btn-skip" onclick="skipQuestion()">Skip</button>
      </div>
      ${current < total - 1
        ? `<button class="btn btn-primary" onclick="goQuestion(${current + 1})">Next →</button>`
        : `<button class="btn btn-green" onclick="showSubmitSection()">Review & Submit →</button>`
      }
    </div>`;

  // Q-nav dot click
  document.querySelectorAll('.q-nav-dot').forEach(d =>
    d.addEventListener('click', () => goQuestion(+d.dataset.i)));

  // Update header info
  const headerQ = document.getElementById('header-q-info');
  if (headerQ) headerQ.textContent = `${current + 1}/${total}`;
}

function selectOption(key) {
  const q = questions[current];
  answers[q.id] = key;
  // Re-render just the options highlight
  document.querySelectorAll('.option-item').forEach(el => {
    el.classList.toggle('selected', el.dataset.key === key);
  });
  // Update nav dot
  const dots = document.querySelectorAll('.q-nav-dot');
  if (dots[current]) dots[current].classList.add('answered');
}

function goQuestion(n) {
  if (n < 0 || n >= questions.length) return;
  current = n;
  renderQuiz();
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function skipQuestion() {
  answers[questions[current].id] = null;
  if (current < questions.length - 1) goQuestion(current + 1);
  else showSubmitSection();
}

// ── Submit section ────────────────────────────────────────────
function showSubmitSection() {
  const main  = document.getElementById('quiz-main');
  const total = questions.length;
  const ans   = Object.values(answers).filter(v => v !== null).length;
  const skip  = total - ans;

  main.innerHTML = `
    <div class="submit-section">
      <h3>🎯 Quiz Submit karne ke liye ready hain?</h3>
      <p>Ek baar submit karne ke baad aap answers change nahi kar sakte</p>
      <div class="submit-stats">
        <div class="stat"><div class="val">${total}</div><div class="lbl">Total</div></div>
        <div class="stat"><div class="val" style="color:var(--green)">${ans}</div><div class="lbl">Answered</div></div>
        <div class="stat"><div class="val" style="color:var(--red)">${skip}</div><div class="lbl">Skipped</div></div>
      </div>
      <!-- Question review dots -->
      <div class="question-nav" style="justify-content:center" id="q-nav">
        ${questions.map((qq, i) => {
          const cls = answers[qq.id] !== null ? 'answered' : 'skipped';
          return `<button class="q-nav-dot ${cls}" data-i="${i}" title="Q${i+1}">${i+1}</button>`;
        }).join('')}
      </div>
      <div style="display:flex;gap:12px;justify-content:center;margin-top:18px;flex-wrap:wrap">
        <button class="btn btn-outline" onclick="goQuestion(0)">← Wapas Review karein</button>
        <button class="btn btn-green btn-lg" onclick="submitQuiz()">✅ Final Submit</button>
      </div>
    </div>`;

  document.querySelectorAll('.q-nav-dot').forEach(d =>
    d.addEventListener('click', () => goQuestion(+d.dataset.i)));
}

// ── Submit to API ─────────────────────────────────────────────
async function submitQuiz() {
  const submitBtn = document.querySelector('.btn-green.btn-lg');
  if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Submitting...'; }

  if (timerInterval) clearInterval(timerInterval);

  const answerList = questions.map(q => ({
    question_id: q.id,
    selected: answers[q.id]
  }));

  const res = await apiPost('public/quiz-submit.php', { attempt_id: attemptId, answers: answerList });

  if (!res.success) {
    Toast.error('Submit mein error: ' + res.message);
    if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = '✅ Final Submit'; }
    return;
  }

  // Redirect to result page
  window.location.href = `/pages/result.html?attempt=${res.data.attempt_id}`;
}

// ── Timer ─────────────────────────────────────────────────────
function startTimer() {
  const timerEl = document.getElementById('quiz-timer-val');

  function updateDisplay() {
    if (!timerEl) return;
    const m = Math.floor(timeLeft / 60).toString().padStart(2, '0');
    const s = (timeLeft % 60).toString().padStart(2, '0');
    timerEl.textContent = `${m}:${s}`;
    const timerWrap = timerEl.closest('.quiz-timer');
    if (timerWrap) timerWrap.classList.toggle('warning', timeLeft <= 60);
  }

  updateDisplay();
  timerInterval = setInterval(() => {
    timeLeft--;
    updateDisplay();
    if (timeLeft <= 0) {
      clearInterval(timerInterval);
      Toast.warn('Time up! Auto-submitting...');
      setTimeout(submitQuiz, 1500);
    }
  }, 1000);
}
