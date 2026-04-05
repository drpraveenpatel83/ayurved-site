<?php
require_once dirname(__DIR__,2).'/helpers.php'; setCorsHeaders();
if($_SERVER['REQUEST_METHOD']!=='POST') jsonError('POST only',405);
$admin=requireAdmin(); $db=getDB(); $rows=[]; $ok=0; $errs=[];
$ct=$_SERVER['CONTENT_TYPE']??'';
if(str_contains($ct,'application/json')){$rows=body();if(!is_array($rows))jsonError('Array expected');}
elseif(!empty($_FILES['file'])){
    $f=$_FILES['file']; if($f['error']!==0)jsonError('Upload error');
    if(strtolower(pathinfo($f['name'],PATHINFO_EXTENSION))!=='csv')jsonError('CSV only');
    if(($h=fopen($f['tmp_name'],'r'))===false)jsonError('Cannot read');
    $hdrs=array_map(fn($x)=>strtolower(trim(preg_replace('/\s+/','_',$x))),fgetcsv($h));
    while(($row=fgetcsv($h))!==false){if(count($row)<count($hdrs))continue;$rows[]=array_combine($hdrs,array_slice($row,0,count($hdrs)));}
    fclose($h);
} else jsonError('Send CSV file or JSON array');
$cats=$db->query("SELECT id,slug FROM categories WHERE is_active=1")->fetchAll();
$slugMap=array_column($cats,'id','slug');
$ins=$db->prepare("INSERT INTO questions(category_id,question_text,option_a,option_b,option_c,option_d,correct_option,explanation,difficulty,source,year,is_active,created_by)VALUES(?,?,?,?,?,?,?,?,?,?,?,1,?)");
foreach($rows as $i=>$row){
    $row=array_map('trim',(array)$row); $rn=$i+2;
    $slug=$row['category_slug']??$row[0]??''; $text=$row['question_text']??$row[1]??'';
    $a=$row['option_a']??$row[2]??''; $b=$row['option_b']??$row[3]??'';
    $c=$row['option_c']??$row[4]??''; $d=$row['option_d']??$row[5]??'';
    $correct=strtolower($row['correct_option']??$row[6]??''); $expl=$row['explanation']??$row[7]??'';
    $diff=in_array($row['difficulty']??'',['easy','medium','hard'])?$row['difficulty']:'medium';
    $src=$row['source']??$row[9]??''; $yr=intval($row['year']??$row[10]??0)?:null;
    if(!$slug||!$text||!$a||!$b||!$c||!$d||!in_array($correct,['a','b','c','d'])){$errs[]="Row $rn: Missing fields";continue;}
    $catId=$slugMap[$slug]??null; if(!$catId){$errs[]="Row $rn: Category '$slug' not found";continue;}
    try{$ins->execute([$catId,$text,$a,$b,$c,$d,$correct,$expl,$diff,$src,$yr,$admin['id']]);$ok++;}
    catch(Exception $e){$errs[]="Row $rn: ".$e->getMessage();}
}
jsonSuccess(['imported'=>$ok,'errors'=>$errs],"$ok questions imported".(count($errs)?" | ".count($errs)." errors":""));
