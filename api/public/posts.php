<?php
/**
 * GET /api/public/posts.php
 * Params:
 *   slug=<slug>          — single post detail (increments view_count)
 *   category=<slug>      — filter by category_slug
 *   featured=1           — only featured posts
 *   popular=1            — order by view_count DESC
 *   search=<term>        — title search
 *   limit=<n>            — default 10, max 50
 *   offset=<n>           — default 0
 */
require_once dirname(__DIR__).'/helpers.php';
setCorsHeaders();
if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonError('GET only', 405);

$db = getDB();

// Single post by slug
if (!empty($_GET['slug'])) {
    $slug = trim($_GET['slug']);
    $stmt = $db->prepare(
        "SELECT id,title,slug,excerpt,content,thumbnail,category,category_slug,
                author,is_featured,view_count,published_at
         FROM posts WHERE slug=? AND is_published=1 LIMIT 1"
    );
    $stmt->execute([$slug]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$post) jsonError('Post not found', 404);

    // Increment view count
    $db->prepare("UPDATE posts SET view_count=view_count+1 WHERE id=?")
       ->execute([$post['id']]);
    $post['view_count']++;

    // Related posts (same category, exclude current)
    $rel = $db->prepare(
        "SELECT id,title,slug,thumbnail,category,published_at
         FROM posts WHERE category_slug=? AND slug!=? AND is_published=1
         ORDER BY published_at DESC LIMIT 5"
    );
    $rel->execute([$post['category_slug'], $slug]);
    $post['related'] = $rel->fetchAll(PDO::FETCH_ASSOC);

    jsonSuccess($post);
}

// List posts
$limit  = min(50, max(1, intVal('limit') ?: 10));
$offset = max(0, intVal('offset') ?: 0);

$where  = ['p.is_published=1'];
$params = [];

if (!empty($_GET['category'])) {
    $where[] = 'p.category_slug=?';
    $params[] = trim($_GET['category']);
}
if (!empty($_GET['featured'])) {
    $where[] = 'p.is_featured=1';
}
if (!empty($_GET['search'])) {
    $where[] = 'p.title LIKE ?';
    $params[] = '%' . trim($_GET['search']) . '%';
}

$orderBy = !empty($_GET['popular'])
    ? 'p.view_count DESC'
    : 'p.published_at DESC';

$sql = "SELECT p.id,p.title,p.slug,p.excerpt,p.thumbnail,
               p.category,p.category_slug,p.author,
               p.is_featured,p.view_count,p.published_at
        FROM posts p
        WHERE " . implode(' AND ', $where) . "
        ORDER BY {$orderBy}
        LIMIT ? OFFSET ?";

$params[] = $limit;
$params[] = $offset;

$stmt = $db->prepare($sql);
$stmt->execute($params);
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Total count for pagination
$countSql = "SELECT COUNT(*) FROM posts p WHERE " . implode(' AND ', $where);
$countParams = array_slice($params, 0, -2); // remove limit/offset
$countStmt = $db->prepare($countSql);
$countStmt->execute($countParams);
$total = (int)$countStmt->fetchColumn();

jsonSuccess([
    'posts'  => $posts,
    'total'  => $total,
    'limit'  => $limit,
    'offset' => $offset,
]);
