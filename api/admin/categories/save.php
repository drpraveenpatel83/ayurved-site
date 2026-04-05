<?php
require_once dirname(__DIR__, 2) . '/helpers.php';
setCorsHeaders();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('POST required', 405);
requireAdmin();

$db       = getDB();
$b        = body();
$id       = intVal('id');
$parentId = intVal('parent_id') ?: null;
$name     = trim($b['name'] ?? '');
$type     = $b['type'] ?? 'subject';
$bamsYear = intVal('bams_year') ?: null;
$icon     = trim($b['icon'] ?? '');
$color    = trim($b['color'] ?? '#E67E22');
$order    = intVal('display_order');
$isActive = isset($b['is_active']) ? (int)$b['is_active'] : 1;
$desc     = trim($b['description'] ?? '');

if (!$name) jsonError('Name required');
$slug = makeSlug($name);

// Ensure unique slug
$check = $db->prepare("SELECT id FROM categories WHERE slug = ?" . ($id ? " AND id != $id" : ""));
$check->execute([$slug]);
if ($check->fetch()) {
    $slug = $slug . '-' . time();
}

if ($id) {
    $db->prepare("UPDATE categories SET parent_id=?,name=?,slug=?,type=?,bams_year=?,icon=?,color=?,display_order=?,is_active=?,description=? WHERE id=?")
       ->execute([$parentId,$name,$slug,$type,$bamsYear,$icon,$color,$order,$isActive,$desc,$id]);
    jsonSuccess(['id'=>$id,'slug'=>$slug],'Category updated');
} else {
    $db->prepare("INSERT INTO categories (parent_id,name,slug,type,bams_year,icon,color,display_order,is_active,description) VALUES (?,?,?,?,?,?,?,?,?,?)")
       ->execute([$parentId,$name,$slug,$type,$bamsYear,$icon,$color,$order,$isActive,$desc]);
    jsonSuccess(['id'=>(int)$db->lastInsertId(),'slug'=>$slug],'Category created',201);
}
