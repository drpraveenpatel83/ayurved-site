<?php
require_once dirname(__DIR__).'/helpers.php'; setCorsHeaders();
$db=getDB(); $catId=intParam('category_id'); $type=str('type'); $checkOnly=intParam('check_only');
if(!$catId) jsonError('category_id required');
if($checkOnly){
    $s=$db->prepare("SELECT type,COUNT(*) as cnt FROM notes WHERE category_id=? AND is_published=1 GROUP BY type");
    $s->execute([$catId]); $rows=$s->fetchAll();
    $r=['has_syllabus'=>false,'has_notes'=>false];
    foreach($rows as $row){if($row['type']==='syllabus')$r['has_syllabus']=true;else $r['has_notes']=true;}
    jsonSuccess($r);
}
requireAuth();
$w=['category_id=?','is_published=1']; $p=[$catId];
if($type){$w[]='type=?';$p[]=$type;}
$s=$db->prepare("SELECT id,title,content,file_url,type,display_order FROM notes WHERE ".implode(' AND ',$w)." ORDER BY display_order,id");
$s->execute($p); jsonSuccess($s->fetchAll());
