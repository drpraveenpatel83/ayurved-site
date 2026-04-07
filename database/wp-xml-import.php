<?php
/**
 * ══════════════════════════════════════════════════════
 *  WordPress XML (WXR) → New Site Posts Import
 * ══════════════════════════════════════════════════════
 *
 *  HOW TO USE:
 *  ───────────
 *  1. WordPress se download ki hui XML file ka naam karo:
 *       wordpress-posts.xml
 *     aur upload karo:
 *       /database/wordpress-posts.xml
 *
 *  2. Browser mein kholo:
 *       https://ayurvedstudies.com/database/wp-xml-import.php?key=AyurvedImport2025
 *
 *  3. Kaam hone ke baad DONO files delete karo:
 *       /database/wp-xml-import.php
 *       /database/wordpress-posts.xml
 * ══════════════════════════════════════════════════════
 */

define('IMPORT_KEY', 'AyurvedImport2025');

// ── Security check ────────────────────────────────────
if (($_GET['key'] ?? '') !== IMPORT_KEY) {
    http_response_code(403);
    die('<h2 style="color:red">❌ Wrong key. Access denied.</h2>');
}

// ── Find XML file ─────────────────────────────────────
$xmlFile = __DIR__ . '/wordpress-posts.xml';
if (!file_exists($xmlFile)) {
    die('<h2 style="color:red">❌ XML file nahi mili!</h2>
         <p>Yeh file yahan rakho: <code>/database/wordpress-posts.xml</code></p>
         <p>WordPress se export ki hui XML file ka naam rename karo → <strong>wordpress-posts.xml</strong></p>');
}

// ── Parse XML ─────────────────────────────────────────
libxml_use_internal_errors(true);
$xml = simplexml_load_file($xmlFile);
if (!$xml) {
    $errors = implode('<br>', array_map(fn($e) => $e->message, libxml_get_errors()));
    die("<h2>❌ XML parse error</h2><p>$errors</p>");
}

// Register WordPress namespaces
$xml->registerXPathNamespace('wp',      'http://wordpress.org/export/1.2/');
$xml->registerXPathNamespace('content', 'http://purl.org/rss/1.0/modules/content/');
$xml->registerXPathNamespace('excerpt', 'http://wordpress.org/export/1.2/excerpt/');
$xml->registerXPathNamespace('dc',      'http://purl.org/dc/elements/1.1/');

// ── DB connect ────────────────────────────────────────
require_once dirname(__DIR__) . '/api/db.php';
$db = getDB();

// ── Prepare statement ─────────────────────────────────
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
        category  = COALESCE(VALUES(category),  category),
        author    = VALUES(author)
");

$inserted = 0;
$updated  = 0;
$skipped  = 0;
$log      = [];

// ── Build attachment map (id → url) for thumbnails ───
// WordPress XML mein attachments alag items hote hain
$attachMap = [];
foreach ($xml->channel->item as $item) {
    $wp = $item->children('http://wordpress.org/export/1.2/');
    if ((string)$wp->post_type === 'attachment') {
        $id  = (string)$wp->post_id;
        $url = (string)$wp->attachment_url;
        if ($id && $url) $attachMap[$id] = $url;
    }
}

// ── Process posts ─────────────────────────────────────
foreach ($xml->channel->item as $item) {
    $wp      = $item->children('http://wordpress.org/export/1.2/');
    $content = $item->children('http://purl.org/rss/1.0/modules/content/');
    $excerpt = $item->children('http://wordpress.org/export/1.2/excerpt/');
    $dc      = $item->children('http://purl.org/dc/elements/1.1/');

    // Only published posts
    if ((string)$wp->post_type !== 'post')      { continue; }
    if ((string)$wp->status    !== 'publish')   { $skipped++; continue; }

    $title = html_entity_decode(trim((string)$item->title), ENT_QUOTES, 'UTF-8');
    $slug  = trim((string)$wp->post_name);

    if (!$title || !$slug) { $skipped++; continue; }

    // Slug: only lowercase alphanumeric + hyphens
    $slug = preg_replace('/[^a-z0-9\-]/', '-', strtolower($slug));
    $slug = preg_replace('/-+/', '-', trim($slug, '-'));
    if (!$slug) { $skipped++; continue; }

    // Content
    $postContent = (string)$content->encoded;

    // Excerpt — prefer explicit, else auto-generate from content
    $postExcerpt = trim(strip_tags(html_entity_decode((string)$excerpt->encoded, ENT_QUOTES, 'UTF-8')));
    if (!$postExcerpt) {
        $postExcerpt = trim(strip_tags($postContent));
        $postExcerpt = preg_replace('/\s+/', ' ', $postExcerpt);
    }
    if (strlen($postExcerpt) > 300) $postExcerpt = substr($postExcerpt, 0, 297) . '...';

    // Author
    $author = trim((string)$dc->creator) ?: 'Admin';

    // Date
    $pubDate = trim((string)$wp->post_date);
    if (!$pubDate || $pubDate === '0000-00-00 00:00:00') {
        $pubDate = date('Y-m-d H:i:s');
    }

    // Category — first non-uncategorized
    $cat     = '';
    $catSlug = '';
    foreach ($item->category as $catEl) {
        $domain   = (string)($catEl->attributes()['domain'] ?? '');
        $nicename = (string)($catEl->attributes()['nicename'] ?? '');
        if ($domain === 'category' && $nicename !== 'uncategorized') {
            $cat     = html_entity_decode(trim((string)$catEl), ENT_QUOTES, 'UTF-8');
            $catSlug = $nicename;
            break;
        }
    }

    // Thumbnail — from postmeta _thumbnail_id → attachMap
    $thumb = '';
    foreach ($wp->postmeta as $meta) {
        if ((string)$meta->meta_key === '_thumbnail_id') {
            $thumbId = (string)$meta->meta_value;
            $thumb   = $attachMap[$thumbId] ?? '';
            break;
        }
    }

    // ── Insert ────────────────────────────────────────
    try {
        $before = $db->lastInsertId();
        $stmt->execute([
            $title,
            $slug,
            $postExcerpt ?: null,
            $postContent ?: null,
            $thumb       ?: null,
            $cat         ?: null,
            $catSlug     ?: null,
            $author,
            $pubDate,
        ]);
        $after = $db->lastInsertId();
        if ($after > $before) { $inserted++; $status = 'new'; }
        else                  { $updated++;  $status = 'updated'; }

        $log[] = compact('status', 'title', 'slug', 'cat', 'thumb');
    } catch (PDOException $e) {
        $skipped++;
        $log[] = ['status'=>'error','title'=>$title,'slug'=>$slug,'cat'=>$cat,'thumb'=>'','error'=>$e->getMessage()];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>WP XML Import Result</title>
  <style>
    *{box-sizing:border-box}
    body{font-family:sans-serif;max-width:960px;margin:36px auto;padding:0 20px;background:#f5f7fa;color:#2c3e50}
    h2{color:#1a6e3c;margin-bottom:4px}
    .stats{display:flex;gap:14px;flex-wrap:wrap;margin:20px 0}
    .stat{background:#fff;border-radius:10px;padding:14px 22px;border-left:4px solid #ccc;box-shadow:0 2px 8px rgba(0,0,0,.07)}
    .stat.g{border-color:#27AE60}.stat.b{border-color:#2980B9}.stat.o{border-color:#E67E22}.stat.r{border-color:#E74C3C}
    .stat .n{font-size:1.8rem;font-weight:800}.stat .l{font-size:0.8rem;color:#7F8C8D}
    table{width:100%;border-collapse:collapse;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.07)}
    th{background:#1a6e3c;color:#fff;padding:10px 12px;text-align:left;font-size:0.8rem}
    td{padding:8px 12px;border-bottom:1px solid #f0f2f5;font-size:0.8rem;vertical-align:middle}
    tr:last-child td{border-bottom:none}
    .badge{display:inline-block;padding:2px 8px;border-radius:4px;font-size:0.72rem;font-weight:700}
    .new{background:#e8f5ee;color:#1a6e3c}.updated{background:#e8f4fd;color:#2980B9}.error{background:#fdecea;color:#E74C3C}
    img.th{width:56px;height:38px;object-fit:cover;border-radius:4px;background:#ddd}
    .warn{background:#fff8e8;border:1.5px solid #f0d09a;border-radius:8px;padding:14px 18px;margin:20px 0;font-size:0.88rem}
    .warn strong{color:#b8620a}
    .success-box{background:#e8f5ee;border:1.5px solid #a9dfbf;border-radius:8px;padding:14px 18px;margin:20px 0}
    code{background:#f0f2f5;padding:2px 6px;border-radius:4px;font-size:0.85em}
  </style>
</head>
<body>

<h2>✅ WordPress XML Import Complete</h2>
<p style="color:#7F8C8D;margin-top:4px">File: <code>wordpress-posts.xml</code> &nbsp;|&nbsp; Attachments found: <strong><?= count($attachMap) ?></strong></p>

<div class="stats">
  <div class="stat g"><div class="n"><?= $inserted ?></div><div class="l">New Posts</div></div>
  <div class="stat b"><div class="n"><?= $updated  ?></div><div class="l">Updated</div></div>
  <div class="stat o"><div class="n"><?= $skipped  ?></div><div class="l">Skipped</div></div>
  <div class="stat" ><div class="n"><?= $inserted + $updated ?></div><div class="l">Total Imported</div></div>
</div>

<table>
  <thead>
    <tr><th>#</th><th>Thumb</th><th>Title</th><th>Slug</th><th>Category</th><th>Status</th></tr>
  </thead>
  <tbody>
  <?php foreach ($log as $i => $r): ?>
    <tr>
      <td><?= $i+1 ?></td>
      <td><?= !empty($r['thumb']) ? "<img class='th' src='{$r['thumb']}' alt=''>" : '—' ?></td>
      <td><?= htmlspecialchars($r['title']) ?></td>
      <td style="font-family:monospace;font-size:0.72rem">/<?= htmlspecialchars($r['slug']) ?>/</td>
      <td><?= htmlspecialchars($r['cat'] ?? '—') ?></td>
      <td>
        <?php if ($r['status']==='new'): ?><span class="badge new">✓ New</span>
        <?php elseif($r['status']==='updated'): ?><span class="badge updated">↻ Updated</span>
        <?php else: ?><span class="badge error">✗ <?= htmlspecialchars($r['error'] ?? 'Error') ?></span>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<div class="success-box" style="margin-top:24px">
  <strong>🎉 Import ho gaya!</strong><br>
  Ab <a href="/">Homepage</a> aur <a href="/blog-post/">Blog Posts</a> check karein.
</div>

<div class="warn">
  <strong>⚠️ Important — Abhi yeh karo:</strong><br>
  Server se <strong>dono files delete karo</strong> File Manager se:<br>
  <code>/database/wp-xml-import.php</code><br>
  <code>/database/wordpress-posts.xml</code>
</div>

</body>
</html>
