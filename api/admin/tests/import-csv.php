<?php
require_once dirname(__DIR__,2).'/helpers.php'; setCorsHeaders(); requireAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('POST only', 405);

$testId = (int)($_POST['test_id'] ?? 0);
if (!$testId) jsonError('test_id required');
if (empty($_FILES['csv']['tmp_name'])) jsonError('CSV file required');

$db = getDB();
// Verify test exists
$chk = $db->prepare("SELECT id FROM mock_tests WHERE id=?"); $chk->execute([$testId]);
if (!$chk->fetch()) jsonError('Test not found', 404);

$handle = fopen($_FILES['csv']['tmp_name'], 'r');
if (!$handle) jsonError('Could not read file');

$inserted = 0;
$errors   = 0;
$rowNum   = 0;

$stmt = $db->prepare("INSERT INTO mock_test_questions
    (test_id, question_text, option_a, option_b, option_c, option_d, correct_option, explanation, order_no)
    VALUES (?,?,?,?,?,?,?,?,?)");

while (($row = fgetcsv($handle)) !== false) {
    $rowNum++;
    if ($rowNum === 1) continue; // skip header

    // Trim all fields
    $row = array_map('trim', $row);

    // Minimum: question, 4 options, correct
    if (count($row) < 6) { $errors++; continue; }

    $qText   = $row[0] ?? '';
    $optA    = $row[1] ?? '';
    $optB    = $row[2] ?? '';
    $optC    = $row[3] ?? '';
    $optD    = $row[4] ?? '';
    $correct = strtolower($row[5] ?? '');
    $explain = $row[6] ?? '';

    if (!$qText || !in_array($correct, ['a','b','c','d'])) { $errors++; continue; }

    try {
        $stmt->execute([$testId, $qText, $optA, $optB, $optC, $optD, $correct, $explain ?: null, $rowNum]);
        $inserted++;
    } catch (PDOException $e) {
        $errors++;
    }
}
fclose($handle);

jsonSuccess(['inserted'=>$inserted,'errors'=>$errors], "$inserted questions import ho gayi");
