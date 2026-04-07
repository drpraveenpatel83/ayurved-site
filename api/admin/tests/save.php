<?php
require_once dirname(__DIR__,2).'/helpers.php'; setCorsHeaders(); requireAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('POST only', 405);

$body           = body();
$id             = !empty($body['id']) ? (int)$body['id'] : null;
$title          = str($body['title'] ?? '');
$examType       = str($body['exam_type'] ?? 'aiapget');
$totalQuestions = max(1, (int)($body['total_questions'] ?? 100));
$timeMinutes    = max(1, (int)($body['time_minutes'] ?? 90));
$isPublished    = (int)(bool)($body['is_published'] ?? 0);
$description    = str($body['description'] ?? '');

if (!$title) jsonError('Title required');
if (!in_array($examType, ['aiapget','govt_exam'])) jsonError('Invalid exam type');

$db = getDB();
if ($id) {
    $db->prepare("UPDATE mock_tests SET title=?,exam_type=?,total_questions=?,time_minutes=?,is_published=?,description=? WHERE id=?")
       ->execute([$title,$examType,$totalQuestions,$timeMinutes,$isPublished,$description?:null,$id]);
    jsonSuccess(['id'=>$id],'Test updated');
} else {
    $db->prepare("INSERT INTO mock_tests(title,exam_type,total_questions,time_minutes,is_published,description) VALUES(?,?,?,?,?,?)")
       ->execute([$title,$examType,$totalQuestions,$timeMinutes,$isPublished,$description?:null]);
    jsonSuccess(['id'=>(int)$db->lastInsertId()],'Test created',201);
}
