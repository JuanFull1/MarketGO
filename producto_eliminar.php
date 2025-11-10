<?php
session_start(); if(!isset($_SESSION['uid'])){ header("Location: index.php"); exit; }
// Headers para evitar caché y proteger la sesión
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
require_once "db.php";
error_reporting(E_ALL); ini_set('display_errors',1); ini_set('log_errors',1);

$pdo=(new DB())->pdo(); $uid=$_SESSION['uid'];
$id=(int)($_GET['id']??0);

// validar dueño
$s=$pdo->prepare("SELECT id, estado FROM producto WHERE id=? AND vendedor_id=?");
$s->execute([$id,$uid]); $p=$s->fetch();
if(!$p){ header("Location: panel.php?error=".urlencode("No encontrado o sin permisos")); exit; }

// bloquear si peligroso/en revisión
$inq=$pdo->prepare("SELECT 1 FROM incidencia WHERE producto_id=? AND estado IN ('abierta','solicita_info','en_apelacion') LIMIT 1");
$inq->execute([$id]); $hasOpen=(bool)$inq->fetch();

if($p['estado']!=='activo' || $hasOpen){
  header("Location: panel.php?error=".urlencode("No puedes eliminar una publicación oculta o en revisión."));
  exit;
}

// eliminar (soft delete recomendado)
$pdo->prepare("UPDATE producto SET estado='eliminado', actualizado_en=now() WHERE id=?")->execute([$id]);
header("Location: panel.php?ok=".urlencode("Publicación eliminada"));
