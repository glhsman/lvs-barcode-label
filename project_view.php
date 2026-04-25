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
$isDbMode = false;
if (isset($_SESSION["csv_raw_13k_project_{$projectId}"])) {
    $csvData = $_SESSION["csv_raw_13k_project_{$projectId}"];
    $csvData = normalize_csv_to_utf8($csvData);
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
} else {
    $isDbMode = true;
    $stmt = $pdo->prepare("SELECT * FROM project_data_records WHERE project_id = ? ORDER BY id ASC");
    $stmt->execute([$projectId]);
    $dbRows = $stmt->fetchAll();
    foreach ($dbRows as $row) {
        $selected = $_SESSION["db_selected_{$projectId}"][$row['id']] ?? true;
        $records[$row['id']] = ['selected' => $selected, 'values' => json_decode($row['data_json'], true) ?? [], 'db_id' => $row['id']];
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
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Inter:wght@400;700&family=Roboto:wght@400;700&family=Montserrat:wght@400;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="designer.css?v=<?= time() ?>">
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
        <div class="flex-grow-1 d-none d-lg-flex justify-content-center px-3">
            <div class="px-4 py-2 rounded-3 fw-semibold text-center"
                 style="min-width: 320px; max-width: 560px; border: 2px solid #84cc16; color: #d9f99d; background: rgba(132, 204, 22, 0.08); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                <?= htmlspecialchars($project['name']) ?>
            </div>
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
                        <option value='<?= (int)$t['id'] ?>' <?= ($format['template_id'] == $t['id']) ? 'selected' : '' ?>>
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
                    <div class="row g-2 mb-2">
                        <div class="col-12">
                            <label class="form-label-sm">Medientyp</label>
                            <input type="hidden" name="media_type_<?= $projectId ?>" value="<?= (($format['media_type'] ?? 'sheet') === 'roll') ? 'roll' : 'sheet' ?>">
                            <input type="text" id="mediaTypeReadonly" class="form-control form-control-sm bg-dark text-light border-secondary px-2" value="<?= (($format['media_type'] ?? 'sheet') === 'roll') ? 'Rolle (aus Vorlage)' : 'Bogen (aus Vorlage)' ?>" readonly>
                            <div class="form-text text-secondary small" style="font-size:0.68rem;">Wird zentral in der Admin-Vorlage gepflegt.</div>
                        </div>
                    </div>
                    <div class="row g-2 mb-3 align-items-end">
                        <div class="col-7">
                            <div class="form-check m-0">
                                <input class="form-check-input bg-dark border-secondary" type="checkbox" name="show_calibration_border_<?= $projectId ?>" id="showCalibrationBorder" <?= ($format['show_calibration_border'] ?? 0) ? 'checked' : '' ?>>
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
                        <?= empty($project['csv_filename']) ? 'CSV hochladen' : 'Reload csv' ?>
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
                    <div class="px-3 py-2 d-flex justify-content-between align-items-center bg-dark bg-opacity-25 border-bottom border-secondary">
                        <div class="small text-secondary"><i class="bi bi-info-circle me-1"></i> <?= count($records) ?> Datensätze <?= $isDbMode ? 'in der Datenbank' : 'aus CSV' ?></div>
                        <?php if ($isDbMode): ?>
                            <div class="btn-group btn-group-sm shadow-sm">
                                <button class="btn btn-outline-info" onclick="openFieldsModal()"><i class="bi bi-layout-three-columns me-1"></i> Spalten verwalten</button>
                                <button class="btn btn-success" onclick="openRecordModal()" <?= count($fields) === 0 ? 'disabled title="Bitte zuerst Spalten anlegen"' : '' ?>><i class="bi bi-plus-circle me-1"></i> Datensatz hinzufügen</button>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="glass-card shadow-lg p-0" style="overflow:hidden;">
                        <div id="table-scroll-container">
                            <div id="table-sentinel"></div>
                            <table class="table table-dark table-hover table-sm mb-0">
                                <thead class="sticky-top-table">
                                    <tr>
                                        <th width="40" class="ps-3 text-center"><input type="checkbox" class="form-check-input" id="checkAllRecords" onchange="toggleAllRecords(this.checked)" title="Alle an/abwählen"></th>
                                        <th width="50" class="text-secondary small">Nr.</th>
                                        <?php if ($isDbMode): ?><th width="70" class="text-secondary small">Aktion</th><?php endif; ?>
                                        <?php foreach($fields as $f): ?><th><?= htmlspecialchars($f['name']) ?></th><?php endforeach; ?>
                                    </tr>
                                    <tr>
                                        <th></th>
                                        <th></th>
                                        <?php if ($isDbMode): ?><th></th><?php endif; ?>
                                        <?php foreach($fields as $idx => $f): ?>
                                            <th><input type="text" class="form-control form-control-sm bg-dark text-light border-secondary column-filter" data-field-id="<?= $f['id'] ?>" placeholder="Filter..." oninput="filterTable()"></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody id="data-table-body">
                                    <!-- Virtual rows injected here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="pills-designer" role="tabpanel">
                    <div class="glass-card shadow-lg">
                        <div class="card-header p-3 d-flex flex-wrap justify-content-between align-items-center border-bottom border-secondary gap-3">
                            <div class="d-flex align-items-center">
                                <span class="fw-bold small me-4" style="letter-spacing: 1px;"><i class="bi bi-pencil-square me-2 text-primary"></i>VISUAL EDITOR</span>
                                <div class="d-flex align-items-center gap-2 px-3 border-start border-secondary">
                                    <div class="form-check form-switch m-0">
                                        <input class="form-check-input" type="checkbox" id="snapToGrid" checked>
                                        <label class="form-check-label small text-muted" for="snapToGrid" style="font-size: 0.75rem;">Raster</label>
                                    </div>
                                    <select id="gridSize" class="form-select form-select-sm bg-dark text-light border-secondary py-0" style="font-size: 0.7rem; width: 75px; height: 24px;">
                                        <option value="0.1">0.1mm</option>
                                        <option value="0.5">0.5mm</option>
                                        <option value="1" selected>1.0mm</option>
                                        <option value="2">2.0mm</option>
                                        <option value="5">5.0mm</option>
                                    </select>
                                </div>
                            </div>
                            <div>
                                <div class="btn-group btn-group-sm me-2 border border-secondary rounded overflow-hidden">
                                    <button class="btn btn-dark" onclick="addObject('text')"><i class="bi bi-plus me-1"></i> Text</button>
                                    <button class="btn btn-dark" onclick="addObject('barcode')"><i class="bi bi-plus me-1"></i> Barcode</button>
                                    <button class="btn btn-dark" onclick="addObject('image')"><i class="bi bi-plus me-1"></i> Bild</button>
                                </div>
                                <button class="btn btn-sm btn-outline-secondary px-3 me-2" onclick="editObject(selectedIndices[0])" title="Eigenschaften des gewählten Objekts bearbeiten" id="btnEditSelected" disabled><i class="bi bi-pencil me-1"></i> Bearbeiten</button>
                                <div class="btn-group btn-group-sm me-3 border border-secondary rounded overflow-hidden">
                                    <button class="btn btn-dark" onclick="alignObjects('left')" title="Links ausrichten"><i class="bi bi-align-start"></i></button>
                                    <button class="btn btn-dark" onclick="alignObjects('right')" title="Rechts ausrichten"><i class="bi bi-align-end"></i></button>
                                    <button class="btn btn-dark" onclick="alignObjects('center_h')" title="Horizontal zentrieren"><i class="bi bi-align-center"></i></button>
                                    <button class="btn btn-dark" onclick="alignObjects('center_v')" title="Vertikal zentrieren"><i class="bi bi-align-middle"></i></button>
                                    <button class="btn btn-dark" onclick="alignObjects('width')" title="Einheilt. Breite"><i class="bi bi-arrows-expand" style="transform: rotate(90deg); display: inline-block;"></i></button>
                                    <button class="btn btn-dark" onclick="alignObjects('spacing_dist')" title="Vert. Gleichzug"><i class="bi bi-distribute-vertical"></i></button>
                                    <button class="btn btn-dark" onclick="adjustSpacing(1)" title="Abstand +"><i class="bi bi-plus"></i></button>
                                    <button class="btn btn-dark" onclick="adjustSpacing(-1)" title="Abstand -"><i class="bi bi-dash"></i></button>
                                </div>
                                <div class="btn-group btn-group-sm me-3 border border-secondary rounded overflow-hidden">
                                    <span title="Rückgängig (Strg+Z)" data-bs-toggle="tooltip" data-bs-placement="top">
                                        <button class="btn btn-dark" onclick="undo()" id="btnUndo" disabled><i class="bi bi-arrow-counterclockwise"></i></button>
                                    </span>
                                    <span title="Wiederholen (Strg+Y)" data-bs-toggle="tooltip" data-bs-placement="top">
                                        <button class="btn btn-dark" onclick="redo()" id="btnRedo" disabled><i class="bi bi-arrow-clockwise"></i></button>
                                    </span>
                                </div>
                                <div class="btn-group btn-group-sm me-2 border border-secondary rounded overflow-hidden" title="Zoom">
                                    <button class="btn btn-dark px-2" onclick="manualZoom(-0.1)" title="Verkleinern"><i class="bi bi-zoom-out"></i></button>
                                    <button class="btn btn-dark px-2" id="zoomLabel" style="min-width:46px; font-size:0.75rem; cursor:default;">100%</button>
                                    <button class="btn btn-dark px-2" onclick="manualZoom(+0.1)" title="Vergrößern"><i class="bi bi-zoom-in"></i></button>
                                    <button class="btn btn-dark px-2" onclick="resetZoom()" title="An Bereich anpassen"><i class="bi bi-fullscreen"></i></button>
                                </div>
                                <button class="btn btn-sm btn-outline-info px-3 me-2 border-info" onclick="openPreview()"><i class="bi bi-eye me-1"></i> Vorschau</button>
                                <button class="btn btn-sm btn-outline-warning px-3 me-2" onclick="restoreDesign()" title="Letzten gespeicherten Stand aus der Datenbank wiederherstellen"><i class="bi bi-arrow-counterclockwise me-1"></i> Wiederherstellen</button>
                                <button id="btnSaveDesigner" class="btn btn-sm btn-primary px-3 shadow-sm" onclick="saveDesigner()"><i class="bi bi-cloud-check me-1"></i> Design speichern</button>
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
                            <div style="position:absolute; bottom:10px; right:15px; font-size:10px; color:rgba(255,255,255,0.2);">UI-v2.9.0-REFACTORED</div>
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
            <div class="mb-4" id="objContentGroup">
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
                <div id="fontSizeGroup" class="col-4"><label class="form-label-sm">Größe (pt)</label><input type="number" class="form-control bg-dark text-light border-secondary" id="objFontSize"></div>
                <div id="fontFamilyGroup" class="col-8"><label class="form-label-sm">Schriftart</label>
                    <select class="form-select bg-dark text-light border-secondary" id="objFontFamily">
                        <option value="'Outfit', sans-serif">Outfit (Standard)</option>
                        <option value="'Inter', sans-serif">Inter</option>
                        <option value="'Roboto', sans-serif">Roboto</option>
                        <option value="'Montserrat', sans-serif">Montserrat</option>
                        <option value="Arial, sans-serif">Arial</option>
                        <option value="'Courier New', monospace">Courier</option>
                        <option value="'Times New Roman', serif">Times</option>
                    </select>
                </div>
            </div>
            <div id="barcodeTypeGroup" class="mt-2"><label class="form-label-sm">Barcode Typ</label><select class="form-select bg-dark text-light border-secondary" id="objBarcodeType"><option value="code128">Code 128</option><option value="ean13">EAN 13</option><option value="ean8">EAN 8</option><option value="qr">QR Code</option></select></div>
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
            <div id="imageOptionsGroup" class="mt-4 pt-3 border-top border-secondary" style="display:none;">
                <div class="mb-3 text-center" style="background:#0f172a; border-radius:8px; padding:8px; min-height:60px;">
                    <img id="objImagePreview" src="" alt="" style="max-width:100%; max-height:100px; object-fit:contain;">
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="objImageLockRatio" checked>
                    <label class="form-check-label small" for="objImageLockRatio"><i class="bi bi-lock-fill me-1 text-warning"></i>Seitenverhältnis sperren</label>
                </div>
                <label class="form-label-sm mb-1">Bild ersetzen</label>
                <div class="alert alert-warning d-flex align-items-center gap-2 py-2 px-3 mb-2" role="alert" style="font-size:0.78rem; border-radius:8px;">
                    <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
                    <span>Nur <strong>JPG</strong> / <strong>PNG</strong> &mdash; max. <strong>200 KB</strong></span>
                </div>
                <input type="file" class="form-control form-control-sm bg-dark text-light border-secondary" id="objImageFile" accept="image/jpeg,image/png">
                <div id="objImageError" class="text-danger small mt-1" style="display:none;"></div>
            </div>
        </div>
        <div class="modal-footer border-top-0 pt-0">
            <button type="button" class="btn btn-link btn-sm text-secondary text-decoration-none" data-bs-dismiss="modal">Abbrechen</button>
            <button type="button" class="btn btn-primary btn-sm px-4 rounded-pill" onclick="applyObjectProperties()">Änderungen übernehmen</button>
        </div>
    </div></div>
</div>

<!-- Modal: Neues Bild einfügen -->
<div class="modal fade" id="imageUploadModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content bg-dark text-light border-secondary shadow-lg" style="border-radius: 20px; border: 1px solid rgba(255,255,255,0.1) !important;">
        <div class="modal-header border-bottom-0 pb-0">
            <h6 class="modal-title fw-bold"><i class="bi bi-image me-2 text-primary"></i>BILD EINFÜGEN</h6>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
            <div class="alert alert-info d-flex align-items-start gap-2 py-3 px-3 mb-4" role="alert" style="font-size:0.83rem; border-radius:10px;">
                <i class="bi bi-info-circle-fill fs-5 flex-shrink-0 mt-1"></i>
                <div>
                    <strong>Erlaubte Formate: JPG und PNG</strong><br>
                    <strong>Maximale Dateigröße: 200 KB</strong><br>
                    <span class="text-light opacity-75 small">Geeignet für Produktbilder, Pfeile und Hinweis-Piktogramme.</span>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label-sm mb-1">Bilddatei auswählen</label>
                <input type="file" class="form-control bg-dark text-light border-secondary" id="newImageFile" accept="image/jpeg,image/png">
                <div id="newImageError" class="text-danger small mt-2" style="display:none;"></div>
            </div>
            <div id="newImagePreviewBox" class="text-center mt-3" style="background:#0f172a; border-radius:8px; padding:10px; display:none;">
                <img id="newImagePreview" src="" alt="Vorschau" style="max-width:100%; max-height:160px; object-fit:contain;">
            </div>
        </div>
        <div class="modal-footer border-top-0 pt-0">
            <button type="button" class="btn btn-link btn-sm text-secondary text-decoration-none" data-bs-dismiss="modal">Abbrechen</button>
            <button type="button" class="btn btn-primary btn-sm px-4 rounded-pill" id="btnConfirmAddImage" onclick="confirmAddImage()" disabled>Einfügen</button>
        </div>
    </div></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bwip-js/dist/bwip-js-min.js"></script>
<script src="designer.js?v=<?= time() ?>"></script>
<script>
    // Configuration for the designer
    const config_projectId = <?= $projectId ?>;
    const config_fields = <?= json_encode($fields) ?>;
    const config_isDbMode = <?= $isDbMode ? 'true' : 'false' ?>;
    const config_records = <?= json_encode(array_values(array_map(function($r, $k) { 
        return ['id' => $k, 'selected' => $r['selected'], 'values' => $r['values'], 'db_id' => $r['db_id'] ?? null]; 
    }, $records, array_keys($records)))) ?>;

    document.addEventListener('DOMContentLoaded', () => {
        initDesigner({
            projectId: config_projectId,
            labelObjects: <?= json_encode($labelObjects) ?>,
            fields: config_fields,
            records: config_records,
            isDbMode: config_isDbMode
        });
    });
</script>

<!-- Modal: Datensatz bearbeiten/erstellen -->
<div class="modal fade" id="recordModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-card border-secondary text-light">
            <div class="modal-header border-secondary">
                <h6 class="modal-title fw-bold" id="recordModalTitle"><i class="bi bi-plus-circle me-2 text-primary"></i>DATENSATZ</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form id="recordForm">
                    <input type="hidden" id="editRecordDbId">
                    <input type="hidden" id="editRecordIdx">
                    <?php foreach($fields as $f): ?>
                        <div class="mb-3">
                            <label class="form-label small text-muted mb-1"><?= htmlspecialchars($f['name']) ?></label>
                            <input type="text" class="form-control bg-dark text-light border-secondary record-input" data-field-id="<?= $f['id'] ?>" placeholder="<?= htmlspecialchars($f['name']) ?> eingeben...">
                        </div>
                    <?php endforeach; ?>
                </form>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-link btn-sm text-secondary text-decoration-none" data-bs-dismiss="modal">Abbrechen</button>
                <button type="button" class="btn btn-primary btn-sm px-4 rounded-pill" onclick="saveRecord()">Speichern</button>
            </div>
        </div>
    </div>
</div>
<!-- Modal: Spalten verwalten (Header-Definition) -->
<div class="modal fade" id="fieldsModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-card border-secondary text-light">
            <div class="modal-header border-secondary">
                <h6 class="modal-title fw-bold"><i class="bi bi-layout-three-columns me-2 text-info"></i>SPALTEN VERWALTEN (HEADER)</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <p class="small text-muted mb-4">Definieren Sie hier die Namen der Spalten. Diese werden als Header in der Tabelle und als Platzhalter (z.B. [~Name~]) im Designer verwendet.</p>
                <div id="fieldsList" class="mb-3">
                    <!-- Spalten-Einträge werden per JS injiziert -->
                </div>
                <button type="button" class="btn btn-outline-info btn-sm w-100 mb-3" onclick="addFieldRow()">
                    <i class="bi bi-plus-circle me-1"></i> Weitere Spalte hinzufügen
                </button>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-link btn-sm text-secondary text-decoration-none" data-bs-dismiss="modal">Abbrechen</button>
                <button type="button" class="btn btn-primary btn-sm px-4 rounded-pill" onclick="saveFields()">Spalten speichern</button>
            </div>
        </div>
    </div>
</div>
</body>
</html>
