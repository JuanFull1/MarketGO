<?php
session_start(); if(!isset($_SESSION['uid'])){ header("Location: index.php"); exit; }
require_once "db.php"; error_reporting(E_ALL); ini_set('display_errors',1); ini_set('log_errors',1);
$pdo=(new DB())->pdo(); $uid=$_SESSION['uid'];
$conv=$pdo->prepare("SELECT c.*, p.nombre producto_nombre FROM conversacion c LEFT JOIN producto p ON p.id=c.producto_id WHERE c.comprador_id=:u OR c.vendedor_id=:u ORDER BY c.actualizada_en DESC");
$conv->execute([':u'=>$uid]); $rows=$conv->fetchAll();
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>Mis chats</title>
<style>body{font-family:system-ui,Segoe UI,Roboto;background:#F6F8FC;color:#17212B} .container{max-width:900px;margin:24px auto;padding:0 20px}
.card{background:#fff;border-radius:14px;box-shadow:0 8px 24px rgba(21,34,50,.08);border:1px solid #eef2f7;padding:12px}
a{color:#17212B;text-decoration:none}</style></head><body>
<div class="container">
  <h2>Mis chats</h2>
  <div style="display:grid;gap:10px">
    <?php foreach($rows as $c): $other=($c['comprador_id']===$uid)?$c['vendedor_id']:$c['comprador_id']; ?>
      <a class="card" href="chat.php?id=<?= $c['id'] ?>">
        <div><strong>#<?= $c['id'] ?></strong> · <?= htmlspecialchars($c['producto_nombre'] ?: 'Chat general') ?></div>
        <small>Con: <?= htmlspecialchars($other) ?></small>
      </a>
    <?php endforeach; if(!$rows): ?><div class="card">No tienes conversaciones.</div><?php endif; ?>
  </div>
</div>
</body></html>
