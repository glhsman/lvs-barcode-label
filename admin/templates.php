<?php
require_once '../config.php';
require_once 'auth_check.php';

// --- API / POST HANDLER ---

// Hilfsfunktion zum Normalisieren von Zahlen (Komma -> Punkt)
function normalize_num($val) {
    return floatval(str_replace(',', '.', $val));
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'load') {
        $stmt = $pdo->prepare("SELECT * FROM global_label_templates WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode($stmt->fetch());
        exit;
    }

    if ($action === 'save' || $action === 'save_new') {
        $name = trim($_POST['name']);
        if (empty($name)) { $error = "Der Name der Vorlage darf nicht leer sein."; }
        else {
            $data = [
                'name' => $name,
                'manufacturer' => $_POST['manufacturer'] ?? '',
                'product_name' => $_POST['product_name'] ?? '',
                'width_mm' => normalize_num($_POST['width_mm']),
                'height_mm' => normalize_num($_POST['height_mm']),
                'margin_top_mm' => normalize_num($_POST['margin_top_mm']),
                'margin_bottom_mm' => normalize_num($_POST['margin_bottom_mm']),
                'margin_left_mm' => normalize_num($_POST['margin_left_mm']),
                'margin_right_mm' => normalize_num($_POST['margin_right_mm']),
                'cols' => (int)$_POST['cols'],
                'rows' => (int)$_POST['rows'],
                'col_gap_mm' => normalize_num($_POST['col_gap_mm']),
                'row_gap_mm' => normalize_num($_POST['row_gap_mm'])
            ];

            // A4-Maßprüfung (Info-Logik bleibt erhalten, Blockierung entfernt)
            $totalWidth = $data['margin_left_mm'] + $data['margin_right_mm'] + ($data['cols'] * $data['width_mm']) + (($data['cols'] - 1) * $data['col_gap_mm']);
            $totalHeight = $data['margin_top_mm'] + $data['margin_bottom_mm'] + ($data['rows'] * $data['height_mm']) + (($data['rows'] - 1) * $data['row_gap_mm']);

            try {
                if ($action === 'save' && $id > 0) {
                    $sql = "UPDATE global_label_templates SET 
                            name=:name, manufacturer=:manufacturer, product_name=:product_name, 
                            width_mm=:width_mm, height_mm=:height_mm, 
                            margin_top_mm=:margin_top_mm, margin_bottom_mm=:margin_bottom_mm, 
                            margin_left_mm=:margin_left_mm, margin_right_mm=:margin_right_mm, 
                            `cols`=:cols, `rows`=:rows, 
                            col_gap_mm=:col_gap_mm, row_gap_mm=:row_gap_mm 
                            WHERE id=:id";
                    $data['id'] = $id;
                    $pdo->prepare($sql)->execute($data);
                    $message = "Vorlage erfolgreich aktualisiert.";
                } else {
                    $sql = "INSERT INTO global_label_templates 
                            (name, manufacturer, product_name, width_mm, height_mm, margin_top_mm, margin_bottom_mm, margin_left_mm, margin_right_mm, `cols`, `rows`, col_gap_mm, row_gap_mm) 
                            VALUES (:name, :manufacturer, :product_name, :width_mm, :height_mm, :margin_top_mm, :margin_bottom_mm, :margin_left_mm, :margin_right_mm, :cols, :rows, :col_gap_mm, :row_gap_mm)";
                    $pdo->prepare($sql)->execute($data);
                    $message = "Neue Vorlage erfolgreich gespeichert.";
                }
                
                if ($totalWidth > 210.1 || $totalHeight > 297.1) {
                    $message .= " (Hinweis: Maße überschreiten A4!)";
                }
            } catch (Exception $e) { $error = "Datenbankfehler: " . $e->getMessage(); }
        }
    }

    if ($action === 'delete' && $id > 0) {
        $pdo->prepare("DELETE FROM global_label_templates WHERE id = ?")->execute([$id]);
        $message = "Vorlage erfolgreich gelöscht.";
    }
}

