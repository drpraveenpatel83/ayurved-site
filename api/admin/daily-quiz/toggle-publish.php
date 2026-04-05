<?php
require_once dirname(__DIR__,2).'/helpers.php'; setCorsHeaders();
if($_SERVER['REQUEST_METHOD']!=='POST') jsonError('POST only',405); requireAdmin();
$id=intVal('id'); $val=intVal('is_published'); if(!$id) jsonError('id required');
getDB()->prepare("UPDATE daily_quizzes SET is_published=? WHERE id=?")->execute([$val,$id]);
jsonSuccess([],'Updated');
