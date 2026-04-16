<?php
session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file']) && isset($_POST['project_id'])) {
    $projectId = (int)$_POST['project_id'];
    $file = $_FILES['csv_file']['tmp_name'];

    if (!$file || !is_uploaded_file($file)) {
        die("Fehler: Keine Datei hochgeladen.");
    }

    try {
        $pdo->beginTransaction();

        // 1. Alte Felder löschen (nur die Spaltendefinitionen, da Datensätze in der Session liegen)
        $pdo->prepare("DELETE FROM project_fields WHERE project_id = ?")->execute([$projectId]);

        // 2. Neue CSV einlesen
        $csvData = file_get_contents($file);
        
        // Erkennung der Kodierung (Windows-1252 ist bei CSV aus Excel oft Standard)
        $encoding = mb_detect_encoding($csvData, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
        if ($encoding !== 'UTF-8') {
            $csvData = mb_convert_encoding($csvData, 'UTF-8', $encoding ?: 'Windows-1252');
        }

        // Zeilen trennen
        $lines = explode("\n", str_replace("\r", "", $csvData));
        if (empty($lines)) throw new Exception("Die Datei ist leer.");

        // Header extrahieren
        $headerLine = array_shift($lines);
        $delimiter = strpos($headerLine, ';') !== false ? ';' : ',';
        $header = str_getcsv($headerLine, $delimiter, '"', '');

        if (!$header) throw new Exception("Ungültiges CSV-Format (Header konnte nicht gelesen werden).");

        // 3. Spalten (Fields) neu anlegen
        $fieldIds = [];
        foreach ($header as $index => $colName) {
            $colName = trim($colName);
            if (empty($colName)) $colName = "Spalte " . ($index + 1);
            
            $stmt = $pdo->prepare("INSERT INTO project_fields (project_id, name, position) VALUES (?, ?, ?)");
            $stmt->execute([$projectId, $colName, $index]);
            $fieldIds[$index] = $pdo->lastInsertId();
        }

        // 4. In die Session laden
        $_SESSION["csv_raw_13k_project_{$projectId}"] = file_get_contents($file);
        $_SESSION["csv_selected_{$projectId}"] = []; // Standardmäßig nichts selektiert
        
        $pdo->commit();
        
        // Nach erfolgreichem Reload sofort zurück zum Projekt
        header("Location: project_view.php?id={$projectId}");
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        die("Fehler beim Reload der CSV: " . $e->getMessage());
    }
} else {
    header("Location: index.php");
    exit;
}