// Alle Vorlagen für die Liste abrufen
$templates = $pdo->query("SELECT * FROM global_label_templates ORDER BY name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vorlagenverwaltung (Admin)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
    <style>
        .template-list-container {
            height: calc(100vh - 250px);
            overflow-y: auto;
            background: rgba(15, 23, 42, 0.4);
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.05);
        }
        .template-item {
            cursor: pointer;
            padding: 12px 15px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            transition: all 0.2s;
            color: #cbd5e1; /* Helles Grau für bessere Lesbarkeit */
        }
        .template-item:hover { 
            background: rgba(59, 130, 246, 0.15); 
            color: #ffffff;
        }
        .template-item.active { 
            background: rgba(59, 130, 246, 0.3); 
            border-left: 4px solid var(--accent);
            color: #ffffff;
        }
        .template-item .text-secondary {
            color: #94a3b8 !important; /* Etwas helleres Grau für Meta-Infos */
            transition: color 0.2s;
        }
        .template-item:hover .text-secondary,
        .template-item.active .text-secondary {
            color: #cbd5e1 !important;
        }
        
        .section-header {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--accent);
            border-bottom: 1px solid rgba(59, 130, 246, 0.2);
            padding-bottom: 5px;
            margin-bottom: 15px;
            font-weight: 700;
        }
        
        .form-label { color: #94a3b8; font-size: 0.85rem; margin-bottom: 3px; }
        .input-group-text { background: rgba(30, 41, 59, 0.8); border-color: rgba(255,255,255,0.1); color: #94a3b8; }
        
        .btn-panel {
            background: rgba(30, 41, 59, 0.5);
            padding: 15px;
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.05);
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark mb-4 shadow-sm">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="index.php">
            <div class="logo-wrapper me-2 d-flex align-items-center justify-content-center bg-danger bg-gradient rounded shadow-sm" style="width: 32px; height: 32px;">
                <i class="bi bi-shield-lock text-white" style="font-size: 1.1rem;"></i>
            </div>
            <span>ADMIN BEREICH</span>
        </a>
        <div class="ms-auto d-flex align-items-center">
            <a href="logout.php" class="btn btn-outline-danger btn-sm rounded-pill px-3 shadow-sm border-danger me-2">
                <i class="bi bi-power me-1"></i> Logout
            </a>
            <a href="index.php" class="btn btn-outline-light btn-sm rounded-pill px-3 shadow-sm border-secondary">
                <i class="bi bi-arrow-left me-1"></i> Dashboard
            </a>
        </div>
    </div>
</nav>

<div class="container py-2">
    <div class="row align-items-center mb-4">
        <div class="col-md-8">
            <h2 class="fw-bold m-0"><i class="bi bi-grid-1x2-fill me-2 text-info"></i>Vorlagenverwaltung</h2>
            <p class="text-secondary small mt-1">Zentrale Verwaltung der globalen Etiketten-Templates für alle Projekte.</p>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success border-success bg-success bg-opacity-10 text-success rounded-3 mb-3 small">
            <i class="bi bi-check-circle me-2"></i> <?= $message ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger border-danger bg-danger bg-opacity-10 text-danger rounded-3 mb-3 small">
            <i class="bi bi-exclamation-triangle me-2"></i> <?= $error ?>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- LINKE SPALTE: LISTE -->
        <div class="col-md-4">
            <div class="card bg-dark border-secondary rounded-4 overflow-hidden h-100 shadow-lg">
                <div class="card-header bg-dark border-secondary p-3">
                    <h6 class="m-0 fw-bold text-white">Vorlagendatenbank</h6>
                </div>
                <div class="template-list-container">
                    <?php if (empty($templates)): ?>
                        <div class="p-4 text-center text-muted small">Keine Vorlagen vorhanden.</div>
                    <?php else: ?>
                        <?php foreach ($templates as $t): ?>
                            <div class="template-item" onclick="loadTemplate(<?= $t['id'] ?>, this)">
                                <div class="fw-bold small"><?= htmlspecialchars($t['name']) ?></div>
                                <div class="text-secondary" style="font-size: 0.75rem;">
                                    <?= htmlspecialchars($t['manufacturer'] ?: 'Generic') ?> - <?= $t['width_mm'] ?>x<?= $t['height_mm'] ?>mm
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- RECHTE SPALTE: DETAILS -->
        <div class="col-md-8">
            <div class="card bg-dark border-secondary rounded-4 overflow-hidden shadow-lg mb-3">
                <div class="card-header bg-dark border-secondary p-3">
                    <h6 class="m-0 fw-bold text-white">Details & Abmessungen</h6>
                </div>
                <div class="card-body p-4">
                    <form id="templateForm" method="POST">
                        <input type="hidden" name="action" id="formAction" value="save">
                        <input type="hidden" name="id" id="templateId" value="0">

                        <!-- Basisdaten -->
                        <div class="section-header">Basisdaten</div>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Name der Vorlage:</label>
                                <input type="text" name="name" id="tplName" class="form-control bg-dark border-secondary text-light" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Hersteller:</label>
                                <input type="text" name="manufacturer" id="tplManufacturer" class="form-control bg-dark border-secondary text-light">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Produkt:</label>
                                <input type="text" name="product_name" id="tplProductName" class="form-control bg-dark border-secondary text-light">
                            </div>
                        </div>

                        <!-- Abmessungen -->
                        <div class="section-header">Abmessungen (Etikett)</div>
                        <div class="row g-3 mb-4">
                            <div class="col-md-3">
                                <label class="form-label">Breite:</label>
                                <div class="input-group">
                                    <input type="text" name="width_mm" id="tplWidth" class="form-control bg-dark border-secondary text-light" placeholder="0.0">
                                    <span class="input-group-text">mm</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Höhe:</label>
                                <div class="input-group">
                                    <input type="text" name="height_mm" id="tplHeight" class="form-control bg-dark border-secondary text-light" placeholder="0.0">
                                    <span class="input-group-text">mm</span>
                                </div>
                            </div>
                        </div>

                        <!-- Ränder -->
                        <div class="section-header">Ränder / Abstände (mm)</div>
                        <div class="row g-3 mb-4">
                            <div class="col-md-3">
                                <label class="form-label">Oben:</label>
                                <input type="text" name="margin_top_mm" id="tplMarginTop" class="form-control bg-dark border-secondary text-light" placeholder="0.0">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Unten:</label>
                                <input type="text" name="margin_bottom_mm" id="tplMarginBottom" class="form-control bg-dark border-secondary text-light" placeholder="0.0">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Links:</label>
                                <input type="text" name="margin_left_mm" id="tplMarginLeft" class="form-control bg-dark border-secondary text-light" placeholder="0.0">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Rechts:</label>
                                <input type="text" name="margin_right_mm" id="tplMarginRight" class="form-control bg-dark border-secondary text-light" placeholder="0.0">
                            </div>
                        </div>

                        <!-- Mehrfachetiketten -->
                        <div class="section-header">Mehrfachetiketten (Bogenlayout)</div>
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Spalten:</label>
                                <input type="number" name="cols" id="tplCols" class="form-control bg-dark border-secondary text-light" value="1">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Reihen:</label>
                                <input type="number" name="rows" id="tplRows" class="form-control bg-dark border-secondary text-light" value="1">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Spaltenabstand:</label>
                                <input type="text" name="col_gap_mm" id="tplColGap" class="form-control bg-dark border-secondary text-light" placeholder="0.0">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Reihenabstand:</label>
                                <input type="text" name="row_gap_mm" id="tplRowGap" class="form-control bg-dark border-secondary text-light" placeholder="0.0">
                            </div>
                        </div>

                        <!-- A4 Status Box -->
                        <div id="a4CheckResult" class="mt-4 p-3 rounded-3 d-none border">
                            <div class="d-flex align-items-center">
                                <div id="a4Icon" class="me-3 fs-3"></div>
                                <div>
                                    <div id="a4Title" class="fw-bold small mb-1">A4-Prüfung</div>
                                    <div id="a4Text" class="small opacity-75"></div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- BUTTON PANEL -->
            <div class="btn-panel d-flex justify-content-between align-items-center mb-5">
                <div>
                    <button type="button" class="btn btn-outline-danger btn-sm me-2 rounded-pill px-3" onclick="deleteTemplate()">
                        <i class="bi bi-trash3 me-1"></i> Ausgewählte Vorlage löschen
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" onclick="clearFields()">
                        <i class="bi bi-eraser me-1"></i> Felder leeren
                    </button>
                </div>
                <div>
                    <button type="button" class="btn btn-outline-success btn-sm me-2 rounded-pill px-3" onclick="submitForm('save_new')">
                        <i class="bi bi-plus-lg me-1"></i> Als NEUE Vorlage speichern
                    </button>
                    <button type="button" class="btn btn-primary btn-sm rounded-pill px-4" onclick="submitForm('save')">
                        <i class="bi bi-save me-1"></i> Änderungen speichern
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function loadTemplate(id, el) {
    document.querySelectorAll('.template-item').forEach(item => item.classList.remove('active'));
    el.classList.add('active');

    const fd = new FormData();
    fd.append('action', 'load');
    fd.append('id', id);

    fetch('templates.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            document.getElementById('templateId').value = data.id;
            document.getElementById('tplName').value = data.name;
            document.getElementById('tplManufacturer').value = data.manufacturer || '';
            document.getElementById('tplProductName').value = data.product_name || '';
            document.getElementById('tplWidth').value = data.width_mm;
            document.getElementById('tplHeight').value = data.height_mm;
            document.getElementById('tplMarginTop').value = data.margin_top_mm;
            document.getElementById('tplMarginBottom').value = data.margin_bottom_mm;
            document.getElementById('tplMarginLeft').value = data.margin_left_mm;
            document.getElementById('tplMarginRight').value = data.margin_right_mm;
            document.getElementById('tplCols').value = data.cols;
            document.getElementById('tplRows').value = data.rows;
            document.getElementById('tplColGap').value = data.col_gap_mm;
            document.getElementById('tplRowGap').value = data.row_gap_mm;
            
            updateA4Check();
        });
}

function updateA4Check() {
    const parse = (val) => parseFloat(String(val).replace(',', '.')) || 0;
    
    const w = parse(document.getElementById('tplWidth').value);
    const h = parse(document.getElementById('tplHeight').value);
    const ml = parse(document.getElementById('tplMarginLeft').value);
    const mr = parse(document.getElementById('tplMarginRight').value);
    const mt = parse(document.getElementById('tplMarginTop').value);
    const mb = parse(document.getElementById('tplMarginBottom').value);
    const cols = parseInt(document.getElementById('tplCols').value) || 1;
    const rows = parseInt(document.getElementById('tplRows').value) || 1;
    const gapX = parse(document.getElementById('tplColGap').value);
    const gapY = parse(document.getElementById('tplRowGap').value);

    const totalW = ml + mr + (cols * w) + ((cols - 1) * gapX);
    const totalH = mt + mb + (rows * h) + ((rows - 1) * gapY);

    const box = document.getElementById('a4CheckResult');
    const icon = document.getElementById('a4Icon');
    const title = document.getElementById('a4Title');
    const text = document.getElementById('a4Text');

    box.classList.remove('d-none');
    
    if (totalW > 210.1 || totalH > 297.1) {
        box.className = 'mt-4 p-3 rounded-3 border border-danger bg-danger bg-opacity-10 text-danger';
        icon.innerHTML = '<i class="bi bi-exclamation-octagon-fill"></i>';
        title.innerText = 'Layout zu groß für A4!';
        text.innerText = `Das Layout (${totalW.toFixed(1)} x ${totalH.toFixed(1)} mm) überschreitet die A4-Maße (210 x 297 mm). Ein Druck ist so nicht möglich.`;
    } else {
        box.className = 'mt-4 p-3 rounded-3 border border-success bg-success bg-opacity-10 text-success';
        icon.innerHTML = '<i class="bi bi-check-circle-fill"></i>';
        title.innerText = 'A4-Bogen Check';
        text.innerText = `Das Layout (${totalW.toFixed(1)} x ${totalH.toFixed(1)} mm) passt perfekt auf einen A4-Bogen.`;
    }
}

// Event-Listener für Live-Rechnung
['tplWidth', 'tplHeight', 'tplMarginLeft', 'tplMarginRight', 'tplMarginTop', 'tplMarginBottom', 'tplCols', 'tplRows', 'tplColGap', 'tplRowGap'].forEach(id => {
    document.getElementById(id).addEventListener('input', updateA4Check);
});

function clearFields() {
    document.getElementById('templateId').value = 0;
    document.getElementById('templateForm').reset();
    document.querySelectorAll('.template-item').forEach(item => item.classList.remove('active'));
}

function submitForm(action) {
    document.getElementById('formAction').value = action;
    document.getElementById('templateForm').submit();
}

function deleteTemplate() {
    const id = document.getElementById('templateId').value;
    if (id == 0) {
        alert("Bitte wählen Sie zuerst eine Vorlage aus.");
        return;
    }
    if (confirm("Möchten Sie diese Vorlage wirklich unwiderruflich löschen?")) {
        const f = document.createElement('form');
        f.method = 'POST';
        f.innerHTML = `<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="${id}">`;
        document.body.appendChild(f);
        f.submit();
    }
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
