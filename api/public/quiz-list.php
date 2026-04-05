<?php
require_once dirname(__DIR__) . '/helpers.php';
setCorsHeaders();
requireAuth();

$categoryId = intVal('category_id');
if (!$categoryId) jsonError('category_id required');

$db   = getDB();
$stmt = $db->prepare("
    SELECT id, title, type, question_count, duration_mins
    FROM quizzes
    WHERE category_id = ? AND is_active = 1
    ORDER BY display_order, id
");
$stmt->execute([$categoryId]);
jsonSuccess($stmt->fetchAll());
