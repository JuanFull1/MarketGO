<?php // producto_detalle.php
session_start(); 
if(!isset($_SESSION['uid'])){ header("Location: index.php"); exit; }
require_once "db.php";
error_reporting(E_ALL); ini_set('display_errors',1); ini_set('log_errors',1);

$pdo=(new DB())->pdo(); 
$uid=$_SESSION['uid']; 
$id=(int)($_GET['id']??0);

$p=$pdo->prepare("SELECT p.*, u.username FROM producto p JOIN perfil_usuario u ON u.id=p.vendedor_id WHERE p.id=?");
$p->execute([$id]); 
$prod=$p->fetch(); 
if(!$prod){ die('No encontrado'); }

// Rol y dueño
$isMod = isset($_SESSION['rol']) && $_SESSION['rol']==='moderador';
$soyVendedor = ($uid === $prod['vendedor_id']);

// ¿Incidencia abierta?
$hasOpen=$pdo->prepare("SELECT id, estado, origen FROM incidencia WHERE producto_id=? AND estado IN ('abierta','solicita_info','en_apelacion') ORDER BY id DESC LIMIT 1");
$hasOpen->execute([$id]); 
$incOpen=$hasOpen->fetch();
$enRev = (bool)$incOpen;

// ¿Detección automática abierta?
$hayAuto = $pdo->prepare("SELECT 1 FROM incidencia WHERE producto_id=? AND origen='auto' AND estado IN ('abierta','solicita_info','en_apelacion') LIMIT 1");
$hayAuto->execute([$id]); 
$autoOpen = (bool)$hayAuto->fetch();

