<?php
require_once dirname(__DIR__,2).'/helpers.php';
setCorsHeaders(); requireAdmin();
if ($_SERVER['REQUEST_METHOD']!=='POST') jsonError('POST only',405);

$b          = body();
$id         = !empty($b['id']) ? (int)$b['id'] : null;
$catId      = (int)($b['category_id'] ?? 0);
$title      = trim($b['title'] ?? '');
$fileUrl    = trim($b['file_url'] ?? '');
$pdfType    = $b['pdf_type'] ?? 'syllabus';
$order      = (int)($b['display_order'] ?? 0);
$published  = (int)(bool)($b['is_published'] ?? 1);

if (!$catId)   jsonError('Subject required');
if (!$title)   jsonError('Title required');
if (!$fileUrl) jsonError('File URL required');
if (!in_array($pdfType,['syllabus','notes','pyq','other'])) $pdfType='other';

$db = getDB();
if ($id) {
    $db->prepare("UPDATE subject_pdfs SET category_id=?,title=?,file_url=?,pdf_type=?,display_order=?,is_published=? WHERE id=?")
       ->execute([$catId,$title,$fileUrl,$pdfType,$order,$published,$id]);
    jsonSuccess(['id'=>$id],'PDF updated');
} else {
    $db->prepare("INSERT INTO subject_pdfs(category_id,title,file_url,pdf_type,display_order,is_published) VALUES(?,?,?,?,?,?)")
       ->execute([$catId,$title,$fileUrl,$pdfType,$order,$published]);
    jsonSuccess(['id'=>(int)$db->lastInsertId()],'PDF added',201);
}
