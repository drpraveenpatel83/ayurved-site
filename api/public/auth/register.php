<?php
require_once dirname(__DIR__,2).'/helpers.php'; setCorsHeaders();
if($_SERVER['REQUEST_METHOD']!=='POST') jsonError('POST only',405);
$name=trim(str('name')); $email=strtolower(trim(str('email')));
$phone=preg_replace('/\D/','',str('phone')); $pass=str('password');
$yearOfStudy=trim(str('year_of_study'));
if(!$name||!$email||!$pass) jsonError('Naam, email aur password required');
if(!filter_var($email,FILTER_VALIDATE_EMAIL)) jsonError('Valid email daalo');
if(strlen($pass)<6) jsonError('Password minimum 6 characters');
$db=getDB();
if($db->prepare("SELECT id FROM users WHERE email=?")->execute([$email]) && $db->query("SELECT FOUND_ROWS()")->fetchColumn()) {
    $chk=$db->prepare("SELECT id FROM users WHERE email=?"); $chk->execute([$email]);
    if($chk->fetch()) jsonError('Email already registered hai');
}
$hash=password_hash($pass,PASSWORD_DEFAULT);
$db->prepare("INSERT INTO users(name,email,phone,password_hash,role,year_of_study)VALUES(?,?,?,?,'student',?)")
   ->execute([$name,$email,$phone?:null,$hash,$yearOfStudy?:null]);
$uid=(int)$db->lastInsertId();
$token=generateToken(); $exp=date('Y-m-d H:i:s',time()+SESSION_LIFETIME);
$db->prepare("INSERT INTO user_sessions(user_id,session_token,ip_address,expires_at)VALUES(?,?,?,?)")
   ->execute([$uid,$token,$_SERVER['REMOTE_ADDR']??null,$exp]);
jsonSuccess(['token'=>$token,'user'=>['id'=>$uid,'name'=>$name,'email'=>$email,'role'=>'student','membership_type'=>'free']],'Account ban gaya!',201);
