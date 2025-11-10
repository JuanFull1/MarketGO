<?php // index.php
session_start();
require_once "db.php";

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

function debug($msg, array $ctx = []) {
  $ctxSan = $ctx;
  unset($ctxSan['password'], $ctxSan['password_hash']);
  error_log('[MarketGO][index] ' . $msg . (empty($ctxSan) ? '' : ' ' . json_encode($ctxSan)));
}

$pdo = null;
$error = "";

// Conexión protegida
try {
  $pdo = (new DB())->pdo();
  // Diagnóstico conexión
  try {
    $info = $pdo->query("SELECT current_database() AS db, current_user AS usr, current_schema AS sch, show_config_by_name('search_path') AS search_path")->fetch();
    debug('Conexion PG', $info ?: []);
  } catch (Throwable $e) {
    debug('Error info conexion', ['exception' => $e->getMessage()]);
  }

  // Verifica existencia de tabla
  try {
    $tblCheck = $pdo->prepare("
      SELECT 1
      FROM information_schema.tables
      WHERE table_name = 'perfil_usuario' AND table_schema = 'public'
      LIMIT 1
    ");
    $tblCheck->execute();
    $tableExists = (bool)$tblCheck->fetchColumn();
    if (!$tableExists) {
      $errMsg = "La tabla 'public.perfil_usuario' no existe. Ejecuta el script SQL en esta BD.";
      debug('Tabla faltante', ['detail' => $errMsg]);
      $error = $errMsg;
    }
  } catch (Throwable $e) {
    debug('Error verificando tabla', ['exception' => $e->getMessage()]);
    $error = "Error verificando esquema: " . $e->getMessage();
  }
} catch (Throwable $e) {
  $error = "Error de conexión a la base de datos: " . $e->getMessage();
  debug('Error de conexion', ['exception' => $e->getMessage()]);
}

$error = $_GET['error'] ?? ($error ?? "");
$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
  try {
    if ($action === 'login') {
      $user = trim($_POST['user'] ?? '');
      $password = $_POST['password'] ?? '';
      debug('Intento de login', ['user' => $user]);
      if (!$user || !$password) throw new Exception("Completa usuario/correo y contraseña.");

      $st = $pdo->prepare("
        SELECT id, nombre, password_hash
        FROM public.perfil_usuario
        WHERE username = :u OR correo = :u
        LIMIT 1
      ");
      $st->execute([':u' => $user]);
      $row = $st->fetch();

      if (!$row || empty($row['password_hash']) || !password_verify($password, $row['password_hash'])) {
        debug('Login fallido: credenciales inválidas', ['user' => $user]);
        throw new Exception("Usuario o contraseña incorrectos.");
      }

      debug('Login OK', ['uid' => $row['id']]);
      $_SESSION['uid'] = $row['id']; // UUID
      $_SESSION['uname'] = $row['nombre']; // nombre mostrado
      header("Location: catalogo.php");
      exit;
    }

    if ($action === 'register') {
      $nombre = trim($_POST['name'] ?? '');
      $apellido = trim($_POST['lastname'] ?? '');
      $cedula = trim($_POST['cedula'] ?? '');
      $email = trim($_POST['email'] ?? '');
      $password = $_POST['password'] ?? '';
      $telefono = trim($_POST['telefono'] ?? '');
      $direccion = trim($_POST['direccion'] ?? '');
      $generoSel = trim($_POST['genero'] ?? '');

      debug('Intento de registro', ['nombre'=>$nombre,'apellido'=>$apellido,'cedula'=>$cedula,'email'=>$email,'genero'=>$generoSel]);

      if (!$nombre || !$apellido || !$cedula || !$email || !$password) {
        throw new Exception("Completa todos los campos obligatorios.");
      }
      if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception("Email inválido.");

      $mapGenero = [
        'masculino' => 'masculino',
        'femenino' => 'femenino',
        'prefiero_no_decirlo' => 'prefiero_no_decirlo'
      ];
      $genero = $mapGenero[$generoSel] ?? null;

      $baseUsername = strstr($email, '@', true) ?: preg_replace('/\s+/', '', strtolower($nombre . $apellido));
      $username = $baseUsername !== '' ? $baseUsername : strtolower(bin2hex(random_bytes(4)));
      $i = 1; $base = $username;
      while (true) {
        $chk = $pdo->prepare("SELECT 1 FROM public.perfil_usuario WHERE username = ? LIMIT 1");
        $chk->execute([$username]);
        if (!$chk->fetch()) break;
        $username = $base . $i++;
      }
      debug('Username asignado', ['username' => $username]);

      $chkMail = $pdo->prepare("SELECT 1 FROM public.perfil_usuario WHERE correo = ? LIMIT 1");
      $chkMail->execute([$email]);
      if ($chkMail->fetch()) {
        debug('Registro fallido: correo ya existe', ['email' => $email]);
        throw new Exception("Este correo ya está registrado.");
      }

      $hash = password_hash($password, PASSWORD_DEFAULT);

      $ins = $pdo->prepare("
        INSERT INTO public.perfil_usuario
          (nombre, apellido, cedula, correo, telefono, direccion, genero, username, password_hash, es_comprador, es_vendedor)
        VALUES
          (:nombre, :apellido, :cedula, :correo, :telefono, :direccion, :genero, :username, :hash, TRUE, TRUE)
        RETURNING id, nombre
      ");
      $ins->execute([
        ':nombre' => $nombre,
        ':apellido' => $apellido,
        ':cedula' => $cedula,
        ':correo' => $email,
        ':telefono' => $telefono ?: null,
        ':direccion' => $direccion ?: null,
        ':genero' => $genero,
        ':username' => $username,
        ':hash' => $hash
      ]);
      $new = $ins->fetch();
      if (!$new) throw new Exception("No se pudo crear el usuario.");

      debug('Registro OK', ['uid' => $new['id']]);
      $_SESSION['uid'] = $new['id']; // UUID
      $_SESSION['uname'] = $new['nombre'];
      header("Location: catalogo.php");
      exit;
    }

    if ($action === 'logout') {
      debug('Logout');
      session_destroy();
      header("Location: index.php");
      exit;
    }
  } catch (Throwable $e) {
    $error = $e->getMessage();
    debug('Excepción atrapada', ['error' => $error]);
  }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>MarketGO - Portada</title>
  <style>
    :root{--primary:#2F80ED;--muted:#7B8A9C;--bg:#F6F8FC;--card:#fff;--shadow:0 8px 24px rgba(21,34,50,.08)}
    *{box-sizing:border-box}html,body{margin:0}body{font-family:system-ui,Segoe UI,Roboto;background:var(--bg);color:#17212B}
    .container{max-width:1100px;margin:auto;padding:0 20px}
    header{background:#fff;box-shadow:var(--shadow)}
    .header-inner{display:flex;align-items:center;justify-content:space-between;padding:14px 0}
    .logo{margin:0;color:var(--primary)}
    .btn{border:0;border-radius:12px;padding:10px 14px;font-weight:700;cursor:pointer}
    .btn-primary{background:var(--primary);color:#fff}.btn-ghost{background:#fff;border:1px solid #E0E7F0}
    .hero{padding:28px 0}.hero-inner{display:grid;grid-template-columns:1.2fr .8fr;gap:24px;align-items:center}
    .hero-title{font-size:2rem;margin:.2rem 0}.hero-sub{color:var(--muted);margin:.3rem 0 1rem}
    .hero-cover{width:100%;border-radius:16px;box-shadow:var(--shadow);object-fit:cover}
    .alert{margin:12px 0;padding:10px;border-radius:10px;background:#ffecec;color:#9b1c1c;border:1px solid #ffd4d4}
    .modal[open]{display:block}.modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.4)}
    .panel{background:#fff;max-width:460px;margin:10vh auto;padding:20px;border-radius:16px;box-shadow:var(--shadow)}
    form{display:grid;gap:10px}label{display:grid;gap:6px;font-weight:600}
    input,select{padding:10px 12px;border:1px solid #E0E7F0;border-radius:10px}
    .input-group{position:relative;display:flex;align-items:center}
    .input-group input{width:100%;padding-right:44px}
    .input-group button{
      position:absolute;right:8px;top:50%;transform:translateY(-50%);
      border:0;background:#fff;border-radius:8px;padding:6px;cursor:pointer;
      border:1px solid #E0E7F0;display:flex;align-items:center;justify-content:center;width:32px;height:32px
    }
    .actions{display:flex;gap:8px;justify-content:flex-end}
    @media(max-width:860px){.hero-inner{grid-template-columns:1fr}}
  </style>
</head>
<body>
<header>
  <div class="container header-inner">
    <h1 class="logo">MarketGO</h1>
    <nav>
      <button class="btn btn-ghost" onclick="openModal('login')">Iniciar sesión</button>
      <button class="btn btn-primary" onclick="openModal('register')">Registrarse</button>
    </nav>
  </div>
</header>

<main class="hero">
  <div class="container hero-inner">
    <div>
      <?php if(!empty($_GET['error']) || !empty($error)): ?>
        <div class="alert"><?= htmlspecialchars($_GET['error'] ?? $error) ?></div>
      <?php endif; ?>

      <h2 class="hero-title">Compra y vende cerca de ti</h2>
      <p class="hero-sub">Explora productos, chatea con vendedores y administra tus ventas — todo en un solo lugar.</p>
      <button class="btn btn-primary" onclick="openModal('register')">Comenzar</button>
    </div>
    <div aria-hidden="false">
      <img class="hero-cover" src="https://images.unsplash.com/photo-1542744173-8e7e53415bb0?auto=format&fit=crop&w=1200&q=80" alt="Mercado local"/>
    </div>
  </div>
</main>

<!-- MODAL LOGIN -->
<div id="modal-login" class="modal">
  <div class="panel">
    <h3>Iniciar sesión</h3>
    <form method="post" action="?action=login">
      <label>Correo o usuario
        <input type="text" name="user" required>
      </label>
      <label>Contraseña
        <div class="input-group">
          <input id="login-password" type="password" name="password" required>
          <button type="button" aria-label="Mostrar u ocultar contraseña" onclick="togglePassword('login-password', this)">
            <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
              <path d="M12 5C7 5 2.73 8.11 1 12c1.73 3.89 6 7 11 7s9.27-3.11 11-7c-1.73-3.89-6-7-11-7zm0 12a5 5 0 1 1 0-10 5 5 0 0 1 0 10z"/>
            </svg>
          </button>
        </div>
      </label>
      <div class="actions">
        <button type="button" class="btn btn-ghost" onclick="closeModals()">Cancelar</button>
        <button class="btn btn-primary" type="submit">Entrar</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL REGISTER -->
<div id="modal-register" class="modal">
  <div class="panel">
    <h3>Crear cuenta</h3>
    <form method="post" action="?action=register">
      <label>Nombre * <input type="text" name="name" required> </label>
      <label>Apellido * <input type="text" name="lastname" required> </label>
      <label>Cédula * <input type="text" name="cedula" required> </label>
      <label>Género
        <select name="genero">
          <option value="" selected>Seleccione...</option>
          <option value="masculino">Masculino</option>
          <option value="femenino">Femenino</option>
          <option value="prefiero_no_decirlo">Prefiero no decirlo</option>
        </select>
      </label>
      <label>Correo electrónico * <input type="email" name="email" required> </label>
      <label>Contraseña *
        <div class="input-group">
          <input id="register-password" type="password" name="password" required>
          <button type="button" aria-label="Mostrar u ocultar contraseña" onclick="togglePassword('register-password', this)">
            <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
              <path d="M12 5C7 5 2.73 8.11 1 12c1.73 3.89 6 7 11 7s9.27-3.11 11-7c-1.73-3.89-6-7-11-7zm0 12a5 5 0 1 1 0-10 5 5 0 0 1 0 10z"/>
            </svg>
          </button>
        </div>
      </label>
      <label>Teléfono <input type="text" name="telefono"> </label>
      <label>Dirección <input type="text" name="direccion"> </label>
      <div class="actions">
        <button type="button" class="btn btn-ghost" onclick="closeModals()">Cancelar</button>
        <button class="btn btn-primary" type="submit">Registrarse</button>
      </div>
    </form>
  </div>
</div>

<script>
  function openModal(which){
    document.getElementById('modal-'+which).style.display='block';
  }
  function closeModals(){
    document.querySelectorAll('.modal').forEach(m=> m.style.display='none');
  }
  window.addEventListener('keydown', e=>{
    if(e.key==='Escape') closeModals();
  });
  function togglePassword(inputId, btn){
    const el = document.getElementById(inputId);
    if(!el) return;
    el.type = el.type === 'password' ? 'text' : 'password';
  }
</script>
</body>
</html>
