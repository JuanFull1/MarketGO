<?php // catalogo.php
session_start(); if(!isset($_SESSION['uid'])){ header("Location: index.php"); exit; }
require_once "db.php";

// Headers para evitar caché y proteger la sesión
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

error_reporting(E_ALL); ini_set('display_errors',1); ini_set('log_errors',1);
function debugC($m,$c=[]){ error_log('[MarketGO][catalogo] '.$m.(empty($c)?'':' '.json_encode($c))); }

$pdo=getPDO(); $uid=$_SESSION['uid'];

$cat=$_GET['cat']??'Todas';
$q=trim($_GET['q']??'');
$min = is_numeric($_GET['min']??'') ? (float)$_GET['min'] : null;
$max = is_numeric($_GET['max']??'') ? (float)$_GET['max'] : null;
$ciudad=trim($_GET['ciudad']??'');

$cad=$pdo->query("SELECT COALESCE((SELECT dias_caducidad FROM politica_publicacion LIMIT 1),90) d")->fetch()['d'] ?? 90;

/* Catálogo público: mostrar solo productos SIN incidencias abiertas
   (abierta, solicita_info, en_apelacion). Esto no depende de estado en BD. */
$sql="SELECT p.id,p.nombre,p.categoria,p.tipo,p.estado,p.ciudad,p.precio,p.imagen_portada_url,
            p.vendedor_id,
            EXISTS(SELECT 1 FROM producto_guardado g WHERE g.usuario_id=:uid AND g.producto_id=p.id) saved
      FROM producto p
      WHERE (p.fecha_publicacion IS NULL OR p.fecha_publicacion > DATE_SUB(NOW(), INTERVAL $cad DAY))
        AND NOT EXISTS (
          SELECT 1 FROM incidencia i
          WHERE i.producto_id = p.id
            AND i.estado IN ('abierta','solicita_info','en_apelacion')
        )";
$params=[":uid"=>$uid];

if($cat && strtolower($cat)!=='todas'){ $sql.=" AND p.categoria = :cat"; $params[':cat']=$cat; }
if($q){
  // Use distinct parameter names for each occurrence to avoid driver issues with repeated named params
  $sql .= " AND (LOWER(p.nombre) LIKE LOWER(:q1) OR LOWER(p.descripcion) LIKE LOWER(:q2) OR LOWER(p.ciudad) LIKE LOWER(:q3) OR LOWER(p.categoria) LIKE LOWER(:q4))";
  $params[':q1'] = "%$q%";
  $params[':q2'] = "%$q%";
  $params[':q3'] = "%$q%";
  $params[':q4'] = "%$q%";
}
if($min!==null){ $sql.=" AND p.precio >= :min"; $params[':min']=$min; }
if($max!==null){ $sql.=" AND p.precio <= :max"; $params[':max']=$max; }
if($ciudad){ $sql .= " AND LOWER(p.ciudad) LIKE LOWER(:ciu)"; $params[':ciu'] = "%$ciudad%"; }
$sql.=" ORDER BY p.creado_en DESC";
$st=$pdo->prepare($sql);
// debug: log SQL and params to help with binding issues
debugC('SQL: '.$sql);
debugC('params: ', $params);
$st->execute($params); $rows=$st->fetchAll();

