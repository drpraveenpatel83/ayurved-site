<?php
require_once dirname(__DIR__,2).'/helpers.php'; setCorsHeaders(); requireAdmin();
$db=getDB(); $catId=intVal('category_id'); $type=str('type');
$w=['1=1'];$p=[];
if($catId){$w[]='n.category_id=?';$p[]=$catId;}
if($type){$w[]='n.type=?';$p[]=$type;}
$s=$db->prepare("SELECT n.*,c.name as category_name FROM notes n JOIN categories c ON c.id=n.category_id WHERE ".implode(' AND ',$w)." ORDER BY n.id DESC LIMIT 100");
$s->execute($p); jsonSuccess(['notes'=>$s->fetchAll(),'total'=>count($s->fetchAll()?:[])]);
