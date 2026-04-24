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

/**
 * Normalisiert CSV-Rohdaten auf UTF-8.
 * Verhindert Mojibake bei UTF-8/Windows-1252/ISO-8859-1 und behandelt BOM/UTF-16.
 */
function normalize_csv_to_utf8(string $csvData): string
{
    if ($csvData === '') {
        return $csvData;
    }

    // UTF-8 BOM entfernen
    if (strncmp($csvData, "\xEF\xBB\xBF", 3) === 0) {
        $csvData = substr($csvData, 3);
    }

    // UTF-16 BOM erkennen und korrekt konvertieren
    if (strncmp($csvData, "\xFF\xFE", 2) === 0) {
        return mb_convert_encoding(substr($csvData, 2), 'UTF-8', 'UTF-16LE');
    }
    if (strncmp($csvData, "\xFE\xFF", 2) === 0) {
        return mb_convert_encoding(substr($csvData, 2), 'UTF-8', 'UTF-16BE');
    }

    // Bereits valides UTF-8 unverändert übernehmen
    if (preg_match('//u', $csvData) === 1) {
        return $csvData;
    }

    $encoding = mb_detect_encoding($csvData, ['Windows-1252', 'ISO-8859-1'], true) ?: 'Windows-1252';
    return mb_convert_encoding($csvData, 'UTF-8', $encoding);
}
?>
