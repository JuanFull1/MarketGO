<?php
// Configuración: ajusta estos valores a tu entorno
define('DB_HOST', 'localhost');
define('DB_NAME', 'marketgo');
define('DB_USER', 'root');
define('DB_PASS', '#1850572809z');
define('DB_CHARSET', 'utf8mb4');

// Agrega una función para obtener la conexión PDO (singleton)
function getPDO(): PDO
{
  static $pdo = null;
  if ($pdo instanceof PDO) {
    return $pdo;
  }

  $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
  $options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ];

  try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    // Crear cuentas por defecto si es posible (no interrumpe si falta la tabla)
    try {
      ensureDefaultAccounts($pdo);
    } catch (Throwable $e) {
      error_log('ensureDefaultAccounts skipped: ' . $e->getMessage());
    }
  } catch (PDOException $e) {
    // No mostrar detalles sensibles en producción; registrar para diagnóstico
    error_log('DB connection error: ' . $e->getMessage());
    throw new RuntimeException('Error al conectar a la base de datos.');
  }

  return $pdo;
}

/**
 * Inserta admin, moderador y un usuario de prueba si no existen.
 * admin/moderador: es_comprador=0, es_vendedor=0
 * usuario: es_comprador=1, es_vendedor=1
 */
function ensureDefaultAccounts(PDO $pdo): void
{
  $defaults = [
    [
      'username' => 'admin',
      'correo' => 'admin@example.local',
      'nombre' => 'Admin',
      'apellido' => 'Default',
      'password' => 'admin123',
      'rol' => 'administrador',
      'es_comprador' => 0,
      'es_vendedor' => 0
    ],
    [
      'username' => 'moderador',
      'correo' => 'moderador@example.local',
      'nombre' => 'Moderador',
      'apellido' => 'Default',
      'password' => 'moderador123',
      'rol' => 'moderador',
      'es_comprador' => 0,
      'es_vendedor' => 0
    ],
    [
      'username' => 'usuario',
      'correo' => 'usuario@example.local',
      'nombre' => 'Usuario',
      'apellido' => 'Demo',
      'password' => 'usuario123',
      'rol' => null,
      'es_comprador' => 1,
      'es_vendedor' => 1
    ]
  ];

  $check = $pdo->prepare("SELECT 1 FROM perfil_usuario WHERE username = ? OR correo = ? LIMIT 1");
  $ins = $pdo->prepare("
        INSERT INTO perfil_usuario
          (cedula, nombre, apellido, correo, telefono, direccion, genero, username, password_hash, estado, es_comprador, es_vendedor, rol_sistema)
        VALUES
          (:cedula, :nombre, :apellido, :correo, :telefono, :direccion, :genero, :username, :hash, 'activo', :es_comprador, :es_vendedor, :rol)
    ");

  foreach ($defaults as $u) {
    $check->execute([$u['username'], $u['correo']]);
    if ($check->fetch())
      continue;
    $hash = password_hash($u['password'], PASSWORD_DEFAULT);
    $ins->execute([
      ':cedula' => null,
      ':nombre' => $u['nombre'],
      ':apellido' => $u['apellido'],
      ':correo' => $u['correo'],
      ':telefono' => null,
      ':direccion' => null,
      ':genero' => null,
      ':username' => $u['username'],
      ':hash' => $hash,
      ':es_comprador' => $u['es_comprador'],
      ':es_vendedor' => $u['es_vendedor'],
      ':rol' => $u['rol'],
    ]);
    error_log('DB default user created: ' . $u['username']);
  }
}

// Inicializa la conexión si la quieres disponible inmediatamente (opcional)
// $pdo = getPDO();
