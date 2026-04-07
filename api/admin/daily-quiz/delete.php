<?php
require_once dirname(__DIR__,2).'/helpers.php'; setCorsHeaders();
if($_SERVER['REQUEST_METHOD']!=='POST') jsonError('POST only',405); requireAdmin();
$id=intParam('id'); if(!$id) jsonError('id required'); $db=getDB();
$db->prepare("DELETE FROM daily_quiz_questions WHERE daily_quiz_id=?")->execute([$id]);
$db->prepare("DELETE FROM daily_quizzes WHERE id=?")->execute([$id]);
jsonSuccess([],'Deleted');
