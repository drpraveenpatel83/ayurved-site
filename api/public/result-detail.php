<?php
require_once dirname(__DIR__).'/helpers.php'; setCorsHeaders();
$user=requireAuth(); $id=intVal('attempt_id'); if(!$id) jsonError('attempt_id required');
$db=getDB();
$s=$db->prepare("SELECT * FROM quiz_attempts WHERE id=? AND user_id=? AND completed_at IS NOT NULL");
$s->execute([$id,$user['id']]); $att=$s->fetch(); if(!$att) jsonError('Not found',404);
$a=$db->prepare("SELECT aa.*,q.question_text,q.option_a,q.option_b,q.option_c,q.option_d,q.correct_option,q.explanation,q.source,q.image_url FROM attempt_answers aa JOIN questions q ON q.id=aa.question_id WHERE aa.attempt_id=? ORDER BY aa.id");
$a->execute([$id]); jsonSuccess(['attempt'=>$att,'answers'=>$a->fetchAll()]);
