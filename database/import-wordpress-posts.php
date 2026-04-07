<?php
/**
 * WordPress XML → posts table import
 *
 * Steps:
 *  1. WordPress Admin → Tools → Export → Posts → Download Export File
 *  2. Upload the .xml file to this server (same folder as this script)
 *  3. Open: https://yourdomain.com/database/import-wordpress-posts.php?file=wordpress.xml&key=ImportSecret123
 *  4. DELETE this file after use!
 *
 * Security: change the KEY below before uploading.
 */

define('IMPORT_KEY', 'ImportSecret123'); // ← Change this!

if (($_GET['key'] ?? '') !== IMPORT_KEY) {
    http_response_code(403);
    die('Forbidden — wrong key');
}

$xmlFile = __DIR__ . '/' . basename($_GET['file'] ?? 'wordpress.xml');
if (!file_exists($xmlFile)) {
    die("File not found: $xmlFile");
}

require_once dirname(__DIR__).'/api/db.php';

// Parse WordPress export XML
libxml_use_internal_errors(true);
$xml = simplexml_load_file($xmlFile);
if (!$xml) {
    echo '<pre>XML parse errors:<br>';
    foreach (libxml_get_errors() as $e) echo htmlspecialchars($e->message).'<br>';
    echo '</pre>';
    exit;
}

$ns = $xml->channel->children('wp', true);
$items = $xml->channel->item;

$db = getDB();
$stmt = $db->prepare("
    INSERT IGNORE INTO posts
        (title, slug, excerpt, content, thumbnail, category, category_slug, author, is_published, published_at)
    VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$inserted = 0;
$skipped  = 0;
$errors   = [];

foreach ($items as $item) {
    $wpNs      = $item->children('wp', true);
    $contentNs = $item->children('content', true);
    $excerptNs = $item->children('excerpt', true);
    $dcNs      = $item->children('dc', true);

    // Only import published posts (not pages, drafts, etc.)
    $postType   = (string)($wpNs->post_type ?? '');
    $postStatus = (string)($wpNs->status ?? '');
    if ($postType !== 'post' || $postStatus !== 'publish') {
        $skipped++;
        continue;
    }

    $title    = (string)$item->title;
    $slug     = (string)($wpNs->post_name ?? '');
    $content  = (string)($contentNs->encoded ?? '');
    $excerpt  = strip_tags((string)($excerptNs->encoded ?? ''));
    $author   = (string)($dcNs->creator ?? 'Admin');
    $pubDate  = (string)($wpNs->post_date ?? '');
    $pubDate  = $pubDate ?: date('Y-m-d H:i:s');

    if (!$title || !$slug) { $skipped++; continue; }

    // Category — use first category
    $cat     = '';
    $catSlug = '';
    foreach ($item->category as $c) {
        $domain = (string)($c['domain'] ?? '');
        if ($domain === 'category') {
            $cat     = (string)$c;
            $catSlug = (string)($c['nicename'] ?? makeSlug($cat));
            break;
        }
    }

    // Thumbnail — WordPress stores it as attachment URL in meta
    $thumb = '';
    foreach ($wpNs->postmeta as $meta) {
        if ((string)$meta->meta_key === '_thumbnail_id') {
            // We'd need another pass to resolve attachment IDs to URLs.
            // For now, leave blank; can be updated manually.
            break;
        }
    }

    try {
        $stmt->execute([
            $title,
            $slug,
            $excerpt ?: null,
            $content ?: null,
            $thumb  ?: null,
            $cat    ?: null,
            $catSlug?: null,
            $author,
            1,
            $pubDate,
        ]);
        $inserted++;
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') { // Duplicate slug
            $skipped++;
        } else {
            $errors[] = "Slug '$slug': " . $e->getMessage();
        }
    }
}

// ── Output ──────────────────────────────────────────────────
?>
<!DOCTYPE html><html><body style="font-family:sans-serif;max-width:600px;margin:40px auto;padding:0 20px">
<h2 style="color:#1a6e3c">✅ WordPress Import Complete</h2>
<table border="1" cellpadding="8" cellspacing="0" style="border-collapse:collapse;width:100%">
  <tr><td><b>Inserted</b></td><td style="color:green"><b><?= $inserted ?></b> posts</td></tr>
  <tr><td><b>Skipped</b></td><td><?= $skipped ?> (drafts, pages, duplicates)</td></tr>
  <tr><td><b>Errors</b></td><td style="color:red"><?= count($errors) ?></td></tr>
</table>
<?php if ($errors): ?>
<h3 style="color:red">Errors:</h3>
<ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
<?php endif; ?>
<br>
<p style="color:red;font-weight:bold">⚠️ Ab yeh file DELETE karo server se!</p>
<p>Ab <a href="/">Homepage</a> check karein — posts show honge.</p>
</body></html>
<?php

// Helper (in case helpers.php not loaded)
function makeSlug($str) {
    $str = strtolower(trim($str));
    $str = preg_replace('/[^a-z0-9\s\-]/', '', $str);
    $str = preg_replace('/[\s\-]+/', '-', $str);
    return trim($str, '-');
}
