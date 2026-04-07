<?php
/**
 * Single blog post page
 * URL: /post-slug/  →  .htaccess routes here with ?slug=post-slug
 */
require_once __DIR__.'/api/helpers.php';
require_once __DIR__.'/api/db.php';

$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';

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
     ORDER BY published_at DESC LIMIT 4"
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

// Insert in-content ad after 2nd paragraph
function insertInContentAd($content) {
    $adCode = '<div class="ad-in-content">
<ins class="adsbygoogle"
     style="display:block;text-align:center"
     data-ad-layout="in-article"
     data-ad-format="fluid"
     data-ad-client="ca-pub-XXXXXXXXXXXXXXXXX"
     data-ad-slot="XXXXXXXXXX"></ins>
<script>(adsbygoogle = window.adsbygoogle || []).push({});</script>
</div>';
    // Insert after 2nd closing </p>
    $count = 0;
    $pos   = 0;
    while ($count < 2 && ($pos = stripos($content, '</p>', $pos)) !== false) {
        $count++;
        $pos += 4;
    }
    if ($count === 2) {
        return substr($content, 0, $pos) . $adCode . substr($content, $pos);
    }
    return $content;
}

$contentWithAd = insertInContentAd($post['content'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
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

  <!-- Google AdSense — SIRF post page pe load hota hai -->
  <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-XXXXXXXXXXXXXXXXX" crossorigin="anonymous"></script>

  <link rel="stylesheet" href="/assets/css/style.css">
  <link rel="icon" type="image/png" href="/assets/img/favicon.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    /* ── Post Page Layout ─────────────────────────────── */
    .post-wrap{display:grid;grid-template-columns:1fr 320px;gap:28px;align-items:start;padding:28px 0 48px}
    @media(max-width:960px){.post-wrap{grid-template-columns:1fr}}
    .post-sidebar{position:sticky;top:80px;display:flex;flex-direction:column;gap:20px}
    @media(max-width:960px){.post-sidebar{display:none}}

    /* ── Post Content ─────────────────────────────────── */
    .post-thumb{border-radius:var(--radius);overflow:hidden;margin-bottom:20px;aspect-ratio:16/9}
    .post-thumb img{width:100%;height:100%;object-fit:cover}
    .post-header{background:#fff;border-radius:var(--radius);padding:24px 28px;box-shadow:var(--shadow);margin-bottom:20px}
    .post-breadcrumb{font-size:0.78rem;color:var(--text-mid);margin-bottom:14px}
    .post-breadcrumb a{color:var(--primary);text-decoration:none}
    .post-cat-tag{display:inline-block;background:#e8f5ee;color:var(--primary);font-size:0.72rem;font-weight:700;padding:3px 10px;border-radius:20px;margin-bottom:12px;text-transform:uppercase;letter-spacing:.5px}
    .post-heading{font-size:1.75rem;font-weight:800;line-height:1.35;color:var(--text);margin-bottom:16px}
    @media(max-width:600px){.post-heading{font-size:1.3rem}}
    .post-byline{display:flex;align-items:center;gap:18px;font-size:0.8rem;color:var(--text-mid);padding-top:14px;border-top:1px solid var(--border);flex-wrap:wrap}
    .post-body{background:#fff;border-radius:var(--radius);padding:28px;box-shadow:var(--shadow);margin-bottom:20px}
    .post-content{font-size:1rem;line-height:1.9;color:var(--text)}
    .post-content h2{font-size:1.3rem;font-weight:800;margin:28px 0 12px;color:var(--primary)}
    .post-content h3{font-size:1.1rem;font-weight:700;margin:22px 0 10px;color:var(--text)}
    .post-content p{margin-bottom:16px}
    .post-content ul,.post-content ol{margin:12px 0 16px 24px}
    .post-content li{margin-bottom:6px}
    .post-content blockquote{border-left:4px solid var(--primary);padding:14px 18px;background:#f0faf5;margin:20px 0;border-radius:0 8px 8px 0;color:var(--text-mid);font-style:italic}
    .post-content img{border-radius:var(--radius);margin:18px 0;max-width:100%;height:auto}
    .post-content table{width:100%;border-collapse:collapse;margin:16px 0;font-size:0.9rem}
    .post-content th{background:var(--primary);color:#fff;padding:10px 12px;text-align:left}
    .post-content td{padding:9px 12px;border-bottom:1px solid var(--border)}
    .post-content tr:nth-child(even) td{background:#f8fafb}
    .post-content a{color:var(--primary);text-decoration:underline}
    .post-content strong{color:var(--text)}

    /* ── Ads ─────────────────────────────────────────── */
    .ad-top-banner{background:#f8f9fa;border:1px dashed #ccc;border-radius:8px;overflow:hidden;margin-bottom:20px;min-height:90px;display:flex;align-items:center;justify-content:center}
    .ad-in-content{background:#f8f9fa;border-radius:8px;overflow:hidden;margin:24px 0;min-height:250px;display:flex;align-items:center;justify-content:center}
    .ad-below-content{background:#f8f9fa;border:1px dashed #ccc;border-radius:8px;overflow:hidden;min-height:250px;display:flex;align-items:center;justify-content:center;margin-bottom:20px}
    .ad-sidebar-box{background:#f8f9fa;border:1px dashed #ccc;border-radius:8px;overflow:hidden;min-height:250px;display:flex;align-items:center;justify-content:center}

    /* ── Share Bar ────────────────────────────────────── */
    .share-bar{background:#fff;border-radius:var(--radius);padding:18px 24px;box-shadow:var(--shadow);display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:20px}
    .share-bar span{font-weight:700;font-size:0.88rem}
    .share-btn{padding:8px 16px;border-radius:6px;font-size:0.82rem;font-weight:600;text-decoration:none;border:none;cursor:pointer;color:#fff}
    .share-wa{background:#25D366}
    .share-tw{background:#1DA1F2}
    .share-copy{background:#f0f4f8;color:#333}

    /* ── Related Posts ────────────────────────────────── */
    .related-section{background:#fff;border-radius:var(--radius);padding:24px 28px;box-shadow:var(--shadow)}
    .related-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:14px;margin-top:16px}
    @media(max-width:600px){.related-grid{grid-template-columns:1fr}}
    .rel-card{display:flex;gap:12px;text-decoration:none;color:inherit;padding:10px;border-radius:8px;transition:background .2s}
    .rel-card:hover{background:#f5fbf7}
    .rel-card-img{width:80px;height:60px;flex-shrink:0;border-radius:6px;overflow:hidden;background:#e8f5ee}
    .rel-card-img img{width:100%;height:100%;object-fit:cover}
    .rel-card-body{display:flex;flex-direction:column;gap:4px}
    .rel-card-cat{font-size:0.7rem;font-weight:700;color:var(--primary);text-transform:uppercase}
    .rel-card-title{font-size:0.84rem;font-weight:600;color:var(--text);line-height:1.4}
    .rel-card-date{font-size:0.74rem;color:var(--text-mid)}

    /* ── Sidebar Widgets ──────────────────────────────── */
    .sidebar-widget{background:#fff;border-radius:var(--radius);padding:20px;box-shadow:var(--shadow)}
    .sidebar-widget-title{font-size:0.88rem;font-weight:800;color:var(--text);margin-bottom:14px;padding-bottom:10px;border-bottom:2px solid var(--primary)}
    .cat-chip-list{display:flex;flex-wrap:wrap;gap:8px}
    .cat-chip-link{background:#e8f5ee;color:var(--primary);font-size:0.78rem;font-weight:600;padding:5px 12px;border-radius:20px;text-decoration:none;transition:background .2s}
    .cat-chip-link:hover{background:var(--primary);color:#fff}
  </style>
</head>
<body>

<!-- Navbar (same as homepage) -->
<nav class="navbar">
  <div class="container nav-inner">
    <a href="/" class="nav-brand">
      <img src="/assets/img/logo.png" alt="Ayurved Studies" class="nav-brand-logo">
      <div class="nav-brand-text">
        <span class="site-name">Ayurved Studies</span>
        <span class="site-tagline">NCISM · AIAPGET · BAMS · AYUSH</span>
      </div>
    </a>
    <div class="nav-menu" id="nav-menu">
      <a href="/">Home</a>
      <div class="nav-dropdown">
        <a href="/category/bams">BAMS <span class="nav-caret">▾</span></a>
        <div class="nav-dropdown-menu">
          <a href="/category/bams-1st-year">1st Year</a>
          <a href="/category/bams-2nd-year">2nd Year</a>
          <a href="/category/bams-3rd-year">3rd Year</a>
          <a href="/category/bams-final-year">Final Year</a>
          <div class="nav-dd-divider"></div>
          <a href="/category/aiapget">AIAPGET</a>
          <a href="/category/ncism">NCISM</a>
        </div>
      </div>
      <a href="/blog-post/">Blog</a>
      <a href="/pages/quiz.html">Quiz</a>
      <div class="nav-dropdown">
        <a href="#">Follow Us <span class="nav-caret">▾</span></a>
        <div class="nav-dropdown-menu">
          <a href="https://youtube.com/@ayurvedstudies" target="_blank" rel="noopener">▶ YouTube</a>
          <a href="https://instagram.com/ayurvedstudies" target="_blank" rel="noopener">📸 Instagram</a>
          <a href="https://t.me/ayurvedstudies" target="_blank" rel="noopener">✈ Telegram</a>
          <div class="nav-dd-divider"></div>
          <a href="https://play.google.com/store/apps/details?id=co.diy4.mvewg" target="_blank" rel="noopener">📱 Android App</a>
          <a href="https://apps.apple.com/in/app/ayurved-studies/id6747332414" target="_blank" rel="noopener">🍎 iOS App</a>
        </div>
      </div>
    </div>
    <button class="nav-hamburger" id="nav-burger" onclick="toggleMobNav()" aria-label="Menu">☰</button>
  </div>
</nav>

<!-- Top Leaderboard Ad (below navbar) -->
<div class="container" style="padding-top:16px">
  <div class="ad-top-banner">
    <ins class="adsbygoogle"
         style="display:block"
         data-ad-client="ca-pub-XXXXXXXXXXXXXXXXX"
         data-ad-slot="XXXXXXXXXX"
         data-ad-format="auto"
         data-full-width-responsive="true"></ins>
    <script>(adsbygoogle = window.adsbygoogle || []).push({});</script>
  </div>
</div>

<!-- Main Content -->
<div class="container">
  <div class="post-wrap">

    <!-- ── Left: Post ──────────────────────────────────── -->
    <main>
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
          <?php if ($post['category']): ?>
           &rsaquo; <a href="/category/<?= e($post['category_slug']) ?>"><?= e($post['category']) ?></a>
          <?php endif; ?>
           &rsaquo; <?= $title ?>
        </div>
        <?php if ($post['category']): ?>
        <div class="post-cat-tag"><?= e($post['category']) ?></div>
        <?php endif; ?>
        <h1 class="post-heading"><?= $title ?></h1>
        <div class="post-byline">
          <span>✍️ <?= e($post['author'] ?: 'Admin') ?></span>
          <span>📅 <?= fmtDate($post['published_at']) ?></span>
          <span>👁 <?= number_format($post['view_count']) ?> views</span>
          <?php if ($post['is_featured']): ?><span style="background:#fff3cd;color:#856404;padding:2px 8px;border-radius:4px;font-weight:700;font-size:0.72rem">⭐ Featured</span><?php endif; ?>
        </div>
      </div>

      <!-- Post Body with in-content ad inserted -->
      <div class="post-body">
        <div class="post-content">
          <?= $contentWithAd ?>
        </div>
      </div>

      <!-- Below Content Ad -->
      <div class="ad-below-content">
        <ins class="adsbygoogle"
             style="display:block"
             data-ad-client="ca-pub-XXXXXXXXXXXXXXXXX"
             data-ad-slot="XXXXXXXXXX"
             data-ad-format="auto"
             data-full-width-responsive="true"></ins>
        <script>(adsbygoogle = window.adsbygoogle || []).push({});</script>
      </div>

      <!-- Share Bar -->
      <div class="share-bar">
        <span>Share:</span>
        <a class="share-btn share-wa"
           href="https://wa.me/?text=<?= urlencode($post['title'].' '.($siteUrl.'/'.$post['slug'].'/')) ?>"
           target="_blank" rel="noopener">📱 WhatsApp</a>
        <a class="share-btn share-tw"
           href="https://twitter.com/intent/tweet?text=<?= urlencode($post['title']) ?>&url=<?= urlencode($siteUrl.'/'.$post['slug'].'/') ?>"
           target="_blank" rel="noopener">𝕏 Twitter</a>
        <button class="share-btn share-copy"
                onclick="navigator.clipboard.writeText(location.href).then(()=>this.textContent='✓ Copied!').catch(()=>{})">🔗 Copy Link</button>
      </div>

      <!-- Related Posts -->
      <?php if (!empty($related)): ?>
      <div class="related-section">
        <div class="section-title" style="margin-bottom:4px"><div class="section-title-bar"></div>Related Articles</div>
        <div class="related-grid">
          <?php foreach ($related as $rp): ?>
          <a href="/<?= e($rp['slug']) ?>/" class="rel-card">
            <div class="rel-card-img">
              <?php if ($rp['thumbnail']): ?>
              <img src="<?= e($rp['thumbnail']) ?>" alt="<?= e($rp['title']) ?>" loading="lazy">
              <?php else: ?>
              <div style="width:100%;height:100%;background:#e8f5ee;display:flex;align-items:center;justify-content:center;font-size:1.4rem">🌿</div>
              <?php endif; ?>
            </div>
            <div class="rel-card-body">
              <?php if ($rp['category']): ?><div class="rel-card-cat"><?= e($rp['category']) ?></div><?php endif; ?>
              <div class="rel-card-title"><?= e($rp['title']) ?></div>
              <div class="rel-card-date">📅 <?= fmtDate($rp['published_at']) ?></div>
            </div>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </main>

    <!-- ── Right: Sidebar ─────────────────────────────── -->
    <aside class="post-sidebar">

      <!-- Sidebar Ad 1 -->
      <div class="ad-sidebar-box">
        <ins class="adsbygoogle"
             style="display:block"
             data-ad-client="ca-pub-XXXXXXXXXXXXXXXXX"
             data-ad-slot="XXXXXXXXXX"
             data-ad-format="auto"
             data-full-width-responsive="true"></ins>
        <script>(adsbygoogle = window.adsbygoogle || []).push({});</script>
      </div>

      <!-- Quiz CTA -->
      <div class="sidebar-widget" style="background:linear-gradient(135deg,#1a6e3c,#0d4a28);color:#fff">
        <div style="font-size:1.6rem;margin-bottom:6px">🎯</div>
        <div style="font-weight:800;font-size:1rem;margin-bottom:6px">Daily Quiz</div>
        <div style="font-size:0.82rem;opacity:.88;margin-bottom:14px;line-height:1.5">BAMS & AIAPGET ke 10 MCQ — har roz practice karo</div>
        <a href="/pages/quiz.html?type=daily" style="background:#fff;color:#1a6e3c;font-weight:700;font-size:0.82rem;padding:9px 18px;border-radius:6px;display:inline-block;text-decoration:none">Start Quiz →</a>
      </div>

      <!-- Categories -->
      <div class="sidebar-widget">
        <div class="sidebar-widget-title">📂 Categories</div>
        <div class="cat-chip-list">
          <a href="/category/latest-news" class="cat-chip-link">Latest News</a>
          <a href="/category/job-alerts-ayush" class="cat-chip-link">Job Alerts</a>
          <a href="/category/aiapget-news" class="cat-chip-link">AIAPGET</a>
          <a href="/category/counselling-news" class="cat-chip-link">Counselling</a>
          <a href="/category/bams" class="cat-chip-link">BAMS</a>
          <a href="/category/ncism" class="cat-chip-link">NCISM</a>
          <a href="/category/samhita" class="cat-chip-link">Samhita</a>
          <a href="/category/traditional-knowledge" class="cat-chip-link">Traditional</a>
          <a href="/category/ayush-courses" class="cat-chip-link">AYUSH Courses</a>
        </div>
      </div>

      <!-- App Download Widget -->
      <div class="sidebar-widget" style="text-align:center">
        <div class="sidebar-widget-title">📱 Ayurved Studies App</div>
        <p style="font-size:0.82rem;color:var(--text-mid);margin-bottom:14px">BAMS Notes, MCQ & Updates — apne phone mein</p>
        <a href="https://play.google.com/store/apps/details?id=co.diy4.mvewg" target="_blank" rel="noopener"
           style="display:block;background:#1a6e3c;color:#fff;padding:9px;border-radius:6px;font-size:0.82rem;font-weight:700;text-decoration:none;margin-bottom:8px">▶ Google Play</a>
        <a href="https://apps.apple.com/in/app/ayurved-studies/id6747332414" target="_blank" rel="noopener"
           style="display:block;background:#333;color:#fff;padding:9px;border-radius:6px;font-size:0.82rem;font-weight:700;text-decoration:none">🍎 App Store</a>
      </div>

      <!-- Sidebar Ad 2 -->
      <div class="ad-sidebar-box">
        <ins class="adsbygoogle"
             style="display:block"
             data-ad-client="ca-pub-XXXXXXXXXXXXXXXXX"
             data-ad-slot="XXXXXXXXXX"
             data-ad-format="auto"
             data-full-width-responsive="true"></ins>
        <script>(adsbygoogle = window.adsbygoogle || []).push({});</script>
      </div>

    </aside>
  </div>
</div>

<!-- Footer -->
<footer class="footer">
  <div class="container footer-top">
    <div class="footer-grid">
      <div class="footer-brand">
        <div class="site-name">🌿 Ayurved Studies</div>
        <p>BAMS, AIAPGET, NCISM aur AYUSH ke liye India ka Ayurveda education platform.</p>
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
        <a href="/category/ncism">NCISM</a>
        <a href="/category/job-alerts-ayush">Job Alerts</a>
        <a href="/pages/quiz.html">Daily Quiz</a>
      </div>
      <div class="footer-col">
        <h4>More</h4>
        <a href="/blog-post/">All Posts</a>
        <a href="/privacy-policy/">Privacy Policy</a>
        <a href="/pages/login.html">Login</a>
      </div>
    </div>
  </div>
  <div class="footer-bottom">
    <div class="container">© 2025 Ayurved Studies. All rights reserved.</div>
  </div>
</footer>

<script src="/assets/js/app.js"></script>
<script>
function toggleMobNav() {
  document.getElementById('nav-menu').classList.toggle('mob-open');
}
</script>
</body>
</html>
