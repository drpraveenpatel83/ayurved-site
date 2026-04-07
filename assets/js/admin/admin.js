/* ============================================================
   admin.js — Full Admin Panel Logic
   ============================================================ */

let quillEditor     = null;
let postQuillEditor = null;
let allCategories   = [];
let currentPage     = { questions: 1, notes: 1, posts: 1 };
let postsTotal      = 0;

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
    dashboard:'Dashboard', posts:'Blog Posts', questions:'Questions',
    'daily-quiz':'Daily Quiz', notes:'Notes & Syllabus',
    categories:'Categories', banners:'Banners'
  };
  document.getElementById('panel-title').textContent = titles[name] || name;

  // Lazy load
  if (name === 'posts')      loadPosts();
  if (name === 'notes')      loadNotesList();
  if (name === 'tests')      loadTests();
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
  const notesHtmlEl = document.getElementById('notes-html-code');
  const content = (notesHtmlEl && notesHtmlEl.style.display !== 'none')
    ? notesHtmlEl.value
    : (quillEditor ? quillEditor.root.innerHTML : '');
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
      <div style="width:140px;height:52px;border-radius:6px;overflow:hidden;flex-shrink:0;background:#f0f4f8">
        ${b.image_url ? `<img src="${escHtml(b.image_url)}" alt="" style="width:100%;height:100%;object-fit:cover" onerror="this.parentElement.style.background='#ddd'">` : '<div style="height:100%;display:flex;align-items:center;justify-content:center;font-size:1.4rem">🖼</div>'}
      </div>
      <div style="flex:1;min-width:0">
        <div style="font-weight:600;font-size:0.88rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${escHtml(b.image_url || '(no image)')}</div>
        <div style="font-size:0.78rem;color:#7F8C8D;margin-top:2px">${b.link_url ? '🔗 ' + escHtml(b.link_url) : 'No link'}</div>
      </div>
      <div style="display:flex;gap:8px;flex-shrink:0">
        <button class="btn-admin btn-admin-outline btn-admin-sm" onclick='editBanner(${JSON.stringify(b).replace(/'/g,"&#39;")})'>✏️ Edit</button>
        <button class="btn-admin btn-admin-red btn-admin-sm" onclick="deleteBanner(${b.id})">🗑</button>
      </div>
    </div>`).join('');
}

function openBannerModal(data = null) {
  document.getElementById('banner-modal-title').textContent = data ? 'Edit Banner' : 'Add Banner';
  document.getElementById('bf-id').value    = data?.id || '';
  document.getElementById('bf-img').value   = data?.image_url || '';
  document.getElementById('bf-link').value  = data?.link_url || '';
  document.getElementById('bf-color').value = data?.color || '#1a6e3c';
  // Update preview
  previewBannerUrl(data?.image_url || '');
  document.getElementById('bf-upload-status').textContent = '';
  openModal('banner-modal');
}
function editBanner(b) { openBannerModal(b); }

// Show preview when URL is typed manually
function previewBannerUrl(url) {
  const preview = document.getElementById('bf-img-preview');
  const label   = document.getElementById('bf-preview-label');
  if (!preview) return;
  if (url) {
    preview.style.background = '';
    preview.innerHTML = `<img src="${url}" alt="Preview" style="width:100%;height:100%;object-fit:cover" onerror="this.parentElement.innerHTML='<span style=color:#e74c3c;font-size:0.82rem>Image not found</span>'">`;
  } else {
    preview.style.background = '#f0f4f8';
    preview.innerHTML = '<span style="color:#95a5a6;font-size:0.82rem" id="bf-preview-label">No image selected</span>';
  }
}

// Upload image file to server
async function uploadBannerImage(input) {
  const file   = input.files[0];
  const status = document.getElementById('bf-upload-status');
  const btn    = document.getElementById('bf-upload-btn');
  if (!file) return;

  status.style.color = '#7F8C8D';
  status.textContent = '⏳ Uploading...';
  btn.disabled = true;
  btn.textContent = 'Uploading...';

  const formData = new FormData();
  formData.append('image', file);
  const token = Auth.getToken();

  try {
    const res = await fetch(`${API_BASE}/admin/upload.php`, {
      method: 'POST',
      headers: token ? { 'Authorization': `Bearer ${token}` } : {},
      body: formData
    }).then(r => r.json());

    if (res.success) {
      const url = res.data.url;
      document.getElementById('bf-img').value = url;
      previewBannerUrl(url);
      status.style.color = '#27AE60';
      status.textContent = '✅ Image uploaded successfully!';
    } else {
      status.style.color = '#E74C3C';
      status.textContent = '❌ ' + (res.message || 'Upload failed');
    }
  } catch {
    status.style.color = '#E74C3C';
    status.textContent = '❌ Upload error. Check your connection.';
  } finally {
    btn.disabled = false;
    btn.textContent = '📤 Upload Image';
    input.value = '';
  }
}

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
  ['q-modal','notes-modal','cat-modal','banner-modal','post-modal'].forEach(id => {
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

// ── Blog Posts ────────────────────────────────────────────────
const POSTS_LIMIT = 20;

async function loadPosts(page = 1) {
  currentPage.posts = page;
  const tbody = document.getElementById('posts-tbody');
  if (!tbody) return;
  tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:20px">Loading...</td></tr>';

  const search = document.getElementById('posts-search')?.value.trim() || '';
  const cat    = document.getElementById('posts-cat-filter')?.value.trim() || '';
  const offset = (page - 1) * POSTS_LIMIT;

  let url = `admin/posts/list.php?limit=${POSTS_LIMIT}&offset=${offset}`;
  if (search) url += `&search=${encodeURIComponent(search)}`;
  if (cat)    url += `&category=${encodeURIComponent(cat)}`;

  const res = await apiGet(url);
  if (!res.success) {
    tbody.innerHTML = '<tr><td colspan="8" style="color:red;text-align:center;padding:16px">Load error</td></tr>';
    return;
  }

  const { posts = [], total = 0 } = res.data;
  postsTotal = total;

  if (!posts.length) {
    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:24px;opacity:.6">Koi post nahi mili</td></tr>';
    renderPostsPagination(total, page);
    return;
  }

  tbody.innerHTML = posts.map((p, i) => `
    <tr>
      <td>${offset + i + 1}</td>
      <td style="max-width:280px">
        <div style="font-weight:600;font-size:0.85rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${escHtml(p.title)}</div>
        <div style="font-size:0.72rem;color:#888;font-family:monospace">/${escHtml(p.slug)}/</div>
      </td>
      <td>${p.category ? `<span style="background:#f0faf5;border:1px solid #c3e6d0;padding:2px 8px;border-radius:4px;font-size:0.75rem">${escHtml(p.category)}</span>` : '-'}</td>
      <td>${escHtml(p.author||'Admin')}</td>
      <td style="font-size:0.8rem;white-space:nowrap">${p.published_at ? p.published_at.slice(0,10) : '-'}</td>
      <td>${p.view_count||0}</td>
      <td>${p.is_published ? '<span style="color:green;font-weight:700">✓ Live</span>' : '<span style="color:#999">Draft</span>'}</td>
      <td style="white-space:nowrap">
        <button onclick="editPost(${p.id},'${escHtml(p.slug)}')" class="btn-admin btn-admin-outline" style="padding:4px 10px;font-size:0.75rem">Edit</button>
        <a href="/${escHtml(p.slug)}/" target="_blank" class="btn-admin btn-admin-outline" style="padding:4px 10px;font-size:0.75rem;margin-left:4px">View</a>
        <button onclick="deletePost(${p.id},'${escHtml(p.title).replace(/'/g,"\\'")}')" class="btn-admin" style="background:#fef2f2;color:#e74c3c;border:1px solid #fecaca;padding:4px 10px;font-size:0.75rem;border-radius:4px;margin-left:4px">Del</button>
      </td>
    </tr>`).join('');

  renderPostsPagination(total, page);
}

function renderPostsPagination(total, page) {
  const wrap = document.getElementById('posts-pagination');
  if (!wrap) return;
  const pages = Math.ceil(total / POSTS_LIMIT);
  if (pages <= 1) { wrap.innerHTML = `<small style="opacity:.6">${total} posts</small>`; return; }

  let html = `<small style="opacity:.6">${total} posts</small> &nbsp; `;
  for (let i = 1; i <= pages; i++) {
    html += `<button onclick="loadPosts(${i})" style="margin:2px;padding:4px 10px;border-radius:4px;border:1px solid #ddd;background:${i===page?'#1a6e3c':'#fff'};color:${i===page?'#fff':'#333'};cursor:pointer;font-size:0.8rem">${i}</button>`;
  }
  wrap.innerHTML = html;
}

let postEditorInited = false;

function openPostModal(data = null) {
  const form = document.getElementById('post-form');
  form.reset();
  document.getElementById('pf-id').value          = data?.id || '';
  document.getElementById('pf-title').value        = data?.title || '';
  document.getElementById('pf-slug').value         = data?.slug || '';
  document.getElementById('pf-category').value     = data?.category || '';
  document.getElementById('pf-category-slug').value= data?.category_slug || '';
  document.getElementById('pf-author').value       = data?.author || 'Admin';
  document.getElementById('pf-excerpt').value      = data?.excerpt || '';
  document.getElementById('pf-thumbnail').value    = data?.thumbnail || '';
  document.getElementById('pf-published').checked  = data ? !!data.is_published : true;
  document.getElementById('pf-featured').checked   = !!data?.is_featured;

  // published_at datetime-local format
  if (data?.published_at) {
    document.getElementById('pf-published-at').value = data.published_at.slice(0,16);
  } else {
    document.getElementById('pf-published-at').value = new Date().toISOString().slice(0,16);
  }

  document.getElementById('post-modal-title').textContent = data ? 'Edit Post' : 'New Post';

  // Thumbnail preview
  previewPostThumb(data?.thumbnail || '');

  // Excerpt count
  updateExcerptCount();

  // Category dropdown — try to match existing slug, else show custom
  const catSel = document.getElementById('pf-cat-select');
  const customRow = document.getElementById('pf-cat-custom-row');
  if (catSel) {
    const existingSlug = data?.category_slug || '';
    if (!existingSlug) {
      catSel.value = '';
      customRow && (customRow.style.display = 'none');
    } else if (POST_CATEGORIES[existingSlug] !== undefined) {
      catSel.value = existingSlug;
      customRow && (customRow.style.display = 'none');
    } else {
      catSel.value = '__custom__';
      customRow && (customRow.style.display = 'flex');
    }
  }

  // Reset slug manual flag for new posts
  const slugEl = document.getElementById('pf-slug');
  if (slugEl) slugEl.dataset.manual = data ? '1' : '';

  // Init post Quill editor
  if (!postEditorInited) {
    postQuillEditor = new Quill('#post-editor', {
      theme: 'snow',
      modules: {
        toolbar: [
          [{ header: [1, 2, 3, false] }],
          ['bold', 'italic', 'underline', 'strike'],
          [{ color: [] }, { background: [] }],
          [{ list: 'ordered' }, { list: 'bullet' }],
          ['blockquote', 'link', 'image'],
          ['clean']
        ]
      }
    });
    postEditorInited = true;
  }
  postQuillEditor.root.innerHTML = data?.content || '';

  openModal('post-modal');
}

async function editPost(id, slug) {
  const res = await apiGet(`public/posts.php?slug=${encodeURIComponent(slug)}`);
  if (res.success) {
    openPostModal(res.data);
  } else {
    Toast.error('Post load nahi hua');
  }
}

async function savePost(e) {
  e.preventDefault();
  const btn = document.getElementById('post-save-btn');
  btn.textContent = 'Saving...'; btn.disabled = true;

  // Get content — visual or HTML code mode
  const htmlCodeEl = document.getElementById('post-html-code');
  const content = (htmlCodeEl && htmlCodeEl.style.display !== 'none')
    ? htmlCodeEl.value
    : (postQuillEditor ? postQuillEditor.root.innerHTML : '');
  document.getElementById('pf-content').value = content;

  const payload = {
    id:            document.getElementById('pf-id').value || null,
    title:         document.getElementById('pf-title').value,
    slug:          document.getElementById('pf-slug').value,
    category:      document.getElementById('pf-category').value,
    category_slug: document.getElementById('pf-category-slug').value,
    author:        document.getElementById('pf-author').value,
    excerpt:       document.getElementById('pf-excerpt').value,
    thumbnail:     document.getElementById('pf-thumbnail').value,
    content,
    is_published:  document.getElementById('pf-published').checked ? 1 : 0,
    is_featured:   document.getElementById('pf-featured').checked  ? 1 : 0,
    published_at:  document.getElementById('pf-published-at').value || null,
  };

  const res = await apiPost('admin/posts/save.php', payload);
  btn.textContent = 'Save Post'; btn.disabled = false;

  if (res.success) {
    Toast.success(`Post ${res.data.action}! Slug: /${res.data.slug}/`);
    closeModal('post-modal');
    loadPosts(currentPage.posts);
  } else {
    Toast.error(res.message || 'Save failed');
  }
}

async function deletePost(id, title) {
  if (!confirm(`"${title}" delete karna chahte hain?\n\nYeh action undo nahi hoga.`)) return;
  const res = await apiDelete('admin/posts/delete.php', { id });
  if (res.success) { Toast.success('Post deleted'); loadPosts(currentPage.posts); }
  else Toast.error(res.message);
}

// Auto-generate slug from title
function autoSlug() {
  const slugEl = document.getElementById('pf-slug');
  if (slugEl.dataset.manual) return; // user manually typed slug
  const title = document.getElementById('pf-title').value;
  slugEl.value = makeAdminSlug(title);
}
document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('pf-slug')?.addEventListener('input', function() {
    this.dataset.manual = this.value ? '1' : '';
  });
});

// Auto-generate category slug from category name
function autoCatSlug() {
  const catSlugEl = document.getElementById('pf-category-slug');
  const catEl     = document.getElementById('pf-category');
  catSlugEl.value = makeAdminSlug(catEl.value);
}

function makeAdminSlug(str) {
  return str.toLowerCase().trim()
    .replace(/[^\w\s-]/g, '')
    .replace(/[\s_]+/g, '-')
    .replace(/^-+|-+$/g, '');
}

// ── Post Thumbnail Upload / Preview ───────────────────────────
async function uploadPostThumb(input) {
  const file   = input.files[0];
  const status = document.getElementById('pf-thumb-status');
  if (!file) return;

  status.textContent = 'Uploading...';
  status.style.color = '#555';

  const fd = new FormData();
  fd.append('image', file);

  try {
    const token = Auth.getToken();
    const res = await fetch(`${API_BASE}/admin/upload.php`, {
      method:  'POST',
      headers: token ? { 'Authorization': `Bearer ${token}` } : {},
      body:    fd
    });
    const json = await res.json();
    if (json.success) {
      document.getElementById('pf-thumbnail').value = json.data.url;
      previewPostThumb(json.data.url);
      status.textContent = '✓ Uploaded';
      status.style.color = '#27ae60';
    } else {
      status.textContent = '✗ ' + (json.message || 'Upload failed');
      status.style.color = '#e74c3c';
    }
  } catch (err) {
    status.textContent = '✗ Network error';
    status.style.color = '#e74c3c';
  }
}

function previewPostThumb(url) {
  const preview = document.getElementById('pf-thumb-preview');
  if (!preview) return;
  if (url && url.trim()) {
    preview.innerHTML = `<img src="${url}" alt="Thumbnail" style="width:100%;height:100%;object-fit:cover;border-radius:8px;">`;
  } else {
    preview.innerHTML = '<span style="color:#aaa;font-size:0.85rem">No thumbnail</span>';
  }
}

// ── Excerpt Character Counter ──────────────────────────────────
function updateExcerptCount() {
  const el    = document.getElementById('pf-excerpt');
  const count = document.getElementById('pf-excerpt-count');
  if (!el || !count) return;
  const len = el.value.length;
  count.textContent = len + '/300';
  count.style.color = len > 300 ? '#e74c3c' : len > 250 ? '#e67e22' : '#888';
}

// ── Category Dropdown Handler ──────────────────────────────────
const POST_CATEGORIES = {
  'latest-news':         'Latest News',
  'job-alerts-ayush':    'Job Alerts (AYUSH)',
  'counselling-news':    'Counselling News',
  'aiapget-news':        'AIAPGET News',
  'traditional-knowledge':'Traditional Knowledge',
  'ayush-courses':       'AYUSH Courses',
  'bams':                'BAMS',
  'ncism':               'NCISM',
  'samhita':             'Samhita',
  'aiapget':             'AIAPGET',
  'study-material':      'Study Material',
  'anatomy':             'Anatomy',
  'dravyaguna':          'Dravyaguna',
};

function onPostCatSelect() {
  const sel      = document.getElementById('pf-cat-select');
  const catEl    = document.getElementById('pf-category');
  const slugEl   = document.getElementById('pf-category-slug');
  const customRow= document.getElementById('pf-cat-custom-row');

  if (sel.value === '__custom__') {
    customRow && (customRow.style.display = 'flex');
    catEl.value  = '';
    slugEl.value = '';
  } else if (sel.value) {
    customRow && (customRow.style.display = 'none');
    slugEl.value = sel.value;
    catEl.value  = POST_CATEGORIES[sel.value] || sel.value;
  } else {
    customRow && (customRow.style.display = 'none');
    catEl.value  = '';
    slugEl.value = '';
  }
}

// ── Post Editor Mode Toggle ────────────────────────────────────
function switchPostMode(mode) {
  const visualBtn = document.getElementById('post-visual-btn');
  const htmlBtn   = document.getElementById('post-html-btn');
  const editorDiv = document.getElementById('post-editor');
  const htmlArea  = document.getElementById('post-html-code');

  if (mode === 'html') {
    // Copy Quill content to textarea
    if (postQuillEditor) htmlArea.value = postQuillEditor.root.innerHTML;
    editorDiv.style.display = 'none';
    htmlArea.style.display  = 'block';
    htmlBtn.style.background   = '#1a6e3c'; htmlBtn.style.color = '#fff';
    visualBtn.style.background = '#fff';    visualBtn.style.color = '#5a6a7a';
  } else {
    // Copy textarea HTML back to Quill
    if (postQuillEditor && htmlArea.value) postQuillEditor.root.innerHTML = htmlArea.value;
    htmlArea.style.display  = 'none';
    editorDiv.style.display = 'block';
    visualBtn.style.background = '#1a6e3c'; visualBtn.style.color = '#fff';
    htmlBtn.style.background   = '#fff';    htmlBtn.style.color = '#5a6a7a';
  }
}

// ── Notes Editor Mode Toggle ───────────────────────────────────
function switchNotesMode(mode) {
  const visualBtn = document.getElementById('notes-visual-btn');
  const htmlBtn   = document.getElementById('notes-html-btn');
  const editorDiv = document.getElementById('notes-editor');
  const htmlArea  = document.getElementById('notes-html-code');

  if (mode === 'html') {
    if (quillEditor) htmlArea.value = quillEditor.root.innerHTML;
    editorDiv.style.display = 'none';
    htmlArea.style.display  = 'block';
    htmlBtn.style.background   = '#1a6e3c'; htmlBtn.style.color = '#fff';
    visualBtn.style.background = '#fff';    visualBtn.style.color = '#5a6a7a';
  } else {
    if (quillEditor && htmlArea.value) quillEditor.root.innerHTML = htmlArea.value;
    htmlArea.style.display  = 'none';
    editorDiv.style.display = 'block';
    visualBtn.style.background = '#1a6e3c'; visualBtn.style.color = '#fff';
    htmlBtn.style.background   = '#fff';    htmlBtn.style.color = '#5a6a7a';
  }
}

// ── Mock Tests ─────────────────────────────────────────────────
async function loadTests() {
  const res = await apiGet('admin/tests/list.php');
  const tbody = document.getElementById('tests-tbody');
  if (!res.success || !res.data.length) {
    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:20px;color:#95a5a6">Koi test nahi hai. ➕ Add Test karo.</td></tr>';
    return;
  }
  const typeLabel = { aiapget: 'AIAPGET', govt_exam: 'Govt Exam' };
  tbody.innerHTML = res.data.map((t, i) => `
    <tr>
      <td>${i + 1}</td>
      <td><strong>${escHtml(t.title)}</strong>${t.description ? `<br><small style="color:#95a5a6">${escHtml(t.description)}</small>` : ''}</td>
      <td><span class="badge-active">${typeLabel[t.exam_type] || t.exam_type}</span></td>
      <td>${t.total_questions}</td>
      <td>${t.time_minutes} min</td>
      <td>${t.is_published ? '<span class="badge-published">Live</span>' : '<span class="badge-draft">Draft</span>'}</td>
      <td class="actions">
        <button class="btn-admin btn-admin-outline btn-admin-sm" onclick="openTestCsvModal(${t.id},'${escHtml(t.title).replace(/'/g,"\\'")}')">📥 CSV</button>
        <button class="btn-admin btn-admin-outline btn-admin-sm" onclick="editTest(${JSON.stringify(t).replace(/'/g,"&#39;")})">✏️</button>
        <button class="btn-admin btn-admin-red btn-admin-sm" onclick="deleteTest(${t.id})">🗑</button>
      </td>
    </tr>`).join('');
}

function openTestModal(data = null) {
  document.getElementById('test-modal-title').textContent = data ? 'Edit Test' : 'New Mock Test';
  document.getElementById('tf-id').value        = data?.id || '';
  document.getElementById('tf-title').value     = data?.title || '';
  document.getElementById('tf-type').value      = data?.exam_type || 'aiapget';
  document.getElementById('tf-total').value     = data?.total_questions || 100;
  document.getElementById('tf-time').value      = data?.time_minutes || 90;
  document.getElementById('tf-published').value = data?.is_published ?? 0;
  document.getElementById('tf-desc').value      = data?.description || '';
  openModal('test-modal');
}

function editTest(t) { openTestModal(t); }

async function saveTest(e) {
  e.preventDefault();
  const btn = document.getElementById('tf-submit-btn');
  btn.textContent = 'Saving...'; btn.disabled = true;
  const id = document.getElementById('tf-id').value;
  const body = {
    id:              id ? parseInt(id) : null,
    title:           document.getElementById('tf-title').value,
    exam_type:       document.getElementById('tf-type').value,
    total_questions: parseInt(document.getElementById('tf-total').value),
    time_minutes:    parseInt(document.getElementById('tf-time').value),
    is_published:    parseInt(document.getElementById('tf-published').value),
    description:     document.getElementById('tf-desc').value
  };
  const res = await apiPost('admin/tests/save.php', body);
  btn.textContent = 'Save & Add Questions'; btn.disabled = false;
  if (res.success) {
    closeModal('test-modal');
    Toast.success(res.message || 'Test saved!');
    loadTests();
    // Auto-open CSV upload for new tests
    if (!id && res.data?.id) openTestCsvModal(res.data.id, body.title);
  } else {
    Toast.error(res.message || 'Save failed');
  }
}

async function deleteTest(id) {
  if (!confirm('Yah test aur uske sab questions delete ho jayenge. Sure?')) return;
  const res = await apiDelete('admin/tests/delete.php', { id });
  if (res.success) { Toast.success('Test deleted'); loadTests(); }
  else Toast.error(res.message || 'Delete failed');
}

function openTestCsvModal(testId, title) {
  document.getElementById('tc-test-id').value = testId;
  document.getElementById('test-csv-title').textContent = `Upload Questions — ${title}`;
  document.getElementById('tc-result').innerHTML = '';
  const fi = document.getElementById('tc-file-input');
  if (fi) fi.value = '';
  openModal('test-csv-modal');
}

async function uploadTestCsv(file) {
  if (!file) return;
  const testId = document.getElementById('tc-test-id').value;
  const resultEl = document.getElementById('tc-result');
  resultEl.innerHTML = '<div class="spinner-wrap"><div class="spinner"></div></div>';

  const formData = new FormData();
  formData.append('csv', file);
  formData.append('test_id', testId);

  const token = localStorage.getItem('admin_token');
  try {
    const resp = await fetch(`${API_BASE}/admin/tests/import-csv.php`, {
      method: 'POST',
      headers: { 'Authorization': `Bearer ${token}` },
      body: formData
    });
    const res = await resp.json();
    if (res.success) {
      resultEl.innerHTML = `<div style="background:#E8F8F0;border:1px solid #A9DFBF;border-radius:8px;padding:14px">
        <strong style="color:#1a6e3c">✅ ${res.data.inserted} questions import ho gayi!</strong>
        ${res.data.errors > 0 ? `<br><small style="color:#E74C3C">${res.data.errors} rows skip hui (errors)</small>` : ''}
      </div>`;
      loadTests();
    } else {
      resultEl.innerHTML = `<div style="background:#fdf0f0;border:1px solid #f5c6c6;border-radius:8px;padding:12px;color:#E74C3C">${res.message}</div>`;
    }
  } catch(err) {
    resultEl.innerHTML = `<div style="color:#E74C3C">Upload failed: ${err.message}</div>`;
  }
}

function downloadTestSampleCsv() {
  const rows = [
    ['question_text','option_a','option_b','option_c','option_d','correct_option','explanation'],
    ['Charaka Samhita ke anusaar rasa kitne prakar ke hote hain?','4','5','6','8','c','Charaka ne 6 rasa bataye hain'],
    ['AIAPGET 2024 mein kitne questions the?','100','120','150','200','b','AIAPGET mein 120 questions hote hain']
  ];
  const csv = rows.map(r => r.map(c => `"${c}"`).join(',')).join('\n');
  const a = document.createElement('a');
  a.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv);
  a.download = 'test_questions_sample.csv';
  a.click();
}
