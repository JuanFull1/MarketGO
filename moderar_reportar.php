<?php
// moderar_reportar.php
session_start();
if(!isset($_SESSION['uid'])){ header("Location: index.php"); exit; }
require_once "db.php";
error_reporting(E_ALL); ini_set('display_errors',1); ini_set('log_errors',1);

$pdo=(new DB())->pdo(); 
$uid=$_SESSION['uid'];

// Solo moderadores
if(!isset($_SESSION['rol']) || $_SESSION['rol']!=='moderador'){
  header("Location: catalogo.php?msg=Acceso%20no%20autorizado&type=error");
  exit;
}

$producto_id = (int)($_POST['producto_id'] ?? 0);
$descripcion = trim($_POST['descripcion'] ?? '');

if(!$producto_id || !$descripcion){
  header("Location: producto_detalle.php?id={$producto_id}&msg=Datos%20incompletos&type=error");
  exit;
}

// Verifica producto
$check=$pdo->prepare("SELECT id FROM producto WHERE id=?");
$check->execute([$producto_id]);
if(!$check->fetch()){
  header("Location: catalogo.php?msg=Producto%20no%20encontrado&type=error");
  exit;
}

// Crea incidencia de moderador (esto OCULTA el producto por trigger)
$ins=$pdo->prepare("INSERT INTO incidencia (producto_id, estado, origen, descripcion, moderador_encargado_id, asignado_en) VALUES (?,?,?,?,?, now())");
$ins->execute([$producto_id, 'abierta', 'moderador', $descripcion, $uid]);

$msg=urlencode("Incidencia abierta y publicación ocultada para revisión.");
header("Location: producto_detalle.php?id={$producto_id}&msg={$msg}&type=success&redir=catalogo");
exit;
