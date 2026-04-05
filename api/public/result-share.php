<?php
require_once dirname(__DIR__) . '/helpers.php';
setCorsHeaders();

$token = str('token');
if (!$token) jsonError('Share token required');

$db   = getDB();
$stmt = $db->prepare("
    SELECT qa.*, u.name as user_name
    FROM quiz_attempts qa
    JOIN users u ON u.id = qa.user_id
    WHERE qa.share_token = ? AND qa.completed_at IS NOT NULL
");
$stmt->execute([$token]);
$attempt = $stmt->fetch();
if (!$attempt) jsonError('Result not found or link expired', 404);

// Get answers
$astmt = $db->prepare("
    SELECT aa.is_correct, aa.selected_option,
           q.question_text, q.option_a, q.option_b, q.option_c, q.option_d,
           q.correct_option, q.explanation, q.source, q.image_url
    FROM attempt_answers aa
    JOIN questions q ON q.id = aa.question_id
    WHERE aa.attempt_id = ?
    ORDER BY aa.id
");
$astmt->execute([$attempt['id']]);
$answers = $astmt->fetchAll();

// Remove internal user data for public view
unset($attempt['user_id']);

jsonSuccess(['attempt' => $attempt, 'answers' => $answers]);
