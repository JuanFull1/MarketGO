<?php // panel.php
session_start();
if (!isset($_SESSION['uid'])) {
  header("Location: index.php");
  exit;
}
// Headers para evitar caché y proteger la sesión
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
require_once "db.php";
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
function debugP($m, $c = [])
{
  error_log('[MarketGO][panel] ' . $m . (empty($c) ? '' : ' ' . json_encode($c)));
}

$pdo = getPDO();
$uid = $_SESSION['uid'];

/* Traer si hay incidencia abierta por publicación */
$mis = $pdo->prepare("
  SELECT p.id,p.nombre,p.estado,p.tipo,p.precio,
         EXISTS(
           SELECT 1 FROM incidencia i
           WHERE i.producto_id=p.id
             AND i.estado IN ('abierta','solicita_info','en_apelacion')
         ) AS en_revision
  FROM producto p
  WHERE p.vendedor_id=?
  ORDER BY p.creado_en DESC");
$mis->execute([$uid]);
$misRows = $mis->fetchAll();

$guard = $pdo->prepare("SELECT p.id,p.nombre,p.precio,p.imagen_portada_url
  FROM producto_guardado g
  JOIN producto p ON p.id=g.producto_id
  WHERE g.usuario_id=?
  ORDER BY g.creado_en DESC");
$guard->execute([$uid]);
$savRows = $guard->fetchAll();

/* ============================================
   DETECCIÓN automática (texto + nombres de archivo)
   ============================================ */
$black = [
  'arma',
  'armas',
  'pistola',
  'revólver',
  'revolver',
  'rifle',
  'escopeta',
  'municion',
  'munición',
  'bala',
  'silenciador',
  'explosivo',
  'c4',
  'granada',
  'dinamita',
  'bomba',
  'droga',
  'drogas',
  'marihuana',
  'cannabis',
  'cocaína',
  'cocaina',
  'anfetamina',
  'lsd',
  'opio',
  'porn',
  'pornografía',
  'pornografia',
  'sexo',
  'prostitucion',
  'prostitución',
  'ácido',
  'acido',
  'ácido sulfúrico',
  'acido sulfurico',
  'ácido muriático',
  'acido muriatico'
];

/**
 * Devuelve true si el $texto contiene alguna palabra de $lista.
 * Usa coincidencia con límites de palabra y es insensible a mayúsculas/acentos.
 */
function contienePalabrasPeligrosas(string $texto, array $lista): bool
{
  $alternativas = array_map(fn($w) => preg_quote($w, '/'), $lista);
  $patron = '/\b(' . implode('|', $alternativas) . ')\b/iu';
  return preg_match($patron, $texto) === 1;
}
?>
<!doctype html>
<html lang="es">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>MarketGO · Panel</title>
  <style>
    :root {
      --primary: #2F80ED;
      --bg: #F6F8FC;
      --card: #fff;
      --shadow: 0 8px 24px rgba(21, 34, 50, .08)
    }

    * {
      box-sizing: border-box
    }

    html,
    body {
      margin: 0
    }

    body {
      font-family: system-ui, Segoe UI, Roboto;
      background: var(--bg);
      color: #17212B
    }

    .topbar {
      position: sticky;
      top: 0;
      background: #fff;
      box-shadow: var(--shadow);
      z-index: 20
    }

    .topbar__inner {
      max-width: 1100px;
      margin: auto;
      padding: 10px 20px;
      display: flex;
      align-items: center;
      gap: 10px
    }

    .topbar__inner a {
      text-decoration: none;
      color: #17212B;
      padding: 8px 12px;
      border-radius: 999px;
      border: 1px solid #e6ecf5
    }

    .topbar__inner .brand {
      font-weight: 800;
      margin-right: auto
    }

    .container {
      max-width: 1100px;
      margin: 16px auto 80px;
      padding: 0 20px
    }

    .card {
      background: var(--card);
      border-radius: 16px;
      box-shadow: var(--shadow);
      border: 1px solid #eef2f7;
      margin-bottom: 20px
    }

    .card h3 {
      margin: 0;
      padding: 14px 16px;
      border-bottom: 1px solid #eef2f7
    }

    .section {
      padding: 14px 16px
    }

    .btn {
      border: 0;
      border-radius: 12px;
      padding: 10px 14px;
      font-weight: 700;
      cursor: pointer;
      text-decoration: none;
      display: inline-block
    }

    .btn-primary {
      background: var(--primary);
      color: #fff
    }

    .btn-outline {
      background: #fff;
      border: 1px solid #dfe6ef;
      color: #17212B
    }

    .btn-danger {
      background: #ED2F2F;
      color: #fff
    }

    .table {
      width: 100%;
      border-collapse: collapse
    }

    .table th,
    .table td {
      padding: 8px;
      border-bottom: 1px solid #eef2f7;
      text-align: left;
      vertical-align: middle
    }

    .badge {
      padding: 4px 8px;
      border-radius: 999px;
      font-size: .75rem;
      font-weight: 700;
      display: inline-block
    }

    .badge-warn {
      background: #FFF5D9;
      color: #8a6d00;
      border: 1px solid #ffe9a6
    }

    .badge-info {
      background: #E8F1FF;
      color: #174ea6;
      border: 1px solid #cfe0ff
    }

    .stack {
      display: flex;
      align-items: center;
      gap: 8px;
      flex-wrap: wrap
    }

    .muted {
      color: #6b7785;
      font-size: .85rem
    }
  </style>
</head>

<body>
  <header class="topbar">
    <div class="topbar__inner">
      <div class="brand">MarketGO</div>
      <a href="catalogo.php">Catálogo</a>
      <a href="panel.php">Mis publicaciones</a>
      <a href="chat_list.php">Mis chats</a>
      <a href="index.php" onclick="event.preventDefault(); document.getElementById('logout').submit();">Salir</a>
      <form id="logout" method="post" action="index.php?action=logout" style="display:none"></form>
    </div>
  </header>

  <main class="container">
    <section class="card">
      <h3>Mis publicaciones</h3>
      <div class="section">
        <a class="btn btn-primary" href="producto_form.php">+ Nueva publicación</a>
        <table class="table">
          <thead>
            <tr>
              <th>Nombre</th>
              <th>Tipo</th>
              <th>Estado</th>
              <th>Precio</th>
              <th>Incidencia</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($misRows as $r): ?>
              <?php
              // Texto a validar contra la lista negra (nombre + tipo)
              $textoValidar = trim(($r['nombre'] ?? '') . ' ' . ($r['tipo'] ?? ''));
              $coincideListaNegra = contienePalabrasPeligrosas($textoValidar, $black);

              // Flag por tipo "peligroso"
              $flagTipoPeligroso = isset($r['tipo']) && preg_match('/peligros?/i', (string) $r['tipo']);

              // Producto peligroso si coincide lista negra O marcado por tipo
              $esPeligroso = $coincideListaNegra || $flagTipoPeligroso;

              // ¿Incidencia abierta/en apelación?
              $enRevision = (bool) $r['en_revision'];
              ?>
              <tr>
                <td><a href="producto_detalle.php?id=<?= $r['id'] ?>"><?= htmlspecialchars($r['nombre']) ?></a></td>
                <td><?= htmlspecialchars($r['tipo']) ?></td>
                <td><?= htmlspecialchars($r['estado']) ?></td>
                <td>$<?= number_format($r['precio'], 2) ?></td>
                <td>
                  <?php if ($enRevision): ?>
                    <span class="badge badge-info">En revisión</span>
                  <?php else: ?>—<?php endif; ?>
                </td>
                <td>
                  <div class="stack">
                    <?php if ($esPeligroso): ?>
                      <!-- SOLO mostrar la advertencia y Apelar; sin Editar ni Ver caso -->
                      <span class="badge badge-warn" title="Coincide con la lista de contenido restringido.">
                        Producto peligroso: no puedes eliminar
                      </span>
                      <a class="btn btn-primary" href="producto_detalle.php?id=<?= $r['id'] ?>">Apelar</a>
                    <?php else: ?>
                      <!-- Producto NO peligroso: Editar + Eliminar -->
                      <a class="btn btn-outline" href="producto_form.php?id=<?= $r['id'] ?>">Editar</a>
                      <a class="btn btn-danger" href="producto_eliminar.php?id=<?= $r['id'] ?>"
                        onclick="return confirm('¿Eliminar publicación?')">Eliminar</a>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach;
            if (!$misRows): ?>
              <tr>
                <td colspan="6">Sin publicaciones.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>

    <section class="card">
      <h3>Guardados (Me interesa)</h3>
      <div class="section" style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px">
        <?php foreach ($savRows as $p): ?>
          <a href="producto_detalle.php?id=<?= $p['id'] ?>" style="text-decoration:none;color:inherit">
            <div class="card" style="overflow:hidden">
              <img src="<?= htmlspecialchars($p['imagen_portada_url'] ?: 'https://placehold.co/800x600?text=Producto') ?>"
                alt="" style="width:100%;aspect-ratio:4/3;object-fit:cover">
              <div class="section">
                <div style="font-weight:700;"><?= htmlspecialchars($p['nombre']) ?></div>
                <div>$<?= number_format($p['precio'], 2) ?></div>
              </div>
            </div>
          </a>
        <?php endforeach;
        if (!$savRows): ?>
          <div class="section">Nada guardado aún.</div>
        <?php endif; ?>
      </div>
    </section>
  </main>
</body>

</html>