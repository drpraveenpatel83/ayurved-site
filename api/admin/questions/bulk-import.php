<?php
/**
 * Bulk import questions from CSV/JSON
 * CSV columns: category_slug,question_text,option_a,option_b,option_c,option_d,correct_option,explanation,difficulty,source,year
 * JSON: array of same objects
 */
require_once dirname(__DIR__, 2) . '/helpers.php';
setCorsHeaders();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('POST required', 405);
$admin = requireAdmin();

$db      = getDB();
$rows    = [];
$errors  = [];
$success = 0;

// ── Accept JSON body or file upload ────────────────────────
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';

if (str_contains($contentType, 'application/json')) {
    $rows = body();
    if (!is_array($rows)) jsonError('JSON must be an array of question objects');
} elseif (!empty($_FILES['file'])) {
    $file = $_FILES['file'];
    if ($file['error'] !== UPLOAD_ERR_OK) jsonError('File upload error');
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext !== 'csv') jsonError('Only CSV files supported (use .csv)');
    $rows = parseCsvFile($file['tmp_name']);
} else {
    jsonError('Send CSV file as multipart or JSON array in body');
}

// ── Load category slugs → IDs ────────────────────────────
$catStmt = $db->query("SELECT id, slug FROM categories WHERE is_active = 1");
$slugMap = [];
foreach ($catStmt->fetchAll() as $c) $slugMap[$c['slug']] = $c['id'];

// ── Process rows ─────────────────────────────────────────
$insertStmt = $db->prepare("
    INSERT INTO questions (category_id, question_text, option_a, option_b, option_c, option_d,
    correct_option, explanation, difficulty, source, year, is_active, created_by)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,1,?)
");

foreach ($rows as $i => $row) {
    $rowNum = $i + 2; // +2 because row 1 = header
    $row    = array_map('trim', (array)$row);

    $slug     = $row['category_slug'] ?? $row[0] ?? '';
    $text     = $row['question_text']  ?? $row[1] ?? '';
    $a        = $row['option_a']       ?? $row[2] ?? '';
    $b        = $row['option_b']       ?? $row[3] ?? '';
    $c        = $row['option_c']       ?? $row[4] ?? '';
    $d        = $row['option_d']       ?? $row[5] ?? '';
    $correct  = strtolower($row['correct_option'] ?? $row[6] ?? '');
    $expl     = $row['explanation']    ?? $row[7] ?? '';
    $diff     = $row['difficulty']     ?? $row[8] ?? 'medium';
    $source   = $row['source']         ?? $row[9] ?? '';
    $year     = intval($row['year']    ?? $row[10] ?? 0) ?: null;

    if (!$slug || !$text || !$a || !$b || !$c || !$d || !in_array($correct, ['a','b','c','d'])) {
        $errors[] = "Row $rowNum: Missing required fields";
        continue;
    }

    $catId = $slugMap[$slug] ?? null;
    if (!$catId) {
        $errors[] = "Row $rowNum: Category '$slug' not found";
        continue;
    }

    if (!in_array($diff, ['easy','medium','hard'])) $diff = 'medium';

    try {
        $insertStmt->execute([$catId, $text, $a, $b, $c, $d, $correct, $expl, $diff, $source, $year, $admin['id']]);
        $success++;
    } catch (Exception $e) {
        $errors[] = "Row $rowNum: DB error - " . $e->getMessage();
    }
}

jsonSuccess(['imported' => $success, 'errors' => $errors],
    "$success questions import ho gaye" . ($errors ? ". " . count($errors) . " errors." : ""));

// ── CSV parser ────────────────────────────────────────────
function parseCsvFile(string $path): array {
    $rows = [];
    if (($handle = fopen($path, 'r')) === false) return [];
    $headers = fgetcsv($handle);
    if (!$headers) { fclose($handle); return []; }
    // Normalize headers
    $headers = array_map(fn($h) => strtolower(trim(preg_replace('/\s+/', '_', $h))), $headers);
    while (($row = fgetcsv($handle)) !== false) {
        if (count($row) < count($headers)) continue;
        $rows[] = array_combine($headers, array_slice($row, 0, count($headers)));
    }
    fclose($handle);
    return $rows;
}
