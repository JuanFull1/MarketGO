<?php
class DB {
  private $host = "127.0.0.1";
  private $port = "3306";
  private $dbname = "marketgo";
  private $user = "root";
  private $pass = "juanK2004";

  public function pdo(): PDO {
    $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->dbname};charset=utf8mb4";
    $opt = [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
    ];
    try {
      $pdo = new PDO($dsn, $this->user, $this->pass, $opt);
      return $pdo;
    } catch (Throwable $e) {
      // log detallado para diagnóstico
      error_log('[MarketGO][db] FALLO CONEXION MYSQL: ' . $e->getMessage());
      // si estás en desarrollo, descomenta la línea siguiente para ver el error exacto en pantalla:
      // die('DEBUG PDO: '.$e->getMessage());
      throw new Exception('No se pudo conectar a MySQL. Revisa host/puerto/usuario/clave y que pdo_mysql esté habilitado.');
    }
  }
}
