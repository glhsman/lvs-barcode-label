<?php
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
$stmt = $pdo->prepare("SELECT dr.id as record_id, dr.selected, pf.id as field_id, rv.value FROM data_records dr JOIN project_fields pf ON pf.project_id = dr.project_id LEFT JOIN record_values rv ON rv.record_id = dr.id AND rv.field_id = pf.id WHERE dr.project_id = ? ORDER BY dr.position ASC, pf.position ASC");
$stmt->execute([$projectId]);
foreach ($stmt->fetchAll() as $row) {
    if (!isset($records[$row['record_id']])) $records[$row['record_id']] = ['selected' => $row['selected'], 'values' => []];
    $records[$row['record_id']]['values'][$row['field_id']] = $row['value'];
}
$globalTemplates = $pdo->query("SELECT * FROM global_label_templates ORDER BY name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($project['name']) ?> - Details</title>
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
                <img src="Image.jpg" alt="Logo" width="32" height="32" class="me-2 rounded shadow-sm">
                <span>BARCODE SYSTEM</span>
            </a>
        </div>
        <div class="ms-auto d-flex align-items-center">
            <div class="text-end me-4 d-none d-md-block">
                <div class="small text-muted fw-bold" style="font-size: 0.65rem; letter-spacing: 1px; text-transform: uppercase;">Aktiv</div>
                <div class="text-primary fw-bold" style="font-size: 0.9rem;"><?= htmlspecialchars($project['name']) ?></div>
            </div>
            <a href="print_labels.php?id=<?= $projectId ?>" class="btn btn-success btn-sm px-4 shadow-sm fw-bold"><i class="bi bi-printer me-2"></i>Drucken</a>
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
                <select class="form-select form-select-sm bg-dark text-light border-secondary mb-3" onchange="applyTemplate(this)">
                    <option value="">-- Vorlage wählen --</option>
                    <?php foreach($globalTemplates as $t): ?><option value='<?= json_encode($t)?>'><?= htmlspecialchars($t['name'])?></option><?php endforeach; ?>
                </select>
                <hr class="border-secondary opacity-25">
                <form id="formatForm">
                    <input type="hidden" name="project_id" value="<?= $projectId ?>">
                    <div class="row g-2 mb-2">
                        <div class="col-6"><label class="form-label-sm">Breite</label><input type="number" step="0.1" name="width_mm" class="form-control form-control-sm bg-dark text-light border-secondary px-2" value="<?= $format['width_mm'] ?>"></div>
                        <div class="col-6"><label class="form-label-sm">Höhe</label><input type="number" step="0.1" name="height_mm" class="form-control form-control-sm bg-dark text-light border-secondary px-2" value="<?= $format['height_mm'] ?>"></div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6"><label class="form-label-sm">Spalten</label><input type="number" name="cols" class="form-control form-control-sm bg-dark text-light border-secondary px-2" value="<?= $format['cols'] ?>"></div>
                        <div class="col-6"><label class="form-label-sm">Zeilen</label><input type="number" name="rows" class="form-control form-control-sm bg-dark text-light border-secondary px-2" value="<?= $format['rows'] ?>"></div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6"><label class="form-label-sm">H-Abst.</label><input type="number" step="0.1" name="col_gap_mm" class="form-control form-control-sm bg-dark text-light border-secondary px-2" value="<?= $format['col_gap_mm'] ?>"></div>
                        <div class="col-6"><label class="form-label-sm">V-Abst.</label><input type="number" step="0.1" name="row_gap_mm" class="form-control form-control-sm bg-dark text-light border-secondary px-2" value="<?= $format['row_gap_mm'] ?>"></div>
                    </div>
                    <div class="form-label-sm mt-3 mb-1">Ränder (Ob / Un / Li / Re)</div>
                    <div class="row g-1 mb-4">
                        <div class="col-3"><input type="number" step="0.1" name="margin_top_mm" class="form-control form-control-sm bg-dark text-light border-secondary px-1" value="<?= $format['margin_top_mm'] ?>"></div>
                        <div class="col-3"><input type="number" step="0.1" name="margin_bottom_mm" class="form-control form-control-sm bg-dark text-light border-secondary px-1" value="<?= $format['margin_bottom_mm'] ?>"></div>
                        <div class="col-3"><input type="number" step="0.1" name="margin_left_mm" class="form-control form-control-sm bg-dark text-light border-secondary px-1" value="<?= $format['margin_left_mm'] ?>"></div>
                        <div class="col-3"><input type="number" step="0.1" name="margin_right_mm" class="form-control form-control-sm bg-dark text-light border-secondary px-1" value="<?= $format['margin_right_mm'] ?>"></div>
                    </div>
                </form>
                <button class="btn btn-primary btn-sm w-100" onclick="saveFormat()"><i class="bi bi-save me-1"></i> Format speichern</button>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-md-9">
            <!-- Navigation -->
            <ul class="nav nav-pills mb-3 glass-card p-1" style="width:fit-content;" id="pills-tab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="pills-data-tab" data-bs-toggle="pill" data-bs-target="#pills-data" type="button" role="tab"><i class="bi bi-grid-3x3-gap me-2"></i>DATEN</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="pills-designer-tab" data-bs-toggle="pill" data-bs-target="#pills-designer" type="button" role="tab"><i class="bi bi-palette-fill me-2"></i>DESIGNER</button>
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content" id="pills-tabContent">
                <div class="tab-pane fade show active" id="pills-data" role="tabpanel">
                    <div class="glass-card shadow-lg p-0" style="overflow:hidden;">
                        <div style="max-height: 72vh; overflow: auto;">
                            <table class="table table-dark table-hover table-sm mb-0">
                                <thead class="sticky-top-table">
                                    <tr>
                                        <th width="40" class="ps-3 text-center"><i class="bi bi-check2-square"></i></th>
                                        <?php foreach($fields as $f): ?><th><?= htmlspecialchars($f['name']) ?></th><?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($records as $rid => $rec): ?>
                                    <tr>
                                        <td class="ps-3 text-center"><input type="checkbox" class="form-check-input" <?= $rec['selected']?'checked':'' ?>></td>
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
                        <div class="card-body p-5 bg-slate-900 border-0 text-center position-relative" style="min-height: 500px;">
                            <div id="designer-canvas" style="width:<?= $format['width_mm']*3.78?>px; height:<?= $format['height_mm']*3.78?>px;"></div>
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
        </div>
        <div class="modal-footer border-top-0 pt-0">
            <button type="button" class="btn btn-link btn-sm text-secondary text-decoration-none" data-bs-dismiss="modal">Abbrechen</button>
            <button type="button" class="btn btn-primary btn-sm px-4 rounded-pill" onclick="applyObjectProperties()">Änderungen übernehmen</button>
        </div>
    </div></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bwip-js/dist/bwip-js-min.js"></script>
<script>
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
        else { const c = document.createElement('canvas'); try { bwipjs.toCanvas(c, {bcid:obj.properties.barcode_type||'code128', text:obj.properties.content||'123', scale:2, height:10, includetext:true}); } catch(e){} inner.appendChild(c); }
        div.appendChild(inner);
        div.onmousedown = (e) => {
            if(e.target.classList.contains('obj-btn')) return;
            selectedIdx = idx;
            document.querySelectorAll('.designer-object').forEach(el => el.classList.remove('selected'));
            div.classList.add('selected');
            const sX=e.clientX, sY=e.clientY, iX=obj.x_mm*PX_PER_MM, iY=obj.y_mm*PX_PER_MM;
            const move = (ev) => { obj.x_mm = (iX+ev.clientX-sX)/PX_PER_MM; obj.y_mm = (iY+ev.clientY-sY)/PX_PER_MM; div.style.left=obj.x_mm*PX_PER_MM+'px'; div.style.top=obj.y_mm*PX_PER_MM+'px'; };
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
    if(o.type==='text') document.getElementById('objFontSize').value = o.properties.font_size||10;
    else document.getElementById('objBarcodeType').value = o.properties.barcode_type||'code128';
    new bootstrap.Modal(document.getElementById('objectModal')).show();
}
function applyObjectProperties() {
    const o = labelObjects[selectedIdx];
    o.properties.content = document.getElementById('objContent').value;
    if(o.type==='text') o.properties.font_size = document.getElementById('objFontSize').value;
    else o.properties.barcode_type = document.getElementById('objBarcodeType').value;
    renderObjects();
    bootstrap.Modal.getInstance(document.getElementById('objectModal')).hide();
}
function saveDesigner(silent = false) {
    const fd = new FormData(); fd.append('project_id', <?= $projectId ?>); fd.append('objects', JSON.stringify(labelObjects));
    return fetch('api_save_objects.php', {method:'POST', body:fd}).then(r=>r.json()).then(d=>{ if(d.success && !silent) alert('Design erfolgreich gespeichert!'); });
}
function saveFormat() { fetch('api_update_format.php', {method:'POST', body:new FormData(document.getElementById('formatForm'))}).then(()=>location.reload()); }
function openPreview() { saveDesigner(true).then(() => window.open(`generate_pdf.php?id=<?= $projectId ?>&start=1`, '_blank')); }
function applyTemplate(s) { 
    if(!s.value) return; const t=JSON.parse(s.value); const f=document.getElementById('formatForm');
    f.querySelector('[name="width_mm"]').value=t.width_mm; f.querySelector('[name="height_mm"]').value=t.height_mm;
    f.querySelector('[name="cols"]').value=t.cols; f.querySelector('[name="rows"]').value=t.rows;
    f.querySelector('[name="col_gap_mm"]').value=t.col_gap_mm; f.querySelector('[name="row_gap_mm"]').value=t.row_gap_mm;
    f.querySelector('[name="margin_top_mm"]').value=t.margin_top_mm; f.querySelector('[name="margin_bottom_mm"]').value=t.margin_bottom_mm;
    f.querySelector('[name="margin_left_mm"]').value=t.margin_left_mm; f.querySelector('[name="margin_right_mm"]').value=t.margin_right_mm;
    saveFormat();
}
</script>
</body>
</html>
