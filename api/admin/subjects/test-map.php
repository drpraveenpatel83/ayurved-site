<?php
// Get which subjects a test is assigned to
require_once dirname(__DIR__,2).'/helpers.php';
setCorsHeaders(); requireAdmin();

$testId = (int)($_GET['test_id'] ?? 0);
if (!$testId) jsonError('test_id required');

$db   = getDB();
$stmt = $db->prepare("
    SELECT tsm.category_id, c.name, c.slug, p.name AS parent_name
    FROM test_subject_map tsm
    JOIN categories c ON c.id=tsm.category_id
    LEFT JOIN categories p ON p.id=c.parent_id
    WHERE tsm.test_id=?
    ORDER BY p.display_order, c.name
");
$stmt->execute([$testId]);
jsonSuccess($stmt->fetchAll());
