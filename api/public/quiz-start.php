<?php
require_once dirname(__DIR__) . '/helpers.php';
setCorsHeaders();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('POST required', 405);

$user = requireAuth();
$db   = getDB();

$type       = str('type');      // random|daily|weekly|monthly|practice|previous_year|mock
$categoryId = intVal('category_id');
$date       = str('date');      // for daily
$quizId     = intVal('quiz_id');

// ── Prevent re-attempt of today's daily quiz ─────────────────
if ($type === 'daily') {
    $checkDate = $date ?: date('Y-m-d');
    $dq = $db->prepare("SELECT id FROM daily_quizzes WHERE quiz_date = ? AND is_published = 1");
    $dq->execute([$checkDate]);
    $dailyQuiz = $dq->fetch();
    if (!$dailyQuiz) jsonError('Aaj ka daily quiz available nahi hai');

    // Check if already attempted
    $prev = $db->prepare("
        SELECT id FROM quiz_attempts
        WHERE user_id = ? AND quiz_type = 'daily' AND daily_quiz_id = ? AND completed_at IS NOT NULL
    ");
    $prev->execute([$user['id'], $dailyQuiz['id']]);
    if ($prev->fetch()) jsonError('Aapne aaj ka quiz already attempt kar liya hai');
}

// ── Fetch questions ──────────────────────────────────────────
$questions = [];
$limits = ['random' => 10, 'daily' => 10, 'practice' => 10, 'weekly' => 20, 'monthly' => 100, 'previous_year' => 10, 'mock' => 10];
$limit = $limits[$type] ?? 10;

if ($type === 'daily') {
    // Get from daily_quiz_questions
    $stmt = $db->prepare("
        SELECT q.id, q.question_text, q.option_a, q.option_b, q.option_c, q.option_d, q.image_url
        FROM daily_quiz_questions dqq
        JOIN questions q ON q.id = dqq.question_id
        WHERE dqq.daily_quiz_id = ? AND q.is_active = 1
        ORDER BY dqq.display_order
        LIMIT 10
    ");
    $stmt->execute([$dailyQuiz['id']]);
    $questions = $stmt->fetchAll();
} elseif ($categoryId) {
    $stmt = $db->prepare("
        SELECT id, question_text, option_a, option_b, option_c, option_d, image_url
        FROM questions WHERE category_id = ? AND is_active = 1
        ORDER BY RAND() LIMIT $limit
    ");
    $stmt->execute([$categoryId]);
    $questions = $stmt->fetchAll();
} else {
    // Random from all
    $stmt = $db->query("
        SELECT id, question_text, option_a, option_b, option_c, option_d, image_url
        FROM questions WHERE is_active = 1
        ORDER BY RAND() LIMIT $limit
    ");
    $questions = $stmt->fetchAll();
}

if (empty($questions)) jsonError('Is section mein abhi koi question available nahi hai');

// ── Shuffle options (anti-cheat) — keep track via mapping ────
// Store correct answer server-side only (not sent to client)
foreach ($questions as &$q) {
    // Randomize option order
    $opts = [
        'a' => $q['option_a'], 'b' => $q['option_b'],
        'c' => $q['option_c'], 'd' => $q['option_d']
    ];
    $keys = array_keys($opts);
    shuffle($keys);
    $map = []; $i = 0;
    $newOpts = [];
    foreach ($keys as $origKey) {
        $newKey = ['a','b','c','d'][$i++];
        $newOpts['option_' . $newKey] = $opts[$origKey];
        $map[$newKey] = $origKey; // newKey -> origKey
    }
    $q = array_merge($q, $newOpts);
    $q['_option_map'] = $map; // will be stored in attempt, not sent to client
    unset($q['option_a_orig']); // keep clean
}
unset($q);

// ── Create attempt record ────────────────────────────────────
$questionIds = array_column($questions, 'id');
$durationSecs = 0;
if ($type === 'weekly')  $durationSecs = 1800;  // 30 min
if ($type === 'monthly') $durationSecs = 7200;  // 2 hrs
if ($type === 'mock')    $durationSecs = 10800; // 3 hrs

$stmt = $db->prepare("
    INSERT INTO quiz_attempts (user_id, quiz_type, category_id, daily_quiz_id, total_questions, started_at)
    VALUES (?,?,?,?,?,NOW())
");
$stmt->execute([
    $user['id'], $type, $categoryId ?: null,
    ($type === 'daily') ? $dailyQuiz['id'] : null,
    count($questions)
]);
$attemptId = (int)$db->lastInsertId();

// Store option maps in session storage (server-side, linked to attemptId)
// We store them in a temp table or session — use a simple JSON in a temp column
$db->prepare("UPDATE quiz_attempts SET share_token = ? WHERE id = ?")
   ->execute([json_encode(array_combine($questionIds, array_column($questions, '_option_map'))), $attemptId]);

// Remove internal data before sending to client
foreach ($questions as &$q) {
    unset($q['_option_map']);
}
unset($q);

jsonSuccess([
    'attempt_id'   => $attemptId,
    'questions'    => $questions,
    'total'        => count($questions),
    'duration_secs'=> $durationSecs,
    'quiz_type'    => $type
]);
