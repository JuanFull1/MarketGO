<?php
// apelar.php
session_start();
if(!isset($_SESSION['uid'])){ header("Location: index.php"); exit; }
require_once "db.php";
error_reporting(E_ALL); ini_set('display_errors',1); ini_set('log_errors',1);

$pdo=(new DB())->pdo(); 
$uid=$_SESSION['uid'];

$producto_id = (int)($_POST['producto_id'] ?? 0);
$motivo = trim($_POST['motivo'] ?? '');
$evidencia_url = trim($_POST['evidencia_url'] ?? '');

if(!$producto_id || !$motivo){
  header("Location: producto_detalle.php?id={$producto_id}&msg=Completa%20el%20motivo%20de%20apelaci%C3%B3n&type=error");
  exit;
}

// Verifica que el producto existe y pertenece al usuario
$prod=$pdo->prepare("SELECT id, vendedor_id FROM producto WHERE id=?");
$prod->execute([$producto_id]);
$row=$prod->fetch();
if(!$row){ 
  header("Location: catalogo.php?msg=Producto%20no%20encontrado&type=error"); exit; 
}
if($row['vendedor_id'] !== $_SESSION['uid']){
  header("Location: catalogo.php?msg=No%20puedes%20apelar%20un%20producto%20de%20otro%20vendedor&type=error"); exit;
}

$pdo->beginTransaction();
try {
  // Buscar incidencia abierta; si no hay, crear una (origen 'auto' si hubo detección, si no 'moderador' genérica)
  $q=$pdo->prepare("SELECT id FROM incidencia WHERE producto_id=? AND estado IN ('abierta','solicita_info','en_apelacion') ORDER BY id DESC LIMIT 1");
  $q->execute([$producto_id]);
  $inc=$q->fetch();

  if(!$inc){
    // Creamos incidencia en estado 'en_apelacion' con origen 'auto' (genérico para bloqueo/automatismos)
    $ins=$pdo->prepare("INSERT INTO incidencia (producto_id, estado, origen, descripcion) VALUES (?,?,?,?) RETURNING id");
    $ins->execute([$producto_id, 'en_apelacion', 'auto', 'Apelación iniciada por el vendedor']);
    $incId = $ins->fetchColumn();

    // El trigger ocultará el producto si no lo estaba ya
  } else {
    $incId = $inc['id'];
    // Ponemos la incidencia en 'en_apelacion' si no lo está
    $upd=$pdo->prepare("UPDATE incidencia SET estado='en_apelacion' WHERE id=? AND estado <> 'en_apelacion'");
    $upd->execute([$incId]);
  }

  // Registrar apelación
  $ap=$pdo->prepare("INSERT INTO apelacion (incidencia_id, vendedor_id, motivo, evidencia_url, estado) VALUES (?,?,?,?, 'abierta')");
  $ap->execute([$incId, $uid, $motivo, $evidencia_url ?: null]);

  $pdo->commit();
  $msg=urlencode("Tu apelación fue enviada. Un moderador revisará tu caso.");
  header("Location: producto_detalle.php?id={$producto_id}&msg={$msg}&type=success&redir=catalogo");
} catch(Exception $e){
  $pdo->rollBack();
  $msg=urlencode("No se pudo registrar la apelación: ".$e->getMessage());
  header("Location: producto_detalle.php?id={$producto_id}&msg={$msg}&type=error");
}
exit;
