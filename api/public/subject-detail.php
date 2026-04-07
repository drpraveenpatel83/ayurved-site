<?php
require_once dirname(__DIR__).'/helpers.php';
setCorsHeaders();

$slug = $_GET['slug'] ?? '';
if (!$slug) jsonError('slug required');

$db = getDB();

// Subject info
$stmt = $db->prepare("
    SELECT c.id,c.name,c.slug,c.icon,c.color,
           p.id AS parent_id, p.name AS parent_name, p.slug AS parent_slug, p.type AS parent_type
    FROM categories c
    LEFT JOIN categories p ON p.id=c.parent_id
    WHERE c.slug=? AND c.type='subject' AND c.is_active=1
");
$stmt->execute([$slug]);
$subject = $stmt->fetch();
if (!$subject) jsonError('Subject not found', 404);

$id = $subject['id'];

// Notes (text)
$notes = $db->prepare("
    SELECT id,title,content,type,display_order
    FROM notes
    WHERE category_id=? AND is_published=1
    ORDER BY display_order,id
");
$notes->execute([$id]);
$notesData = $notes->fetchAll();

// PDFs
$pdfs = $db->prepare("
    SELECT id,title,file_url,pdf_type,display_order
    FROM subject_pdfs
    WHERE category_id=? AND is_published=1
    ORDER BY display_order,id
");
$pdfs->execute([$id]);
$pdfsData = $pdfs->fetchAll();

// Tests assigned to this subject
$tests = $db->prepare("
    SELECT mt.id,mt.title,mt.exam_type,mt.total_questions,mt.time_minutes,mt.description,mt.is_published
    FROM test_subject_map tsm
    JOIN mock_tests mt ON mt.id=tsm.test_id
    WHERE tsm.category_id=? AND mt.is_published=1
    ORDER BY mt.id DESC
");
$tests->execute([$id]);
$testsData = $tests->fetchAll();

jsonSuccess([
    'subject' => $subject,
    'notes'   => $notesData,
    'pdfs'    => $pdfsData,
    'tests'   => $testsData,
]);
