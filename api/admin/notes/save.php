<?php
require_once dirname(__DIR__, 2) . '/helpers.php';
setCorsHeaders();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('POST required', 405);
$admin = requireAdmin();

$db         = getDB();
$b          = body();
$id         = intVal('id');
$categoryId = intVal('category_id');
$title      = trim($b['title'] ?? '');
$content    = $b['content'] ?? '';      // HTML allowed
$type       = $b['type'] ?? 'short_notes';
$isPublished= isset($b['is_published']) ? (int)$b['is_published'] : 1;
$order      = intVal('display_order');

$allowedTypes = ['syllabus','short_notes','full_notes'];
if (!in_array($type, $allowedTypes)) jsonError('Invalid type');
if (!$categoryId || !$title) jsonError('category_id and title required');

// Sanitize HTML content (allow safe tags)
$content = strip_tags($content,
    '<p><br><b><i><u><strong><em><h1><h2><h3><h4><ul><ol><li><table><thead><tbody><tr><th><td><div><span><hr><blockquote><pre><code><mark><a><img>'
);

if ($id) {
    $db->prepare("
        UPDATE notes SET category_id=?, title=?, content=?, type=?, is_published=?, display_order=?, updated_at=NOW()
        WHERE id=?
    ")->execute([$categoryId, $title, $content, $type, $isPublished, $order, $id]);
    jsonSuccess(['id' => $id], 'Notes updated');
} else {
    $db->prepare("
        INSERT INTO notes (category_id, title, content, type, is_published, display_order, created_by)
        VALUES (?,?,?,?,?,?,?)
    ")->execute([$categoryId, $title, $content, $type, $isPublished, $order, $admin['id']]);
    jsonSuccess(['id' => (int)$db->lastInsertId()], 'Notes saved', 201);
}
