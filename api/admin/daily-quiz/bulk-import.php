<?php
/**
 * Bulk import daily quiz — one full month
 *
 * CSV format (each row = one day, 10 questions inline):
 * date, q1_text, q1_a, q1_b, q1_c, q1_d, q1_correct, q1_explanation,
 *       q2_text, q2_a, ... q2_explanation,
 *       ... (up to q10)
 *
 * OR: date, category_slug, q1_id, q2_id, ... q10_id  (use existing question IDs)
 */
require_once dirname(__DIR__, 2) . '/helpers.php';
setCorsHeaders();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('POST required', 405);
$admin = requireAdmin();

$db = getDB();

if (empty($_FILES['file'])) jsonError('CSV file required');
$file = $_FILES['file'];
if ($file['error'] !== UPLOAD_ERR_OK) jsonError('Upload error');
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if ($ext !== 'csv') jsonError('Only CSV supported');

$success = 0;
$errors  = [];

if (($handle = fopen($file['tmp_name'], 'r')) === false) jsonError('Cannot read file');

$headers = fgetcsv($handle);
if (!$headers) { fclose($handle); jsonError('Empty CSV'); }
$headers = array_map(fn($h) => strtolower(trim($h)), $headers);

// Detect format by headers
$useIds = in_array('q1_id', $headers) || in_array('question_1_id', $headers);

$catStmt = $db->query("SELECT id, slug FROM categories WHERE is_active = 1");
$slugMap = [];
foreach ($catStmt->fetchAll() as $c) $slugMap[$c['slug']] = $c['id'];

while (($row = fgetcsv($handle)) !== false) {
    if (count($row) < 2) continue;
    $r    = array_combine(array_slice($headers, 0, count($row)), $row);
    $date = trim($r['date'] ?? '');

    if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $errors[] = "Invalid date: $date";
        continue;
    }

    $db->beginTransaction();
    try {
        // Insert or update daily_quiz record
        $stmt = $db->prepare("INSERT INTO daily_quizzes (quiz_date, title, is_published) VALUES (?,?,1) ON DUPLICATE KEY UPDATE title = VALUES(title), is_published = 1");
        $stmt->execute([$date, $r['title'] ?? null]);
        $dqId = (int)$db->lastInsertId();
        if (!$dqId) {
            $dqId = $db->query("SELECT id FROM daily_quizzes WHERE quiz_date = '$date'")->fetchColumn();
        }

        // Remove old questions for this date
        $db->prepare("DELETE FROM daily_quiz_questions WHERE daily_quiz_id = ?")->execute([$dqId]);

        $qIds = [];

        if ($useIds) {
            // Format: date, q1_id, q2_id, ... q10_id
            for ($i = 1; $i <= 10; $i++) {
                $qid = (int)($r["q{$i}_id"] ?? $r["question_{$i}_id"] ?? 0);
                if ($qid) $qIds[] = $qid;
            }
        } else {
            // Format: inline question data — insert each question first
            $catSlug = trim($r['category_slug'] ?? '');
            $catId   = $slugMap[$catSlug] ?? null;

            for ($i = 1; $i <= 10; $i++) {
                $text    = trim($r["q{$i}_text"] ?? $r["question_{$i}_text"] ?? '');
                $optA    = trim($r["q{$i}_a"]   ?? $r["q{$i}_option_a"]   ?? '');
                $optB    = trim($r["q{$i}_b"]   ?? $r["q{$i}_option_b"]   ?? '');
                $optC    = trim($r["q{$i}_c"]   ?? $r["q{$i}_option_c"]   ?? '');
                $optD    = trim($r["q{$i}_d"]   ?? $r["q{$i}_option_d"]   ?? '');
                $correct = strtolower(trim($r["q{$i}_correct"] ?? $r["q{$i}_answer"] ?? ''));
                $expl    = trim($r["q{$i}_explanation"] ?? '');

                if (!$text || !$optA || !$optB || !$optC || !$optD || !in_array($correct, ['a','b','c','d'])) continue;

                $useCatId = $catId ?: 1; // fallback to category ID 1
                $ins = $db->prepare("INSERT INTO questions (category_id, question_text, option_a, option_b, option_c, option_d, correct_option, explanation, is_active, created_by) VALUES (?,?,?,?,?,?,?,?,1,?)");
                $ins->execute([$useCatId, $text, $optA, $optB, $optC, $optD, $correct, $expl, $admin['id']]);
                $qIds[] = (int)$db->lastInsertId();
            }
        }

        // Link questions to daily quiz
        $linkStmt = $db->prepare("INSERT INTO daily_quiz_questions (daily_quiz_id, question_id, display_order) VALUES (?,?,?)");
        foreach ($qIds as $order => $qid) {
            $linkStmt->execute([$dqId, $qid, $order + 1]);
        }

        $db->commit();
        $success++;
    } catch (Exception $e) {
        $db->rollBack();
        $errors[] = "Date $date: " . $e->getMessage();
    }
}

fclose($handle);
jsonSuccess(['imported_days' => $success, 'errors' => $errors],
    "$success din ke questions import ho gaye");
