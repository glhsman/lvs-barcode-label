<?php
session_start();
require_once 'config.php';

$projectId = $_GET['id'] ?? null;
if (!$projectId) {
    header("Location: index.php");
    exit;
}

// Projekt-Daten laden
$stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
$stmt->execute([$projectId]);
$project = $stmt->fetch();

if (!$project) {
    die("Projekt nicht gefunden.");
}

// Etikettenformat laden
$stmt = $pdo->prepare("SELECT * FROM label_formats WHERE project_id = ?");
$stmt->execute([$projectId]);
$format = $stmt->fetch();

// Objekte laden für EAN8 Validierung
$stmt = $pdo->prepare("SELECT * FROM label_objects WHERE project_id = ?");
$stmt->execute([$projectId]);
$objects = $stmt->fetchAll();

// Ausgewählte Datensätze laden (aus Session!)
$selectedRecords = [];
if (isset($_SESSION["csv_raw_13k_project_{$projectId}"])) {
    // Projekt-Felder laden
    $stmt = $pdo->prepare("SELECT * FROM project_fields WHERE project_id = ? ORDER BY position ASC");
    $stmt->execute([$projectId]);
    $fields = $stmt->fetchAll();

    $csvData = $_SESSION["csv_raw_13k_project_{$projectId}"];
    $encoding = mb_detect_encoding($csvData, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
    if ($encoding !== 'UTF-8') $csvData = mb_convert_encoding($csvData, 'UTF-8', $encoding ?: 'Windows-1252');
    $lines = explode("\n", str_replace("\r", "", $csvData));
    $headerLine = array_shift($lines);
    $delimiter = strpos($headerLine, ';') !== false ? ';' : ',';
    
    foreach ($lines as $idx => $line) {
        $selected = $_SESSION["csv_selected_{$projectId}"][$idx] ?? true;
        if (!$selected) continue;
        
        $line = trim($line);
        if (!$line) continue;
        $row = str_getcsv($line, $delimiter, '"', '');
        
        $selectedRecords[$idx] = [];
        foreach ($fields as $colIdx => $field) {
            $selectedRecords[$idx][$field['name']] = $row[$colIdx] ?? '';
        }
    }
}

$ean8Errors = 0;
foreach ($selectedRecords as $record) {
    foreach ($objects as $obj) {
        $p = $obj['properties'];
        if (is_string($p)) $p = json_decode($p, true) ?: [];
        $bType = $p['barcode_type'] ?? '';
        if ($bType === 'ean8' || $bType === 'ean13') {
             $txt = $p['content'] ?? '';
             foreach ($record as $k => $v) {
                 $txt = str_ireplace("[~$k~]", (string)$v, $txt);
             }
             $valid = ($bType === 'ean8') ? preg_match('/^\d{8}$/', $txt) : preg_match('/^\d{12,13}$/', $txt);
             if (!$valid) {
                 $ean8Errors++;
                 break; // Ein Fehler pro Datensatz reicht
             }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Druckvorschau - <?= htmlspecialchars($project['name']) ?></title>
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .sheet-preview {
            background: white;
            border: 1px solid #ddd;
            width: 100%;
            max-width: 400px;
            aspect-ratio: 210 / 297; /* A4 Ratio */
            margin: 0 auto;
            display: grid;
            padding: 10px;
            gap: 2px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            position: relative;
        }
        .label-cell {
            border: 1px dashed #ccc;
            background: #f9f9f9;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            color: #999;
            transition: all 0.2s;
        }
        .label-cell:hover {
            background: #eef2ff;
            border-color: var(--accent);
        }
        .label-cell.start {
            background: var(--accent);
            color: white;
            border-style: solid;
            border-color: var(--accent);
            box-shadow: inset 0 0 10px rgba(0,0,0,0.2);
        }
        .label-cell.skipped {
            background: #eee;
            opacity: 0.5;
        }
        .preview-container {
            background: rgba(15, 23, 42, 0.5);
            padding: 40px;
            border-radius: 20px;
            border: 1px solid var(--glass-border);
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark mb-4">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="project_view.php?id=<?= $projectId ?>">
            <i class="bi bi-arrow-left me-2"></i>
            <span>ZURÜCK ZUM PROJEKT</span>
        </a>
    </div>
</nav>

<div class="container">
    <div class="row align-items-start">
        <div class="col-lg-6">
            <h2 class="mb-4">Druckkonfiguration</h2>
            
            <?php if ($ean8Errors > 0): ?>
                <div class="alert alert-warning border-warning bg-warning bg-opacity-10 text-dark rounded-4 mb-4 shadow-sm">
                    <div class="d-flex">
                        <div class="me-3">
                            <i class="bi bi-exclamation-triangle-fill fs-2 text-warning"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-1">Barcode Validierungs-Warnung</h5>
                            <p class="mb-0 small"><strong><?= $ean8Errors ?></strong> der gewählten Datensätze enthalten Daten, die nicht den Anforderungen für EAN8 (8 Ziffern) oder EAN13 (12-13 Ziffern) entsprechen. Diese werden im Druck als Fehler markiert.</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ((int)$format['cols'] === 1 && (int)$format['rows'] === 1): ?>
                <div class="card mb-4 border-info">
                    <div class="card-body d-flex align-items-center">
                        <div class="bg-info bg-opacity-10 p-3 rounded-circle me-3">
                            <i class="bi bi-printer-fill fs-3 text-info"></i>
                        </div>
                        <div>
                            <h5 class="card-title mb-1">Rollen-Drucker (Endlos)</h5>
                            <p class="text-secondary small mb-0">Einzel-Etikettendruck (z.B. Brother P-Touch).<br>Die Auswahl einer Startposition entfällt.</p>
                        </div>
                        <span id="startIndexLabel" style="display:none;">1</span>
                    </div>
                </div>
                <div class="card mb-4" style="display: none;">
            <?php else: ?>
                <div class="card mb-4">
            <?php endif; ?>
                <div class="card-body">
                    <h5 class="card-title mb-3">Starteinstellungen</h5>
                    <p class="text-secondary small">
                        Wähle das Etikett auf dem Bogen aus, an dem der Druck beginnen soll. 
                        Dies ist nützlich, wenn du einen bereits teilweise bedruckten A4-Bogen verwendest.
                    </p>
                    
                    <div class="alert alert-info py-2 small d-flex align-items-center">
                        <i class="bi bi-info-circle-fill me-2"></i>
                        <span>Papier-Startposition: <strong>Aufkleber Nr. <span id="startIndexLabel">1</span></strong></span>
                    </div>

                    <div class="mt-4">
                        <button class="btn btn-primary w-100 py-3" onclick="startPrinting()">
                            <i class="bi bi-printer me-2"></i> Jetzt Drucken (PDF)
                        </button>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header border-bottom-0 py-3 d-flex justify-content-between align-items-center">
                    <span class="fw-bold"><i class="bi bi-list-check me-2"></i>Zu druckende Datensätze</span>
                    <span class="badge bg-primary rounded-pill"><?= count($selectedRecords) ?></span>
                </div>
                <div class="card-body p-0" style="max-height: 40vh; overflow-y: auto;">
                    <ul class="list-group list-group-flush bg-transparent">
                        <?php foreach ($selectedRecords as $id => $fields): ?>
                            <li class="list-group-item bg-transparent text-secondary border-secondary-subtle small px-3">
                                <?= htmlspecialchars(implode(' | ', array_slice($fields, 0, 3))) ?>
                            </li>
                        <?php endforeach; ?>
                        <?php if (empty($selectedRecords)): ?>
                            <li class="list-group-item bg-transparent text-warning text-center">Keine Datensätze ausgewählt!</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <?php if ((int)$format['cols'] === 1 && (int)$format['rows'] === 1): ?>
                <div class="preview-container text-center py-5 d-flex flex-column align-items-center justify-content-center" style="min-height: 100%;">
                    <i class="bi bi-receipt mb-3 text-secondary" style="font-size: 5rem; opacity: 0.5;"></i>
                    <h5 class="text-primary mb-2">Ansicht für Rollen-Layout</h5>
                    <p class="text-secondary small w-75 mx-auto">
                        Dein Layout generiert pro PDF-Seite genau ein Etikett.<br>
                        Der Drucker zieht das Band automatisch so weit ein, wie deine hinterlegte mm-Länge dieses Projekts es verlangt.
                    </p>
                </div>
            <?php else: ?>
                <div class="preview-container text-center">
                    <h5 class="mb-4 text-primary">Visuelle Hilfe: Bogen-Layout</h5>
                    <div id="sheetGrid" class="sheet-preview" style="grid-template-columns: repeat(<?= $format['cols'] ?>, 1fr); grid-template-rows: repeat(<?= $format['rows'] ?>, 1fr);">
                        <?php
                        $totalLabels = $format['cols'] * $format['rows'];
                        for ($i = 1; $i <= $totalLabels; $i++) {
                            echo "<div class='label-cell' data-index='$i' onclick='setStartIndex($i)'>$i</div>";
                        }
                        ?>
                    </div>
                    <div class="mt-3 text-secondary small">
                        Klicke auf das gewünschte Start-Etikett (Zelle)
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
let startIndex = 1;

function setStartIndex(idx) {
    startIndex = idx;
    document.getElementById('startIndexLabel').innerText = idx;
    
    // UI Update
    document.querySelectorAll('.label-cell').forEach(el => {
        const elIdx = parseInt(el.getAttribute('data-index'));
        el.classList.remove('start', 'skipped');
        if (elIdx === idx) el.classList.add('start');
        else if (elIdx < idx) el.classList.add('skipped');
    });
}

// Initialisierung
setStartIndex(1);

function startPrinting() {
    const urlParams = new URLSearchParams(window.location.search);
    const cal = urlParams.get('cal') || 0;
    const url = `generate_pdf.php?id=<?= $projectId ?>&start=${startIndex}&cal=${cal}`;
    window.open(url, '_blank');
}
</script>

</body>
</html>
