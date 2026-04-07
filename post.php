<?php
/**
 * Single blog post page
 * URL: /post-slug/  →  .htaccess routes here with ?slug=post-slug
 */
require_once __DIR__.'/api/helpers.php';
require_once __DIR__.'/api/db.php';

$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';

// Basic slug validation
if (!$slug || !preg_match('/^[a-z0-9][a-z0-9\-]+[a-z0-9]$/', $slug)) {
    http_response_code(404);
    include __DIR__.'/pages/404.html';
    exit;
}

$db   = getDB();
$stmt = $db->prepare(
    "SELECT id,title,slug,excerpt,content,thumbnail,category,category_slug,
            author,is_featured,view_count,published_at
     FROM posts WHERE slug=? AND is_published=1 LIMIT 1"
);
$stmt->execute([$slug]);
$post = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$post) {
    http_response_code(404);
    include __DIR__.'/pages/404.html';
    exit;
}

// Increment view count
$db->prepare("UPDATE posts SET view_count=view_count+1 WHERE id=?")->execute([$post['id']]);

// Related posts
$relStmt = $db->prepare(
    "SELECT title,slug,thumbnail,category,published_at
     FROM posts WHERE category_slug=? AND slug!=? AND is_published=1
     ORDER BY published_at DESC LIMIT 5"
);
$relStmt->execute([$post['category_slug'], $slug]);
$related = $relStmt->fetchAll(PDO::FETCH_ASSOC);

