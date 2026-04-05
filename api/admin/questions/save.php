<?php
require_once dirname(__DIR__, 2) . '/helpers.php';
setCorsHeaders();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('POST required', 405);
requireAdmin();

$db = getDB();
$b  = body();

$id         = intVal('id');
$categoryId = intVal('category_id');
$text       = trim($b['question_text'] ?? '');
$optA       = trim($b['option_a'] ?? '');
$optB       = trim($b['option_b'] ?? '');
$optC       = trim($b['option_c'] ?? '');
$optD       = trim($b['option_d'] ?? '');
$correct    = strtolower(trim($b['correct_option'] ?? ''));
$explanation= trim($b['explanation'] ?? '');
$difficulty = in_array($b['difficulty'] ?? '', ['easy','medium','hard']) ? $b['difficulty'] : 'medium';
$source     = trim($b['source'] ?? '');
$year       = intVal('year') ?: null;
$isActive   = isset($b['is_active']) ? (int)$b['is_active'] : 1;

if (!$categoryId || !$text || !$optA || !$optB || !$optC || !$optD || !in_array($correct, ['a','b','c','d'])) {
    jsonError('All fields required: category, question, 4 options, correct option (a/b/c/d)');
}

if ($id) {
    $db->prepare("
        UPDATE questions SET category_id=?, question_text=?, option_a=?, option_b=?, option_c=?, option_d=?,
        correct_option=?, explanation=?, difficulty=?, source=?, year=?, is_active=?
        WHERE id=?
    ")->execute([$categoryId, $text, $optA, $optB, $optC, $optD, $correct, $explanation, $difficulty, $source, $year, $isActive, $id]);
    jsonSuccess(['id' => $id], 'Question updated');
} else {
    $user = getAuthUser();
    $db->prepare("
        INSERT INTO questions (category_id, question_text, option_a, option_b, option_c, option_d,
        correct_option, explanation, difficulty, source, year, is_active, created_by)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
    ")->execute([$categoryId, $text, $optA, $optB, $optC, $optD, $correct, $explanation, $difficulty, $source, $year, $isActive, $user['id']]);
    jsonSuccess(['id' => (int)$db->lastInsertId()], 'Question added', 201);
}
