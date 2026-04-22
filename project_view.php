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
        $selected = $_SESSION["csv_selected_{$projectId}"][$idx] ?? false;

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
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
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
        .obj-controls { position: absolute; top: 0; right: 0; display: none; background: rgba(0,0,0,0.6); padding: 2px; border-radius: 0 0 0 8px; z-index: 2000; transform-origin: top right; }
        .designer-object:hover .obj-controls, .designer-object.selected .obj-controls { display: flex; }
        .obj-btn { width: 20px; height: 20px; border: none; border-radius: 3px; color: white; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 10px; margin-left: 2px; }
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
            <a href="index.php?location_id=<?= $project['location_id'] ?>" class="btn btn-outline-light btn-sm me-3 border-secondary"><i class="bi bi-arrow-left me-1"></i> Projektwahl</a>
            <a class="navbar-brand fw-bold d-flex align-items-center m-0" href="index.php">
                <div class="d-flex align-items-center justify-content-center bg-primary bg-gradient rounded shadow-sm me-2" style="width: 32px; height: 32px;">
                    <i class="bi bi-upc-scan text-white" style="font-size: 1.1rem;"></i>
                </div>
                <span>BARCODE SYSTEM</span>
            </a>
            <a href="handbuch.html" target="_blank" rel="noopener" class="btn btn-outline-info btn-sm rounded-pill px-3 shadow-sm border-info text-info ms-3 d-none d-lg-inline-block"><i class="bi bi-question-circle me-1"></i> Hilfe</a>
            <a href="Online-Barcode-System.pdf" target="_blank" rel="noopener" class="btn btn-outline-info btn-sm rounded-pill px-3 shadow-sm border-info text-info ms-2 d-none d-lg-inline-block"><i class="bi bi-file-earmark-pdf me-1"></i> Anleitung (PDF)</a>
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
                    <div class="row g-1 mb-2">
                        <div class="col-3"><input type="number" step="0.1" name="margin_top_mm_<?= $projectId ?>" class="form-control form-control-sm bg-dark text-light border-secondary px-1" value="<?= (float)$format['margin_top_mm'] ?>"></div>
                        <div class="col-3"><input type="number" step="0.1" name="margin_bottom_mm_<?= $projectId ?>" class="form-control form-control-sm bg-dark text-light border-secondary px-1" value="<?= (float)$format['margin_bottom_mm'] ?>"></div>
                        <div class="col-3"><input type="number" step="0.1" name="margin_left_mm_<?= $projectId ?>" class="form-control form-control-sm bg-dark text-light border-secondary px-1" value="<?= (float)$format['margin_left_mm'] ?>"></div>
                        <div class="col-3"><input type="number" step="0.1" name="margin_right_mm_<?= $projectId ?>" class="form-control form-control-sm bg-dark text-light border-secondary px-1" value="<?= (float)$format['margin_right_mm'] ?>"></div>
                    </div>
                    <div class="row g-2 mb-3 align-items-end">
                        <div class="col-7">
                            <div class="form-check m-0">
                                <input class="form-check-input bg-dark border-secondary" type="checkbox" name="show_calibration_border_<?= $projectId ?>" id="showCalibrationBorder" onchange="renderObjects()" <?= ($format['show_calibration_border'] ?? 0) ? 'checked' : '' ?>>
                                <label class="form-check-label small text-info" for="showCalibrationBorder" style="cursor:pointer; font-size: 0.75rem;">
                                    <i class="bi bi-bounding-box-circles me-1"></i> Kalib.-Rahmen
                                </label>
                            </div>
                        </div>
                        <div class="col-5">
                            <label class="form-label-sm" style="font-size: 0.65rem;">Skalier. (%)</label>
                            <input type="number" step="0.1" name="print_scale_<?= $projectId ?>" class="form-control form-control-sm bg-dark text-light border-secondary px-1 text-center" value="<?= (float)($format['print_scale'] ?? 100.0) ?>">
                        </div>
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
                <div class="d-flex align-items-center gap-2">
                    <?php if (!empty($project['csv_filename'])): ?>
                    <span class="text-secondary small" style="border: 1px solid #a3e635; border-radius: 6px; padding: 6px 14px; font-size: 0.8rem;">
                        <i class="bi bi-file-earmark-spreadsheet me-1 text-success"></i><?= htmlspecialchars($project['csv_filename']) ?>
                    </span>
                    <?php endif; ?>
                    <button class="btn btn-outline-warning text-danger" style="border-color: #a3e635; color: #ef4444 !important; background: transparent; padding: 6px 20px;" onclick="document.getElementById('csvUploadInput').click()">
                        Reload csv
                    </button>
                </div>
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
                                <div class="btn-group btn-group-sm me-2 border border-secondary rounded overflow-hidden">
                                    <button class="btn btn-dark" onclick="addObject('text')"><i class="bi bi-plus me-1"></i> Text</button>
                                    <button class="btn btn-dark" onclick="addObject('barcode')"><i class="bi bi-plus me-1"></i> Barcode</button>
                                </div>
                                <button class="btn btn-sm btn-outline-secondary px-3 me-2" onclick="editObject(selectedIndices[0])" title="Eigenschaften des gewählten Objekts bearbeiten" id="btnEditSelected" disabled><i class="bi bi-pencil me-1"></i> Bearbeiten</button>
                                <div class="btn-group btn-group-sm me-3 border border-secondary rounded overflow-hidden">
                                    <button class="btn btn-dark" onclick="alignObjects('left')" title="Links ausrichten"><i class="bi bi-align-start"></i></button>
                                    <button class="btn btn-dark" onclick="alignObjects('width')" title="Einheilt. Breite"><i class="bi bi-arrows-expand" style="transform: rotate(90deg); display: inline-block;"></i></button>
                                    <button class="btn btn-dark" onclick="alignObjects('spacing_dist')" title="Vert. Gleichzug"><i class="bi bi-distribute-vertical"></i></button>
                                    <button class="btn btn-dark" onclick="adjustSpacing(1)" title="Abstand +"><i class="bi bi-plus"></i></button>
                                    <button class="btn btn-dark" onclick="adjustSpacing(-1)" title="Abstand -"><i class="bi bi-dash"></i></button>
                                </div>
                                <div class="btn-group btn-group-sm me-2 border border-secondary rounded overflow-hidden" title="Zoom">
                                    <button class="btn btn-dark px-2" onclick="manualZoom(-0.1)" title="Verkleinern"><i class="bi bi-zoom-out"></i></button>
                                    <button class="btn btn-dark px-2" id="zoomLabel" style="min-width:46px; font-size:0.75rem; cursor:default;">100%</button>
                                    <button class="btn btn-dark px-2" onclick="manualZoom(+0.1)" title="Vergrößern"><i class="bi bi-zoom-in"></i></button>
                                    <button class="btn btn-dark px-2" onclick="resetZoom()" title="An Bereich anpassen"><i class="bi bi-fullscreen"></i></button>
                                </div>
                                <button class="btn btn-sm btn-outline-info px-3 me-2 border-info" onclick="openPreview()"><i class="bi bi-eye me-1"></i> Vorschau</button>
                                <button class="btn btn-sm btn-outline-warning px-3 me-2" onclick="restoreDesign()" title="Letzten gespeicherten Stand aus der Datenbank wiederherstellen"><i class="bi bi-arrow-counterclockwise me-1"></i> Wiederherstellen</button>
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
                            <div style="position:absolute; bottom:10px; right:15px; font-size:10px; color:rgba(255,255,255,0.2);">UI-v2.6.0-STABLE</div>
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
                <div id="barcodeTypeGroup" class="col-6"><label class="form-label-sm">Barcode Typ</label><select class="form-select bg-dark text-light border-secondary" id="objBarcodeType"><option value="code128">Code 128</option><option value="ean13">EAN 13</option><option value="ean8">EAN 8</option><option value="qr">QR Code</option></select></div>
            </div>
            <div class="row g-2 mt-2">
                <div class="col-6"><label class="form-label-sm">Breite der Box (mm)</label><input type="number" step="0.5" class="form-control bg-dark text-light border-secondary" id="objWidth"></div>
                <div class="col-6"><label class="form-label-sm">Höhe der Box (mm)</label><input type="number" step="0.5" class="form-control bg-dark text-light border-secondary" id="objHeight"></div>
            </div>
            <div class="mt-3">
                <label class="form-label-sm mb-1">Rotation</label>
                <div class="d-flex align-items-center gap-2">
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-secondary" onclick="setObjRotation(0)">0°</button>
                        <button type="button" class="btn btn-outline-secondary" onclick="setObjRotation(90)">90°</button>
                        <button type="button" class="btn btn-outline-secondary" onclick="setObjRotation(180)">180°</button>
                        <button type="button" class="btn btn-outline-secondary" onclick="setObjRotation(270)">270°</button>
                    </div>
                    <input type="number" step="1" min="0" max="359" class="form-control bg-dark text-light border-secondary" id="objRotation" style="width:80px;" placeholder="°">
                </div>
            </div>
            <!-- Formatierungs-Optionen -->
            <div id="textOptionsGroup" class="mt-4 pt-3 border-top border-secondary">
                <div class="d-flex flex-wrap gap-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="objBold">
                        <label class="form-check-label small" for="objBold">Fett (B)</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="objItalic">
                        <label class="form-check-label small" for="objItalic">Kursiv (I)</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="objVertical">
                        <label class="form-check-label small" for="objVertical">Senkrecht</label>
                    </div>
                </div>
                <div class="mt-3">
                    <label class="form-label-sm mb-1">Textausrichtung (Horizontal)</label>
                    <div class="btn-group btn-group-sm w-100 border border-secondary rounded overflow-hidden">
                        <input type="radio" class="btn-check" name="objTextAlign" id="alignLeft" value="left">
                        <label class="btn btn-outline-secondary border-0" for="alignLeft"><i class="bi bi-justify-left"></i></label>
                        <input type="radio" class="btn-check" name="objTextAlign" id="alignCenter" value="center" checked>
                        <label class="btn btn-outline-secondary border-0" for="alignCenter"><i class="bi bi-justify-center"></i></label>
                        <input type="radio" class="btn-check" name="objTextAlign" id="alignRight" value="right">
                        <label class="btn btn-outline-secondary border-0" for="alignRight"><i class="bi bi-justify-right"></i></label>
                    </div>
                </div>
            </div>
            <div id="barcodeOptionsGroup" class="mt-4 pt-3 border-top border-secondary">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="objShowHTR">
                    <label class="form-check-label small" for="objShowHTR">Klartextzeile sichtbar</label>
                </div>
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
    const fw = parseFloat(document.querySelector(`[name="width_mm_${pId}"]`).value.replace(',', '.')) || 10;
    const fh = parseFloat(document.querySelector(`[name="height_mm_${pId}"]`).value.replace(',', '.')) || 10;
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
    window._autoZoom = newZoom; // Auto-Zoom merken für Reset
    window._manualZoom = null; // Auto-Zoom zurücksetzen
    applyZoom(newZoom);
}

