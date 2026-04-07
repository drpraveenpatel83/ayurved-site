<?php
/**
 * POST /api/admin/posts/save.php
 * Create or update a blog post.
 * Body JSON: { id?, title, slug?, excerpt, content, thumbnail, category, category_slug, author, is_published, is_featured, published_at }
 */
require_once dirname(__DIR__,2).'/helpers.php'; setCorsHeaders(); requireAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('POST only', 405);

$body = body();

$id          = intVal('id', $body) ?: null;
$title       = str($body['title'] ?? '');
$content     = $body['content'] ?? '';          // HTML — trusted admin input
$excerpt     = str($body['excerpt'] ?? '');
$thumbnail   = str($body['thumbnail'] ?? '');
$category    = str($body['category'] ?? '');
$catSlug     = str($body['category_slug'] ?? '');
$author      = str($body['author'] ?? 'Admin');
$isPublished = isset($body['is_published']) ? (int)(bool)$body['is_published'] : 1;
$isFeatured  = isset($body['is_featured'])  ? (int)(bool)$body['is_featured']  : 0;
$publishedAt = str($body['published_at'] ?? '');

if (!$title) jsonError('Title required');

// Auto-generate slug from title if not provided
$slug = str($body['slug'] ?? '');
if (!$slug) $slug = makeSlug($title);
if (!$slug) jsonError('Could not generate slug from title');

// Auto-derive category_slug from category name if not given
if ($category && !$catSlug) $catSlug = makeSlug($category);

if (!$publishedAt) $publishedAt = date('Y-m-d H:i:s');

$db = getDB();

if ($id) {
    // Update existing post
    $stmt = $db->prepare("
        UPDATE posts SET
            title=?, slug=?, excerpt=?, content=?, thumbnail=?,
            category=?, category_slug=?, author=?,
            is_published=?, is_featured=?, published_at=?
        WHERE id=?
    ");
    $stmt->execute([
        $title, $slug, $excerpt ?: null, $content, $thumbnail ?: null,
        $category ?: null, $catSlug ?: null, $author,
        $isPublished, $isFeatured, $publishedAt, $id
    ]);
    jsonSuccess(['id' => $id, 'slug' => $slug, 'action' => 'updated']);
} else {
    // Insert new post — handle duplicate slug
    try {
        $stmt = $db->prepare("
            INSERT INTO posts
                (title, slug, excerpt, content, thumbnail, category, category_slug,
                 author, is_published, is_featured, published_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)
        ");
        $stmt->execute([
            $title, $slug, $excerpt ?: null, $content, $thumbnail ?: null,
            $category ?: null, $catSlug ?: null, $author,
            $isPublished, $isFeatured, $publishedAt
        ]);
        jsonSuccess(['id' => (int)$db->lastInsertId(), 'slug' => $slug, 'action' => 'created']);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            jsonError("Slug '$slug' already exists. Ek alag slug use karein.");
        }
        throw $e;
    }
}