// Bloqueo de vista: solo compradores (u otros) quedan bloqueados si no está activo o está en revisión.
// - Vendedor: SÍ puede ver su propia página para apelar.
// - Moderador: siempre puede ver.
if(!$isMod && !$soyVendedor){
  if($prod['estado']!=='activo' || $enRev || $autoOpen){
    echo "<!doctype html><html><head><meta charset='utf-8'><meta name='viewport' content='width=device-width,initial-scale=1'><title>No disponible</title>
    <style>body{font-family:system-ui,Segoe UI,Roboto;background:#F6F8FC;color:#17212B;display:grid;place-items:center;height:100vh} .card{background:#fff;padding:20px;border-radius:14px;border:1px solid #eef2f7;box-shadow:0 8px 24px rgba(21,34,50,.08)}</style></head><body>
    <div class='card'><h3>Publicación no disponible</h3><p>Este artículo ha sido retirado temporalmente para revisión.</p><p><a href='catalogo.php'>Volver al catálogo</a></p></div></body></html>";
    exit;
  }
}

// Cargar imágenes
$imgs=$pdo->prepare("SELECT url FROM producto_imagen WHERE producto_id=? ORDER BY orden"); 
$imgs->execute([$id]); 
$im=$imgs->fetchAll();

// Guardado
$saved=$pdo->prepare("SELECT 1 FROM producto_guardado WHERE usuario_id=? AND producto_id=?"); 
$saved->execute([$uid,$id]); 
$isSaved=(bool)$saved->fetch();

// Mensajes (flash simple por querystring)
$msg = isset($_GET['msg']) ? trim($_GET['msg']) : '';
$typ = isset($_GET['type']) ? trim($_GET['type']) : 'info';
$redirCatalogo = isset($_GET['redir']) && $_GET['redir']==='catalogo';

?>
<!doctype html><html lang="es"><head>
<meta charset="utf-8"/><meta name="viewport" content="width=device-width, initial-scale=1"/>
<title><?= htmlspecialchars($prod['nombre']) ?> · MarketGO</title>
<style>
:root{--primary:#2F80ED;--bg:#F6F8FC;--card:#fff;--shadow:0 8px 24px rgba(21,34,50,.08)}
*{box-sizing:border-box}html,body{margin:0}body{font-family:system-ui,Segoe UI,Roboto;background:var(--bg);color:#17212B}
.topbar{position:sticky;top:0;background:#fff;box-shadow:var(--shadow);z-index:20}
.topbar__inner{max-width:1100px;margin:auto;padding:10px 20px;display:flex;align-items:center;gap:10px}
.topbar__inner a{ text-decoration:none; color:#17212B; padding:8px 12px; border-radius:999px; border:1px solid #e6ecf5 }
.topbar__inner .brand{font-weight:800;margin-right:auto}
.container{max-width:1100px;margin:16px auto;padding:0 20px}
.card{background:#fff;border-radius:16px;box-shadow:var(--shadow);border:1px solid #eef2f7}
.section{padding:14px 16px}
.btn{border:0;border-radius:12px;padding:10px 14px;font-weight:700;cursor:pointer;text-decoration:none;display:inline-block}
.btn-primary{background:var(--primary);color:#fff}
.btn-outline{background:#fff;border:1px solid #dfe6ef;color:#17212B}
.btn-danger{background:#ED2F2F;color:#fff}
.gallery{display:flex;gap:8px;overflow:auto}
.gallery img{width:220px;height:160px;object-fit:cover;border-radius:10px;border:1px solid #eee}
.notice{padding:10px;border:1px solid #ffe9a6;background:#FFF5D9;color:#8a6d00;border-radius:10px;margin-bottom:10px}
.alert{padding:12px;border-radius:12px;margin-bottom:12px;border:1px solid transparent}
.alert.info{background:#E8F1FF;border-color:#cfe0ff;color:#174ea6}
.alert.success{background:#EAF7EE;border-color:#c8edd2;color:#1e7b31}
.alert.warn{background:#FFF5D9;border-color:#ffe9a6;color:#8a6d00}
.alert.error{background:#FDECEC;border-color:#f7c2c2;color:#a12b2b}
.stack{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.card-grid{display:grid;grid-template-columns:2fr 1fr;gap:16px}
</style>
</head><body>
<header class="topbar">
  <div class="topbar__inner">
    <div class="brand">MarketGO</div>
    <a href="catalogo.php">Catálogo</a>
    <a href="panel.php">Mis publicaciones</a>
    <a href="chat_list.php">Mis chats</a>
  </div>
</header>

<div class="container">

  <?php if($msg): ?>
    <div class="alert <?= htmlspecialchars($typ) ?>">
      <div class="stack">
        <div><?= htmlspecialchars($msg) ?></div>
        <?php if($redirCatalogo): ?>
          <a class="btn btn-outline" href="catalogo.php">Ir al catálogo</a>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>

  <div class="card">
    <div class="section card-grid">
      <div>
        <?php if($isMod && ($enRev || $prod['estado']!=='activo')): ?>
          <div class="notice">Vista de moderador: esta publicación está <strong><?= htmlspecialchars($prod['estado']) ?></strong><?= $enRev ? ' y en revisión' : '' ?>.</div>
        <?php endif; ?>

        <?php if($soyVendedor && ($prod['estado']!=='activo' || $enRev || $autoOpen)): ?>
          <div class="alert warn">
            Tu publicación no está disponible al público en este momento. Puedes <strong>apelar</strong> si consideras que fue un error.
          </div>
        <?php endif; ?>

        <h2 style="margin:0 0 8px 0"><?= htmlspecialchars($prod['nombre']) ?></h2>
        <div style="color:#667"> <?= htmlspecialchars($prod['categoria']) ?> · <?= htmlspecialchars($prod['ciudad'] ?: 's/ciudad') ?> </div>
        <div class="gallery" style="margin:12px 0">
          <img src="<?= htmlspecialchars($prod['imagen_portada_url'] ?: 'https://placehold.co/800x600?text=Producto') ?>" alt="">
          <?php foreach($im as $g): ?><img src="<?= htmlspecialchars($g['url']) ?>" alt=""><?php endforeach; ?>
        </div>
        <p><?= nl2br(htmlspecialchars($prod['descripcion'])) ?></p>
        <?php if($prod['tipo']==='servicio' && !empty($prod['horario_atencion'])): ?>
          <p><strong>Horario:</strong> <?= htmlspecialchars($prod['horario_atencion']) ?></p>
        <?php endif; ?>
      </div>

      <aside>
        <div class="card" style="padding:14px">
          <div style="font-size:1.4rem;font-weight:800;margin-bottom:8px">$<?= number_format($prod['precio'],2) ?></div>
          <div style="margin-bottom:8px">Vendedor: @<?= htmlspecialchars($prod['username']) ?></div>
          <?php if(!$soyVendedor): ?>
            <div style="display:flex;gap:8px;margin-bottom:8px">
              <form method="post" action="toggle_guardado.php" style="margin:0">
                <input type="hidden" name="id" value="<?= $prod['id'] ?>">
                <button class="btn"><?= $isSaved?'💙 Guardado':'🤍 Me interesa' ?></button>
              </form>
              <a class="btn btn-primary" href="chat.php?producto=<?= $prod['id'] ?>&vendedor=<?= $prod['vendedor_id'] ?>">Chatear</a>
            </div>
          <?php endif; ?>

          <!-- ====== Reporte de COMPRADOR (no desactiva automáticamente) ====== -->
          <?php if(!$soyVendedor): ?>
            <div class="section" style="padding:0;border-top:1px solid #eef2f7;margin-top:10px"></div>
            <form method="post" action="reportar.php">
              <input type="hidden" name="producto_id" value="<?= $prod['id'] ?>">
              <label>Reportar:
                <select name="tipo" required>
                  <option value="fraude">Fraude</option>
                  <option value="contenido_prohibido">Contenido prohibido</option>
                  <option value="categoria_erronea">Categoría errónea</option>
                  <option value="ofensivo">Ofensivo</option>
                  <option value="otro">Otro</option>
                </select>
              </label>
              <label style="display:block;margin-top:6px">Comentario (opcional)
                <textarea name="comentario" style="width:100%;height:70px"></textarea>
              </label>
              <button class="btn btn-outline" type="submit">Enviar reporte</button>
            </form>
            <div class="alert info" style="margin-top:8px">
              Los reportes de compradores se revisan por un moderador. La publicación no se oculta automáticamente.
            </div>
          <?php endif; ?>

          <!-- ====== Acciones de MODERADOR (crea incidencia y oculta por trigger) ====== -->
          <?php if($isMod): ?>
            <div class="section" style="padding:0;border-top:1px solid #eef2f7;margin-top:10px"></div>
            <form method="post" action="moderar_reportar.php">
              <input type="hidden" name="producto_id" value="<?= $prod['id'] ?>">
              <label>Motivo de incidencia (moderación)
                <textarea name="descripcion" required style="width:100%;height:80px"></textarea>
              </label>
              <button class="btn btn-danger" type="submit">Abrir incidencia (ocultar)</button>
            </form>
          <?php endif; ?>
        </div>
      </aside>
    </div>
  </div>

  <!-- ====== Apelación del VENDEDOR (sección separada) ====== -->
  <?php if($soyVendedor && ($prod['estado']!=='activo' || $enRev || $autoOpen)): ?>
    <div class="card" style="margin-top:14px">
      <div class="section">
        <h3 style="margin:0 0 8px 0">Apelar decisión</h3>
        <p class="alert info" style="margin:8px 0">
          Explica por qué tu publicación debería ser aprobada. Puedes adjuntar una URL con evidencia (drive, imagen, sitio oficial, etc).
        </p>
        <form method="post" action="apelar.php" class="stack" style="flex-direction:column;align-items:stretch">
          <input type="hidden" name="producto_id" value="<?= $prod['id'] ?>">
          <label>Motivo de apelación
            <textarea name="motivo" required style="width:100%;height:100px"></textarea>
          </label>
          <label>Evidencia (URL)
            <input type="url" name="evidencia_url" placeholder="https://...">
          </label>
          <button class="btn btn-primary" type="submit">Enviar apelación</button>
        </form>
        <?php if($incOpen): ?>
          <p class="alert warn" style="margin-top:8px">
            Ya existe una incidencia en curso (estado: <strong><?= htmlspecialchars($incOpen['estado']) ?></strong>). Tu apelación se anexará al caso.
          </p>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>

</div>
</body></html>
