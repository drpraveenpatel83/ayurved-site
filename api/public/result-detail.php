<?php
require_once dirname(__DIR__) . '/helpers.php';
setCorsHeaders();

$user      = requireAuth();
$attemptId = intVal('attempt_id');
if (!$attemptId) jsonError('attempt_id required');

$db   = getDB();
$stmt = $db->prepare("SELECT * FROM quiz_attempts WHERE id = ? AND user_id = ? AND completed_at IS NOT NULL");
$stmt->execute([$attemptId, $user['id']]);
$attempt = $stmt->fetch();
if (!$attempt) jsonError('Result not found', 404);

// Get all answers with question details
$astmt = $db->prepare("
    SELECT aa.*, q.question_text, q.option_a, q.option_b, q.option_c, q.option_d,
           q.correct_option, q.explanation, q.source, q.image_url
    FROM attempt_answers aa
    JOIN questions q ON q.id = aa.question_id
    WHERE aa.attempt_id = ?
    ORDER BY aa.id
");
$astmt->execute([$attemptId]);
$answers = $astmt->fetchAll();

// Clean up internal share_token JSON (was reused as temp storage)
$attempt['share_token'] = $attempt['share_token'];

jsonSuccess(['attempt' => $attempt, 'answers' => $answers]);
