<?php
session_start();
require_once 'config.php';
$projectId = $_GET['id'] ?? null;
if (!$projectId) { header("Location: index.php"); exit; }

$stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
$stmt->execute([$projectId]);
$project = $stmt->fetch();
if (!$project) die("Projekt nicht gefunden.");

$format = $pdo->prepare("SELECT * FROM label_formats WHERE project_id = ?");
$format->execute([$projectId]);
$format = $format->fetch();

$objects = $pdo->prepare("SELECT * FROM label_objects WHERE project_id = ? ORDER BY z_order ASC");
$objects->execute([$projectId]);
$labelObjects = $objects->fetchAll();

$fields = $pdo->prepare("SELECT * FROM project_fields WHERE project_id = ? ORDER BY position ASC");
$fields->execute([$projectId]);
$fields = $fields->fetchAll();

$records = [];
$records = [];
if (isset($_SESSION["csv_raw_13k_project_{$projectId}"])) {
    $csvData = $_SESSION["csv_raw_13k_project_{$projectId}"];
    $encoding = mb_detect_encoding($csvData, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
    if ($encoding !== 'UTF-8') $csvData = mb_convert_encoding($csvData, 'UTF-8', $encoding ?: 'Windows-1252');
    $lines = explode("\n", str_replace("\r", "", $csvData));
    $headerLine = array_shift($lines);
    $delimiter = strpos($headerLine, ';') !== false ? ';' : ',';
    
    foreach ($lines as $idx => $line) {
        $line = trim($line);
        if (!$line) continue;
        $row = str_getcsv($line, $delimiter, '"', '');
        $selected = $_SESSION["csv_selected_{$projectId}"][$idx] ?? true;
        
        $values = [];
        foreach ($fields as $colIdx => $field) {
             $values[$field['id']] = $row[$colIdx] ?? '';
        }
        $records[$idx] = ['selected' => $selected, 'values' => $values];
    }
}
$globalTemplates = $pdo->query("SELECT * FROM global_label_templates ORDER BY name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($project['name']) ?> - Details</title>
    <link rel="icon" type="image/x-icon" href="barcode_green.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <style>
        body { font-family: 'Outfit', sans-serif; background: #0f172a; color: #f8fafc; overflow-x: hidden; }
        .glass-card { background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.1); border-radius: 16px; }
        #designer-canvas { background: #fff; background-image: radial-gradient(#ddd 1px, transparent 1px); background-size: 10px 10px; position: relative; margin: auto; box-shadow: 0 0 40px rgba(0,0,0,0.5); }
        .designer-object { position: absolute; border: 1px dashed #bbb; cursor: move; background: rgba(255,255,255,0.9); color: #000; display: flex; align-items: center; justify-content: center; overflow: visible !important; }
        .designer-object:hover { border-color: #3b82f6; z-index: 1000 !important; }
        .designer-object.selected { border: 2px solid #3b82f6; z-index: 1001 !important; }
        .obj-controls { position: absolute; top: 0; right: 0; display: none; background: rgba(0,0,0,0.6); padding: 3px; border-radius: 0 0 0 8px; z-index: 2000; }
        .designer-object:hover .obj-controls { display: flex; }
        .obj-btn { width: 22px; height: 22px; border: none; border-radius: 4px; color: white; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 11px; margin-left: 2px; }
        .sticky-top-table { position: sticky; top: 0; background: #1e293b; z-index: 10; border-bottom: 2px solid #3b82f6; }
        .nav-pills .nav-link { color: #94a3b8; border-radius: 12px; transition: all 0.3s; padding: 10px 25px; font-weight: 500; font-size: 0.9rem; }
        .nav-pills .nav-link.active { background: #3b82f6; color: white; box-shadow: 0 4px 15px rgba(59, 130, 246, 0.4); }
        .form-label-sm { font-size: 0.72rem; color: #94a3b8; margin-bottom: 1px; display: block; }
        .badge-placeholder { cursor: pointer; transition: all 0.2s; border: 1px solid rgba(59, 130, 246, 0.3); background: rgba(59, 130, 246, 0.1); color: #60a5fa; }
        .badge-placeholder:hover { background: #3b82f6; color: white; transform: pady(-1px); }
    </style>
</head>
<body>

<!-- Header -->
<nav class="navbar navbar-expand-lg navbar-dark mb-4 py-3">
    <div class="container container-fluid">
        <div class="d-flex align-items-center">
            <a href="index.php" class="btn btn-outline-light btn-sm me-3 border-secondary"><i class="bi bi-arrow-left me-1"></i> Projektwahl</a>
            <a class="navbar-brand fw-bold d-flex align-items-center m-0" href="index.php">
                <div class="d-flex align-items-center justify-content-center bg-primary bg-gradient rounded shadow-sm me-2" style="width: 32px; height: 32px;">
                    <i class="bi bi-upc-scan text-white" style="font-size: 1.1rem;"></i>
                </div>
                <span>BARCODE SYSTEM</span>
            </a>
        </div>
        <div class="ms-auto d-flex align-items-center">
            <div class="text-end me-4 d-none d-md-block">
                <div class="small text-muted fw-bold" style="font-size: 0.65rem; letter-spacing: 1px; text-transform: uppercase;">Aktiv</div>
                <div class="text-primary fw-bold" style="font-size: 0.9rem;"><?= htmlspecialchars($project['name']) ?></div>
            </div>
            <button onclick="saveDesignerAndPrint()" class="btn btn-success btn-sm px-4 shadow-sm fw-bold"><i class="bi bi-printer me-2"></i>Drucken</button>
        </div>
    </div>
</nav>

<div class="container-fluid px-4">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3">
            <div class="glass-card p-4 mb-4">
                <div class="fw-bold mb-3 small" style="letter-spacing: 1px; color: #3b82f6;">FORMAT & VORLAGEN</div>
                <label class="form-label-sm">Vorlage laden</label>
                <select id="templateSelect" class="form-select form-select-sm bg-dark text-light border-secondary mb-3" onchange="applyTemplate(this)">
                    <option value="">-- Vorlage wählen --</option>
                    <?php foreach($globalTemplates as $t): ?>
                        <option value='<?= json_encode($t)?>' <?= ($format['template_id'] == $t['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($t['name'])?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <hr class="border-secondary opacity-25">
                <form id="formatForm" autocomplete="off">
                    <input type="hidden" name="project_id" value="<?= $projectId ?>">
                    <input type="hidden" name="template_id_<?= $projectId ?>" value="<?= $format['template_id'] ?>">
                    <div class="row g-2 mb-2">
                        <div class="col-6"><label class="form-label-sm">Breite</label><input type="number" step="0.1" name="width_mm_<?= $projectId ?>" class="form-control form-control-sm bg-dark text-light border-secondary px-2" value="<?= (float)$format['width_mm'] ?>"></div>
                        <div class="col-6"><label class="form-label-sm">Höhe</label><input type="number" step="0.1" name="height_mm_<?= $projectId ?>" class="form-control form-control-sm bg-dark text-light border-secondary px-2" value="<?= (float)$format['height_mm'] ?>"></div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6"><label class="form-label-sm">Spalten</label><input type="number" name="cols_<?= $projectId ?>" class="form-control form-control-sm bg-dark text-light border-secondary px-2" value="<?= (int)$format['cols'] ?>"></div>
                        <div class="col-6"><label class="form-label-sm">Zeilen</label><input type="number" name="rows_<?= $projectId ?>" class="form-control form-control-sm bg-dark text-light border-secondary px-2" value="<?= (int)$format['rows'] ?>"></div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6"><label class="form-label-sm">H-Abst.</label><input type="number" step="0.1" name="col_gap_mm_<?= $projectId ?>" class="form-control form-control-sm bg-dark text-light border-secondary px-2" value="<?= (float)$format['col_gap_mm'] ?>"></div>
                        <div class="col-6"><label class="form-label-sm">V-Abst.</label><input type="number" step="0.1" name="row_gap_mm_<?= $projectId ?>" class="form-control form-control-sm bg-dark text-light border-secondary px-2" value="<?= (float)$format['row_gap_mm'] ?>"></div>
                    </div>
                    <div class="form-label-sm mt-3 mb-1">Ränder (Ob / Un / Li / Re)</div>
                    <div class="row g-1 mb-4">
                        <div class="col-3"><input type="number" step="0.1" name="margin_top_mm_<?= $projectId ?>" class="form-control form-control-sm bg-dark text-light border-secondary px-1" value="<?= (float)$format['margin_top_mm'] ?>"></div>
                        <div class="col-3"><input type="number" step="0.1" name="margin_bottom_mm_<?= $projectId ?>" class="form-control form-control-sm bg-dark text-light border-secondary px-1" value="<?= (float)$format['margin_bottom_mm'] ?>"></div>
                        <div class="col-3"><input type="number" step="0.1" name="margin_left_mm_<?= $projectId ?>" class="form-control form-control-sm bg-dark text-light border-secondary px-1" value="<?= (float)$format['margin_left_mm'] ?>"></div>
                        <div class="col-3"><input type="number" step="0.1" name="margin_right_mm_<?= $projectId ?>" class="form-control form-control-sm bg-dark text-light border-secondary px-1" value="<?= (float)$format['margin_right_mm'] ?>"></div>
                    </div>
                </form>
                <button class="btn btn-primary btn-sm w-100" onclick="saveFormat()"><i class="bi bi-check-circle me-1"></i> Template zuweisen</button>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-md-9">
            <!-- Navigation -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <ul class="nav nav-pills glass-card p-1 m-0" style="width:fit-content;" id="pills-tab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="pills-data-tab" data-bs-toggle="pill" data-bs-target="#pills-data" type="button" role="tab">
                            <i class="bi bi-grid-3x3-gap me-2"></i>DATEN 
                            <span class="badge bg-light text-dark ms-2 rounded-pill"><?= count($records) ?> <small class="text-muted" style="font-size: 0.6rem;">Zeilen</small></span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="pills-designer-tab" data-bs-toggle="pill" data-bs-target="#pills-designer" type="button" role="tab"><i class="bi bi-palette-fill me-2"></i>DESIGNER</button>
                    </li>
                </ul>
                <button class="btn btn-outline-warning text-danger" style="border-color: #a3e635; color: #ef4444 !important; background: transparent; padding: 6px 20px;" onclick="document.getElementById('csvUploadInput').click()">
                    Reload csv
                </button>
                <form id="csvReloadForm" style="display:none;" action="reload_csv.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="project_id" value="<?= $projectId ?>">
                    <input type="file" id="csvUploadInput" name="csv_file" accept=".csv" onchange="document.getElementById('csvReloadForm').submit()">
                </form>
            </div>

            <!-- Tab Content -->
            <div class="tab-content" id="pills-tabContent">
                <div class="tab-pane fade show active" id="pills-data" role="tabpanel">
                    <div class="glass-card shadow-lg p-0" style="overflow:hidden;">
                        <div style="max-height: 72vh; overflow: auto;">
                            <table class="table table-dark table-hover table-sm mb-0">
                                <thead class="sticky-top-table">
                                    <tr>
                                        <th width="40" class="ps-3 text-center"><input type="checkbox" class="form-check-input" id="checkAllRecords" onchange="toggleAllRecords(this.checked)" title="Alle an/abwählen"></th>
                                        <?php foreach($fields as $f): ?><th><?= htmlspecialchars($f['name']) ?></th><?php endforeach; ?>
                                    </tr>
                                    <tr>
                                        <th></th>
                                        <?php foreach($fields as $idx => $f): ?>
                                            <th><input type="text" class="form-control form-control-sm bg-dark text-light border-secondary column-filter" data-col="<?= $idx + 1 ?>" placeholder="Filter..." oninput="filterTable()"></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody id="data-table-body">
                                    <?php foreach($records as $rid => $rec): ?>
                                    <tr>
                                        <td class="ps-3 text-center"><input type="checkbox" class="form-check-input record-select-checkbox" data-id="<?= $rid ?>" onchange="updateSelection(<?= $rid ?>, this.checked)" <?= $rec['selected']?'checked':'' ?>></td>
                                        <?php foreach($fields as $f): ?><td><?= htmlspecialchars($rec['values'][$f['id']]??'') ?></td><?php endforeach; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="pills-designer" role="tabpanel">
                    <div class="glass-card shadow-lg">
                        <div class="card-header p-3 d-flex justify-content-between align-items-center border-bottom border-secondary">
                            <span class="fw-bold small" style="letter-spacing: 1px;"><i class="bi bi-pencil-square me-2 text-primary"></i>VISUAL EDITOR</span>
                            <div>
                                <div class="btn-group btn-group-sm me-3 border border-secondary rounded overflow-hidden">
                                    <button class="btn btn-dark" onclick="addObject('text')"><i class="bi bi-plus me-1"></i> Text</button>
                                    <button class="btn btn-dark" onclick="addObject('barcode')"><i class="bi bi-plus me-1"></i> Barcode</button>
                                </div>
                                <button class="btn btn-sm btn-outline-info px-3 me-2 border-info" onclick="openPreview()"><i class="bi bi-eye me-1"></i> Vorschau</button>
                                <button class="btn btn-sm btn-primary px-3 shadow-sm" onclick="saveDesigner()"><i class="bi bi-cloud-check me-1"></i> Design speichern</button>
                            </div>
                        </div>
                        <div class="card-body p-5 bg-slate-900 border-0 text-center position-relative d-flex justify-content-center align-items-center" style="min-height: 600px; overflow: auto;">
                            <div id="zoom-container" style="transform-origin: center center; padding: 50px;">
                                <div style="position: relative;">
                                <!-- Lineale -->
                                <div id="ruler-x" style="position:absolute; top:-20px; left:0; width:<?= $format['width_mm']*3.78?>px; height:20px; border-bottom:1px solid #475569; font-size:9px; color:#94a3b8; font-family:monospace; text-align:left;"></div>
                                <div id="ruler-y" style="position:absolute; left:-30px; top:0; height:<?= $format['height_mm']*3.78?>px; width:30px; border-right:1px solid #475569; font-size:9px; color:#94a3b8; font-family:monospace;"></div>
                                
                                <div id="designer-canvas" style="width:<?= $format['width_mm']*3.78?>px; height:<?= $format['height_mm']*3.78?>px;"></div>
                                </div>
                            </div>
                            <div style="position:absolute; bottom:10px; right:15px; font-size:10px; color:rgba(255,255,255,0.2);">UI-V2.0.1-STABLE</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="objectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content bg-dark text-light border-secondary shadow-lg" style="border-radius: 20px; border: 1px solid rgba(255,255,255,0.1) !important;">
        <div class="modal-header border-bottom-0 pb-0">
            <h6 class="modal-title fw-bold">EIGENSCHAFTEN</h6>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
            <div class="mb-4">
                <label class="form-label-sm mb-2">Inhalt / Platzhalter</label>
                <textarea class="form-control bg-dark text-light border-secondary mb-3" id="objContent" rows="3"></textarea>
                <div class="small text-muted mb-2">Schnell-Einfügen (Felder):</div>
                <div class="d-flex flex-wrap gap-1">
                    <?php foreach($fields as $f): ?>
                        <span class="badge badge-placeholder rounded-pill px-2 py-1" onclick="insertPlaceholder('<?= htmlspecialchars($f['name']) ?>')">[~<?= htmlspecialchars($f['name']) ?>~]</span>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="row g-2">
                <div id="fontSizeGroup" class="col-6"><label class="form-label-sm">Schriftgröße (pt)</label><input type="number" class="form-control bg-dark text-light border-secondary" id="objFontSize"></div>
                <div id="barcodeTypeGroup" class="col-6"><label class="form-label-sm">Barcode Typ</label><select class="form-select bg-dark text-light border-secondary" id="objBarcodeType"><option value="code128">Code 128</option><option value="ean13">EAN 13</option><option value="qr">QR Code</option></select></div>
            </div>
            <div class="row g-2 mt-2">
                <div class="col-6"><label class="form-label-sm">Breite der Box (mm)</label><input type="number" step="0.5" class="form-control bg-dark text-light border-secondary" id="objWidth"></div>
                <div class="col-6"><label class="form-label-sm">Höhe der Box (mm)</label><input type="number" step="0.5" class="form-control bg-dark text-light border-secondary" id="objHeight"></div>
            </div>
        </div>
        <div class="modal-footer border-top-0 pt-0">
            <button type="button" class="btn btn-link btn-sm text-secondary text-decoration-none" data-bs-dismiss="modal">Abbrechen</button>
            <button type="button" class="btn btn-primary btn-sm px-4 rounded-pill" onclick="applyObjectProperties()">Änderungen übernehmen</button>
        </div>
    </div></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bwip-js/dist/bwip-js-min.js"></script>
<script>
// Lineale (Rulers) rendern
const pxPerMm = 3.78;
const formatW = <?= (float)$format['width_mm'] ?>;
const formatH = <?= (float)$format['height_mm'] ?>;

function updateDesignerZoom() {
    const pId = <?= $projectId ?>;
    const fw = parseFloat(document.querySelector(`[name="width_mm_${pId}"]`).value) || 10;
    const fh = parseFloat(document.querySelector(`[name="height_mm_${pId}"]`).value) || 10;
    const canv = document.getElementById('designer-canvas');
    const rx = document.getElementById('ruler-x');
    const ry = document.getElementById('ruler-y');
    
    // Canvas Größe live anpassen
    canv.style.width = (fw * pxPerMm) + 'px';
    canv.style.height = (fh * pxPerMm) + 'px';
    rx.style.width = (fw * pxPerMm) + 'px';
    ry.style.height = (fh * pxPerMm) + 'px';

    // Rulers neu zeichnen
    renderRulers(fw, fh);

    // Zoom-Berechnung
    const targetAreaW = 850; 
    const targetAreaH = 450;
    let scaleW = targetAreaW / (fw * pxPerMm);
    let scaleH = targetAreaH / (fh * pxPerMm);
    let newZoom = Math.min(scaleW, scaleH);
    if (newZoom > 5.0) newZoom = 5.0;
    if (newZoom < 0.2) newZoom = 0.2;
    
    window.zoomLevel = newZoom; // Global verfügbar machen für Drag&Drop
    document.getElementById('zoom-container').style.transform = `scale(${newZoom})`;
}

function renderRulers(fw, fh) {
    const rx = document.getElementById('ruler-x');
    const ry = document.getElementById('ruler-y');
    rx.innerHTML = '';
    ry.innerHTML = '';
    
    for(let i=0; i<=fw; i+=10) {
        rx.innerHTML += `<div style="position:absolute; left:${i*pxPerMm}px; bottom:2px; transform:translateX(2px);">${i}</div>
                         <div style="position:absolute; left:${i*pxPerMm}px; bottom:0; height:12px; border-left:1px solid #475569;"></div>`;
    }
    for(let i=5; i<=fw; i+=10) rx.innerHTML += `<div style="position:absolute; left:${i*pxPerMm}px; bottom:0; height:5px; border-left:1px solid #475569;"></div>`;
    
    for(let i=0; i<=fh; i+=10) {
        ry.innerHTML += `<div style="position:absolute; top:${i*pxPerMm}px; right:12px; transform:translateY(-50%);">${i}</div>
                         <div style="position:absolute; top:${i*pxPerMm}px; right:0; width:10px; border-top:1px solid #475569;"></div>`;
    }
    for(let i=5; i<=fh; i+=10) ry.innerHTML += `<div style="position:absolute; top:${i*pxPerMm}px; right:0; width:5px; border-top:1px solid #475569;"></div>`;
}

// Initialer Aufruf zur Korrektur von Browser-Cache-Fehlern
window.addEventListener('DOMContentLoaded', () => {
    // Sicherstellen, dass die PHP-Werte wirklich in den Feldern stehen (gegen Browser-Persistence)
    const pId = <?= $projectId ?>;
    const f = document.getElementById('formatForm');
    f.querySelector(`[name="width_mm_${pId}"]`).value = "<?= (float)$format['width_mm'] ?>";
    f.querySelector(`[name="height_mm_${pId}"]`).value = "<?= (float)$format['height_mm'] ?>";
    f.querySelector(`[name="cols_${pId}"]`).value = "<?= (int)$format['cols'] ?>";
    f.querySelector(`[name="rows_${pId}"]`).value = "<?= (int)$format['rows'] ?>";
    f.querySelector(`[name="col_gap_mm_${pId}"]`).value = "<?= (float)$format['col_gap_mm'] ?>";
    f.querySelector(`[name="row_gap_mm_${pId}"]`).value = "<?= (float)$format['row_gap_mm'] ?>";

    updateDesignerZoom();
});

let labelObjects = <?= json_encode($labelObjects) ?>;
labelObjects = labelObjects.map(o => { if(typeof o.properties==='string') try { o.properties=JSON.parse(o.properties); } catch(e){ o.properties={}; } return o; });
const PX_PER_MM = 3.78;
let selectedIdx = null;

document.addEventListener('DOMContentLoaded', () => {
    const hash = window.location.hash;
    if (hash) { 
        const tCol = document.querySelector(`button[data-bs-target="${hash}"]`); 
        if(tCol) bootstrap.Tab.getOrCreateInstance(tCol).show(); 
    }
    document.querySelectorAll('button[data-bs-toggle="pill"]').forEach(tab => {
        tab.addEventListener('shown.bs.tab', (e) => { 
            const t = e.target.getAttribute('data-bs-target');
            history.replaceState(null, null, t);
            if(t==='#pills-designer') renderObjects();
        });
    });
    renderObjects();
});

function insertPlaceholder(n) { const t = document.getElementById('objContent'); const s = t.selectionStart; t.value = t.value.substring(0, s) + `[~${n}~]` + t.value.substring(t.selectionEnd); t.focus(); }

function updateSelection(recordId, isSelected) {
    const fd = new FormData();
    fd.append('record_id', recordId);
    fd.append('project_id', <?= $projectId ?>);
    fd.append('selected', isSelected ? 1 : 0);
    fetch('api_update_selection.php', { method: 'POST', body: fd });
}

let filterTimeout;
function filterTable() {
    clearTimeout(filterTimeout);
    filterTimeout = setTimeout(() => {
        const filters = Array.from(document.querySelectorAll('.column-filter'))
            .filter(i => i.value.trim() !== '')
            .map(input => ({
                col: parseInt(input.getAttribute('data-col')),
                val: input.value.toLowerCase()
            }));
        
        const rows = document.getElementById('data-table-body').rows;
        const rowCount = rows.length;

        if (filters.length === 0) {
            for (let i = 0; i < rowCount; i++) rows[i].style.display = '';
            return;
        }
        
        for (let i = 0; i < rowCount; i++) {
            const cells = rows[i].cells;
            let showRow = true;
            for (let f = 0; f < filters.length; f++) {
                const filter = filters[f];
                // textContent ist deutlich schneller als innerText, da es kein Layout-Reflow erzwingt
                if (!cells[filter.col].textContent.toLowerCase().includes(filter.val)) {
                    showRow = false;
                    break;
                }
            }
            rows[i].style.display = showRow ? '' : 'none';
        }
    }, 300); // 300ms Verzögerung abwarten
}

function renderObjects() {
    const canv = document.getElementById('designer-canvas');
    if(!canv) return;
    canv.innerHTML = '';
    labelObjects.forEach((obj, idx) => {
        const div = document.createElement('div');
        div.className = 'designer-object ' + (selectedIdx === idx ? 'selected' : '');
        div.style.cssText = `left:${obj.x_mm*PX_PER_MM}px; top:${obj.y_mm*PX_PER_MM}px; width:${obj.width_mm*PX_PER_MM}px; height:${obj.height_mm*PX_PER_MM}px;`;
        const ctrl = document.createElement('div');
        ctrl.className = 'obj-controls no-print';
        ctrl.innerHTML = `<div class="obj-btn" style="background:#3b82f6" onclick="event.stopPropagation(); editObject(${idx})">✏️</div>
                          <div class="obj-btn" style="background:#ef4444" onclick="event.stopPropagation(); deleteObject(${idx})">🗑️</div>`;
        div.appendChild(ctrl);
        const inner = document.createElement('div');
        inner.style.pointerEvents = 'none'; inner.style.width='100%'; inner.style.height='100%'; inner.style.display='flex'; inner.style.alignItems='center'; inner.style.justifyContent='center';
        if(obj.type==='text') { inner.innerText = obj.properties.content||'Text'; inner.style.fontSize = (obj.properties.font_size||10)+'pt'; }
        else { 
            const c = document.createElement('canvas'); 
            try { 
                let bType = obj.properties.barcode_type||'code128';
                let isQR = bType === 'qr';
                if(isQR) bType = 'qrcode';
                
                const opts = {
                    bcid: bType, 
                    text: obj.properties.content||'123', 
                    scale: 2,
                    includetext: !isQR
                };
                if(!isQR) opts.height = 10;
                
                bwipjs.toCanvas(c, opts); 
                c.style.width = '100%';
                c.style.height = '100%';
                c.style.objectFit = 'contain';
            } catch(e){} 
            inner.appendChild(c); 
        }
        div.appendChild(inner);
        div.onmousedown = (e) => {
            if(e.target.classList.contains('obj-btn')) return;
            selectedIdx = idx;
            document.querySelectorAll('.designer-object').forEach(el => el.classList.remove('selected'));
            div.classList.add('selected');
            const sX=e.clientX, sY=e.clientY, iX=obj.x_mm*PX_PER_MM, iY=obj.y_mm*PX_PER_MM;
            const move = (ev) => { 
                obj.x_mm = (iX+(ev.clientX-sX)/zoomLevel)/PX_PER_MM; 
                obj.y_mm = (iY+(ev.clientY-sY)/zoomLevel)/PX_PER_MM; 
                div.style.left=obj.x_mm*PX_PER_MM+'px'; 
                div.style.top=obj.y_mm*PX_PER_MM+'px'; 
            };
            const up = () => { document.removeEventListener('mousemove', move); document.removeEventListener('mouseup', up); };
            document.addEventListener('mousemove', move);
            document.addEventListener('mouseup', up);
        };
        div.ondblclick = () => editObject(idx);
        canv.appendChild(div);
    });
}
function addObject(t) { labelObjects.push({type:t, x_mm:5, y_mm:5, width_mm:40, height_mm:15, properties:{content:t==='text'?'Text':'123', font_size:10, barcode_type:'code128'}}); renderObjects(); }
function deleteObject(idx) { labelObjects.splice(idx, 1); selectedIdx=null; renderObjects(); }
function editObject(idx) {
    selectedIdx = idx; const o = labelObjects[idx];
    document.getElementById('objContent').value = o.properties.content;
    document.getElementById('fontSizeGroup').style.display = o.type==='text'?'block':'none';
    document.getElementById('barcodeTypeGroup').style.display = o.type==='barcode'?'block':'none';
    document.getElementById('objWidth').value = o.width_mm;
    document.getElementById('objHeight').value = o.height_mm;
    if(o.type==='text') document.getElementById('objFontSize').value = o.properties.font_size||10;
    else document.getElementById('objBarcodeType').value = o.properties.barcode_type||'code128';
    new bootstrap.Modal(document.getElementById('objectModal')).show();
}
function applyObjectProperties() {
    const o = labelObjects[selectedIdx];
    o.properties.content = document.getElementById('objContent').value;
    o.width_mm = parseFloat(document.getElementById('objWidth').value) || o.width_mm;
    o.height_mm = parseFloat(document.getElementById('objHeight').value) || o.height_mm;
    if(o.type==='text') o.properties.font_size = document.getElementById('objFontSize').value;
    else o.properties.barcode_type = document.getElementById('objBarcodeType').value;
    bootstrap.Modal.getInstance(document.getElementById('objectModal')).hide();
    renderObjects();
}
function saveDesigner(silent = false) {
    const fd = new FormData(); fd.append('project_id', <?= $projectId ?>); fd.append('objects', JSON.stringify(labelObjects));
    return fetch('api_save_objects.php', {method:'POST', body:fd}).then(r=>r.json()).then(d=>{ if(d.success && !silent) alert('Design erfolgreich gespeichert!'); });
}
function saveDesignerAndPrint() {
    saveDesigner(true).then(() => { window.location.href = 'print_labels.php?id=<?= $projectId ?>'; });
}

function toggleAllRecords(checked) {
    const checkboxes = document.querySelectorAll('.record-select-checkbox');
    checkboxes.forEach(cb => cb.checked = checked);
    
    const fd = new FormData();
    fd.append('action_all', '1');
    fd.append('project_id', <?= $projectId ?>);
    fd.append('selected', checked ? 1 : 0);
    fetch('api_update_selection.php', { method: 'POST', body: fd });
}

function saveFormat() { fetch('api_update_format.php', {method:'POST', body:new FormData(document.getElementById('formatForm'))}).then(()=>location.reload()); }
function openPreview() { saveDesigner(true).then(() => window.open(`generate_pdf.php?id=<?= $projectId ?>&start=1`, '_blank')); }
function applyTemplate(s) { 
    if(!s.value) return; const t=JSON.parse(s.value); const f=document.getElementById('formatForm');
    const pId = <?= $projectId ?>;
    
    // Template ID setzen
    f.querySelector(`[name="template_id_${pId}"]`).value = t.id;
    f.querySelector(`[name="width_mm_${pId}"]`).value=t.width_mm; 
    f.querySelector(`[name="height_mm_${pId}"]`).value=t.height_mm;
    f.querySelector(`[name="cols_${pId}"]`).value=t.cols; 
    f.querySelector(`[name="rows_${pId}"]`).value=t.rows;
    f.querySelector(`[name="col_gap_mm_${pId}"]`).value=t.col_gap_mm || 0; 
    f.querySelector(`[name="row_gap_mm_${pId}"]`).value=t.row_gap_mm || 0;
    f.querySelector(`[name="margin_top_mm_${pId}"]`).value=t.margin_top_mm || 0; 
    f.querySelector(`[name="margin_bottom_mm_${pId}"]`).value=t.margin_bottom_mm || 0;
    f.querySelector(`[name="margin_left_mm_${pId}"]`).value=t.margin_left_mm || 0; 
    f.querySelector(`[name="margin_right_mm_${pId}"]`).value=t.margin_right_mm || 0;
    
    updateDesignerZoom();
    fetch('api_update_format.php', {method:'POST', body:new FormData(f)});
}

// Live-Update bei Tippen
document.querySelectorAll('#formatForm input').forEach(inp => {
    inp.addEventListener('input', updateDesignerZoom);
});

// QR Code 1:1 Ratio Sync
document.getElementById('objBarcodeType').addEventListener('change', function() {
    if (this.value === 'qr') document.getElementById('objHeight').value = document.getElementById('objWidth').value;
});
document.getElementById('objWidth').addEventListener('input', function() {
    if (labelObjects[selectedIdx] && labelObjects[selectedIdx].type === 'barcode' && document.getElementById('objBarcodeType').value === 'qr') {
        document.getElementById('objHeight').value = this.value;
    }
});
document.getElementById('objHeight').addEventListener('input', function() {
    if (labelObjects[selectedIdx] && labelObjects[selectedIdx].type === 'barcode' && document.getElementById('objBarcodeType').value === 'qr') {
        document.getElementById('objWidth').value = this.value;
    }
});
</script>
</body>
</html>