$cats = $pdo->query("SELECT 'Todas' AS c UNION SELECT DISTINCT categoria AS c FROM producto WHERE categoria IS NOT NULL AND categoria<>'' ORDER BY c ASC")->fetchAll(PDO::FETCH_COLUMN);
if(!$cats){ $cats = ['Todas','Electrónica','Hogar','Servicios','Moda','Deportes','Aficiones','Mascotas','Vehículos','Jardín']; }
?>
<!doctype html><html lang="es"><head>
<meta charset="utf-8"/><meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>MarketGO · Catálogo</title>
<style>
:root{--primary:#2F80ED;--bg:#F6F8FC;--card:#fff;--shadow:0 8px 24px rgba(21,34,50,.08)}
*{box-sizing:border-box}html,body{margin:0}body{font-family:system-ui,Segoe UI,Roboto;background:var(--bg);color:#17212B}
.topbar{position:sticky;top:0;background:#fff;box-shadow:var(--shadow);z-index:20}
.topbar__inner{max-width:1100px;margin:auto;padding:10px 20px;display:flex;align-items:center;gap:10px}
.topbar__inner a{ text-decoration:none; color:#17212B; padding:8px 12px; border-radius:999px; border:1px solid #e6ecf5 }
.topbar__inner .brand{font-weight:800;margin-right:auto}
.container{max-width:1100px;margin:16px auto 80px;padding:0 20px}
.card{background:var(--card);border-radius:16px;box-shadow:var(--shadow);border:1px solid #eef2f7}
.toolbar{padding:12px;display:grid;gap:12px;grid-template-columns:repeat(12,1fr);border-bottom:1px solid #EEF2F7}
.field{grid-column:span 3}.field--wide{grid-column:span 6}
label{display:block;font-size:.9rem;color:#556;margin-bottom:6px}
.input,select{width:100%;padding:10px 12px;border:1px solid #E0E7F0;border-radius:10px;background:#fff}
.btn{border:0;border-radius:12px;padding:10px 14px;font-weight:700;cursor:pointer}
.btn-primary{background:var(--primary);color:#fff}
.grid{display:grid;gap:16px}.grid-4{grid-template-columns:repeat(4,1fr)}
.product{overflow:hidden;border-radius:14px;border:1px solid #EEF2F7;background:#fff}
.product__img{aspect-ratio:4/3;width:100%;object-fit:cover;border-bottom:1px solid #EEF2F7}
.product__body{padding:12px}
.price{font-weight:800}
@media(max-width:900px){.toolbar{grid-template-columns:repeat(2,1fr)}.field,.field--wide{grid-column:span 2}.grid-4{grid-template-columns:repeat(2,1fr)}}
@media(max-width:560px){.grid-4{grid-template-columns:1fr}}
.badge{background:#EAF2FF;color:#2F80ED;padding:4px 8px;border-radius:999px;font-size:.75rem;font-weight:700}
.badge-green{background:#E7FBF1;color:#0B7C4A;padding:4px 8px;border-radius:999px;font-size:.75rem;font-weight:700}
</style>
</head><body>
<header class="topbar">
  <div class="topbar__inner">
    <div class="brand">MarketGO</div>
    <a href="catalogo.php">Catálogo</a>
    <a href="panel.php">Mis publicaciones</a>
    <a href="chat_list.php">Mis chats</a>
    <a href="logout.php" style="color: #e74c3c; font-weight: bold;">Cerrar sesión</a>
  </div>
</header>

<main class="container">
<section class="card">
  <form class="toolbar" method="get" action="catalogo.php">
    <div class="field">
      <label>Categoría</label>
      <select name="cat">
        <?php foreach($cats as $c){ $sel=($cat===$c)?'selected':''; echo "<option $sel>".htmlspecialchars($c)."</option>"; } ?>
      </select>
    </div>
    <div class="field"><label>Precio mín</label><input class="input" type="number" step="0.01" name="min" value="<?= htmlspecialchars($min??'') ?>"></div>
    <div class="field"><label>Precio máx</label><input class="input" type="number" step="0.01" name="max" value="<?= htmlspecialchars($max??'') ?>"></div>
    <div class="field"><label>Ciudad</label><input class="input" name="ciudad" value="<?= htmlspecialchars($ciudad) ?>"></div>
    <div class="field field--wide"><label>Buscar</label>
      <input class="input" name="q" list="cats" placeholder="Buscar por nombre, descripción o categoría..." value="<?= htmlspecialchars($q) ?>">
      <datalist id="cats"><?php foreach($cats as $c) echo "<option value=\"".htmlspecialchars($c)."\">"; ?></datalist>
    </div>
    <div class="field" style="display:flex;gap:8px;align-items:flex-end">
      <button class="btn btn-primary" type="submit">Filtrar</button>
    </div>
  </form>

  <div style="padding:16px">
    <div class="grid grid-4">
      <?php if(!$rows): ?>
        <p style="padding:10px;color:#666">No hay resultados.</p>
      <?php else: foreach($rows as $p): ?>
        <article class="product">
          <img class="product__img" loading="lazy" src="<?= htmlspecialchars($p['imagen_portada_url'] ?: 'https://placehold.co/800x600?text=Producto') ?>" alt="<?= htmlspecialchars($p['nombre']) ?>"/>
          <div class="product__body">
            <div style="display:flex;justify-content:space-between;align-items:center">
              <span class="badge"><?= htmlspecialchars($p['tipo']) ?></span>
              <span class="badge-green"><?= htmlspecialchars($p['estado']) ?></span>
            </div>
            <h3 style="margin:8px 0 4px"><a href="producto_detalle.php?id=<?= $p['id'] ?>" style="text-decoration:none;color:inherit"><?= htmlspecialchars($p['nombre']) ?></a></h3>
            <div class="meta"><?= htmlspecialchars($p['ciudad']) ?> · <?= htmlspecialchars($p['categoria']) ?></div>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:8px">
              <span class="price">$<?= number_format((float)$p['precio'],2) ?></span>
              <div style="display:flex;gap:6px">
                <form method="post" action="toggle_guardado.php" style="display:inline">
                  <input type="hidden" name="id" value="<?= $p['id'] ?>">
                  <button class="btn" title="Me interesa"><?= $p['saved']?'💙':'🤍' ?></button>
                </form>
                <a class="btn" href="chat.php?producto=<?= $p['id'] ?>&vendedor=<?= $p['vendedor_id'] ?>">💬</a>
              </div>
            </div>
          </div>
        </article>
      <?php endforeach; endif; ?>
    </div>
  </div>
</section>
</main>

</body></html>