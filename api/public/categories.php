<?php
require_once dirname(__DIR__).'/helpers.php'; setCorsHeaders();
$db=getDB(); $id=intVal('id');
if($id){
    $s=$db->prepare("SELECT c.*,p.name as parent_name,p.bams_year as parent_bams_year,p.type as parent_type,(SELECT COUNT(*) FROM questions q WHERE q.category_id=c.id AND q.is_active=1) as question_count FROM categories c LEFT JOIN categories p ON p.id=c.parent_id WHERE c.id=? AND c.is_active=1");
    $s->execute([$id]); $cat=$s->fetch(); if(!$cat) jsonError('Not found',404);
    jsonSuccess($cat);
}
$root=intVal('root');
if($root){
    $s=$db->query("SELECT c.*,(SELECT COUNT(*) FROM questions q JOIN categories sc ON sc.id=q.category_id WHERE(sc.id=c.id OR sc.parent_id=c.id)AND q.is_active=1)as question_count FROM categories c WHERE c.parent_id IS NULL AND c.is_active=1 ORDER BY c.display_order,c.id");
    jsonSuccess($s->fetchAll());
}
$type=str('type'); $year=intVal('year'); $pid=intVal('parent_id'); $inc=intVal('include_children');
$w=['c.is_active=1']; $p=[];
if($type){$w[]='c.type=?';$p[]=$type;}
if($year){$w[]='c.bams_year=?';$p[]=$year;}
if($pid){$w[]='c.parent_id=?';$p[]=$pid;}
elseif(!$type&&!$year){$w[]='c.parent_id IS NULL';}
$s=$db->prepare("SELECT c.*,(SELECT COUNT(*) FROM questions q WHERE q.category_id=c.id AND q.is_active=1)as question_count,(SELECT COUNT(*) FROM notes n WHERE n.category_id=c.id AND n.is_published=1)as has_notes FROM categories c WHERE ".implode(' AND ',$w)." ORDER BY c.display_order,c.name");
$s->execute($p); $cats=$s->fetchAll();
if($inc){foreach($cats as &$cat){$cs=$db->prepare("SELECT c.*,(SELECT COUNT(*) FROM questions q WHERE q.category_id=c.id AND q.is_active=1)as question_count,(SELECT COUNT(*) FROM notes n WHERE n.category_id=c.id AND n.is_published=1)as has_notes FROM categories c WHERE c.parent_id=? AND c.is_active=1 ORDER BY c.display_order,c.name");$cs->execute([$cat['id']]);$cat['children']=$cs->fetchAll();}unset($cat);}
jsonSuccess($cats);
