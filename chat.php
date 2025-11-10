<?php
session_start(); if(!isset($_SESSION['uid'])){ header("Location: index.php"); exit; }
// Headers para evitar caché y proteger la sesión
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
require_once "db.php"; error_reporting(E_ALL); ini_set('display_errors',1); ini_set('log_errors',1);
$pdo=(new DB())->pdo(); $uid=$_SESSION['uid'];

$cid = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if(!$cid && isset($_GET['producto'],$_GET['vendedor'])){
  $pid=(int)$_GET['producto']; $vid=$_GET['vendedor'];
  $s=$pdo->prepare("SELECT id FROM conversacion WHERE comprador_id=? AND vendedor_id=? AND producto_id=? AND cerrada_en IS NULL");
  $s->execute([$uid,$vid,$pid]); $cid=$s->fetchColumn();
  if(!$cid){
    $ins=$pdo->prepare("INSERT INTO conversacion (comprador_id,vendedor_id,producto_id) VALUES (?,?,?) RETURNING id");
    $ins->execute([$uid,$vid,$pid]); $cid=$ins->fetchColumn();
  }
  header("Location: chat.php?id=".$cid); exit;
}
$c=$pdo->prepare("SELECT * FROM conversacion WHERE id=? AND (comprador_id=? OR vendedor_id=?)");
$c->execute([$cid,$uid,$uid]); $conv=$c->fetch(); if(!$conv){ die('No autorizado'); }

$msg=$pdo->prepare("SELECT m.*, (SELECT string_agg(a.url,',') FROM mensaje_adjunto a WHERE a.mensaje_id=m.id) adj FROM mensaje m WHERE m.conversacion_id=? ORDER BY m.enviado_en ASC");
$msg->execute([$cid]); $rows=$msg->fetchAll();
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>Chat #<?= $cid ?></title>
<style>
:root{--primary:#2F80ED;--bg:#F6F8FC;--card:#fff;--shadow:0 8px 24px rgba(21,34,50,.08)}
*{box-sizing:border-box}body{margin:0;font-family:system-ui,Segoe UI,Roboto;background:var(--bg)}
.container{max-width:900px;margin:24px auto;padding:0 20px}
.card{background:#fff;border-radius:16px;box-shadow:var(--shadow);border:1px solid #eef2f7}
.header{padding:12px 16px;border-bottom:1px solid #eef2f7}
.messages{padding:12px 16px;max-height:60vh;overflow:auto;display:flex;flex-direction:column;gap:8px}
.msg{padding:8px 10px;border:1px solid #eef2f7;border-radius:12px;max-width:70%}
.me{align-self:flex-end;background:#EAF2FF}
.them{align-self:flex-start;background:#fff}
.form{display:flex;gap:8px;padding:12px 16px;border-top:1px solid #eef2f7}
.btn{border:0;border-radius:10px;padding:10px 12px;cursor:pointer;font-weight:700}
.btn-primary{background:var(--primary);color:#fff}
</style>
</head><body>
<div class="container">
  <div class="card">
    <div class="header"><strong>Chat #<?= $cid ?></strong></div>
    <div class="messages" id="messages">
      <?php foreach($rows as $m): $mine=($m['autor_id']===$_SESSION['uid']); $adj= array_filter(explode(',',$m['adj']??'')); ?>
        <div class="msg <?= $mine?'me':'them' ?>">
          <div><?= nl2br(htmlspecialchars($m['contenido'])) ?></div>
          <?php foreach($adj as $a): ?><div><a href="<?= htmlspecialchars($a) ?>" target="_blank">📎 adjunto</a></div><?php endforeach; ?>
          <div style="font-size:.75rem;color:#667"><?= htmlspecialchars($m['enviado_en']) ?></div>
        </div>
      <?php endforeach; if(!$rows): ?><div class="them msg">¡Hola! ¿Sigue disponible?</div><?php endif; ?>
    </div>
    <form class="form" method="post" action="chat_send.php" enctype="multipart/form-data">
      <input type="hidden" name="id" value="<?= $cid ?>">
      <input name="contenido" placeholder="Escribe un mensaje" style="flex:1;padding:10px;border:1px solid #E0E7F0;border-radius:10px">
      <input type="file" name="adj[]" multiple accept="image/*">
      <button class="btn btn-primary">Enviar</button>
    </form>
  </div>
</div>
</body></html>
