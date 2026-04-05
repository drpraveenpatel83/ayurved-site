<?php
require_once dirname(__DIR__) . '/helpers.php';
setCorsHeaders();

$db         = getDB();
$categoryId = intVal('category_id');
$type       = str('type');       // syllabus|short_notes|full_notes
$checkOnly  = intVal('check_only'); // 1 = just check if notes exist, don't return content

if (!$categoryId) jsonError('category_id required');

// Require auth for notes content
if (!$checkOnly) {
    requireAuth();
}

if ($checkOnly) {
    $stmt = $db->prepare("
        SELECT type, COUNT(*) as cnt
        FROM notes
        WHERE category_id = ? AND is_published = 1
        GROUP BY type
    ");
    $stmt->execute([$categoryId]);
    $rows = $stmt->fetchAll();
    $result = ['has_syllabus' => false, 'has_notes' => false];
    foreach ($rows as $r) {
        if ($r['type'] === 'syllabus')     $result['has_syllabus'] = true;
        if (in_array($r['type'], ['short_notes','full_notes'])) $result['has_notes'] = true;
    }
    jsonSuccess($result);
}

$where  = ['category_id = ?', 'is_published = 1'];
$params = [$categoryId];
if ($type) { $where[] = 'type = ?'; $params[] = $type; }

$stmt = $db->prepare("
    SELECT id, title, content, file_url, type, display_order
    FROM notes
    WHERE " . implode(' AND ', $where) . "
    ORDER BY display_order, id
");
$stmt->execute($params);
$notes = $stmt->fetchAll();

jsonSuccess($notes);
