<?php
/**
 * Lädt die Konfiguration aus der config.ini
 */
$config = parse_ini_file('config.ini', true);

if (!$config) {
    die("Fehler: config.ini konnte nicht geladen werden.");
}

// Datenbank-Konfiguration (Wir nutzen standardmäßig die Sektion [database])
$db_section = 'database';
$db_host = $config[$db_section]['host'];
$db_name = $config[$db_section]['database'];
$db_user = $config[$db_section]['user'];
$db_pass = $config[$db_section]['password'];
$db_port = $config[$db_section]['port'] ?? 3306;

/**
 * Erstellt eine PDO-Datenbankverbindung
 */
try {
    $dsn = "mysql:host=$db_host;dbname=$db_name;port=$db_port;charset=utf8mb4";
    $pdo = new PDO($dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    die("Datenbankverbindung fehlgeschlagen: " . $e->getMessage());
}
?>
