<?php
session_start();
require_once 'config.php';

/**
 * import_csv.php
 * Verarbeitet den Upload einer CSV-Datei und speichert die Daten in die bestehende Struktur.
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Prüfen ob ein POST-Body vorhanden ist (z.B. post_max_size überschritten)
    if (!isset($_FILES['csv_file']) && !isset($_POST['project_name'])) {
        die("Fehler: Formular-Daten unvollständig. Möglicherweise ist die Datei größer als das erlaubte Limit (post_max_size / upload_max_filesize).");
    }

    $hasCsv = isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] !== UPLOAD_ERR_NO_FILE;

    if ($hasCsv) {
        $uploadError = $_FILES['csv_file']['error'];
        if ($uploadError !== UPLOAD_ERR_OK) {
            $maxSize = ini_get('upload_max_filesize');
            switch ($uploadError) {
                case UPLOAD_ERR_INI_SIZE:
                    die("Fehler: Die Datei ist zu groß. Das Server-Limit liegt bei $maxSize.");
                case UPLOAD_ERR_FORM_SIZE:
                    die("Fehler: Die Datei überschreitet das MAX_FILE_SIZE Limit im Formular.");
                case UPLOAD_ERR_PARTIAL:
                    die("Fehler: Der Upload wurde nur teilweise übertragen (Verbindungsabbruch?).");
                default:
                    die("Fehler beim Upload (Fehlercode: $uploadError).");
            }
        }
        $file = $_FILES['csv_file']['tmp_name'];
        if (!$file || !is_uploaded_file($file)) {
            die("Fehler: Die hochgeladene Datei konnte nicht verarbeitet werden.");
        }
    }

    $projectName = $_POST['project_name'] ?? 'Unbenanntes Projekt';

    try {
        $pdo->beginTransaction();

        // 1. Projekt erstellen
        $locationId = (int)($_POST['location_id'] ?? 1);
        $csvFilename = $hasCsv ? basename($_FILES['csv_file']['name']) : null;
        $stmt = $pdo->prepare("INSERT INTO projects (location_id, name, description, csv_filename, created_at, modified_at) VALUES (?, ?, 'Erstellt über Web-Interface', ?, NOW(), NOW())");
        $stmt->execute([$locationId, $projectName, $csvFilename]);
        $projectId = $pdo->lastInsertId();

        $recordCount = 0;

        if ($hasCsv) {
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
            $header = str_getcsv($headerLine, $delimiter, '"', '');

            if (!$header) throw new Exception("Ungültiges CSV-Format (Header konnte nicht gelesen werden).");

            // 3. Spalten (Fields) anlegen
            foreach ($header as $index => $colName) {
                $colName = trim($colName);
                if (empty($colName)) $colName = "Spalte " . ($index + 1);
                $stmt = $pdo->prepare("INSERT INTO project_fields (project_id, name, position) VALUES (?, ?, ?)");
                $stmt->execute([$projectId, $colName, $index]);
            }

            // 4. Datensätze in die Session laden
            $_SESSION["csv_raw_13k_project_{$projectId}"] = file_get_contents($file);
            $_SESSION["csv_selected_{$projectId}"] = [];
            $recordCount = count(array_filter($lines, 'trim'));
        }

        // 5. Standard-Etikettenformat anlegen
        $stmt = $pdo->prepare("INSERT INTO label_formats (project_id, width_mm, height_mm, margin_top_mm, margin_bottom_mm, margin_left_mm, margin_right_mm, `cols`, `rows`)
                               VALUES (?, 100.0, 50.0, 2.0, 2.0, 2.0, 2.0, 1, 1)");
        $stmt->execute([$projectId]);

        $pdo->commit();
        header("Location: index.php?location_id=" . $locationId . "&success=1&count=" . $recordCount);
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
