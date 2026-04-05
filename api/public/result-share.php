<?php
require_once dirname(__DIR__).'/helpers.php'; setCorsHeaders();
$token=str('token'); if(!$token) jsonError('Token required');
$db=getDB();
$s=$db->prepare("SELECT qa.*,u.name as user_name FROM quiz_attempts qa JOIN users u ON u.id=qa.user_id WHERE qa.share_token=? AND qa.completed_at IS NOT NULL");
$s->execute([$token]); $att=$s->fetch(); if(!$att) jsonError('Not found',404);
$a=$db->prepare("SELECT aa.is_correct,aa.selected_option,q.question_text,q.option_a,q.option_b,q.option_c,q.option_d,q.correct_option,q.explanation,q.source,q.image_url FROM attempt_answers aa JOIN questions q ON q.id=aa.question_id WHERE aa.attempt_id=? ORDER BY aa.id");
$a->execute([$att['id']]); unset($att['user_id']);
jsonSuccess(['attempt'=>$att,'answers'=>$a->fetchAll()]);
