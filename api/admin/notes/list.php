<?php
require_once dirname(__DIR__, 2) . '/helpers.php';
setCorsHeaders();
requireAdmin();

$db     = getDB();
$catId  = intVal('category_id');
$type   = str('type');
$page   = max(1, intVal('page') ?: 1);
$limit  = 20;
$offset = ($page - 1) * $limit;

$where  = ['1=1'];
$params = [];
if ($catId) { $where[] = 'n.category_id = ?'; $params[] = $catId; }
if ($type)  { $where[] = 'n.type = ?'; $params[] = $type; }

$total = (int)$db->prepare("SELECT COUNT(*) FROM notes n WHERE " . implode(' AND ', $where))->execute($params)->fetchColumn();

$stmt  = $db->prepare("
    SELECT n.*, c.name as category_name
    FROM notes n JOIN categories c ON c.id = n.category_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY n.id DESC LIMIT $limit OFFSET $offset
");
$stmt->execute($params);

jsonSuccess(['notes' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => ceil($total/$limit)]);
