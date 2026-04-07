<?php
require_once dirname(__DIR__,2).'/helpers.php';
setCorsHeaders(); requireAdmin();

$catId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$db    = getDB();

$where  = $catId ? "WHERE sp.category_id=?" : "WHERE 1=1";
$params = $catId ? [$catId] : [];

$stmt = $db->prepare("
    SELECT sp.*, c.name AS subject_name
    FROM subject_pdfs sp
    JOIN categories c ON c.id=sp.category_id
    {$where}
    ORDER BY sp.category_id, sp.display_order, sp.id DESC
");
$stmt->execute($params);
jsonSuccess($stmt->fetchAll());
