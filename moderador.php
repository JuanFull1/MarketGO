<?php
// moderador_panel.php — Panel de Moderador
session_start(); if(!isset($_SESSION['uid'])){ header("Location: index.php"); exit; }
// Headers para evitar caché y proteger la sesión
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
require_once "db.php";
error_reporting(E_ALL); ini_set('display_errors',1); ini_set('log_errors',1);
$pdo=(new DB())->pdo(); $uid=$_SESSION['uid'];

// Verifica rol moderador o admin
$me = $pdo->prepare("SELECT id, rol_sistema FROM perfil_usuario WHERE id=? AND estado='activo'");
$me->execute([$uid]); $yo=$me->fetch();
if(!$yo || !in_array($yo['rol_sistema'], ['moderador','administrador'])){ die('No autorizado'); }

// Incidencias abiertas (contador rápido)
$ab = $pdo->query("SELECT COUNT(*)::int FROM incidencia WHERE estado IN ('abierta','solicita_info','en_apelacion')")->fetchColumn();
$rep = $pdo->query("SELECT COUNT(*)::int FROM reporte_comprador WHERE incidencia_id IS NULL")->fetchColumn();
?>
<!doctype html>
<html lang="es"><head><meta charset="utf-8"/><meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>Panel de Moderación · MarketGO</title>
<style>
:root{--primary:#2F80ED;--bg:#F6F8FC;--card:#fff;--shadow:0 8px 24px rgba(21,34,50,.08)}
*{box-sizing:border-box}body{margin:0;font-family:system-ui,Segoe UI,Roboto;background:var(--bg);color:#17212B}
.topbar{position:sticky;top:0;background:#fff;box-shadow:var(--shadow);z-index:20}
.topbar__inner{max-width:1100px;margin:auto;padding:10px 20px;display:flex;gap:10px;align-items:center}
.topbar__inner a{ text-decoration:none; color:#17212B; padding:8px 12px; border:1px solid #e6ecf5; border-radius:999px }
.topbar__inner .brand{font-weight:800;margin-right:auto}
.container{max-width:1100px;margin:16px auto;padding:0 20px}
.grid{display:grid;gap:12px;grid-template-columns:1fr 1fr}
.card{background:#fff;border:1px solid #eef2f7;border-radius:16px;box-shadow:var(--shadow);padding:16px}
.btn{border:0;border-radius:12px;padding:10px 14px;cursor:pointer;font-weight:700}
.btn-primary{background:var(--primary);color:#fff}
</style></head>
<body>
<header class="topbar"><div class="topbar__inner">
  <div class="brand">MarketGO</div>
  <a href="catalogo.php">Catálogo</a>
  <a href="moderador_panel.php">Moderación</a>
  <a href="incidencias_list.php">Incidencias</a>
  <a href="reportes_list.php">Reportes</a>
  <?php if($yo['rol_sistema']==='administrador'): ?><a href="admin_panel.php">Admin</a><?php endif; ?>
  <a href="index.php" onclick="event.preventDefault();document.getElementById('logout').submit();">Salir</a>
  <form id="logout" method="post" action="index.php?action=logout" style="display:none"></form>
</div></header>

<main class="container">
  <div class="grid">
    <div class="card">
      <h3 style="margin:0 0 8px">Incidencias</h3>
      <p>Abiertas/pendientes: <strong><?= (int)$ab ?></strong></p>
      <a class="btn btn-primary" href="incidencias_list.php">Ir a incidencias</a>
    </div>
    <div class="card">
      <h3 style="margin:0 0 8px">Reportes de compradores</h3>
      <p>Reportes sin vincular a incidencia: <strong><?= (int)$rep ?></strong></p>
      <a class="btn" href="reportes_list.php">Ver reportes</a>
    </div>
  </div>
</main>
</body></html>
