<?php
require_once dirname(__DIR__) . '/helpers.php';
setCorsHeaders();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('POST required', 405);

$user = requireAuth();
$db   = getDB();

$attemptId = intVal('attempt_id');
$submitted  = body()['answers'] ?? [];  // [{question_id, selected}]

if (!$attemptId) jsonError('attempt_id required');

// Verify attempt belongs to this user and not yet completed
$stmt = $db->prepare("SELECT * FROM quiz_attempts WHERE id = ? AND user_id = ? AND completed_at IS NULL");
$stmt->execute([$attemptId, $user['id']]);
$attempt = $stmt->fetch();
if (!$attempt) jsonError('Invalid or already completed attempt');

// Get option map (stored as JSON in share_token temporarily)
$optionMaps = json_decode($attempt['share_token'], true) ?? [];

// Get correct answers from DB
$questionIds = array_column($submitted, 'question_id');
if (empty($questionIds)) jsonError('No answers submitted');

$in     = implode(',', array_fill(0, count($questionIds), '?'));
$qstmt  = $db->prepare("SELECT id, correct_option, explanation, question_text, option_a, option_b, option_c, option_d, source, image_url FROM questions WHERE id IN ($in)");
$qstmt->execute($questionIds);
$questionsDb = [];
foreach ($qstmt->fetchAll() as $q) {
    $questionsDb[$q['id']] = $q;
}

$score = 0;
$timeTaken = (int)(microtime(true) - strtotime($attempt['started_at']));
if ($timeTaken < 0 || $timeTaken > 86400) $timeTaken = null;

$db->beginTransaction();
try {
    foreach ($submitted as $ans) {
        $qid      = (int)$ans['question_id'];
        $selected = isset($ans['selected']) ? strtolower(trim($ans['selected'])) : null;
        $q        = $questionsDb[$qid] ?? null;
        if (!$q) continue;

        // Map back submitted option to original option key using stored map
        $map         = $optionMaps[(string)$qid] ?? null;
        $origSelected = null;
        if ($selected && $map) {
            $origSelected = $map[$selected] ?? $selected;
        } elseif ($selected) {
            $origSelected = $selected;
        }

        $isCorrect = ($origSelected !== null && $origSelected === $q['correct_option']) ? 1 : 0;
        if ($isCorrect) $score++;

        $db->prepare("INSERT INTO attempt_answers (attempt_id, question_id, selected_option, is_correct) VALUES (?,?,?,?)")
           ->execute([$attemptId, $qid, $origSelected, $isCorrect]);
    }

    // Generate share token
    $shareToken = generateToken(16);

    $db->prepare("
        UPDATE quiz_attempts
        SET score = ?, completed_at = NOW(), time_taken_secs = ?, share_token = ?
        WHERE id = ?
    ")->execute([$score, $timeTaken, $shareToken, $attemptId]);

    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
    jsonError('Submit mein error aaya: ' . $e->getMessage(), 500);
}

jsonSuccess([
    'attempt_id'  => $attemptId,
    'score'       => $score,
    'total'       => count($submitted),
    'percentage'  => count($submitted) ? round(($score / count($submitted)) * 100) : 0,
    'share_token' => $shareToken
], 'Quiz submit ho gaya!');
