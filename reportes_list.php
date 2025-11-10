<?php
// reportes_list.php — Reportes de compradores, posibilidad de crear incidencia desde aquí
session_start(); if(!isset($_SESSION['uid'])){ header("Location: index.php"); exit; }
// Headers para evitar caché y proteger la sesión
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
require_once "db.php"; error_reporting(E_ALL); ini_set('display_errors',1); ini_set('log_errors',1);
$pdo=(new DB())->pdo(); $uid=$_SESSION['uid'];
$rol=$pdo->prepare("SELECT rol_sistema FROM perfil_usuario WHERE id=? AND estado='activo'"); $rol->execute([$uid]); $r=$rol->fetchColumn();
if(!$r || !in_array($r,['moderador','administrador'])) die('No autorizado');

$rows=$pdo->query("SELECT r.*, p.nombre prod FROM reporte_comprador r JOIN producto p ON p.id=r.producto_id ORDER BY r.creado_en DESC")->fetchAll();
?>
<!doctype html>
<html lang="es"><head><meta charset="utf-8"/><meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>Reportes · MarketGO</title>
<style>
:root{--primary:#2F80ED;--bg:#F6F8FC;--card:#fff;--shadow:0 8px 24px rgba(21,34,50,.08)}
*{box-sizing:border-box}body{margin:0;font-family:system-ui,Segoe UI,Roboto;background:var(--bg)}
.topbar{position:sticky;top:0;background:#fff;box-shadow:var(--shadow);z-index:20}
.topbar__inner{max-width:1100px;margin:auto;padding:10px 20px;display:flex;gap:10px;align-items:center}
.topbar__inner a{ text-decoration:none; color:#17212B; padding:8px 12px; border:1px solid #e6ecf5; border-radius:999px }
.topbar__inner .brand{font-weight:800;margin-right:auto}
.container{max-width:1100px;margin:16px auto;padding:0 20px}
.card{background:#fff;border:1px solid #eef2f7;border-radius:16px;box-shadow:var(--shadow);padding:16px}
table{width:100%;border-collapse:collapse}
th,td{padding:8px;border-bottom:1px solid #eef2f7;text-align:left}
.btn{border:0;border-radius:10px;padding:8px 12px;cursor:pointer;font-weight:700}
.btn-primary{background:var(--primary);color:#fff}
</style></head>
<body>
<header class="topbar"><div class="topbar__inner">
  <div class="brand">MarketGO</div>
  <a href="moderador_panel.php">Moderación</a>
  <a href="incidencias_list.php">Incidencias</a>
  <a href="reportes_list.php">Reportes</a>
  <?php if($r==='administrador'): ?><a href="admin_panel.php">Admin</a><?php endif; ?>
</div></header>

<main class="container">
  <div class="card">
    <table>
      <thead><tr><th>#</th><th>Producto</th><th>Tipo</th><th>Comentario</th><th>Fecha</th><th>Incidencia</th><th>Acción</th></tr></thead>
      <tbody>
        <?php foreach($rows as $r): ?>
        <tr>
          <td><?= $r['id'] ?></td>
          <td><?= htmlspecialchars($r['prod']) ?></td>
          <td><?= htmlspecialchars($r['tipo']) ?></td>
          <td><?= htmlspecialchars($r['comentario'] ?: '—') ?></td>
          <td><?= htmlspecialchars($r['creado_en']) ?></td>
          <td><?= $r['incidencia_id'] ? ('#'.$r['incidencia_id']) : '—' ?></td>
          <td>
            <?php if(!$r['incidencia_id']): ?>
              <form method="post" action="crear_incidencia_desde_reporte.php">
                <input type="hidden" name="reporte_id" value="<?= $r['id'] ?>">
                <button class="btn btn-primary">Crear incidencia</button>
              </form>
            <?php else: ?>
              <a class="btn" href="incidencia_ver.php?id=<?= $r['incidencia_id'] ?>">Abrir</a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; if(!$rows): ?><tr><td colspan="7">Sin reportes.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</main>
</body></html>
