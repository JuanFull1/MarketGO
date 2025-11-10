<?php
// incidencia_ver.php — Ver y gestionar una incidencia
session_start(); if(!isset($_SESSION['uid'])){ header("Location: index.php"); exit; }
require_once "db.php"; error_reporting(E_ALL); ini_set('display_errors',1); ini_set('log_errors',1);
$pdo=(new DB())->pdo(); $uid=$_SESSION['uid'];
$rol=$pdo->prepare("SELECT rol_sistema FROM perfil_usuario WHERE id=? AND estado='activo'"); $rol->execute([$uid]); $r=$rol->fetchColumn();
if(!$r || !in_array($r,['moderador','administrador'])) die('No autorizado');

$id=(int)($_GET['id']??0);
$inc=$pdo->prepare("SELECT i.*, p.nombre, p.descripcion, p.categoria, p.precio, p.imagen_portada_url, p.vendedor_id, u.username vend_username
                    FROM incidencia i
                    JOIN producto p ON p.id=i.producto_id
                    JOIN perfil_usuario u ON u.id=p.vendedor_id
                    WHERE i.id=?");
$inc->execute([$id]); $row=$inc->fetch(); if(!$row) die('Incidencia no existe');

// Auto-asignación si no tiene encargado
if(!$row['moderador_encargado_id']){
  $pdo->prepare("UPDATE incidencia SET moderador_encargado_id=?, asignado_en=now() WHERE id=?")->execute([$uid,$id]);
  $row['moderador_encargado_id']=$uid;
}

$apel=$pdo->prepare("SELECT * FROM apelacion WHERE incidencia_id=? ORDER BY creada_en DESC");
$apel->execute([$id]); $apelRows=$apel->fetchAll();

$imgs=$pdo->prepare("SELECT url FROM producto_imagen WHERE producto_id=? ORDER BY orden");
$imgs->execute([$row['producto_id']]); $gal=$imgs->fetchAll();
?>
<!doctype html>
<html lang="es"><head><meta charset="utf-8"/><meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>Incidencia #<?= $id ?> · MarketGO</title>
<style>
:root{--primary:#2F80ED;--bg:#F6F8FC;--card:#fff;--shadow:0 8px 24px rgba(21,34,50,.08)}
*{box-sizing:border-box}body{margin:0;font-family:system-ui,Segoe UI,Roboto;background:var(--bg)}
.topbar{position:sticky;top:0;background:#fff;box-shadow:var(--shadow);z-index:20}
.topbar__inner{max-width:1100px;margin:auto;padding:10px 20px;display:flex;gap:10px;align-items:center}
.topbar__inner a{ text-decoration:none; color:#17212B; padding:8px 12px; border:1px solid #e6ecf5; border-radius:999px }
.topbar__inner .brand{font-weight:800;margin-right:auto}
.container{max-width:1100px;margin:16px auto;padding:0 20px}
.card{background:#fff;border:1px solid #eef2f7;border-radius:16px;box-shadow:var(--shadow);padding:16px;margin-bottom:12px}
.gallery{display:flex;gap:8px;overflow:auto}
.gallery img{width:220px;height:160px;object-fit:cover;border-radius:10px;border:1px solid #eee}
.btn{border:0;border-radius:10px;padding:10px 12px;cursor:pointer;font-weight:700}
.btn-primary{background:var(--primary);color:#fff}
textarea,input,select{padding:10px 12px;border:1px solid #E0E7F0;border-radius:10px}
</style></head>
<body>
<header class="topbar"><div class="topbar__inner">
  <div class="brand">MarketGO</div>
  <a href="incidencias_list.php">Volver</a>
</div></header>

<main class="container">
  <div class="card">
    <h3 style="margin:0 0 8px">Incidencia #<?= $row['id'] ?> · Estado: <?= htmlspecialchars($row['estado']) ?> · Origen: <?= htmlspecialchars($row['origen']) ?></h3>
    <p><strong>Producto:</strong> <?= htmlspecialchars($row['nombre']) ?> (<?= htmlspecialchars($row['categoria']) ?>) — $<?= number_format($row['precio'],2) ?></p>
    <div class="gallery" style="margin:8px 0">
      <img src="<?= htmlspecialchars($row['imagen_portada_url'] ?: 'https://placehold.co/800x600?text=Producto') ?>">
      <?php foreach($gal as $g): ?><img src="<?= htmlspecialchars($g['url']) ?>"><?php endforeach; ?>
    </div>
    <p><strong>Vendedor:</strong> @<?= htmlspecialchars($row['vend_username']) ?></p>
    <p><strong>Detalle detección/reporte:</strong> <?= nl2br(htmlspecialchars($row['descripcion'])) ?></p>
  </div>

  <?php if($apelRows): ?>
  <div class="card">
    <h4 style="margin:0 0 8px">Apelaciones</h4>
    <?php foreach($apelRows as $a): ?>
      <div style="border:1px solid #eef2f7;border-radius:10px;padding:10px;margin-bottom:8px">
        <div><strong>Motivo:</strong> <?= nl2br(htmlspecialchars($a['motivo'])) ?></div>
        <?php if($a['evidencia_url']): ?><div><strong>Evidencia:</strong> <a target="_blank" href="<?= htmlspecialchars($a['evidencia_url']) ?>"><?= htmlspecialchars($a['evidencia_url']) ?></a></div><?php endif; ?>
        <small>Estado: <?= htmlspecialchars($a['estado']) ?> · <?= htmlspecialchars($a['creada_en']) ?></small>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="card">
    <form method="post" action="moderacion_accion.php" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
      <input type="hidden" name="id" value="<?= $row['id'] ?>">
      <label>Acción
        <select name="accion" required>
          <option value="resuelta_aprobado">Aprobar (volver a mostrar)</option>
          <option value="solicita_info">Solicitar información</option>
          <option value="resuelta_rechazado">Rechazar (marcar como no permitido)</option>
        </select>
      </label>
      <label>Comentario <input name="comentario" placeholder="Comentario interno opcional"></label>
      <label>Opciones:
        <select name="cambio_estado">
          <option value="">—</option>
          <option value="rechazar">Si rechazo, marcar producto como RECHAZADO</option>
        </select>
      </label>
      <button class="btn btn-primary">Aplicar</button>
    </form>
  </div>
</main>
</body></html>