function applyZoom(z) {
    window.zoomLevel = z;
    document.getElementById('zoom-container').style.transform = `scale(${z})`;
    const lbl = document.getElementById('zoomLabel');
    if (lbl) lbl.textContent = Math.round(z * 100) + '%';
}

function manualZoom(delta) {
    const current = window._manualZoom !== null ? window._manualZoom : window.zoomLevel;
    let next = Math.round((current + delta) * 10) / 10;
    if (next < 0.1) next = 0.1;
    if (next > 5.0) next = 5.0;
    window._manualZoom = next;
    applyZoom(next);
}

function resetZoom() {
    window._manualZoom = null;
    applyZoom(window._autoZoom || 1);
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
let selectedIndices = [];

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

function updateEditButton() {
    const btn = document.getElementById('btnEditSelected');
    if (!btn) return;
    const hasOne = selectedIndices.length === 1;
    btn.disabled = !hasOne;
    btn.classList.toggle('btn-outline-primary', hasOne);
    btn.classList.toggle('btn-outline-secondary', !hasOne);
}

function renderObjects() {
    const canv = document.getElementById('designer-canvas');
    if(!canv) return;
    canv.innerHTML = '';
    updateEditButton();

    const pId = <?= $projectId ?>;
    const fw = parseFloat(document.querySelector(`[name="width_mm_${pId}"]`).value) || 10;
    const fh = parseFloat(document.querySelector(`[name="height_mm_${pId}"]`).value) || 10;

    // Canvas-Klick: Alle deselektieren
    canv.addEventListener('click', (e) => {
        if (e.target === canv) {
            selectedIndices = [];
            document.querySelectorAll('.designer-object').forEach(el => el.classList.remove('selected'));
            updateEditButton();
        }
    }, true);

    // Kalibrierungsrahmen (1mm innerhalb der Begrenzung)
    if (document.getElementById('showCalibrationBorder').checked) {
        const border = document.createElement('div');
        border.style.position = 'absolute';
        border.style.boxSizing = 'border-box';
        border.style.pointerEvents = 'none';
        border.style.zIndex = '500';

        // Der Rahmen soll 1mm kleiner sein als das Etikett (0.5mm Rand rundherum)
        // Linienstärke 1mm
        const bW = (fw - 1) * PX_PER_MM;
        const bH = (fh - 1) * PX_PER_MM;
        const bL = 0.5 * PX_PER_MM;
        const bT = 0.5 * PX_PER_MM;
        const bThick = 1 * PX_PER_MM;

        border.style.left = bL + 'px';
        border.style.top = bT + 'px';
        border.style.width = bW + 'px';
        border.style.height = bH + 'px';
        border.style.border = `${bThick}px solid rgba(239, 68, 68, 0.6)`; // Rötlich und halbtransparent

        canv.appendChild(border);
    }

    labelObjects.forEach((obj, idx) => {
        const div = document.createElement('div');
        div.className = 'designer-object ' + (selectedIndices.includes(idx) ? 'selected' : '');
        const rot = obj.rotation || 0;
        div.style.cssText = `left:${obj.x_mm*PX_PER_MM}px; top:${obj.y_mm*PX_PER_MM}px; width:${obj.width_mm*PX_PER_MM}px; height:${obj.height_mm*PX_PER_MM}px; transform: rotate(${rot}deg);`;
        const ctrl = document.createElement('div');
        ctrl.className = 'obj-controls no-print';
        const elHeightPx = obj.height_mm * PX_PER_MM;
        const ctrlScale = Math.min(1, Math.max(0.45, elHeightPx / 56));
        ctrl.style.transform = `scale(${ctrlScale.toFixed(2)})`;
        ctrl.innerHTML = `<div class="obj-btn" style="background:#6366f1" title="Ebene nach vorne" onclick="event.stopPropagation(); bringForward(${idx})">▲</div>
                          <div class="obj-btn" style="background:#6366f1" title="Ebene nach hinten" onclick="event.stopPropagation(); sendBackward(${idx})">▼</div>
                          <div class="obj-btn" style="background:#3b82f6" onclick="event.stopPropagation(); editObject(${idx})">✏️</div>
                          <div class="obj-btn" style="background:#ef4444" onclick="event.stopPropagation(); deleteObject(${idx})">🗑️</div>`;
        div.appendChild(ctrl);
        const inner = document.createElement('div');
        inner.style.pointerEvents = 'none'; inner.style.width='100%'; inner.style.height='100%'; inner.style.display='flex'; inner.style.alignItems='center'; inner.style.justifyContent='center';

        if(obj.type==='text') {
            inner.innerText = obj.properties.content||'Text';
            inner.style.fontSize = (obj.properties.font_size||10)+'pt';
            if(obj.properties.bold) inner.style.fontWeight = 'bold';
            if(obj.properties.italic) inner.style.fontStyle = 'italic';
            if(obj.properties.vertical) {
                inner.style.writingMode = 'vertical-rl';
                inner.style.textOrientation = 'upright';
                inner.style.letterSpacing = '-2px';
            }
            const ta = obj.properties.text_align || 'center';
            if (ta === 'left') inner.style.justifyContent = 'flex-start';
            if (ta === 'right') inner.style.justifyContent = 'flex-end';
        }
        else {
            const c = document.createElement('canvas');
            const bType = obj.properties.barcode_type||'code128';
            const content = obj.properties.content||'123';

            // EAN Validierung (nur falls kein Platzhalter enthalten ist)
            let hasError = false;
            let errorMsg = "";
            if (bType === 'ean8' && !/^\d{8}$/.test(content) && content.indexOf('[~') === -1) {
                hasError = true; errorMsg = "EAN8 ERROR\n(8 Ziffern!)";
            } else if (bType === 'ean13' && !/^\d{12,13}$/.test(content) && content.indexOf('[~') === -1) {
                hasError = true; errorMsg = "EAN13 ERROR\n(12-13 Ziffern!)";
            }

            if (hasError) {
                const warn = document.createElement('div');
                warn.style.cssText = "position:absolute; top:0; left:0; width:100%; height:100%; background:rgba(239, 68, 68, 0.6); display:flex; align-items:center; justify-content:center; text-align:center; color:white; font-size:8px; z-index:10; pointer-events:none;";
                warn.innerText = errorMsg;
                inner.appendChild(warn);
            }

            try {
                let bTypeInternal = bType;
                let isQR = bTypeInternal === 'qr';
                if(isQR) bTypeInternal = 'qrcode';

                const opts = {
                    bcid: bTypeInternal,
                    text: content,
                    scale: 2,
                    includetext: isQR ? false : (obj.properties.show_htr !== false)
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

        // Resizer immer hinzufügen (Sichtbarkeit via CSS .selected)
        ['tl','tr','bl','br'].forEach(pos => {
            const resizer = document.createElement('div');
            resizer.className = 'resizer ' + pos;
            resizer.onmousedown = (e) => {
                e.stopPropagation();
                const sX=e.clientX, sY=e.clientY;
                const iW=obj.width_mm, iH=obj.height_mm, iX=obj.x_mm, iY=obj.y_mm;
                const isQR = obj.properties.barcode_type === 'qr';

                const move = (ev) => {
                    const dx = (ev.clientX - sX) / (PX_PER_MM * zoomLevel);
                    const dy = (ev.clientY - sY) / (PX_PER_MM * zoomLevel);

                    let nw = iW, nh = iH, nx = iX, ny = iY;

                    if(pos.includes('r')) nw = iW + dx;
                    if(pos.includes('l')) { nw = iW - dx; nx = iX + dx; }
                    if(pos.includes('b')) nh = iH + dy;
                    if(pos.includes('t')) { nh = iH - dy; ny = iY + dy; }

                    if(nw < 3) { if(pos.includes('l')) nx = iX + (iW - 3); nw = 3; }
                    if(nh < 3) { if(pos.includes('t')) ny = iY + (iH - 3); nh = 3; }

                    if(isQR) {
                        const newSize = Math.max(nw, nh);
                        if(pos === 'br') { nw = newSize; nh = newSize; }
                        else if(pos === 'tl') { nx = iX + (iW - newSize); ny = iY + (iH - newSize); nw = newSize; nh = newSize; }
                        else if(pos === 'tr') { ny = iY + (iH - newSize); nw = newSize; nh = newSize; }
                        else if(pos === 'bl') { nx = iX + (iW - newSize); nw = newSize; nh = newSize; }
                    }

                    obj.width_mm = nw; obj.height_mm = nh;
                    obj.x_mm = nx; obj.y_mm = ny;

                    div.style.width = (nw * PX_PER_MM) + 'px';
                    div.style.height = (nh * PX_PER_MM) + 'px';
                    div.style.left = (nx * PX_PER_MM) + 'px';
                    div.style.top = (ny * PX_PER_MM) + 'px';
                };
                const up = () => {
                    document.removeEventListener('mousemove', move);
                    document.removeEventListener('mouseup', up);
                    renderObjects();
                };
                document.addEventListener('mousemove', move);
                document.addEventListener('mouseup', up);
            };
            div.appendChild(resizer);
        });

        div.onmousedown = (e) => {
            if(e.target.classList.contains('obj-btn')) return;

            if (e.ctrlKey) {
                if (selectedIndices.includes(idx)) {
                    selectedIndices = selectedIndices.filter(i => i !== idx);
                } else {
                    selectedIndices.push(idx);
                }
            } else {
                selectedIndices = [idx];
            }

            document.querySelectorAll('.designer-object').forEach((el, i) => {
                if (selectedIndices.includes(i)) el.classList.add('selected');
                else el.classList.remove('selected');
            });
            updateEditButton();

            const sX=e.clientX, sY=e.clientY;
            const initialPos = selectedIndices.map(i => ({idx: i, x: labelObjects[i].x_mm * PX_PER_MM, y: labelObjects[i].y_mm * PX_PER_MM}));

            const move = (ev) => {
                const dx = (ev.clientX - sX) / zoomLevel;
                const dy = (ev.clientY - sY) / zoomLevel;

                initialPos.forEach(p => {
                    const obj = labelObjects[p.idx];
                    obj.x_mm = (p.x + dx) / PX_PER_MM;
                    obj.y_mm = (p.y + dy) / PX_PER_MM;

                    // Live-Update der DIVs (optional, aber performanter für Feedback)
                    const el = document.querySelectorAll('.designer-object')[p.idx];
                    if (el) {
                        el.style.left = obj.x_mm * PX_PER_MM + 'px';
                        el.style.top = obj.y_mm * PX_PER_MM + 'px';
                    }
                });
            };
            const up = () => { document.removeEventListener('mousemove', move); document.removeEventListener('mouseup', up); };
            document.addEventListener('mousemove', move);
            document.addEventListener('mouseup', up);
        };
        div.ondblclick = () => editObject(idx);
        canv.appendChild(div);
    });
}
function addObject(t) {
    const STEP = 5, W = 40, H = 15;
    const n = labelObjects.length;
    const maxX = Math.max(0, formatW - W);
    const maxY = Math.max(0, formatH - H);
    const offsetX = Math.min(n * STEP, maxX);
    const offsetY = Math.min(n * STEP, maxY);
    labelObjects.push({type:t, x_mm: offsetX, y_mm: offsetY, width_mm:W, height_mm:H, rotation: 0, properties:{content:t==='text'?'Text':'123', font_size:10, barcode_type:'code128', text_align: 'center'}});
    selectedIndices = [labelObjects.length - 1];
    renderObjects();
}
function deleteObject(idx) { labelObjects.splice(idx, 1); selectedIndices = selectedIndices.filter(i => i !== idx).map(i => i > idx ? i - 1 : i); renderObjects(); }
function bringForward(idx) {
    if (idx >= labelObjects.length - 1) return;
    [labelObjects[idx], labelObjects[idx+1]] = [labelObjects[idx+1], labelObjects[idx]];
    selectedIndices = selectedIndices.map(i => i === idx ? idx+1 : i === idx+1 ? idx : i);
    renderObjects();
}
function sendBackward(idx) {
    if (idx <= 0) return;
    [labelObjects[idx], labelObjects[idx-1]] = [labelObjects[idx-1], labelObjects[idx]];
    selectedIndices = selectedIndices.map(i => i === idx ? idx-1 : i === idx-1 ? idx : i);
    renderObjects();
}
function editObject(idx) {
    if (!selectedIndices.includes(idx)) selectedIndices = [idx];
    const o = labelObjects[idx];
    document.getElementById('objContent').value = o.properties.content;
    document.getElementById('fontSizeGroup').style.display = o.type==='text'?'block':'none';
    document.getElementById('barcodeTypeGroup').style.display = o.type==='barcode'?'block':'none';
    document.getElementById('textOptionsGroup').style.display = o.type==='text'?'block':'none';
    document.getElementById('barcodeOptionsGroup').style.display = o.type==='barcode'?'block':'none';

    document.getElementById('objWidth').value = o.width_mm;
    document.getElementById('objHeight').value = o.height_mm;
    document.getElementById('objRotation').value = o.rotation || 0;

    if(o.type==='text') {
        document.getElementById('objFontSize').value = o.properties.font_size||10;
        document.getElementById('objBold').checked = !!o.properties.bold;
        document.getElementById('objItalic').checked = !!o.properties.italic;
        document.getElementById('objVertical').checked = !!o.properties.vertical;
        const ta = o.properties.text_align || 'center';
        const rb = document.querySelector(`input[name="objTextAlign"][value="${ta}"]`);
        if (rb) rb.checked = true;
    } else {
        document.getElementById('objBarcodeType').value = o.properties.barcode_type||'code128';
        document.getElementById('objShowHTR').checked = o.properties.show_htr !== false;
    }
    new bootstrap.Modal(document.getElementById('objectModal')).show();
}
function applyObjectProperties() {
    // Falls mehrere gewählt sind, aber nur eines editiert wurde (Standardverhalten), nehmen wir das erste aus der Auswahl
    const idx = selectedIndices[0];
    if (idx === undefined) return;
    const o = labelObjects[idx];
    o.properties.content = document.getElementById('objContent').value;
    o.width_mm = parseFloat(document.getElementById('objWidth').value.replace(',', '.')) || o.width_mm;
    o.height_mm = parseFloat(document.getElementById('objHeight').value.replace(',', '.')) || o.height_mm;
    o.rotation = parseFloat(document.getElementById('objRotation').value) || 0;

    if(o.type==='text') {
        o.properties.font_size = document.getElementById('objFontSize').value;
        o.properties.bold = document.getElementById('objBold').checked;
        o.properties.italic = document.getElementById('objItalic').checked;
        o.properties.vertical = document.getElementById('objVertical').checked;
        o.properties.text_align = document.querySelector('input[name="objTextAlign"]:checked').value;
    } else {
        o.properties.barcode_type = document.getElementById('objBarcodeType').value;
        o.properties.show_htr = document.getElementById('objShowHTR').checked;
    }
    bootstrap.Modal.getInstance(document.getElementById('objectModal')).hide();
    renderObjects();
}
function saveDesigner(silent = false) {
    const fd = new FormData(); fd.append('project_id', <?= $projectId ?>); fd.append('objects', JSON.stringify(labelObjects));
    return fetch('api_save_objects.php', {method:'POST', body:fd}).then(r=>r.json()).then(d=>{ if(d.success && !silent) alert('Design erfolgreich gespeichert!'); });
}
function restoreDesign() {
    if (!confirm('Möchten Sie den letzten gespeicherten Stand aus der Datenbank wirklich wiederherstellen? Alle ungespeicherten Änderungen gehen verloren.')) return;
    fetch('api_get_objects.php?project_id=<?= $projectId ?>')
        .then(r => r.json())
        .then(d => {
            if (!d.success) { alert('Fehler beim Wiederherstellen: ' + (d.message || 'Unbekannter Fehler')); return; }
            labelObjects = d.objects.map(o => { if (typeof o.properties === 'string') try { o.properties = JSON.parse(o.properties); } catch(e) { o.properties = {}; } return o; });
            selectedIndices = [];
            renderObjects();
        })
        .catch(() => alert('Verbindungsfehler beim Wiederherstellen.'));
}
function saveDesignerAndPrint() {
    const cal = document.getElementById('showCalibrationBorder').checked ? 1 : 0;
    saveDesigner(true).then(() => { window.location.href = 'print_labels.php?id=<?= $projectId ?>&cal=' + cal; });
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
function openPreview() {
    const cal = document.getElementById('showCalibrationBorder').checked ? 1 : 0;
    saveDesigner(true).then(() => window.open(`generate_pdf.php?id=<?= $projectId ?>&start=1&cal=` + cal, '_blank'));
}
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

function setObjRotation(deg) { document.getElementById('objRotation').value = deg; }

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
    const idx = selectedIndices[0];
    if (idx !== undefined && labelObjects[idx].type === 'barcode' && document.getElementById('objBarcodeType').value === 'qr') {
        document.getElementById('objWidth').value = this.value;
    }
});

function alignObjects(type) {
    if (selectedIndices.length < 2) return;
    const targets = selectedIndices.map(idx => labelObjects[idx]);

    if (type === 'left') {
        const minX = Math.min(...targets.map(o => o.x_mm));
        targets.forEach(o => o.x_mm = minX);
    } else if (type === 'width') {
        const maxWidth = Math.max(...targets.map(o => o.width_mm));
        targets.forEach(o => o.width_mm = maxWidth);
    } else if (type === 'spacing_dist') {
        targets.sort((a, b) => a.y_mm - b.y_mm);
        const first = targets[0];
        const last = targets[targets.length - 1];
        const totalHeightOfMiddle = targets.slice(0, -1).reduce((s, o) => s + o.height_mm, 0);
        const gap = (last.y_mm - first.y_mm - totalHeightOfMiddle) / (targets.length - 1);

        let currentY = first.y_mm;
        for (let i = 1; i < targets.length - 1; i++) {
            currentY += targets[i-1].height_mm + gap;
            targets[i].y_mm = currentY;
        }
    }
    renderObjects();
}

function adjustSpacing(dir) {
    if (selectedIndices.length < 2) return;
    const targets = selectedIndices.map(idx => labelObjects[idx]);
    targets.sort((a, b) => a.y_mm - b.y_mm);

    const step = 2 * dir; // 2mm
    for (let i = 1; i < targets.length; i++) {
        targets[i].y_mm += i * step;
    }
    renderObjects();
}
</script>
</body>
</html>
