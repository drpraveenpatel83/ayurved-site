<?php
require_once dirname(__DIR__,2).'/helpers.php'; setCorsHeaders();
if($_SERVER['REQUEST_METHOD']!=='POST') jsonError('POST only',405);
$admin=requireAdmin(); $db=getDB(); $b=body();
$id=intVal('id'); $catId=intVal('category_id'); $title=trim($b['title']??'');
$content=$b['content']??''; $type=$b['type']??'short_notes';
$pub=isset($b['is_published'])?(int)$b['is_published']:1; $ord=intVal('display_order');
if(!in_array($type,['syllabus','short_notes','full_notes'])) jsonError('Invalid type');
if(!$catId||!$title) jsonError('category_id and title required');
$content=strip_tags($content,'<p><br><b><i><u><strong><em><h1><h2><h3><h4><ul><ol><li><table><thead><tbody><tr><th><td><div><span><hr><blockquote><pre><code><mark><a><img>');
if($id){
    $db->prepare("UPDATE notes SET category_id=?,title=?,content=?,type=?,is_published=?,display_order=?,updated_at=NOW() WHERE id=?")
       ->execute([$catId,$title,$content,$type,$pub,$ord,$id]);
    jsonSuccess(['id'=>$id],'Updated');
} else {
    $db->prepare("INSERT INTO notes(category_id,title,content,type,is_published,display_order,created_by)VALUES(?,?,?,?,?,?,?)")
       ->execute([$catId,$title,$content,$type,$pub,$ord,$admin['id']]);
    jsonSuccess(['id'=>(int)$db->lastInsertId()],'Saved',201);
}
