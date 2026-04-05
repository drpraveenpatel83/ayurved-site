<?php
require_once dirname(__DIR__, 2) . '/helpers.php';
setCorsHeaders();
requireAdmin();

$db = getDB();
$stmt = $db->query("
    SELECT c.*, p.name as parent_name,
           (SELECT COUNT(*) FROM questions q WHERE q.category_id = c.id) as question_count
    FROM categories c
    LEFT JOIN categories p ON p.id = c.parent_id
    ORDER BY c.parent_id IS NULL DESC, c.display_order, c.id
");
jsonSuccess($stmt->fetchAll());
