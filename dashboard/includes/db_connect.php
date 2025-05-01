<?php
// includes/db_connect.php - Anslutning till databasen
// Inkludera konfigurationsfilen som ligger en nivå upp
require_once dirname(__DIR__) . '/../config.php';

// Anslut till databasen med mysqli
try {
    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // Kontrollera anslutning
    if ($db->connect_error) {
        throw new Exception("Databasanslutning misslyckades: " . $db->connect_error);
    }
    
    // Sätt UTF-8
    $db->set_charset("utf8");
    
    // Om debug-läge är aktiverat
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
    }
} catch (Exception $e) {
    die("Databasfel: " . $e->getMessage());
}

// Etablera även en PDO-anslutning för Auth-klassen
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    die("PDO-anslutning misslyckades: " . $e->getMessage());
}
?>
