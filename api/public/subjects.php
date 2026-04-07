<?php
require_once dirname(__DIR__).'/helpers.php';
setCorsHeaders();

$parentSlug = $_GET['parent'] ?? '';   // bams-1st-prof, aiapget, etc.
$db = getDB();

if ($parentSlug) {
    // Get parent info
    $parent = $db->prepare("SELECT id,name,slug,type,icon,color FROM categories WHERE slug=? AND is_active=1");
    $parent->execute([$parentSlug]);
    $parentRow = $parent->fetch();
    if (!$parentRow) jsonError('Category not found', 404);

    // Get subjects under this parent
    $stmt = $db->prepare("
        SELECT c.id, c.name, c.slug, c.icon, c.color, c.display_order,
               (SELECT COUNT(*) FROM notes n WHERE n.category_id=c.id AND n.is_published=1) AS notes_count,
               (SELECT COUNT(*) FROM subject_pdfs sp WHERE sp.category_id=c.id AND sp.is_published=1) AS pdfs_count,
               (SELECT COUNT(*) FROM test_subject_map tsm WHERE tsm.category_id=c.id) AS tests_count
        FROM categories c
        WHERE c.parent_id=? AND c.type='subject' AND c.is_active=1
        ORDER BY c.display_order, c.name
    ");
    $stmt->execute([$parentRow['id']]);
    $subjects = $stmt->fetchAll();

    jsonSuccess(['parent'=>$parentRow, 'subjects'=>$subjects]);
}

// List all parent categories
$rows = $db->query("
    SELECT id,name,slug,type,icon,color,bams_year,display_order
    FROM categories
    WHERE type IN('bams_prof','aiapget','govt_exam','ncism') AND is_active=1
    ORDER BY display_order,id
")->fetchAll();
jsonSuccess($rows);
