<?php
require_once dirname(__DIR__,2).'/helpers.php'; setCorsHeaders();
if($_SERVER['REQUEST_METHOD']!=='POST') jsonError('POST only',405); requireAdmin();
$db=getDB(); $b=body();
$id=intVal('id'); $pid=intVal('parent_id')?:null; $name=trim($b['name']??'');
$type=$b['type']??'subject'; $yr=intVal('bams_year')?:null;
$icon=trim($b['icon']??''); $color=trim($b['color']??'#E67E22');
$order=intVal('display_order'); $active=isset($b['is_active'])?(int)$b['is_active']:1;
if(!$name) jsonError('Name required');
$slug=makeSlug($name);
$chk=$db->prepare("SELECT id FROM categories WHERE slug=?".($id?" AND id!=?":""));
$params=[$slug]; if($id) $params[]=$id; $chk->execute($params);
if($chk->fetch()) $slug=$slug.'-'.time();
if($id){
    $db->prepare("UPDATE categories SET parent_id=?,name=?,slug=?,type=?,bams_year=?,icon=?,color=?,display_order=?,is_active=? WHERE id=?")
       ->execute([$pid,$name,$slug,$type,$yr,$icon,$color,$order,$active,$id]);
    jsonSuccess(['id'=>$id,'slug'=>$slug],'Updated');
} else {
    $db->prepare("INSERT INTO categories(parent_id,name,slug,type,bams_year,icon,color,display_order,is_active)VALUES(?,?,?,?,?,?,?,?,?)")
       ->execute([$pid,$name,$slug,$type,$yr,$icon,$color,$order,$active]);
    jsonSuccess(['id'=>(int)$db->lastInsertId(),'slug'=>$slug],'Created',201);
}
