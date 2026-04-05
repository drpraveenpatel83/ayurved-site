<?php
require_once dirname(__DIR__,2).'/helpers.php'; setCorsHeaders();
if($_SERVER['REQUEST_METHOD']!=='POST') jsonError('POST only',405);
$email=strtolower(trim(str('email'))); $pass=str('password');
if(!$email||!$pass) jsonError('Email aur password required');
$db=getDB();
$s=$db->prepare("SELECT id,name,email,role,password_hash,is_active,membership_type FROM users WHERE email=?");
$s->execute([$email]); $u=$s->fetch();
if(!$u||!password_verify($pass,$u['password_hash'])) jsonError('Email ya password galat hai',401);
if(!$u['is_active']) jsonError('Account disabled',403);
$token=generateToken(); $exp=date('Y-m-d H:i:s',time()+SESSION_LIFETIME);
$db->prepare("INSERT INTO user_sessions(user_id,session_token,ip_address,expires_at)VALUES(?,?,?,?)")
   ->execute([$u['id'],$token,$_SERVER['REMOTE_ADDR']??null,$exp]);
$db->prepare("UPDATE users SET last_login_at=NOW() WHERE id=?")->execute([$u['id']]);
unset($u['password_hash'],$u['is_active']);
jsonSuccess(['token'=>$token,'user'=>$u],'Login successful');
