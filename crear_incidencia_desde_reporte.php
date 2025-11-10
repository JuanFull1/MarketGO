<?php
session_start(); if(!isset($_SESSION['uid'])){ header("Location: index.php"); exit; }
require_once "db.php"; error_reporting(E_ALL); ini_set('display_errors',1); ini_set('log_errors',1);
$pdo=(new DB())->pdo(); $uid=$_SESSION['uid'];
$rol=$pdo->prepare("SELECT rol_sistema FROM perfil_usuario WHERE id=? AND estado='activo'"); $rol->execute([$uid]); $r=$rol->fetchColumn();
if(!$r || !in_array($r,['moderador','administrador'])) die('No autorizado');

try{
  $rid=(int)($_POST['reporte_id']??0);
  $rep=$pdo->prepare("SELECT * FROM reporte_comprador WHERE id=?"); $rep->execute([$rid]); $row=$rep->fetch();
  if(!$row) throw new Exception('Reporte no existe');
  $desc="Reporte de comprador (".$row['tipo'].")".($row['comentario']?": ".$row['comentario']:"");
  $pdo->prepare("INSERT INTO incidencia (producto_id, origen, descripcion) VALUES (?, 'comprador', ?)")->execute([$row['producto_id'],$desc]);
  $iid=$pdo->query("SELECT currval(pg_get_serial_sequence('incidencia','id'))")->fetchColumn();
  $pdo->prepare("UPDATE reporte_comprador SET incidencia_id=? WHERE id=?")->execute([$iid,$rid]);
  header("Location: incidencia_ver.php?id=".$iid); exit;
}catch(Throwable $e){ error_log('[MarketGO][rep->inc] '.$e->getMessage()); die('Error: '.$e->getMessage()); }
