<?php
require_once dirname(__DIR__) . '/helpers.php';
setCorsHeaders();

$db = getDB();

// Single category by ID
$id = intVal('id');
if ($id) {
    $stmt = $db->prepare("
        SELECT c.*, p.id as parent_id, p.name as parent_name, p.bams_year as parent_bams_year,
               p.type as parent_type, p.slug as parent_slug,
               (SELECT COUNT(*) FROM questions q WHERE q.category_id = c.id AND q.is_active = 1) as question_count
        FROM categories c
        LEFT JOIN categories p ON p.id = c.parent_id
        WHERE c.id = ? AND c.is_active = 1
    ");
    $stmt->execute([$id]);
    $cat = $stmt->fetch();
    if (!$cat) jsonError('Category not found', 404);
    // Format parent as object
    if ($cat['parent_id']) {
        $cat['parent'] = [
            'id' => $cat['parent_id'], 'name' => $cat['parent_name'],
            'bams_year' => $cat['parent_bams_year'], 'type' => $cat['parent_type'],
            'slug' => $cat['parent_slug']
        ];
    } else {
        $cat['parent'] = null;
    }
    jsonSuccess($cat);
}

// Root categories (for homepage grid)
$root = intVal('root');
if ($root) {
    $stmt = $db->query("
        SELECT c.*,
               (SELECT COUNT(*) FROM questions q
                JOIN categories sub ON sub.id = q.category_id
                WHERE (sub.id = c.id OR sub.parent_id = c.id) AND q.is_active = 1) as question_count
        FROM categories c
        WHERE c.parent_id IS NULL AND c.is_active = 1
        ORDER BY c.display_order, c.id
    ");
    jsonSuccess($stmt->fetchAll());
}

// By type (bams_year, samhita, aiapget, govt_exam)
$type = str('type');
$year = intVal('year');
$parentId = intVal('parent_id');
$includeChildren = intVal('include_children');

$where = ['c.is_active = 1'];
$params = [];

if ($type)   { $where[] = 'c.type = ?'; $params[] = $type; }
if ($year)   { $where[] = 'c.bams_year = ?'; $params[] = $year; }
if ($parentId) { $where[] = 'c.parent_id = ?'; $params[] = $parentId; }
else if (!$type && !$year) { $where[] = 'c.parent_id IS NULL'; }

$sql = "SELECT c.*,
               (SELECT COUNT(*) FROM questions q WHERE q.category_id = c.id AND q.is_active = 1) as question_count,
               (SELECT COUNT(*) FROM notes n WHERE n.category_id = c.id AND n.is_published = 1 AND n.type != 'syllabus') as has_notes
        FROM categories c
        WHERE " . implode(' AND ', $where) . "
        ORDER BY c.display_order, c.name";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$cats = $stmt->fetchAll();

// Optionally include children
if ($includeChildren && $cats) {
    foreach ($cats as &$cat) {
        $cstmt = $db->prepare("
            SELECT c.*,
                   (SELECT COUNT(*) FROM questions q WHERE q.category_id = c.id AND q.is_active = 1) as question_count,
                   (SELECT COUNT(*) FROM notes n WHERE n.category_id = c.id AND n.is_published = 1 AND n.type != 'syllabus') as has_notes
            FROM categories c
            WHERE c.parent_id = ? AND c.is_active = 1
            ORDER BY c.display_order, c.name
        ");
        $cstmt->execute([$cat['id']]);
        $cat['children'] = $cstmt->fetchAll();
    }
    unset($cat);
}

jsonSuccess($cats);
