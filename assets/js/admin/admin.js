/* ============================================================
   admin.js — Full Admin Panel Logic
   ============================================================ */

let quillEditor = null;
let allCategories = [];
let currentPage = { questions: 1, notes: 1 };

// ── Init ──────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  // Mobile menu button show
  const mobBtn = document.getElementById('mob-menu-btn');
  if (window.innerWidth <= 900 && mobBtn) mobBtn.style.display = 'block';
  window.addEventListener('resize', () => {
    if (mobBtn) mobBtn.style.display = window.innerWidth <= 900 ? 'block' : 'none';
  });

  // Check auth
  if (!Auth.isLoggedIn() || !Auth.isAdmin()) {
    document.getElementById('admin-login-screen').style.display = 'flex';
    document.getElementById('admin-app').classList.add('hidden');
  } else {
    showAdminApp();
  }
});

async function adminLogin(e) {
  e.preventDefault();
  const btn   = document.getElementById('al-btn');
  const errEl = document.getElementById('al-error');
  btn.textContent = 'Logging in...'; btn.disabled = true; errEl.textContent = '';

  const res = await apiPost('public/auth/login.php', {
    email: document.getElementById('al-email').value,
    password: document.getElementById('al-pass').value
  });

  if (res.success && res.data.user.role === 'admin') {
    Auth.setToken(res.data.token);
    Auth.setUser(res.data.user);
    showAdminApp();
  } else {
    errEl.textContent = res.success ? 'Admin access required' : (res.message || 'Login failed');
    btn.textContent = 'Login'; btn.disabled = false;
  }
}

function adminLogout() {
  Auth.logout();
}

function showAdminApp() {
  document.getElementById('admin-login-screen').style.display = 'none';
  document.getElementById('admin-app').classList.remove('hidden');
  const user = Auth.getUser();
  if (user) document.getElementById('admin-name-display').textContent = user.name;

  loadDashboardStats();
  loadCategoriesForSelects();
  loadQuestions(1);
  loadDQCalendar();
}

// ── Panel Navigation ──────────────────────────────────────────
function showPanel(name) {
  document.querySelectorAll('.admin-panel').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.admin-nav a[data-panel]').forEach(a => a.classList.remove('active'));

  const panel = document.getElementById(`panel-${name}`);
  if (panel) panel.classList.add('active');

  const navLink = document.querySelector(`.admin-nav a[data-panel="${name}"]`);
  if (navLink) navLink.classList.add('active');

  const titles = {
    dashboard:'Dashboard', questions:'Questions', 'daily-quiz':'Daily Quiz',
    notes:'Notes & Syllabus', categories:'Categories', banners:'Banners'
  };
  document.getElementById('panel-title').textContent = titles[name] || name;

  // Lazy load
  if (name === 'notes')      loadNotesList();
  if (name === 'categories') loadCategoriesList();
  if (name === 'banners')    loadBannersList();

  // Close mobile sidebar
  if (window.innerWidth <= 900) toggleSidebar(false);
}

function toggleSidebar(forceOpen) {
  const sidebar  = document.getElementById('admin-sidebar');
  const overlay  = document.getElementById('sidebar-overlay');
  const isOpen   = typeof forceOpen === 'boolean' ? forceOpen : !sidebar.classList.contains('open');
  sidebar.classList.toggle('open', isOpen);
  overlay.classList.toggle('show', isOpen);
}

// ── Dashboard ─────────────────────────────────────────────────
async function loadDashboardStats() {
  const res = await apiGet('admin/stats.php');
  if (!res.success) return;
  const s = res.data;
  document.getElementById('s-questions').textContent = s.total_questions;
  document.getElementById('s-users').textContent     = s.total_users;
  document.getElementById('s-attempts').textContent  = s.total_attempts;
  document.getElementById('s-today').textContent     = s.attempts_today;
  document.getElementById('s-daily').textContent     = s.daily_quizzes;
  document.getElementById('s-notes').textContent     = s.total_notes;
}

// ── Categories for Select boxes ───────────────────────────────
async function loadCategoriesForSelects() {
  const res = await apiGet('admin/categories/list.php');
  if (!res.success) return;
  allCategories = res.data;

  const selectors = ['qf-cat','nf-cat','cf-parent','q-filter-cat','n-filter-cat'];
  selectors.forEach(id => {
    const el = document.getElementById(id);
    if (!el) return;
    const isFilter = id.startsWith('q-filter') || id.startsWith('n-filter');
    const opts = isFilter ? ['<option value="">All Categories</option>'] : ['<option value="">Select...</option>'];
    if (id === 'cf-parent') opts.push('<option value="">None (Root)</option>');
    allCategories.forEach(c => {
      const indent = c.parent_id ? '— ' : '';
      opts.push(`<option value="${c.id}">${indent}${c.name}</option>`);
    });
    el.innerHTML = opts.join('');
  });
}

