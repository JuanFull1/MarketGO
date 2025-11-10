<?php
session_start(); if(!isset($_SESSION['uid'])){ header("Location: index.php"); exit; }
require_once "db.php"; error_reporting(E_ALL); ini_set('display_errors',1); ini_set('log_errors',1);
$pdo=(new DB())->pdo(); $uid=$_SESSION['uid']; $pid=(int)($_POST['id']??0);
try{
  $chk=$pdo->prepare("SELECT 1 FROM producto_guardado WHERE usuario_id=? AND producto_id=?"); $chk->execute([$uid,$pid]);
  if($chk->fetch()){ $pdo->prepare("DELETE FROM producto_guardado WHERE usuario_id=? AND producto_id=?")->execute([$uid,$pid]); }
  else{ $pdo->prepare("INSERT INTO producto_guardado (usuario_id,producto_id) VALUES (?,?)")->execute([$uid,$pid]); }
  header("Location: ".$_SERVER['HTTP_REFERER']); exit;
}catch(Throwable $e){ error_log('[MarketGO][guardado] '.$e->getMessage()); header("Location: catalogo.php?error=guardado"); exit; }
