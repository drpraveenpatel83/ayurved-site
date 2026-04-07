<?php
require_once dirname(__DIR__,2).'/helpers.php';
setCorsHeaders(); requireAdmin();
if ($_SERVER['REQUEST_METHOD']!=='POST') jsonError('POST only',405);

$b    = body();
$id   = !empty($b['id']) ? (int)$b['id'] : null;
$name = trim($b['name'] ?? '');
$parentId = !empty($b['parent_id']) ? (int)$b['parent_id'] : null;
$icon  = trim($b['icon'] ?? '📚');
$color = trim($b['color'] ?? '#1a6e3c');
$order = (int)($b['display_order'] ?? 0);

if (!$name) jsonError('Subject name required');
if (!$parentId) jsonError('Parent category required');

$slug = makeSlug($name);
$db   = getDB();

if ($id) {
    // Check slug conflict (exclude self)
    $exists = $db->prepare("SELECT id FROM categories WHERE slug=? AND id!=?")->execute([$slug,$id]);
    $db->prepare("UPDATE categories SET name=?,slug=?,parent_id=?,type='subject',icon=?,color=?,display_order=?,is_active=1 WHERE id=?")
       ->execute([$name,$slug,$parentId,$icon,$color,$order,$id]);
    jsonSuccess(['id'=>$id], 'Subject updated');
} else {
    // Auto-fix duplicate slug
    $base = $slug; $i = 1;
    while ($db->prepare("SELECT id FROM categories WHERE slug=?")->execute([$slug]) && $db->query("SELECT id FROM categories WHERE slug='$slug'")->fetch()) {
        $slug = $base.'-'.$i++;
    }
    $db->prepare("INSERT INTO categories(name,slug,parent_id,type,icon,color,display_order,is_active) VALUES(?,?,?,'subject',?,?,?,1)")
       ->execute([$name,$slug,$parentId,$icon,$color,$order]);
    jsonSuccess(['id'=>(int)$db->lastInsertId()], 'Subject created', 201);
}
