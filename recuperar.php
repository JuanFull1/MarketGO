<?php // recuperar.php
session_start(); require_once "db.php";
error_reporting(E_ALL); ini_set('display_errors',1); ini_set('log_errors',1);

$pdo=(new DB())->pdo();
$msg = "";
if($_SERVER['REQUEST_METHOD']==='POST'){
  try{
    $correo = trim($_POST['correo']??'');
    $cedula = trim($_POST['cedula']??'');
    $pass1  = $_POST['password']??'';
    $pass2  = $_POST['password2']??'';
    if(!$correo || !$cedula || !$pass1 || !$pass2) throw new Exception('Completa todos los campos.');
    if(!filter_var($correo,FILTER_VALIDATE_EMAIL)) throw new Exception('Correo inválido.');
    if($pass1!==$pass2) throw new Exception('Las contraseñas no coinciden.');
    if(strlen($pass1)<8 || !preg_match('/[A-Z]/',$pass1) || !preg_match('/[0-9]/',$pass1)) throw new Exception('Usa una contraseña fuerte (8+, mayúsculas y números).');
    $st=$pdo->prepare("SELECT id FROM perfil_usuario WHERE correo=? AND cedula=? LIMIT 1"); $st->execute([$correo,$cedula]);
    $u=$st->fetch(); if(!$u) throw new Exception('No se encontró un usuario con esos datos.');
    $hash=password_hash($pass1,PASSWORD_DEFAULT);
    $pdo->prepare("UPDATE perfil_usuario SET password_hash=? WHERE id=?")->execute([$hash,$u['id']]);
    $msg="Contraseña actualizada. Ya puedes iniciar sesión.";
  }catch(Throwable $e){ $msg=$e->getMessage(); }
}
?>
<!doctype html><html lang="es"><head>
<meta charset="utf-8"/><meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>Recuperar contraseña · MarketGO</title>
<style>
:root{--primary:#2F80ED;--bg:#F6F8FC;--card:#fff;--shadow:0 8px 24px rgba(21,34,50,.08)}
*{box-sizing:border-box}html,body{margin:0}body{font-family:system-ui,Segoe UI,Roboto;background:var(--bg);color:#17212B}
.container{max-width:480px;margin:40px auto;padding:0 20px}
.card{background:#fff;border-radius:16px;box-shadow:var(--shadow);border:1px solid #eef2f7;padding:16px}
label{display:block;margin-bottom:10px;font-weight:600}
input{width:100%;padding:10px 12px;border:1px solid #E0E7F0;border-radius:10px}
.btn{border:0;border-radius:12px;padding:10px 14px;font-weight:700;cursor:pointer;background:var(--primary);color:#fff;margin-top:8px}
.alert{margin:12px 0;padding:10px;border-radius:10px;background:#eef6ff;color:#0b57d0;border:1px solid #d6e6ff}
</style>
</head><body>
<div class="container">
  <div class="card">
    <h2>Recuperar contraseña</h2>
    <?php if($msg): ?><div class="alert"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <form method="post">
      <label>Correo <input type="email" name="correo" required></label>
      <label>Cédula <input name="cedula" required></label>
      <label>Nueva contraseña <input type="password" name="password" required></label>
      <label>Repite la contraseña <input type="password" name="password2" required></label>
      <button class="btn">Actualizar</button>
    </form>
    <div style="margin-top:8px"><a href="index.php">Volver</a></div>
  </div>
</div>
</body></html>
