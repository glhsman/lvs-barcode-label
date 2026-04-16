<?php
require_once 'config.php';

try {
    $sql = file_get_contents('migration.sql');
    // Wir brechen das SQL in einzelne Statements auf, da exec() oft nur eines sauber verarbeitet 
    // oder Probleme mit Delimitern hat.
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($statements as $stmt) {
        if (!empty($stmt)) {
            $pdo->exec($stmt);
        }
    }
    echo "Migration erfolgreich durchgeführt.";
} catch (Exception $e) {
    echo "Fehler bei der Migration: " . $e->getMessage();
}
