<?php
// incidencias_list.php — Listado con filtros por fecha/origen/estado
session_start(); if(!isset($_SESSION['uid'])){ header("Location: index.php"); exit; }
// Headers para evitar caché y proteger la sesión
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
require_once "db.php"; error_reporting(E_ALL); ini_set('display_errors',1); ini_set('log_errors',1);
$pdo=(new DB())->pdo(); $uid=$_SESSION['uid'];
$yo=$pdo->prepare("SELECT rol_sistema FROM perfil_usuario WHERE id=? AND estado='activo'"); $yo->execute([$uid]); $r=$yo->fetchColumn();
if(!$r || !in_array($r,['moderador','administrador'])) die('No autorizado');

$desde=$_GET['desde']??''; $hasta=$_GET['hasta']??''; $origen=$_GET['origen']??''; $estado=$_GET['estado']??'';
$sql="SELECT i.id,i.producto_id,i.fecha_incidencia,i.estado,i.origen,i.descripcion,i.moderador_encargado_id,
            p.nombre prod, p.categoria, p.precio
      FROM incidencia i
      JOIN producto p ON p.id=i.producto_id
      WHERE 1=1";
$prm=[];
if($desde){ $sql.=" AND i.fecha_incidencia::date >= :d"; $prm[':d']=$desde; }
if($hasta){ $sql.=" AND i.fecha_incidencia::date <= :h"; $prm[':h']=$hasta; }
if($origen){ $sql.=" AND i.origen = :o"; $prm[':o']=$origen; }
if($estado){ $sql.=" AND i.estado = :e"; $prm[':e']=$estado; }
$sql.=" ORDER BY i.fecha_incidencia DESC";
$st=$pdo->prepare($sql); $st->execute($prm); $rows=$st->fetchAll();
?>
<!doctype html>
<html lang="es"><head><meta charset="utf-8"/><meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>Incidencias · MarketGO</title>
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
label{display:block;margin:6px 0}
input,select{padding:8px 10px;border:1px solid #E0E7F0;border-radius:8px}
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
    <form method="get" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end">
      <label>Desde <input type="date" name="desde" value="<?= htmlspecialchars($desde) ?>"></label>
      <label>Hasta <input type="date" name="hasta" value="<?= htmlspecialchars($hasta) ?>"></label>
      <label>Origen
        <select name="origen">
          <option value="">Todos</option>
          <option value="auto" <?= $origen==='auto'?'selected':'' ?>>Auto</option>
          <option value="comprador" <?= $origen==='comprador'?'selected':'' ?>>Comprador</option>
          <option value="moderador" <?= $origen==='moderador'?'selected':'' ?>>Moderador</option>
        </select>
      </label>
      <label>Estado
        <select name="estado">
          <option value="">Todos</option>
          <?php foreach(['abierta','solicita_info','en_apelacion','resuelta_aprobado','resuelta_rechazado'] as $e)
            echo "<option ".($estado===$e?'selected':'').">".$e."</option>"; ?>
        </select>
      </label>
      <button class="btn">Filtrar</button>
    </form>
    <div style="overflow:auto;margin-top:10px">
      <table>
        <thead><tr><th>#</th><th>Fecha</th><th>Producto</th><th>Categoría</th><th>Precio</th><th>Origen</th><th>Estado</th><th>Encargado</th><th></th></tr></thead>
        <tbody>
          <?php foreach($rows as $i): ?>
            <tr>
              <td><?= $i['id'] ?></td>
              <td><?= htmlspecialchars($i['fecha_incidencia']) ?></td>
              <td><?= htmlspecialchars($i['prod']) ?></td>
              <td><?= htmlspecialchars($i['categoria']) ?></td>
              <td>$<?= number_format($i['precio'],2) ?></td>
              <td><?= htmlspecialchars($i['origen']) ?></td>
              <td><?= htmlspecialchars($i['estado']) ?></td>
              <td><?= htmlspecialchars(substr((string)$i['moderador_encargado_id'],0,8) ?: '—') ?></td>
              <td><a class="btn btn-primary" href="incidencia_ver.php?id=<?= $i['id'] ?>">Revisar</a></td>
            </tr>
          <?php endforeach; if(!$rows): ?><tr><td colspan="9">Sin resultados.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>
</body></html>
