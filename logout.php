<?php
// logout.php - Cierra la sesión del usuario de forma segura
session_start();

// Destruir la sesión
session_destroy();

// Headers para evitar caché del navegador
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Redirigir a la página de inicio
header("Location: index.php");
exit;
?>
