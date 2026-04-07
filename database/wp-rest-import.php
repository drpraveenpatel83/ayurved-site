<?php
/**
 * ════════════════════════════════════════════════════════���═
 *  WordPress REST API → New Site Posts Import
 * ══════════════════════════════════════════════════════════
 *
 *  Yeh script WordPress ke live posts (thumbnails ke saath)
 *  is nayi site ke database mein import karta hai.
 *
 *  HOW TO USE:
 *  ───────────
 *  1. Yeh file server pe upload karo: /database/wp-rest-import.php
 *  2. Browser mein kholo:
 *     https://newsite.com/database/wp-rest-import.php
 *         ?key=AyurvedImport2025
 *         &wp=https://ayurvedstudies.com
 *         &page=1
 *
 *  3. Agar bahut saare posts hain → page=1, page=2, page=3 ... chalate jao
 *  4. Kaam hone ke baad IS FILE KO DELETE KARO server se!
 *
 *  REQUIREMENTS:
 *  ─────────────
 *  • WordPress site pe REST API enabled hona chahiye (default ON hai)
 *  • URL: https://yourwp.com/wp-json/wp/v2/posts
 *  ══════════════════════════════════════════════════════════
 */

define('IMPORT_KEY', 'AyurvedImport2025'); // ← Apna secret key yahan likho

// Security check
if (($_GET['key'] ?? '') !== IMPORT_KEY) {
    http_response_code(403);
    die('<h2 style="color:red">❌ Wrong key. Access denied.</h2>');
}

$wpBase = rtrim($_GET['wp'] ?? '', '/');
if (!$wpBase) die('<h2>❌ wp= parameter required. e.g. ?wp=https://ayurvedstudies.com</h2>');

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20; // WordPress REST API max per request

require_once dirname(__DIR__).'/api/db.php';
$db = getDB();

// ── Fetch from WordPress REST API ────────────────────────────
$apiUrl = $wpBase . '/wp-json/wp/v2/posts?'
        . http_build_query([
            'per_page' => $perPage,
            'page'     => $page,
            'status'   => 'publish',
            '_embed'   => 1, // includes author, featured image, categories
        ]);

$ctx = stream_context_create(['http' => [
    'timeout' => 30,
    'header'  => "User-Agent: AyurvedStudiesImporter/1.0\r\n"
]]);

