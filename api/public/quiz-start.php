<?php
require_once dirname(__DIR__).'/helpers.php'; setCorsHeaders();
if($_SERVER['REQUEST_METHOD']!=='POST') jsonError('POST only',405);
$user=requireAuth(); $db=getDB();
$type=str('type'); $catId=intParam('category_id'); $date=str('date');
$limits=['random'=>10,'daily'=>10,'practice'=>10,'weekly'=>20,'monthly'=>100,'previous_year'=>10,'mock'=>10];
$limit=$limits[$type]??10;
$dailyId=null;
if($type==='daily'){
    $d=str('date')?:date('Y-m-d');
    $dq=$db->prepare("SELECT id FROM daily_quizzes WHERE quiz_date=? AND is_published=1");
    $dq->execute([$d]); $dqRow=$dq->fetch();
    if(!$dqRow) jsonError('Aaj ka quiz available nahi');
    $dailyId=$dqRow['id'];
    $prev=$db->prepare("SELECT id FROM quiz_attempts WHERE user_id=? AND daily_quiz_id=? AND completed_at IS NOT NULL");
    $prev->execute([$user['id'],$dailyId]);
    if($prev->fetch()) jsonError('Aaj ka quiz already attempt kar liya');
    $qs=$db->prepare("SELECT q.id,q.question_text,q.option_a,q.option_b,q.option_c,q.option_d,q.correct_option,q.explanation,q.image_url FROM daily_quiz_questions dq JOIN questions q ON q.id=dq.question_id WHERE dq.daily_quiz_id=? AND q.is_active=1 ORDER BY dq.display_order LIMIT 10");
    $qs->execute([$dailyId]);
} elseif($catId){
    $qs=$db->prepare("SELECT id,question_text,option_a,option_b,option_c,option_d,correct_option,explanation,image_url FROM questions WHERE category_id=? AND is_active=1 ORDER BY RAND() LIMIT $limit");
    $qs->execute([$catId]);
} else {
    $qs=$db->query("SELECT id,question_text,option_a,option_b,option_c,option_d,correct_option,explanation,image_url FROM questions WHERE is_active=1 ORDER BY RAND() LIMIT $limit");
}
$questions=$qs->fetchAll();
if(empty($questions)) jsonError('Is section mein questions abhi available nahi hain');
$db->prepare("INSERT INTO quiz_attempts(user_id,quiz_type,category_id,daily_quiz_id,total_questions,started_at)VALUES(?,?,?,?,?,NOW())")
   ->execute([$user['id'],$type,$catId?:null,$dailyId,count($questions)]);
$attemptId=(int)$db->lastInsertId();
// Remove correct_option from client response — answers stored server side
$clientQs=array_map(fn($q)=>array_diff_key($q,['correct_option'=>1,'explanation'=>1]),$questions);
// Store correct answers in attempt temporarily
$answers=array_map(fn($q)=>['qid'=>$q['id'],'correct'=>$q['correct_option'],'explanation'=>$q['explanation']??''],$questions);
$db->prepare("UPDATE quiz_attempts SET share_token=? WHERE id=?")->execute([json_encode($answers),$attemptId]);
jsonSuccess(['attempt_id'=>$attemptId,'questions'=>$clientQs,'total'=>count($questions)]);
