<?php
require_once dirname(__DIR__) . '/helpers.php';
setCorsHeaders();
$user  = requireAuth();
$limit = min(50, max(1, intVal('limit') ?: 20));
$db    = getDB();
$stmt  = $db->prepare("
    SELECT qa.id, qa.quiz_type, qa.score, qa.total_questions, qa.time_taken_secs, qa.completed_at,
           qa.share_token, c.name as category_name
    FROM quiz_attempts qa
    LEFT JOIN categories c ON c.id = qa.category_id
    WHERE qa.user_id = ? AND qa.completed_at IS NOT NULL
    ORDER BY qa.completed_at DESC
    LIMIT $limit
");
$stmt->execute([$user['id']]);
jsonSuccess($stmt->fetchAll());
