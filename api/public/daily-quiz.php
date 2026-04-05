<?php
require_once dirname(__DIR__).'/helpers.php'; setCorsHeaders();
$db=getDB(); $date=str('date')?:date('Y-m-d');
$s=$db->prepare("SELECT id,quiz_date,title FROM daily_quizzes WHERE quiz_date=? AND is_published=1");
$s->execute([$date]); $quiz=$s->fetch();
if(!$quiz) { jsonSuccess(null,'Quiz not available'); }
$data=['id'=>$quiz['id'],'title'=>$quiz['title'],'date'=>$quiz['quiz_date'],'attempted'=>false,'last_score'=>null];
$user=getAuthUser();
if($user){
    $p=$db->prepare("SELECT id as attempt_id,score,total_questions as total FROM quiz_attempts WHERE user_id=? AND daily_quiz_id=? AND completed_at IS NOT NULL ORDER BY completed_at DESC LIMIT 1");
    $p->execute([$user['id'],$quiz['id']]); $la=$p->fetch();
    if($la){$data['attempted']=true;$data['last_score']=$la;}
}
jsonSuccess($data);
