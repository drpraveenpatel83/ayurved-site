<?php
require_once dirname(__DIR__, 2) . '/helpers.php';
setCorsHeaders();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('POST required', 405);
requireAdmin();
$b = body();
$id       = intVal('id');
$imgUrl   = trim($b['image_url'] ?? '');
$title    = trim($b['title'] ?? '');
$subtitle = trim($b['subtitle'] ?? '');
$linkUrl  = trim($b['link_url'] ?? '');
$color    = trim($b['color'] ?? '#E67E22');
$order    = intVal('display_order');
if (!$imgUrl) jsonError('Image URL required');
$db = getDB();
if ($id) {
    $db->prepare("UPDATE banners SET image_url=?,title=?,subtitle=?,link_url=?,color=?,display_order=? WHERE id=?")
       ->execute([$imgUrl,$title,$subtitle,$linkUrl,$color,$order,$id]);
    jsonSuccess(['id'=>$id],'Banner updated');
} else {
    $db->prepare("INSERT INTO banners (image_url,title,subtitle,link_url,color,display_order,is_active) VALUES (?,?,?,?,?,?,1)")
       ->execute([$imgUrl,$title,$subtitle,$linkUrl,$color,$order]);
    jsonSuccess(['id'=>(int)$db->lastInsertId()],'Banner added',201);
}
