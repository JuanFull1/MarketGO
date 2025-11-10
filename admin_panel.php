<?php
// admin_panel.php — Panel de Administrador (usuarios y política)
session_start(); if(!isset($_SESSION['uid'])){ header("Location: index.php"); exit; }
require_once "db.php"; error_reporting(E_ALL); ini_set('display_errors',1); ini_set('log_errors',1);
$pdo=(new DB())->pdo(); $uid=$_SESSION['uid'];
$rol=$pdo->prepare("SELECT rol_sistema FROM perfil_usuario WHERE id=? AND estado='activo'"); $rol->execute([$uid]); $r=$rol->fetchColumn();
if($r!=='administrador') die('No autorizado');

$pol=$pdo->query("SELECT id, dias_caducidad FROM politica_publicacion LIMIT 1")->fetch();
?>
<!doctype html>
<html lang="es"><head><meta charset="utf-8"/><meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>Admin · MarketGO</title>
<style>
:root{--primary:#2F80ED;--bg:#F6F8FC;--card:#fff;--shadow:0 8px 24px rgba(21,34,50,.08)}
*{box-sizing:border-box}body{margin:0;font-family:system-ui,Segoe UI,Roboto;background:var(--bg)}
.topbar{position:sticky;top:0;background:#fff;box-shadow:var(--shadow);z-index:20}
.topbar__inner{max-width:1100px;margin:auto;padding:10px 20px;display:flex;gap:10px;align-items:center}
.topbar__inner a{ text-decoration:none; color:#17212B; padding:8px 12px; border:1px solid #e6ecf5; border-radius:999px }
.topbar__inner .brand{font-weight:800;margin-right:auto}
.container{max-width:1100px;margin:16px auto;padding:0 20px}
.grid{display:grid;gap:12px;grid-template-columns:1fr 1fr}
.card{background:#fff;border:1px solid #eef2f7;border-radius:16px;box-shadow:var(--shadow);padding:16px}
label{display:block;margin:6px 0} input,select{padding:10px 12px;border:1px solid #E0E7F0;border-radius:10px}
.btn{border:0;border-radius:10px;padding:10px 12px;cursor:pointer;font-weight:700}
.btn-primary{background:var(--primary);color:#fff}
table{width:100%;border-collapse:collapse} th,td{padding:8px;border-bottom:1px solid #eef2f7;text-align:left}
</style></head>
<body>
<header class="topbar"><div class="topbar__inner">
  <div class="brand">MarketGO</div>
  <a href="admin_panel.php">Admin</a>
  <a href="usuarios_admin.php">Usuarios</a>
  <a href="moderador_panel.php">Moderación</a>
</div></header>

<main class="container">
  <div class="grid">
    <div class="card">
      <h3>Configurar caducidad de publicaciones</h3>
      <form method="post" action="config_politica.php" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
        <label>Días de caducidad <input type="number" name="dias" min="1" value="<?= (int)$pol['dias_caducidad'] ?>"></label>
        <button class="btn btn-primary">Guardar</button>
      </form>
      <small>Las publicaciones con más días que este valor dejarán de mostrarse en el catálogo (no se borran).</small>
    </div>
    <div class="card">
      <h3>Atajos</h3>
      <p><a href="incidencias_list.php">Ver incidencias</a> · <a href="reportes_list.php">Ver reportes</a> · <a href="usuarios_admin.php">Administrar usuarios</a></p>
    </div>
  </div>
</main>
</body></html>
