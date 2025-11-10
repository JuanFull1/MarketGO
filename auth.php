<?php // auth.php
session_start();
require_once "db.php";

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

function debug($msg, array $ctx = []) {
  unset($ctx['password'], $ctx['password_hash']);
  error_log('[MarketGO][auth] ' . $msg . (empty($ctx) ? '' : ' ' . json_encode($ctx)));
}

// Conexión protegida
try {
  $pdo = (new DB())->pdo();
} catch (Throwable $e) {
  debug('Error de conexion', ['error' => $e->getMessage()]);
  $msg = urlencode('No se pudo conectar a la base de datos.');
  header("Location: index.php?error=$msg");
  exit;
}

$action = $_GET['action'] ?? 'login';

if ($action === 'logout') {
  session_destroy();
  header("Location: index.php");
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: index.php');
  exit;
}

try {
  if ($action === 'register') {
    // Recolecta datos (coinciden con el formulario de index.php)
    $nombre = trim($_POST['name'] ?? '');
    $apellido = trim($_POST['lastname'] ?? '');
    $cedula = trim($_POST['cedula'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $telefono = trim($_POST['telefono'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $generoSel = trim($_POST['genero'] ?? '');

    debug('Registro (auth.php)', ['email' => $email, 'cedula' => $cedula, 'genero' => $generoSel]);

    if (!$nombre || !$apellido || !$cedula || !$email || !$password) {
      throw new Exception('Datos incompletos.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception('Email inválido.');

    // normalizar género
    $mapGenero = [
      'masculino' => 'masculino',
      'femenino' => 'femenino',
      'prefiero_no_decirlo' => 'prefiero_no_decirlo'
    ];
    $genero = $mapGenero[$generoSel] ?? null;

    // username único basado en email o nombre+apellido
    $baseUsername = strstr($email, '@', true) ?: preg_replace('/\s+/', '', strtolower($nombre . $apellido));
    $username = $baseUsername !== '' ? $baseUsername : strtolower(bin2hex(random_bytes(4)));
    $i = 1;
    $base = $username;
    while (true) {
      $chk = $pdo->prepare("SELECT 1 FROM public.perfil_usuario WHERE username = ? LIMIT 1");
      $chk->execute([$username]);
      if (!$chk->fetch()) break;
      $username = $base . $i++;
    }

    // correo único
    $chkMail = $pdo->prepare("SELECT 1 FROM public.perfil_usuario WHERE correo = ? LIMIT 1");
    $chkMail->execute([$email]);
    if ($chkMail->fetch()) throw new Exception('Este correo ya está registrado.');

    $hash = password_hash($password, PASSWORD_DEFAULT);

    // insert comprador+vendedor (admin/mod se crean por SQL aparte)
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
    if (!$new) throw new Exception('No se pudo crear el usuario.');

    // Autologin
    $_SESSION['uid'] = $new['id']; // UUID
    $_SESSION['uname'] = $new['nombre'];

    header("Location: catalogo.php");
    exit;
  }

  if ($action === 'login') {
    $user = trim($_POST['user'] ?? '');
    $password = $_POST['password'] ?? '';
    if (!$user || !$password) throw new Exception('Completa usuario y contraseña.');

    // buscar por username o correo
    $stmt = $pdo->prepare("
      SELECT id, nombre, password_hash
      FROM public.perfil_usuario
      WHERE username = :u OR correo = :u
      LIMIT 1
    ");
    $stmt->execute([':u' => $user]);
    $row = $stmt->fetch();

    if (!$row || empty($row['password_hash']) || !password_verify($password, $row['password_hash'])) {
      throw new Exception('Usuario o contraseña incorrectos.');
    }

    $_SESSION['uid'] = $row['id'];
    $_SESSION['uname'] = $row['nombre'];

    header("Location: catalogo.php");
    exit;
  }

  throw new Exception('Acción no válida.');
} catch (Throwable $e) {
  $msg = urlencode($e->getMessage());
  debug('Error auth', ['error' => $e->getMessage()]);
  header("Location: index.php?error=$msg");
  exit;
}
