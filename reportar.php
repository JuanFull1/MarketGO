<?php
// reportar.php
session_start();
if(!isset($_SESSION['uid'])){ header("Location: index.php"); exit; }
require_once "db.php";
error_reporting(E_ALL); ini_set('display_errors',1); ini_set('log_errors',1);

$pdo=(new DB())->pdo(); 
$uid=$_SESSION['uid'];

$producto_id = (int)($_POST['producto_id'] ?? 0);
$tipo = $_POST['tipo'] ?? null;
$comentario = trim($_POST['comentario'] ?? '');

if(!$producto_id || !$tipo){
  header("Location: producto_detalle.php?id={$producto_id}&msg=Datos%20incompletos&type=error");
  exit;
}

// Verifica que el producto existe
$check=$pdo->prepare("SELECT id FROM producto WHERE id=?");
$check->execute([$producto_id]);
if(!$check->fetch()){
  header("Location: catalogo.php?msg=Producto%20no%20encontrado&type=error");
  exit;
}

// Insertar reporte del comprador (NO crea incidencia)
$ins=$pdo->prepare("INSERT INTO reporte_comprador (producto_id, reportante_id, tipo, comentario) VALUES (?,?,? ,?)");
$ins->execute([$producto_id, $uid, $tipo, $comentario ?: null]);

$msg=urlencode("Reporte enviado. Un moderador revisará tu solicitud.");
header("Location: producto_detalle.php?id={$producto_id}&msg={$msg}&type=success&redir=catalogo");
exit;