$raw = @file_get_contents($apiUrl, false, $ctx);
if ($raw === false) {
    die("<h2>❌ WordPress site se connect nahi hua: $wpBase</h2>
         <p>Check karein: WordPress REST API enabled hai? Site accessible hai?</p>
         <p>Test URL: <a href='$apiUrl' target='_blank'>$apiUrl</a></p>");
}

// Total pages from response headers
$totalPages = 1;
foreach ($http_response_header ?? [] as $h) {
    if (stripos($h, 'X-WP-TotalPages:') === 0) {
        $totalPages = (int)trim(explode(':', $h, 2)[1]);
    }
}

$posts = json_decode($raw, true);
if (!is_array($posts)) {
    die('<h2>❌ WordPress API response parse nahi hua.</h2><pre>' . htmlspecialchars(substr($raw, 0, 500)) . '</pre>');
}

// ── Insert into database ──────────────────────────────────────
$stmt = $db->prepare("
    INSERT INTO posts
        (title, slug, excerpt, content, thumbnail, category, category_slug,
         author, is_published, is_featured, published_at)
    VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, 1, 0, ?)
    ON DUPLICATE KEY UPDATE
        title     = VALUES(title),
        content   = VALUES(content),
        excerpt   = VALUES(excerpt),
        thumbnail = COALESCE(VALUES(thumbnail), thumbnail),
        category  = COALESCE(VALUES(category), category),
        author    = VALUES(author)
");

$inserted = 0;
$updated  = 0;
$skipped  = 0;
$log      = [];

foreach ($posts as $p) {
    $title = html_entity_decode(strip_tags($p['title']['rendered'] ?? ''), ENT_QUOTES, 'UTF-8');
    $slug  = $p['slug'] ?? '';
    if (!$title || !$slug) { $skipped++; continue; }

    // Content & excerpt
    $content = $p['content']['rendered'] ?? '';
    $excerpt = strip_tags(html_entity_decode($p['excerpt']['rendered'] ?? '', ENT_QUOTES, 'UTF-8'));
    $excerpt = trim(preg_replace('/\s+/', ' ', $excerpt));
    if (strlen($excerpt) > 300) $excerpt = substr($excerpt, 0, 297) . '...';

    // Author
    $author = $p['_embedded']['author'][0]['name'] ?? 'Admin';

    // Featured image (thumbnail)
    $thumb = '';
    $media = $p['_embedded']['wp:featuredmedia'][0] ?? null;
    if ($media && !empty($media['source_url'])) {
        $thumb = $media['source_url'];
    }

    // Category — use first category
    $cat     = '';
    $catSlug = '';
    $terms   = $p['_embedded']['wp:term'] ?? [];
    foreach ($terms as $termGroup) {
        foreach ($termGroup as $term) {
            if ($term['taxonomy'] === 'category' && ($term['slug'] ?? '') !== 'uncategorized') {
                $cat     = $term['name'] ?? '';
                $catSlug = $term['slug'] ?? '';
                break 2;
            }
        }
    }

    // Date
    $pubDate = $p['date'] ?? date('Y-m-d H:i:s');

    try {
        $before = $db->lastInsertId();
        $stmt->execute([$title, $slug, $excerpt ?: null, $content ?: null,
                        $thumb ?: null, $cat ?: null, $catSlug ?: null,
                        $author, $pubDate]);
        $after = $db->lastInsertId();
        if ($after > $before) $inserted++;
        else $updated++;
        $log[] = ['status' => $after > $before ? 'new' : 'updated', 'title' => $title, 'slug' => $slug, 'thumb' => $thumb, 'cat' => $cat];
    } catch (PDOException $e) {
        $skipped++;
        $log[] = ['status' => 'error', 'title' => $title, 'slug' => $slug, 'error' => $e->getMessage()];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>WP Import Result</title>
  <style>
    body{font-family:sans-serif;max-width:860px;margin:36px auto;padding:0 20px;background:#f8f9fa}
    h2{color:#1a6e3c}
    .stats{display:flex;gap:16px;flex-wrap:wrap;margin:20px 0}
    .stat{background:#fff;border-radius:10px;padding:16px 24px;border-left:4px solid #ccc;box-shadow:0 2px 8px rgba(0,0,0,.07)}
    .stat.green{border-color:#27AE60}.stat.blue{border-color:#0170B9}.stat.orange{border-color:#E67E22}.stat.red{border-color:#E74C3C}
    .stat .n{font-size:1.8rem;font-weight:800;color:#2c3e50}.stat .l{font-size:0.82rem;color:#7F8C8D}
    table{width:100%;border-collapse:collapse;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.07)}
    th{background:#1a6e3c;color:#fff;padding:10px 14px;text-align:left;font-size:0.82rem}
    td{padding:9px 14px;border-bottom:1px solid #f0f2f5;font-size:0.82rem}
    tr:last-child td{border-bottom:none}
    .badge{display:inline-block;padding:2px 8px;border-radius:4px;font-size:0.72rem;font-weight:700}
    .badge.new{background:#e8f5ee;color:#1a6e3c}
    .badge.updated{background:#e8f4fd;color:#0170B9}
    .badge.error{background:#fdecea;color:#E74C3C}
    .nav-pages{display:flex;gap:10px;flex-wrap:wrap;margin:20px 0}
    .nav-pages a{padding:8px 16px;background:#1a6e3c;color:#fff;border-radius:6px;text-decoration:none;font-size:0.85rem}
    .nav-pages a.prev{background:#7F8C8D}
    .warn{background:#fff8e8;border:1.5px solid #f0d09a;border-radius:8px;padding:14px 18px;margin:20px 0;font-size:0.88rem}
    .warn strong{color:#b8620a}
    img.thumb{width:60px;height:40px;object-fit:cover;border-radius:4px;background:#ddd}
  </style>
</head>
<body>
<h2>✅ WordPress Import — Page <?= $page ?> / <?= $totalPages ?></h2>
<p>Source: <strong><?= htmlspecialchars($wpBase) ?></strong></p>

<div class="stats">
  <div class="stat green"><div class="n"><?= $inserted ?></div><div class="l">New Posts Inserted</div></div>
  <div class="stat blue"><div class="n"><?= $updated ?></div><div class="l">Existing Posts Updated</div></div>
  <div class="stat orange"><div class="n"><?= $skipped ?></div><div class="l">Skipped / Errors</div></div>
  <div class="stat"><div class="n"><?= $totalPages ?></div><div class="l">Total Pages in WP</div></div>
</div>

<!-- Page Navigation -->
<?php if ($totalPages > 1): ?>
<div class="nav-pages">
  <?php if ($page > 1): ?>
    <a class="prev" href="?key=<?= IMPORT_KEY ?>&wp=<?= urlencode($wpBase) ?>&page=<?= $page-1 ?>">← Prev Page</a>
  <?php endif; ?>
  <?php for ($i = 1; $i <= $totalPages; $i++): ?>
    <a href="?key=<?= IMPORT_KEY ?>&wp=<?= urlencode($wpBase) ?>&page=<?= $i ?>"
       style="<?= $i === $page ? 'background:#0d4a28' : '' ?>"><?= $i ?></a>
  <?php endfor; ?>
  <?php if ($page < $totalPages): ?>
    <a href="?key=<?= IMPORT_KEY ?>&wp=<?= urlencode($wpBase) ?>&page=<?= $page+1 ?>">Next Page →</a>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- Post Log Table -->
<table>
  <thead><tr><th>#</th><th>Thumbnail</th><th>Title</th><th>Slug</th><th>Category</th><th>Status</th></tr></thead>
  <tbody>
  <?php foreach ($log as $i => $r): ?>
    <tr>
      <td><?= $i + 1 ?></td>
      <td><?= !empty($r['thumb']) ? "<img class='thumb' src='{$r['thumb']}' alt=''>" : '—' ?></td>
      <td><?= htmlspecialchars($r['title']) ?></td>
      <td style="font-family:monospace;font-size:0.75rem">/<?= htmlspecialchars($r['slug']) ?>/</td>
      <td><?= htmlspecialchars($r['cat'] ?? '—') ?></td>
      <td>
        <?php if ($r['status'] === 'new'): ?>
          <span class="badge new">✓ New</span>
        <?php elseif ($r['status'] === 'updated'): ?>
          <span class="badge updated">↻ Updated</span>
        <?php else: ?>
          <span class="badge error">✗ <?= htmlspecialchars($r['error'] ?? 'Error') ?></span>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<?php if ($page >= $totalPages): ?>
<div class="warn" style="background:#e8f5ee;border-color:#a9dfbf;margin-top:24px">
  <strong>🎉 All pages imported!</strong><br>
  Ab <a href="/">Homepage</a> check karein — saare posts show ho rahe honge.
</div>
<?php endif; ?>

<div class="warn">
  <strong>⚠️ Important:</strong> Kaam khatam hone ke baad yeh file server se <strong>DELETE KARO</strong>:<br>
  <code>/database/wp-rest-import.php</code>
</div>

<p style="margin-top:20px"><a href="/" style="color:#1a6e3c">← Back to Site</a></p>
</body>
</html>
