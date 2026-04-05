<?php
require_once dirname(__DIR__) . '/helpers.php';
setCorsHeaders();

$db   = getDB();
$date = str('date') ?: date('Y-m-d');

// Validate date
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) jsonError('Invalid date format');

$stmt = $db->prepare("SELECT id, quiz_date, title, is_published FROM daily_quizzes WHERE quiz_date = ?");
$stmt->execute([$date]);
$quiz = $stmt->fetch();

if (!$quiz || !$quiz['is_published']) {
    jsonSuccess(null, 'Quiz not available');
}

$data = ['id' => $quiz['id'], 'title' => $quiz['title'], 'date' => $quiz['quiz_date'], 'attempted' => false, 'last_score' => null];

// Check if user already attempted (if logged in)
$user = getAuthUser();
if ($user) {
    $prev = $db->prepare("
        SELECT id as attempt_id, score, total_questions as total
        FROM quiz_attempts
        WHERE user_id = ? AND daily_quiz_id = ? AND completed_at IS NOT NULL
        ORDER BY completed_at DESC LIMIT 1
    ");
    $prev->execute([$user['id'], $quiz['id']]);
    $lastAttempt = $prev->fetch();
    if ($lastAttempt) {
        $data['attempted']   = true;
        $data['last_score']  = $lastAttempt;
    }
}

jsonSuccess($data);
