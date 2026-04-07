<?php
require_once dirname(__DIR__,2).'/helpers.php'; setCorsHeaders();
if($_SERVER['REQUEST_METHOD']!=='POST') jsonError('POST only',405); requireAdmin();
$db=getDB(); $b=body();
$id=intParam('id'); $img=trim($b['image_url']??''); $title=trim($b['title']??'');
$sub=trim($b['subtitle']??''); $link=trim($b['link_url']??'');
$color=trim($b['color']??'#E67E22'); $order=intParam('display_order');
if(!$img) jsonError('Image URL required');
if($id){
    $db->prepare("UPDATE banners SET image_url=?,title=?,subtitle=?,link_url=?,color=?,display_order=? WHERE id=?")
       ->execute([$img,$title,$sub,$link,$color,$order,$id]);
    jsonSuccess(['id'=>$id],'Updated');
} else {
    $db->prepare("INSERT INTO banners(image_url,title,subtitle,link_url,color,display_order,is_active)VALUES(?,?,?,?,?,?,1)")
       ->execute([$img,$title,$sub,$link,$color,$order]);
    jsonSuccess(['id'=>(int)$db->lastInsertId()],'Added',201);
}
