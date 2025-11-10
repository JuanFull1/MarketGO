
<?php
// Configuración: ajusta estos valores a tu entorno
define('DB_HOST', 'localhost');
define('DB_NAME', 'marketgo');
define('DB_USER', 'root');
define('DB_PASS', 'juanK2004');
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
        // PDO::ATTR_PERSISTENT => true, // opcional: activar si quieres conexiones persistentes
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        // No mostrar detalles sensibles en producción; registrar para diagnóstico
        error_log('DB connection error: ' . $e->getMessage());
        throw new RuntimeException('Error al conectar a la base de datos.');
    }

    return $pdo;
}

// Inicializa la conexión si la quieres disponible inmediatamente (opcional)
// $pdo = getPDO();