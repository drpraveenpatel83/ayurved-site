<?php
require_once dirname(__DIR__, 2) . '/helpers.php';
setCorsHeaders();
requireAdmin();

$db     = getDB();
$page   = max(1, intVal('page') ?: 1);
$limit  = 20;
$offset = ($page - 1) * $limit;
$catId  = intVal('category_id');
$search = str('search');

$where = ['1=1'];
$params = [];

if ($catId)  { $where[] = 'q.category_id = ?'; $params[] = $catId; }
if ($search) { $where[] = 'q.question_text LIKE ?'; $params[] = "%$search%"; }

$countStmt = $db->prepare("SELECT COUNT(*) FROM questions q WHERE " . implode(' AND ', $where));
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$stmt = $db->prepare("
    SELECT q.id, q.question_text, q.option_a, q.option_b, q.option_c, q.option_d,
           q.correct_option, q.explanation, q.difficulty, q.source, q.year,
           q.is_active, q.created_at, c.name as category_name
    FROM questions q
    JOIN categories c ON c.id = q.category_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY q.id DESC
    LIMIT $limit OFFSET $offset
");
$stmt->execute($params);

jsonSuccess([
    'questions'  => $stmt->fetchAll(),
    'total'      => $total,
    'page'       => $page,
    'pages'      => ceil($total / $limit),
    'limit'      => $limit
]);
