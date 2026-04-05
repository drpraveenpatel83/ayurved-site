<?php
require_once dirname(__DIR__).'/helpers.php'; setCorsHeaders();
if($_SERVER['REQUEST_METHOD']!=='POST') jsonError('POST only',405);
$user=requireAuth(); $db=getDB();
$attemptId=intVal('attempt_id'); $submitted=body()['answers']??[];
if(!$attemptId) jsonError('attempt_id required');
$attempt=$db->prepare("SELECT * FROM quiz_attempts WHERE id=? AND user_id=? AND completed_at IS NULL");
$attempt->execute([$attemptId,$user['id']]); $att=$attempt->fetch();
if(!$att) jsonError('Invalid attempt');
// Get stored correct answers
$correctMap=[];
$stored=json_decode($att['share_token'],true)??[];
foreach($stored as $a) $correctMap[$a['qid']]=['correct'=>$a['correct'],'explanation'=>$a['explanation']??''];
$score=0; $answerRows=[];
foreach($submitted as $ans){
    $qid=(int)$ans['question_id']; $sel=strtolower(trim($ans['selected']??''));
    $correct=$correctMap[$qid]['correct']??null;
    $isCorrect=($sel&&$sel===$correct)?1:0;
    if($isCorrect) $score++;
    $answerRows[]=[$attemptId,$qid,$sel?:null,$isCorrect];
}
$shareToken=generateToken(16);
$db->prepare("UPDATE quiz_attempts SET score=?,completed_at=NOW(),time_taken_secs=?,share_token=? WHERE id=?")
   ->execute([$score,intVal('time_taken'),$shareToken,$attemptId]);
$ins=$db->prepare("INSERT INTO attempt_answers(attempt_id,question_id,selected_option,is_correct)VALUES(?,?,?,?)");
foreach($answerRows as $r) $ins->execute($r);
// Return full result with explanations
$resultRows=[];
foreach($submitted as $ans){
    $qid=(int)$ans['question_id']; $sel=strtolower(trim($ans['selected']??''));
    $q=$db->prepare("SELECT id,question_text,option_a,option_b,option_c,option_d,correct_option,explanation,source,image_url FROM questions WHERE id=?");
    $q->execute([$qid]); $qRow=$q->fetch();
    if($qRow){$qRow['selected_option']=$sel?:null;$qRow['is_correct']=($sel===$qRow['correct_option'])?1:0;$resultRows[]=$qRow;}
}
jsonSuccess(['attempt_id'=>$attemptId,'score'=>$score,'total'=>count($submitted),'percentage'=>count($submitted)?round(($score/count($submitted))*100):0,'share_token'=>$shareToken,'answers'=>$resultRows],'Quiz submit ho gaya!');
