<?php
// moderacion_accion.php — Aplica la acción del moderador sobre la incidencia
session_start(); if(!isset($_SESSION['uid'])){ header("Location: index.php"); exit; }
// Headers para evitar caché y proteger la sesión
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
require_once "db.php"; error_reporting(E_ALL); ini_set('display_errors',1); ini_set('log_errors',1);
$pdo=(new DB())->pdo(); $uid=$_SESSION['uid'];
$rol=$pdo->prepare("SELECT rol_sistema FROM perfil_usuario WHERE id=? AND estado='activo'"); $rol->execute([$uid]); $r=$rol->fetchColumn();
if(!$r || !in_array($r,['moderador','administrador'])) die('No autorizado');

try{
  $id=(int)($_POST['id']??0);
  $accion=$_POST['accion']??'';
  $coment=trim($_POST['comentario']??'');
  $chg=$_POST['cambio_estado']??'';

  $inc=$pdo->prepare("SELECT producto_id FROM incidencia WHERE id=?"); $inc->execute([$id]); $pid=$inc->fetchColumn();
  if(!$pid) throw new Exception('Incidencia no existe.');

  if(!in_array($accion,['resuelta_aprobado','resuelta_rechazado','solicita_info'])) throw new Exception('Acción inválida');

  $pdo->prepare("UPDATE incidencia SET estado=?, actualizado_en=now(), cerrada_en = CASE WHEN ? IN ('resuelta_aprobado','resuelta_rechazado') THEN now() ELSE NULL END, moderador_encargado_id=COALESCE(moderador_encargado_id,?) WHERE id=?")
      ->execute([$accion,$accion,$uid,$id]);

  // Si rechaza y se pide marcar el producto como rechazado, actualizar estado del producto
  if($accion==='resuelta_rechazado' && $chg==='rechazar'){
    $pdo->prepare("UPDATE producto SET estado='rechazado', actualizado_en=now() WHERE id=?")->execute([$pid]);
  }

  // (Registro opcional del comentario en la descripción de la incidencia)
  if($coment){
    $pdo->prepare("UPDATE incidencia SET descripcion = COALESCE(descripcion,'') || E'\n---\n[mod] ' || :c WHERE id=:i")->execute([':c'=>$coment,':i'=>$id]);
  }

  header("Location: incidencia_ver.php?id=".$id); exit;
}catch(Throwable $e){
  error_log('[MarketGO][mod_accion] '.$e->getMessage());
  die('Error: '.$e->getMessage());
}
