<?php
require_once dirname(__DIR__,2).'/helpers.php';
setCorsHeaders(); requireAdmin();

$db       = getDB();
$type     = $_GET['type'] ?? '';   // bams-1st-prof, bams-2nd-prof, aiapget, etc.
$parentId = isset($_GET['parent_id']) ? (int)$_GET['parent_id'] : null;

// Get parent categories (profs, aiapget etc.)
if ($type === 'parents') {
    $rows = $db->query("SELECT id,name,slug,type,icon,color,bams_year,display_order
                        FROM categories
                        WHERE type IN('bams_prof','aiapget','govt_exam','ncism') AND is_active=1
                        ORDER BY display_order,id")->fetchAll();
    jsonSuccess($rows);
}

// Get subjects under a parent
$where = "c.type='subject' AND c.is_active=1";
$params = [];

if ($parentId) {
    $where .= " AND c.parent_id=?";
    $params[] = $parentId;
}

$rows = $db->prepare("
    SELECT c.id, c.name, c.slug, c.icon, c.color, c.display_order, c.parent_id,
           p.name AS parent_name, p.slug AS parent_slug,
           (SELECT COUNT(*) FROM notes n WHERE n.category_id=c.id AND n.is_published=1) AS notes_count,
           (SELECT COUNT(*) FROM subject_pdfs sp WHERE sp.category_id=c.id AND sp.is_published=1) AS pdfs_count,
           (SELECT COUNT(*) FROM test_subject_map tsm WHERE tsm.category_id=c.id) AS tests_count
    FROM categories c
    LEFT JOIN categories p ON p.id=c.parent_id
    WHERE {$where}
    ORDER BY c.parent_id, c.display_order, c.name
");
$rows->execute($params);
jsonSuccess($rows->fetchAll());
