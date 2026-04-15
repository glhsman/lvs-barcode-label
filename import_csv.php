<?php
require_once 'config.php';

/**
 * import_csv.php
 * Verarbeitet den Upload einer CSV-Datei und speichert die Daten in die bestehende Struktur.
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $projectName = $_POST['project_name'] ?? 'Unbenanntes Projekt';
    $file = $_FILES['csv_file']['tmp_name'];

    if (!$file || !is_uploaded_file($file)) {
        die("Fehler: Keine Datei hochgeladen.");
    }

    try {
        $pdo->beginTransaction();

        // 1. Projekt erstellen
        $stmt = $pdo->prepare("INSERT INTO projects (name, description, created_at, modified_at) VALUES (?, 'Importiert aus Web-Interface', NOW(), NOW())");
        $stmt->execute([$projectName]);
        $projectId = $pdo->lastInsertId();

        // 2. CSV einlesen und Kodierung behandeln
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
        $header = str_getcsv($headerLine, $delimiter);

        if (!$header) throw new Exception("Ungültiges CSV-Format (Header konnte nicht gelesen werden).");

        // 3. Spalten (Fields) anlegen
        $fieldIds = [];
        foreach ($header as $index => $colName) {
            $colName = trim($colName);
            if (empty($colName)) $colName = "Spalte " . ($index + 1);
            
            $stmt = $pdo->prepare("INSERT INTO project_fields (project_id, name, position) VALUES (?, ?, ?)");
            $stmt->execute([$projectId, $colName, $index]);
            $fieldIds[$index] = $pdo->lastInsertId();
        }

        // 4. Datensätze importieren
        $recordCount = 0;
        foreach ($lines as $lineIndex => $line) {
            $line = trim($line);
            if (empty($line)) continue;

            $row = str_getcsv($line, $delimiter);
            if (empty($row)) continue;

            // Record anlegen
            $stmt = $pdo->prepare("INSERT INTO data_records (project_id, position) VALUES (?, ?)");
            $stmt->execute([$projectId, $recordCount]);
            $recordId = $pdo->lastInsertId();

            // Werte speichern
            foreach ($row as $colIndex => $value) {
                if (isset($fieldIds[$colIndex])) {
                    $stmt = $pdo->prepare("INSERT INTO record_values (record_id, field_id, value) VALUES (?, ?, ?)");
                    $stmt->execute([$recordId, $fieldIds[$colIndex], trim($value)]);
                }
            }
            $recordCount++;
        }
        
        // 5. Standard-Etikettenformat anlegen
        $stmt = $pdo->prepare("INSERT INTO label_formats (project_id, width_mm, height_mm, margin_top_mm, margin_bottom_mm, margin_left_mm, margin_right_mm, cols, rows) 
                               VALUES (?, 100.0, 50.0, 2.0, 2.0, 2.0, 2.0, 1, 1)");
        $stmt->execute([$projectId]);

        $pdo->commit();
        header("Location: index.php?success=1&count=" . $recordCount);
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        die("Fehler beim Import: " . $e->getMessage());
    }
} else {
    header("Location: index.php");
    exit;
}
