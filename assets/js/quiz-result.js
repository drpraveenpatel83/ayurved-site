/* ============================================================
   quiz-result.js — Show full result with all answers reviewed
   ============================================================ */

document.addEventListener('DOMContentLoaded', initResultPage);

async function initResultPage() {
  const params = Params.all();
  const shareToken = params.share;
  const attemptId  = params.attempt;

  if (!shareToken && !Auth.isLoggedIn()) {
    const main = document.getElementById('result-main');
    if (main) showLoginGate(main, 'Result dekhne ke liye login karein');
    return;
  }

  const main = document.getElementById('result-main');
  showSpinner(main);

  let res;
  if (shareToken) {
    res = await apiGet(`public/result-share.php?token=${shareToken}`);
  } else {
    res = await apiGet(`public/result-detail.php?attempt_id=${attemptId}`);
  }

  if (!res.success) {
    main.innerHTML = `<div class="empty-state"><div class="icon">⚠️</div><h3>${res.message}</h3></div>`;
    return;
  }

  renderResult(res.data, !!shareToken);
}

function renderResult(data, isShared) {
  const main   = document.getElementById('result-main');
  const { attempt, answers } = data;
  const score  = attempt.score;
  const total  = attempt.total_questions;
  const pct    = Math.round((score / total) * 100);
  const correct= score;
  const wrong  = answers.filter(a => !a.is_correct && a.selected_option).length;
  const skipped= answers.filter(a => !a.selected_option).length;
  const grade  = pct >= 80 ? '🏆 Excellent!' : pct >= 60 ? '✅ Good' : pct >= 40 ? '📚 Average' : '💪 Keep Practicing';

  // Build share info
  const shareLinks = attempt.share_token ? shareResult(score, total, attempt.share_token) : null;

  main.innerHTML = `
    <!-- Result Hero -->
    <div class="result-hero">
      <div class="container">
        <div class="score-circle">
          <div class="score-val">${score}<span style="font-size:1rem">/${total}</span></div>
          <div class="score-sub">${pct}%</div>
        </div>
        <h2>${grade}</h2>
        <p>${attempt.quiz_type ? attempt.quiz_type.replace('_',' ').toUpperCase() : 'QUIZ'} Result</p>
        <div class="result-stats">
          <div class="result-stat"><div class="val" style="color:#2ECC71">${correct}</div><div class="lbl">Correct</div></div>
          <div class="result-stat"><div class="val" style="color:#E74C3C">${wrong}</div><div class="lbl">Wrong</div></div>
          <div class="result-stat"><div class="val" style="color:#F39C12">${skipped}</div><div class="lbl">Skipped</div></div>
          <div class="result-stat"><div class="val">${attempt.time_taken_secs ? formatTime(attempt.time_taken_secs) : '-'}</div><div class="lbl">Time</div></div>
        </div>
      </div>
    </div>

    <div class="container">
      <!-- Share bar -->
      ${shareLinks && !isShared ? `
      <div class="section">
        <div class="share-bar">
          <button class="share-btn whatsapp" onclick="window.open('${shareLinks.whatsapp}','_blank')">
            📱 WhatsApp Share
          </button>
          <button class="share-btn twitter" onclick="window.open('${shareLinks.twitter}','_blank')">
            🐦 Twitter
          </button>
          <button class="share-btn copy" onclick="copyToClipboard(\`${shareLinks.copy.replace(/`/g, '\\`')}\`)">
            📋 Copy Link
          </button>
        </div>
      </div>` : ''}

      ${!isShared ? `
      <div class="section" style="display:flex;gap:10px;flex-wrap:wrap">
        <a href="javascript:history.back()" class="btn btn-outline">← Back</a>
        <a href="/pages/quiz.html?type=${attempt.quiz_type}&category=${attempt.category_id||''}" class="btn btn-primary">🔄 Retry Quiz</a>
        <a href="/" class="btn btn-outline">🏠 Home</a>
      </div>` : ''}

      <!-- Answer Review — ALL questions shown with correct answers -->
      <div class="section">
        <div class="section-header">
          <div class="section-title">Answer Review (Saare ${total} Questions)</div>
        </div>
        <div class="answer-review-list">
          ${answers.map((a, i) => renderAnswerItem(a, i)).join('')}
        </div>
      </div>
    </div>`;
}

function renderAnswerItem(a, i) {
  const opts = ['a','b','c','d'];
  const optionsHtml = opts.map(key => {
    let cls = 'reveal';
    if (key === a.correct_option) cls = 'correct';
    else if (key === a.selected_option && !a.is_correct) cls = 'wrong';
    const icon = key === a.correct_option ? '✅' : (key === a.selected_option && !a.is_correct) ? '❌' : '';
    return `
      <div class="option-item ${cls}">
        <span class="option-key">${key.toUpperCase()}</span>
        <span class="option-text">${a['option_' + key]}</span>
        ${icon ? `<span class="option-icon">${icon}</span>` : ''}
      </div>`;
  }).join('');

  const statusIcon = !a.selected_option ? '⬜' : a.is_correct ? '✅' : '❌';
  const statusLabel = !a.selected_option ? 'Skipped' : a.is_correct ? 'Correct' : 'Wrong';

  return `
    <div class="answer-review-item">
      <div class="q-num" style="display:flex;align-items:center;gap:8px;justify-content:space-between">
        <span>Q${i + 1}</span>
        <span>${statusIcon} ${statusLabel}</span>
      </div>
      ${a.image_url ? `<img class="question-img" src="${a.image_url}" alt="">` : ''}
      <div class="q-text">${a.question_text}</div>
      <div class="options-list">${optionsHtml}</div>
      ${a.explanation ? `
        <div class="explanation-box show">
          <strong>📖 Explanation:</strong><br>${a.explanation}
        </div>` : ''}
      ${a.source ? `<div class="text-small text-muted mt-1">📚 Source: ${a.source}</div>` : ''}
    </div>`;
}

function formatTime(secs) {
  const m = Math.floor(secs / 60);
  const s = secs % 60;
  return m > 0 ? `${m}m ${s}s` : `${s}s`;
}