// ── Questions ─────────────────────────────────────────────────
async function loadQuestions(page = 1) {
  currentPage.questions = page;
  const catId  = document.getElementById('q-filter-cat')?.value || '';
  const search = document.getElementById('q-search')?.value || '';

  const res = await apiGet(`admin/questions/list.php?page=${page}&category_id=${catId}&search=${encodeURIComponent(search)}`);
  const tbody = document.getElementById('q-tbody');
  if (!res.success) { tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;color:#E74C3C">${res.message}</td></tr>`; return; }

  const { questions, total, pages } = res.data;

  if (!questions.length) {
    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:20px;color:#7F8C8D">No questions found</td></tr>';
    document.getElementById('q-pagination').innerHTML = '';
    return;
  }

  tbody.innerHTML = questions.map((q, i) => `
    <tr>
      <td>${(page-1)*20 + i + 1}</td>
      <td style="max-width:320px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${escHtml(q.question_text)}">${escHtml(q.question_text.substring(0,80))}${q.question_text.length > 80 ? '...' : ''}</td>
      <td>${escHtml(q.category_name)}</td>
      <td><span class="badge">${q.correct_option?.toUpperCase()}</span></td>
      <td><span class="badge ${q.difficulty==='easy'?'green':q.difficulty==='hard'?'red':''}">${q.difficulty}</span></td>
      <td>${q.is_active ? '<span class="badge-published">Active</span>' : '<span class="badge-draft">Inactive</span>'}</td>
      <td class="actions">
        <button class="btn-admin btn-admin-outline btn-admin-sm" onclick='editQuestion(${JSON.stringify(q).replace(/'/g,"&#39;")})'>✏️ Edit</button>
        <button class="btn-admin btn-admin-red btn-admin-sm" onclick="deleteQuestion(${q.id})">🗑</button>
      </td>
    </tr>`).join('');

  // Pagination
  document.getElementById('q-pagination').innerHTML = Array.from({length: pages}, (_,i) =>
    `<button class="page-btn ${i+1===page?'active':''}" onclick="loadQuestions(${i+1})">${i+1}</button>`
  ).join('');
}

function openQModal(data = null) {
  document.getElementById('q-modal-title').textContent = data ? 'Edit Question' : 'Add Question';
  document.getElementById('qf-id').value          = data?.id || '';
  document.getElementById('qf-cat').value         = data?.category_id || '';
  document.getElementById('qf-text').value        = data?.question_text || '';
  document.getElementById('qf-a').value           = data?.option_a || '';
  document.getElementById('qf-b').value           = data?.option_b || '';
  document.getElementById('qf-c').value           = data?.option_c || '';
  document.getElementById('qf-d').value           = data?.option_d || '';
  document.getElementById('qf-correct').value     = data?.correct_option || '';
  document.getElementById('qf-diff').value        = data?.difficulty || 'medium';
  document.getElementById('qf-source').value      = data?.source || '';
  document.getElementById('qf-year').value        = data?.year || '';
  document.getElementById('qf-explanation').value = data?.explanation || '';
  openModal('q-modal');
}

function editQuestion(q) { openQModal(q); }

async function saveQuestion(e) {
  e.preventDefault();
  const btn = document.getElementById('qf-submit-btn');
  btn.disabled = true; btn.textContent = 'Saving...';

  const body = {
    id: parseInt(document.getElementById('qf-id').value) || 0,
    category_id: parseInt(document.getElementById('qf-cat').value),
    question_text: document.getElementById('qf-text').value,
    option_a: document.getElementById('qf-a').value,
    option_b: document.getElementById('qf-b').value,
    option_c: document.getElementById('qf-c').value,
    option_d: document.getElementById('qf-d').value,
    correct_option: document.getElementById('qf-correct').value,
    difficulty: document.getElementById('qf-diff').value,
    source: document.getElementById('qf-source').value,
    year: parseInt(document.getElementById('qf-year').value) || 0,
    explanation: document.getElementById('qf-explanation').value
  };

  const res = await apiPost('admin/questions/save.php', body);
  btn.disabled = false; btn.textContent = 'Save Question';

  if (res.success) {
    Toast.success(res.message);
    closeModal('q-modal');
    loadQuestions(currentPage.questions);
  } else {
    Toast.error(res.message);
  }
}

async function deleteQuestion(id) {
  if (!confirm('Is question ko delete karna chahte hain?')) return;
  const res = await apiPost('admin/questions/delete.php', { id });
  if (res.success) { Toast.success('Question deleted'); loadQuestions(currentPage.questions); }
  else Toast.error(res.message);
}

// ── Bulk Import CSV ───────────────────────────────────────────
function openBulkImport() {
  const panel = document.getElementById('bulk-import-panel');
  panel.classList.toggle('hidden');
}

async function uploadCsv(file) {
  if (!file) return;
  const resultEl = document.getElementById('import-result');
  resultEl.innerHTML = '<div class="spinner-wrap"><div class="spinner"></div></div>';
  resultEl.classList.remove('hidden');

  const formData = new FormData();
  formData.append('file', file);

  const token = Auth.getToken();
  const res = await fetch(`${API_BASE}/admin/questions/bulk-import.php`, {
    method: 'POST',
    headers: { 'Authorization': `Bearer ${token}` },
    body: formData
  }).then(r => r.json()).catch(() => ({ success: false, message: 'Upload error' }));

  if (res.success) {
    const d = res.data;
    resultEl.innerHTML = `
      <div style="background:#E8F8F0;border:1px solid #A9DFBF;border-radius:8px;padding:14px">
        <strong style="color:var(--green)">✅ ${d.imported} questions imported!</strong>
        ${d.errors?.length ? `<br><strong style="color:var(--red)">Errors (${d.errors.length}):</strong><ul style="margin-top:6px;font-size:0.82rem">${d.errors.map(e => `<li>${e}</li>`).join('')}</ul>` : ''}
      </div>`;
    loadQuestions(1);
  } else {
    resultEl.innerHTML = `<div style="background:#FDEDEC;border:1px solid #E74C3C;border-radius:8px;padding:14px;color:var(--red)">❌ ${res.message}</div>`;
  }
}

function downloadSampleCsv() {
  const csv = 'category_slug,question_text,option_a,option_b,option_c,option_d,correct_option,explanation,difficulty,source,year\npadartha-vigyan,Pancha Mahabhutas mein se kaunsa sabse sthool hai?,Prithvi,Jala,Agni,Vayu,a,Prithvi is the densest of all five elements.,easy,Charaka Samhita,2023';
  downloadFile('sample_questions.csv', csv, 'text/csv');
}

function downloadDailySample() {
  const csv = 'date,category_slug,q1_text,q1_a,q1_b,q1_c,q1_d,q1_correct,q1_explanation,q2_text,q2_a,q2_b,q2_c,q2_d,q2_correct,q2_explanation\n2026-05-01,padartha-vigyan,Tridosha mein sabse important kaun hai?,Vata,Pitta,Kapha,Sabhee,d,Tridoshas equally important.,Charaka ka janma kahan hua?,Varanasi,Prayagraj,Taxila,Ujjain,c,Charaka was born in Taxila.';
  downloadFile('sample_daily_quiz.csv', csv, 'text/csv');
}

function downloadFile(name, content, type) {
  const a = document.createElement('a');
  a.href = URL.createObjectURL(new Blob([content], { type }));
  a.download = name;
  a.click();
}

// ── Daily Quiz ────────────────────────────────────────────────
async function loadDQCalendar() {
  const container = document.getElementById('dq-calendar-list');
  if (!container) return;

  const res = await apiGet('admin/daily-quiz/list.php');
  if (!res.success) { container.innerHTML = '<p style="color:red">' + res.message + '</p>'; return; }

  const quizzes = res.data;
  if (!quizzes.length) {
    container.innerHTML = '<div class="empty-state"><div class="icon">📅</div><h3>No daily quizzes scheduled</h3><p>Bulk upload karein</p></div>';
    return;
  }

  container.innerHTML = `<div class="table-wrap"><table class="admin-table">
    <thead><tr><th>Date</th><th>Title</th><th>Questions</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>${quizzes.map(q => `
      <tr>
        <td>${q.quiz_date}</td>
        <td>${escHtml(q.title || 'Daily Quiz')}</td>
        <td>${q.question_count || 0} Q</td>
        <td>${q.is_published ? '<span class="badge-published">Published</span>' : '<span class="badge-draft">Draft</span>'}</td>
        <td class="actions">
          <button class="btn-admin btn-admin-outline btn-admin-sm" onclick="toggleDQPublish(${q.id}, ${q.is_published})">
            ${q.is_published ? '🔒 Unpublish' : '✅ Publish'}
          </button>
          <button class="btn-admin btn-admin-red btn-admin-sm" onclick="deleteDQ(${q.id})">🗑</button>
        </td>
      </tr>`).join('')}
    </tbody></table></div>`;
}

async function uploadDailyQuiz(file) {
  if (!file) return;
  const resultEl = document.getElementById('dq-import-result');
  resultEl.innerHTML = '<div class="spinner-wrap"><div class="spinner"></div></div>';
  resultEl.classList.remove('hidden');

  const formData = new FormData();
  formData.append('file', file);

  const res = await fetch(`${API_BASE}/admin/daily-quiz/bulk-import.php`, {
    method: 'POST',
    headers: { 'Authorization': `Bearer ${Auth.getToken()}` },
    body: formData
  }).then(r => r.json()).catch(() => ({ success: false, message: 'Upload error' }));

  if (res.success) {
    resultEl.innerHTML = `<div style="background:#E8F8F0;border:1px solid #A9DFBF;border-radius:8px;padding:14px"><strong style="color:var(--green)">✅ ${res.data.imported_days} din ka quiz import ho gaya!</strong>${res.data.errors?.length ? `<br><ul style="font-size:0.8rem;margin-top:6px">${res.data.errors.map(e=>`<li style="color:red">${e}</li>`).join('')}</ul>` : ''}</div>`;
    loadDQCalendar();
  } else {
    resultEl.innerHTML = `<div style="background:#FDEDEC;border-radius:8px;padding:14px;color:red">❌ ${res.message}</div>`;
  }
}

async function toggleDQPublish(id, current) {
  const res = await apiPost('admin/daily-quiz/toggle-publish.php', { id, is_published: current ? 0 : 1 });
  if (res.success) { Toast.success('Status updated'); loadDQCalendar(); }
  else Toast.error(res.message);
}

async function deleteDQ(id) {
  if (!confirm('Is daily quiz ko delete karna chahte hain?')) return;
  const res = await apiPost('admin/daily-quiz/delete.php', { id });
  if (res.success) { Toast.success('Deleted'); loadDQCalendar(); }
  else Toast.error(res.message);
}

function showDQTab(name) {
  document.getElementById('dq-tab-calendar').classList.toggle('hidden', name !== 'calendar');
  document.getElementById('dq-tab-upload').classList.toggle('hidden', name !== 'upload');
  document.querySelectorAll('#panel-daily-quiz .admin-tab-btn').forEach((b, i) =>
    b.classList.toggle('active', (i === 0 && name === 'calendar') || (i === 1 && name === 'upload')));
}

// ── Notes ─────────────────────────────────────────────────────
let quillInited = false;

async function loadNotesList() {
  const tbody = document.getElementById('notes-tbody');
  const catId = document.getElementById('n-filter-cat')?.value || '';
  const type  = document.getElementById('n-filter-type')?.value || '';

  const res = await apiGet(`admin/notes/list.php?category_id=${catId}&type=${type}`);
  if (!res.success) { tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;color:red">${res.message}</td></tr>`; return; }

  const notes = res.data.notes;
  if (!notes.length) {
    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:20px;color:#7F8C8D">No notes found</td></tr>';
    return;
  }

  tbody.innerHTML = notes.map((n, i) => `
    <tr>
      <td>${i+1}</td>
      <td>${escHtml(n.title)}</td>
      <td>${escHtml(n.category_name)}</td>
      <td><span class="badge-active">${n.type}</span></td>
      <td>${n.is_published ? '<span class="badge-published">Published</span>' : '<span class="badge-draft">Draft</span>'}</td>
      <td class="actions">
        <button class="btn-admin btn-admin-outline btn-admin-sm" onclick='editNotes(${JSON.stringify(n).replace(/'/g,"&#39;")})'>✏️</button>
        <button class="btn-admin btn-admin-red btn-admin-sm" onclick="deleteNotes(${n.id})">🗑</button>
      </td>
    </tr>`).join('');
}

function openNotesModal(data = null) {
  document.getElementById('notes-modal-title').textContent = data ? 'Edit Notes' : 'Add Notes';
  document.getElementById('nf-id').value        = data?.id || '';
  document.getElementById('nf-cat').value       = data?.category_id || '';
  document.getElementById('nf-type').value      = data?.type || 'short_notes';
  document.getElementById('nf-title').value     = data?.title || '';
  document.getElementById('nf-published').value = data?.is_published ?? 1;
  document.getElementById('nf-order').value     = data?.display_order || 0;

  // Init Quill editor
  if (!quillInited) {
    quillEditor = new Quill('#notes-editor', {
      theme: 'snow',
      modules: {
        toolbar: [
          [{ header: [1, 2, 3, false] }],
          ['bold', 'italic', 'underline', 'strike'],
          [{ color: [] }, { background: [] }],
          [{ list: 'ordered' }, { list: 'bullet' }],
          ['blockquote', 'code-block'],
          ['link'],
          ['clean']
        ]
      }
    });
    quillInited = true;
  }

  if (data?.content) quillEditor.root.innerHTML = data.content;
  else quillEditor.root.innerHTML = '';

  openModal('notes-modal');
}

function editNotes(n) { openNotesModal(n); }

async function saveNotes(e) {
  e.preventDefault();
  const content = quillEditor ? quillEditor.root.innerHTML : '';
  const body = {
    id:           parseInt(document.getElementById('nf-id').value) || 0,
    category_id:  parseInt(document.getElementById('nf-cat').value),
    title:        document.getElementById('nf-title').value,
    type:         document.getElementById('nf-type').value,
    content,
    is_published: parseInt(document.getElementById('nf-published').value),
    display_order:parseInt(document.getElementById('nf-order').value) || 0
  };

  const res = await apiPost('admin/notes/save.php', body);
  if (res.success) { Toast.success(res.message); closeModal('notes-modal'); loadNotesList(); }
  else Toast.error(res.message);
}

async function deleteNotes(id) {
  if (!confirm('Delete karein?')) return;
  const res = await apiPost('admin/notes/delete.php', { id });
  if (res.success) { Toast.success('Deleted'); loadNotesList(); }
  else Toast.error(res.message);
}

// ── Categories ────────────────────────────────────────────────
async function loadCategoriesList() {
  const tbody = document.getElementById('cat-tbody');
  const res   = await apiGet('admin/categories/list.php');
  if (!res.success) { tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;color:red">${res.message}</td></tr>`; return; }

  tbody.innerHTML = res.data.map((c, i) => `
    <tr>
      <td>${i+1}</td>
      <td>${c.parent_id ? '— ' : ''}<strong>${escHtml(c.name)}</strong></td>
      <td>${escHtml(c.parent_name || '-')}</td>
      <td><span class="badge-active">${c.type}</span></td>
      <td>${c.question_count}</td>
      <td>${c.is_active ? '<span class="badge-published">Active</span>' : '<span class="badge-draft">Inactive</span>'}</td>
      <td class="actions">
        <button class="btn-admin btn-admin-outline btn-admin-sm" onclick='editCategory(${JSON.stringify(c).replace(/'/g,"&#39;")})'>✏️</button>
        <button class="btn-admin btn-admin-red btn-admin-sm" onclick="deleteCategory(${c.id})">🗑</button>
      </td>
    </tr>`).join('');
}

function openCatModal(data = null) {
  document.getElementById('cat-modal-title').textContent = data ? 'Edit Category' : 'Add Category';
  document.getElementById('cf-id').value     = data?.id || '';
  document.getElementById('cf-name').value   = data?.name || '';
  document.getElementById('cf-type').value   = data?.type || 'subject';
  document.getElementById('cf-parent').value = data?.parent_id || '';
  document.getElementById('cf-year').value   = data?.bams_year || '';
  document.getElementById('cf-icon').value   = data?.icon || '';
  document.getElementById('cf-color').value  = data?.color || '#E67E22';
  document.getElementById('cf-order').value  = data?.display_order || 0;
  document.getElementById('cf-active').value = data?.is_active ?? 1;
  openModal('cat-modal');
}

function editCategory(c) { openCatModal(c); }
function onCatTypeChange() {
  const type  = document.getElementById('cf-type').value;
  const yf    = document.getElementById('cf-year-field');
  if (yf) yf.classList.toggle('hidden', type !== 'bams_year');
}

async function saveCategory(e) {
  e.preventDefault();
  const body = {
    id:           parseInt(document.getElementById('cf-id').value) || 0,
    name:         document.getElementById('cf-name').value,
    type:         document.getElementById('cf-type').value,
    parent_id:    parseInt(document.getElementById('cf-parent').value) || 0,
    bams_year:    parseInt(document.getElementById('cf-year').value) || 0,
    icon:         document.getElementById('cf-icon').value,
    color:        document.getElementById('cf-color').value,
    display_order:parseInt(document.getElementById('cf-order').value) || 0,
    is_active:    parseInt(document.getElementById('cf-active').value)
  };

  const res = await apiPost('admin/categories/save.php', body);
  if (res.success) { Toast.success(res.message); closeModal('cat-modal'); loadCategoriesList(); loadCategoriesForSelects(); }
  else Toast.error(res.message);
}

async function deleteCategory(id) {
  if (!confirm('Delete karein?')) return;
  const res = await apiPost('admin/categories/delete.php', { id });
  if (res.success) { Toast.success('Deleted'); loadCategoriesList(); }
  else Toast.error(res.message);
}

// ── Banners ───────────────────────────────────────────────────
async function loadBannersList() {
  const container = document.getElementById('banners-list');
  const res = await apiGet('public/banners.php');
  if (!res.success || !res.data.length) {
    container.innerHTML = '<div class="empty-state"><div class="icon">🖼</div><h3>No banners</h3></div>';
    return;
  }
  container.innerHTML = res.data.map((b, i) => `
    <div style="display:flex;align-items:center;gap:16px;padding:12px;border:1px solid var(--border);border-radius:8px;margin-bottom:10px;background:white">
      <img src="${b.image_url}" alt="" style="width:80px;height:50px;object-fit:cover;border-radius:6px;background:#ddd" onerror="this.style.background='#ddd'">
      <div style="flex:1">
        <div style="font-weight:600">${escHtml(b.title||'(no title)')}</div>
        <div style="font-size:0.8rem;color:#7F8C8D">${escHtml(b.subtitle||'')}</div>
      </div>
      <div style="display:flex;gap:8px">
        <button class="btn-admin btn-admin-outline btn-admin-sm" onclick='editBanner(${JSON.stringify(b).replace(/'/g,"&#39;")})'>✏️</button>
        <button class="btn-admin btn-admin-red btn-admin-sm" onclick="deleteBanner(${b.id})">🗑</button>
      </div>
    </div>`).join('');
}

function openBannerModal(data = null) {
  document.getElementById('bf-id').value       = data?.id || '';
  document.getElementById('bf-img').value      = data?.image_url || '';
  document.getElementById('bf-title').value    = data?.title || '';
  document.getElementById('bf-subtitle').value = data?.subtitle || '';
  document.getElementById('bf-link').value     = data?.link_url || '';
  document.getElementById('bf-color').value    = data?.color || '#E67E22';
  openModal('banner-modal');
}
function editBanner(b) { openBannerModal(b); }

async function saveBanner(e) {
  e.preventDefault();
  const body = {
    id:        parseInt(document.getElementById('bf-id').value) || 0,
    image_url: document.getElementById('bf-img').value,
    title:     document.getElementById('bf-title').value,
    subtitle:  document.getElementById('bf-subtitle').value,
    link_url:  document.getElementById('bf-link').value,
    color:     document.getElementById('bf-color').value
  };
  const res = await apiPost('admin/banners/save.php', body);
  if (res.success) { Toast.success(res.message); closeModal('banner-modal'); loadBannersList(); }
  else Toast.error(res.message);
}

async function deleteBanner(id) {
  if (!confirm('Banner delete karein?')) return;
  const res = await apiPost('admin/banners/delete.php', { id });
  if (res.success) { Toast.success('Deleted'); loadBannersList(); }
  else Toast.error(res.message);
}

// ── Modal helpers ─────────────────────────────────────────────
function openModal(id) {
  const m = document.getElementById(id);
  m.classList.remove('hidden');
  m.style.display = 'block';
  document.body.style.overflow = 'hidden';
}
function closeModal(id) {
  const m = document.getElementById(id);
  m.classList.add('hidden');
  m.style.display = 'none';
  document.body.style.overflow = '';
}

// Close modal on backdrop click
document.addEventListener('click', e => {
  ['q-modal','notes-modal','cat-modal','banner-modal'].forEach(id => {
    const m = document.getElementById(id);
    if (m && e.target === m) closeModal(id);
  });
});

// ── Utility ───────────────────────────────────────────────────
function escHtml(str) {
  return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function debounce(fn, delay) {
  let t;
  return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), delay); };
}