function e($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function fmtDate($dt) {
    if (!$dt) return '';
    $d = new DateTime($dt);
    return $d->format('j M, Y');
}

$title   = e($post['title']);
$desc    = e($post['excerpt'] ?: mb_substr(strip_tags($post['content'] ?? ''), 0, 160));
$thumb   = e($post['thumbnail'] ?? '');
$siteUrl = defined('SITE_URL') ? SITE_URL : 'https://ayurvedstudies.com';
?>
<!DOCTYPE html>
<html lang="hi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $title ?> – Ayurved Studies</title>
  <meta name="description" content="<?= $desc ?>">
  <meta name="theme-color" content="#1a6e3c">
  <meta property="og:title" content="<?= $title ?>">
  <meta property="og:description" content="<?= $desc ?>">
  <meta property="og:type" content="article">
  <meta property="og:url" content="<?= e($siteUrl) ?>/<?= e($post['slug']) ?>/">
  <?php if ($thumb): ?>
  <meta property="og:image" content="<?= $thumb ?>">
  <?php endif; ?>
  <link rel="stylesheet" href="/assets/css/style.css">
  <link rel="icon" type="image/png" href="/assets/img/favicon.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    /* Post content styles */
    .post-content{font-size:1rem;line-height:1.85;color:var(--text)}
    .post-content h2{font-size:1.35rem;font-weight:800;margin:28px 0 12px;color:var(--primary)}
    .post-content h3{font-size:1.1rem;font-weight:700;margin:22px 0 10px;color:var(--text)}
    .post-content p{margin-bottom:16px}
    .post-content ul,.post-content ol{margin:12px 0 16px 24px}
    .post-content li{margin-bottom:6px}
    .post-content blockquote{border-left:4px solid var(--primary);padding:12px 18px;background:#f0faf5;margin:18px 0;border-radius:0 var(--radius-sm) var(--radius-sm) 0;color:var(--text-mid)}
    .post-content img{border-radius:var(--radius);margin:18px 0;width:100%}
    .post-content table{width:100%;border-collapse:collapse;margin:16px 0;font-size:0.9rem}
    .post-content th{background:var(--primary);color:#fff;padding:10px 12px;text-align:left}
    .post-content td{padding:8px 12px;border-bottom:1px solid var(--border)}
    .post-content tr:nth-child(even) td{background:#f8f9fa}
    .post-content a{color:var(--primary);text-decoration:underline}
    .post-header{background:#fff;border-radius:var(--radius);padding:28px;box-shadow:var(--shadow);margin-bottom:24px}
    .post-thumb{aspect-ratio:16/9;overflow:hidden;border-radius:var(--radius);margin-bottom:24px}
    .post-thumb img{width:100%;height:100%;object-fit:cover}
    .post-breadcrumb{font-size:0.78rem;color:var(--text-mid);margin-bottom:12px}
    .post-breadcrumb a{color:var(--primary)}
    .post-heading{font-size:1.8rem;font-weight:800;line-height:1.35;margin-bottom:14px}
    .post-byline{display:flex;align-items:center;gap:16px;font-size:0.8rem;color:var(--text-mid);padding-top:14px;border-top:1px solid var(--border);flex-wrap:wrap}
    .post-body{background:#fff;border-radius:var(--radius);padding:28px;box-shadow:var(--shadow)}
    .related-section{margin-top:28px}
    .related-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}
    @media(max-width:700px){.related-grid{grid-template-columns:1fr}.post-heading{font-size:1.35rem}}
  </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar">
  <div class="container nav-inner">
    <a href="/" class="nav-brand">
      <div class="nav-brand-logo">🌿</div>
      <div class="nav-brand-text">
        <span class="site-name">Ayurved Studies</span>
        <span class="site-tagline">NCISM · AIAPGET · BAMS · AYUSH</span>
      </div>
    </a>
    <div class="nav-menu" id="nav-menu">
      <a href="/">Home</a>
      <a href="/category/bams">BAMS</a>
      <a href="/category/aiapget">AIAPGET</a>
      <a href="/category/ncism">NCISM</a>
      <a href="/category/ayush">AYUSH</a>
      <a href="/pages/quiz.html">Quiz <span class="new-badge">New</span></a>
    </div>
    <div class="nav-actions">
      <div id="nav-login-btn"><a href="/pages/login.html" class="btn btn-primary btn-sm">Login</a></div>
      <div class="hidden" id="nav-user-btn">
        <a href="/pages/profile.html" class="btn btn-outline btn-sm">👤 <span id="nav-user-name"></span></a>
      </div>
    </div>
    <button class="nav-hamburger" id="nav-burger" aria-label="Menu">☰</button>
  </div>
</nav>

<!-- Main Content -->
<div class="container" style="padding-top:28px;padding-bottom:40px">
  <div class="main-layout">

    <!-- Left: Post -->
    <div>
      <!-- Thumbnail -->
      <?php if ($post['thumbnail']): ?>
      <div class="post-thumb">
        <img src="<?= $thumb ?>" alt="<?= $title ?>" loading="eager">
      </div>
      <?php endif; ?>

      <!-- Header -->
      <div class="post-header">
        <div class="post-breadcrumb">
          <a href="/">Home</a>
          <?php if ($post['category']): ?> › <a href="/category/<?= e($post['category_slug']) ?>"><?= e($post['category']) ?></a><?php endif; ?>
          › <?= $title ?>
        </div>
        <?php if ($post['category']): ?>
        <span class="hero-tag" style="margin-bottom:12px;display:inline-block"><?= e($post['category']) ?></span>
        <?php endif; ?>
        <h1 class="post-heading"><?= $title ?></h1>
        <div class="post-byline">
          <span>✍️ <?= e($post['author'] ?: 'Admin') ?></span>
          <span>📅 <?= fmtDate($post['published_at']) ?></span>
          <span>👁 <?= number_format($post['view_count']) ?> views</span>
        </div>
      </div>

      <!-- Content -->
      <div class="post-body">
        <div class="post-content">
          <?= $post['content'] /* HTML content — sanitized on upload */ ?>
        </div>
      </div>

      <!-- Share -->
      <div style="background:#fff;border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);margin-top:20px;display:flex;align-items:center;gap:12px;flex-wrap:wrap">
        <span style="font-weight:700;font-size:0.9rem">Share karein:</span>
        <a href="https://wa.me/?text=<?= urlencode($post['title'].' - '.$siteUrl.'/'.$post['slug'].'/') ?>"
           target="_blank" rel="noopener"
           style="background:#25D366;color:#fff;padding:8px 16px;border-radius:6px;font-size:0.82rem;font-weight:600;text-decoration:none">
          📱 WhatsApp
        </a>
        <a href="https://twitter.com/intent/tweet?text=<?= urlencode($post['title']) ?>&url=<?= urlencode($siteUrl.'/'.$post['slug'].'/') ?>"
           target="_blank" rel="noopener"
           style="background:#1DA1F2;color:#fff;padding:8px 16px;border-radius:6px;font-size:0.82rem;font-weight:600;text-decoration:none">
          🐦 Twitter
        </a>
        <button onclick="navigator.clipboard.writeText(location.href).then(()=>alert('Link copied!'))"
                style="background:#f0f4f8;border:none;padding:8px 16px;border-radius:6px;font-size:0.82rem;font-weight:600;cursor:pointer">
          🔗 Copy Link
        </button>
      </div>

      <!-- Related Posts -->
      <?php if (!empty($related)): ?>
      <div class="related-section">
        <div class="section-head">
          <div class="section-title"><div class="section-title-bar"></div> Related Articles</div>
        </div>
        <div class="related-grid">
          <?php foreach ($related as $rp): ?>
          <a href="/<?= e($rp['slug']) ?>/" class="post-card">
            <div class="post-card-img">
              <?php if ($rp['thumbnail']): ?>
              <img src="<?= e($rp['thumbnail']) ?>" alt="<?= e($rp['title']) ?>" loading="lazy">
              <?php endif; ?>
            </div>
            <div class="post-card-body">
              <?php if ($rp['category']): ?><div class="post-card-cat"><?= e($rp['category']) ?></div><?php endif; ?>
              <span class="post-card-title"><?= e($rp['title']) ?></span>
              <div class="post-card-meta"><span class="date">📅 <?= fmtDate($rp['published_at']) ?></span></div>
            </div>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </div><!-- /left -->

    <!-- Sidebar -->
    <aside>
      <!-- Ad Square -->
      <div class="ad-slot ad-slot-square" style="margin-bottom:20px">Advertisement</div>

      <!-- Quiz CTA -->
      <div class="sidebar-section" style="background:linear-gradient(135deg,#1a6e3c,#0d4a28);color:#fff;border:none">
        <div style="font-size:1.4rem;margin-bottom:8px">🎯</div>
        <div style="font-weight:800;font-size:0.95rem;margin-bottom:6px">Daily Quiz</div>
        <div style="font-size:0.82rem;opacity:.85;margin-bottom:14px">Aaj ke 10 MCQ solve karein. BAMS & AIAPGET ke liye.</div>
        <a href="/pages/quiz.html?type=daily" style="background:#fff;color:var(--primary);font-weight:700;font-size:0.82rem;padding:8px 16px;border-radius:6px;display:inline-block">Start Quiz →</a>
      </div>

      <!-- Categories -->
      <div class="sidebar-section">
        <div class="sidebar-title">📂 Categories</div>
        <div class="cat-chips">
          <a href="/category/bams" class="cat-chip">BAMS</a>
          <a href="/category/aiapget" class="cat-chip">AIAPGET</a>
          <a href="/category/ncism" class="cat-chip">NCISM</a>
          <a href="/category/ayush" class="cat-chip">AYUSH</a>
          <a href="/category/notes" class="cat-chip">Notes</a>
          <a href="/category/govt-exam" class="cat-chip">Govt Exam</a>
          <a href="/category/samhita" class="cat-chip">Samhita</a>
        </div>
      </div>

      <!-- Ad Square 2 -->
      <div class="ad-slot ad-slot-square">Advertisement</div>
    </aside>

  </div><!-- /main-layout -->
</div>

<!-- Footer -->
<footer class="footer">
  <div class="container footer-top">
    <div class="footer-grid">
      <div class="footer-brand">
        <div class="site-name">🌿 Ayurved Studies</div>
        <p>BAMS, AIAPGET, NCISM aur Govt Exams ke liye India ka best free Ayurveda education platform.</p>
      </div>
      <div class="footer-col">
        <h4>BAMS</h4>
        <a href="/category/bams-1st-year">1st Year</a>
        <a href="/category/bams-2nd-year">2nd Year</a>
        <a href="/category/bams-3rd-year">3rd Year</a>
        <a href="/category/samhita">Samhita</a>
      </div>
      <div class="footer-col">
        <h4>Exams</h4>
        <a href="/category/aiapget">AIAPGET</a>
        <a href="/category/govt-exam">Govt Exams</a>
        <a href="/category/ncism">NCISM</a>
        <a href="/pages/quiz.html">Daily Quiz</a>
      </div>
      <div class="footer-col">
        <h4>Account</h4>
        <a href="/pages/login.html">Login / Register</a>
        <a href="/pages/profile.html">My Profile</a>
        <a href="/sitemap.xml">Sitemap</a>
      </div>
    </div>
  </div>
  <div class="footer-bottom">
    <div class="container">© 2025 Ayurved Studies. All rights reserved. | Educational purpose only.</div>
  </div>
</footer>

<script src="/assets/js/app.js"></script>
</body>
</html>
