<?php
require_once dirname(__DIR__,2).'/helpers.php'; setCorsHeaders(); requireAdmin();
$db=getDB(); $pg=max(1,intVal('page')?:1); $lim=20; $off=($pg-1)*$lim;
$catId=intVal('category_id'); $search=str('search');
$w=['1=1'];$p=[];
if($catId){$w[]='q.category_id=?';$p[]=$catId;}
if($search){$w[]='q.question_text LIKE ?';$p[]="%$search%";}
$wStr=implode(' AND ',$w);
$total=(int)$db->prepare("SELECT COUNT(*) FROM questions q WHERE $wStr")->execute($p)?$db->prepare("SELECT COUNT(*) FROM questions q WHERE $wStr")->execute($p)||1:0;
$cnt=$db->prepare("SELECT COUNT(*) FROM questions q WHERE $wStr"); $cnt->execute($p); $total=(int)$cnt->fetchColumn();
$s=$db->prepare("SELECT q.id,q.question_text,q.option_a,q.option_b,q.option_c,q.option_d,q.correct_option,q.explanation,q.difficulty,q.source,q.year,q.is_active,q.created_at,c.name as category_name FROM questions q JOIN categories c ON c.id=q.category_id WHERE $wStr ORDER BY q.id DESC LIMIT $lim OFFSET $off");
$s->execute($p); jsonSuccess(['questions'=>$s->fetchAll(),'total'=>$total,'page'=>$pg,'pages'=>(int)ceil($total/$lim)]);
