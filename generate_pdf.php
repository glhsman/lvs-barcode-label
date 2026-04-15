<?php
session_start();
require_once 'config.php';

$projectId = $_GET['id'] ?? null;
$startIndex = isset($_GET['start']) ? (int)$_GET['start'] : 1;

if (!$projectId) die("Ungültige Projekt-ID");

// Projekt laden
$stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
$stmt->execute([$projectId]);
$project = $stmt->fetch();

// Format laden
$stmt = $pdo->prepare("SELECT * FROM label_formats WHERE project_id = ?");
$stmt->execute([$projectId]);
$format = $stmt->fetch();

// Objekte laden
$stmt = $pdo->prepare("SELECT * FROM label_objects WHERE project_id = ? ORDER BY z_order ASC");
$stmt->execute([$projectId]);
$objects = $stmt->fetchAll();

// Gewählte Datensätze laden (aus Session!)
$records = [];
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
        if (!$selected) continue; // Nur gewählte drucken!
        
        $line = trim($line);
        if (!$line) continue;
        $row = str_getcsv($line, $delimiter);
        
        $records[$idx] = [];
        foreach ($fields as $colIdx => $field) {
            $records[$idx][$field['name']] = $row[$colIdx] ?? '';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Druckvorschau - <?= htmlspecialchars($project['name']) ?></title>
    <style>
        @page { size: A4; margin: 0; }
        body { margin: 0; padding: 0; background: #f0f0f0; font-family: Arial, sans-serif; }
        .page { 
            width: 210mm; height: 297mm; background: white; margin: 10mm auto; 
            position: relative; overflow: hidden; box-shadow: 0 0 10px rgba(0,0,0,0.2);
            page-break-after: always;
        }
        .label { 
            position: absolute; width: <?= $format['width_mm'] ?>mm; height: <?= $format['height_mm'] ?>mm;
            box-sizing: border-box; overflow: hidden;
        }
        .label-object { position: absolute; overflow: hidden; }
        @media print {
            body { background: none; }
            .page { margin: 0; box-shadow: none; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

<div class="no-print" style="position: fixed; top: 15px; right: 20px; z-index: 1000;">
    <button onclick="window.print()" style="background-color: #10b981; color: white; border: none; padding: 10px 20px; font-size: 16px; border-radius: 8px; cursor: pointer; box-shadow: 0 4px 6px rgba(0,0,0,0.1); font-weight: bold;">
        🖨️ Jetzt Drucken
    </button>
</div>

<?php
if (empty($records)) {
    echo '<div style="padding:50px; text-align:center;"><h2>Keine Datensätze ausgewählt.</h2><p>Bitte haken Sie in der Projektansicht die gewünschten Zeilen an.</p></div>';
    exit;
}

$labelsPerPage = $format['cols'] * $format['rows'];
$printQueue = array_values($records);

// Start-Position berücksichtigen (Leere Etiketten am Anfang)
for ($i = 1; $i < $startIndex; $i++) array_unshift($printQueue, null);

$pageCount = 0;
$pageOpen = false;
foreach ($printQueue as $idx => $record) {
    if ($idx % $labelsPerPage == 0) {
        if ($pageOpen) echo '</div>';
        echo '<div class="page">';
        $pageOpen = true;
    }

    $col = $idx % $format['cols'];
    $row = floor(($idx % $labelsPerPage) / $format['cols']);
    
    $left = $format['margin_left_mm'] + ($col * ($format['width_mm'] + $format['col_gap_mm']));
    $top = $format['margin_top_mm'] + ($row * ($format['height_mm'] + $format['row_gap_mm']));

    echo "<div class='label' style='left:{$left}mm; top:{$top}mm;'>";
    if ($record) {
        foreach ($objects as $obj) {
            $p = $obj['properties'];
            if (is_string($p)) $p = json_decode($p, true) ?: [];
            
            $txt = $p['content'] ?? '';
            foreach ($record as $k => $v) {
                $txt = str_ireplace("[~$k~]", (string)$v, $txt);
            }

            $style = "left:{$obj['x_mm']}mm; top:{$obj['y_mm']}mm; width:{$obj['width_mm']}mm; height:{$obj['height_mm']}mm; color:black !important;";
            
            if ($obj['type'] === 'text') {
                $fs = $p['font_size'] ?? 10;
                echo "<div class='label-object' style='{$style} font-size:{$fs}pt;'>".htmlspecialchars($txt)."</div>";
            } else {
                echo "<div class='label-object' style='{$style}'><canvas class='barcode-render' data-type='".($p['barcode_type']??'code128')."' data-content='".htmlspecialchars($txt)."' style='width:100%; height:100%;'></canvas></div>";
            }
        }
    }
    echo "</div>";
}
if ($pageOpen) echo '</div>';
?>

<script src="https://cdn.jsdelivr.net/npm/bwip-js/dist/bwip-js-min.js"></script>
<script>
window.onload = () => {
    document.querySelectorAll('.barcode-render').forEach(canvas => {
        try {
            let bType = canvas.getAttribute('data-type');
            let isQR = bType === 'qr';
            if (isQR) bType = 'qrcode';
            
            const opts = {
                bcid: bType,
                text: canvas.getAttribute('data-content'),
                scale: 3, 
                includetext: !isQR
            };
            if(!isQR) opts.height = 10;
            
            bwipjs.toCanvas(canvas, opts);
            canvas.style.objectFit = 'contain';
        } catch (e) { console.error(e); }
    });
};
</script>
</body>
</html>
