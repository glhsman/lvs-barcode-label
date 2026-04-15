<?php
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

// Ausgewählte Datensätze laden
$stmt = $pdo->prepare("
    SELECT dr.id, rv.value, pf.name as field_name
    FROM data_records dr
    JOIN project_fields pf ON pf.project_id = dr.project_id
    LEFT JOIN record_values rv ON rv.record_id = dr.id AND rv.field_id = pf.id
    WHERE dr.project_id = ? AND dr.selected = 1
    ORDER BY dr.position ASC, pf.position ASC
");
$stmt->execute([$projectId]);
$rawData = $stmt->fetchAll();

$selectedRecords = [];
foreach ($rawData as $row) {
    $selectedRecords[$row['id']][$row['field_name']] = $row['value'];
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Druckvorschau - <?= htmlspecialchars($project['name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
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
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-3">Starteinstellungen</h5>
                    <p class="text-secondary small">
                        Wähle das Etikett auf dem Bogen aus, an dem der Druck beginnen soll. 
                        Dies ist nützlich, wenn du einen bereits teilweise bedruckten A4-Bogen verwendest.
                    </p>
                    
                    <div class="alert alert-info py-2 small d-flex align-items-center">
                        <i class="bi bi-info-circle-fill me-2"></i>
                        <span>Aktuell gewählt: <strong>Etikett <span id="startIndexLabel">1</span></strong></span>
                    </div>

                    <div class="mt-4">
                        <button class="btn btn-primary w-100 py-3" onclick="startPrinting()">
                            <i class="bi bi-printer me-2"></i> Jetzt Drucken (PDF)
                        </button>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header border-bottom-0 py-3">
                    <span class="fw-bold"><i class="bi bi-list-check me-2"></i>Druckliste</span>
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
            <div class="preview-container text-center">
                <h5 class="mb-4 text-primary">Visuelle Hilfe: Bogen-Layout</h5>
                <div id="sheetGrid" class="sheet-preview" style="grid-template-columns: repeat(<?= $format['cols'] ?>, 1fr); grid-template-rows: repeat(<?= $format['rows'] ?>, 1fr);">
                    <?php
                    $totalLabels = $format['cols'] * $format['rows'];
                    for ($i = 1; $i <= $totalLabels; $i++) {
                        $col = (($i - 1) % $format['cols']) + 1;
                        $row = floor(($i - 1) / $format['cols']) + 1;
                        echo "<div class='label-cell' data-index='$i' onclick='setStartIndex($i)'>$i</div>";
                    }
                    ?>
                </div>
                <div class="mt-3 text-secondary small">
                    Klicke auf das gewünschte Start-Etikett (Zelle)
                </div>
            </div>
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
    const url = `generate_pdf.php?id=<?= $projectId ?>&start=${startIndex}`;
    window.open(url, '_blank');
}
</script>

</body>
</html>
