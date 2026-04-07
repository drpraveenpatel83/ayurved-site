<?php
require_once dirname(__DIR__,2).'/helpers.php'; setCorsHeaders();
if($_SERVER['REQUEST_METHOD']!=='POST') jsonError('POST only',405);
$admin=requireAdmin(); $db=getDB(); $b=body();
$id=intParam('id'); $catId=intParam('category_id');
$text=trim($b['question_text']??''); $a=trim($b['option_a']??''); $bO=trim($b['option_b']??'');
$c=trim($b['option_c']??''); $d=trim($b['option_d']??''); $correct=strtolower(trim($b['correct_option']??''));
$expl=trim($b['explanation']??''); $diff=in_array($b['difficulty']??'',['easy','medium','hard'])?$b['difficulty']:'medium';
$src=trim($b['source']??''); $yr=intParam('year')?:null;
if(!$catId||!$text||!$a||!$bO||!$c||!$d||!in_array($correct,['a','b','c','d'])) jsonError('All fields required');
if($id){
    $db->prepare("UPDATE questions SET category_id=?,question_text=?,option_a=?,option_b=?,option_c=?,option_d=?,correct_option=?,explanation=?,difficulty=?,source=?,year=? WHERE id=?")
       ->execute([$catId,$text,$a,$bO,$c,$d,$correct,$expl,$diff,$src,$yr,$id]);
    jsonSuccess(['id'=>$id],'Updated');
} else {
    $db->prepare("INSERT INTO questions(category_id,question_text,option_a,option_b,option_c,option_d,correct_option,explanation,difficulty,source,year,is_active,created_by)VALUES(?,?,?,?,?,?,?,?,?,?,?,1,?)")
       ->execute([$catId,$text,$a,$bO,$c,$d,$correct,$expl,$diff,$src,$yr,$admin['id']]);
    jsonSuccess(['id'=>(int)$db->lastInsertId()],'Added',201);
}
