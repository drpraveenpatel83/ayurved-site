<?php
require_once dirname(__DIR__,2).'/helpers.php'; setCorsHeaders();
if($_SERVER['REQUEST_METHOD']!=='POST') jsonError('POST only',405);
$admin=requireAdmin(); $db=getDB(); $ok=0; $errs=[];
if(empty($_FILES['file'])) jsonError('CSV file required');
$f=$_FILES['file']; if($f['error']!==0)jsonError('Upload error');
if(strtolower(pathinfo($f['name'],PATHINFO_EXTENSION))!=='csv') jsonError('CSV only');
if(($h=fopen($f['tmp_name'],'r'))===false) jsonError('Cannot read file');
$hdrs=array_map(fn($x)=>strtolower(trim($x)),fgetcsv($h));
$cats=$db->query("SELECT id,slug FROM categories WHERE is_active=1")->fetchAll();
$slugMap=array_column($cats,'id','slug');
while(($row=fgetcsv($h))!==false){
    if(count($row)<2) continue;
    $r=array_combine(array_slice($hdrs,0,count($row)),$row);
    $date=trim($r['date']??'');
    if(!$date||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)){$errs[]="Invalid date: $date";continue;}
    $db->beginTransaction();
    try{
        $db->prepare("INSERT INTO daily_quizzes(quiz_date,title,is_published)VALUES(?,?,0)ON DUPLICATE KEY UPDATE title=VALUES(title)")->execute([$date,$r['title']??null]);
        $dqId=$db->lastInsertId();
        if(!$dqId){$dqId=$db->prepare("SELECT id FROM daily_quizzes WHERE quiz_date=?")->execute([$date])?$db->query("SELECT id FROM daily_quizzes WHERE quiz_date='$date'")->fetchColumn():null;}
        if(!$dqId){$errs[]="Date $date: Could not get quiz ID";$db->rollBack();continue;}
        $db->prepare("DELETE FROM daily_quiz_questions WHERE daily_quiz_id=?")->execute([$dqId]);
        $qIds=[]; $catSlug=trim($r['category_slug']??''); $catId=$slugMap[$catSlug]??1;
        for($i=1;$i<=10;$i++){
            $text=trim($r["q{$i}_text"]??''); $oa=trim($r["q{$i}_a"]??''); $ob=trim($r["q{$i}_b"]??'');
            $oc=trim($r["q{$i}_c"]??''); $od=trim($r["q{$i}_d"]??'');
            $correct=strtolower(trim($r["q{$i}_correct"]??'')); $expl=trim($r["q{$i}_explanation"]??'');
            if(!$text||!$oa||!$ob||!$oc||!$od||!in_array($correct,['a','b','c','d'])) continue;
            $db->prepare("INSERT INTO questions(category_id,question_text,option_a,option_b,option_c,option_d,correct_option,explanation,is_active,created_by)VALUES(?,?,?,?,?,?,?,?,1,?)")
               ->execute([$catId,$text,$oa,$ob,$oc,$od,$correct,$expl,$admin['id']]);
            $qIds[]=(int)$db->lastInsertId();
        }
        $lnk=$db->prepare("INSERT INTO daily_quiz_questions(daily_quiz_id,question_id,display_order)VALUES(?,?,?)");
        foreach($qIds as $ord=>$qid) $lnk->execute([$dqId,$qid,$ord+1]);
        $db->prepare("UPDATE daily_quizzes SET is_published=1 WHERE id=?")->execute([$dqId]);
        $db->commit(); $ok++;
    } catch(Exception $e){$db->rollBack();$errs[]="Date $date: ".$e->getMessage();}
}
fclose($h);
jsonSuccess(['imported_days'=>$ok,'errors'=>$errs],"$ok din ka quiz import ho gaya");
