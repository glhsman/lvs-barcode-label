<?php
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

// Gewählte Datensätze laden
$stmt = $pdo->prepare("
    SELECT dr.id as record_id, pf.name as field_name, rv.value 
    FROM data_records dr
    JOIN project_fields pf ON pf.project_id = dr.project_id
    JOIN record_values rv ON rv.record_id = dr.id AND rv.field_id = pf.id
    WHERE dr.project_id = ? AND dr.selected = 1
    ORDER BY dr.position ASC, pf.position ASC
");
$stmt->execute([$projectId]);
$rawData = $stmt->fetchAll();

$records = [];
foreach ($rawData as $row) {
    if (!isset($records[$row['record_id']])) $records[$row['record_id']] = [];
    $records[$row['record_id']][$row['field_name']] = $row['value'];
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
            bwipjs.toCanvas(canvas, {
                bcid: canvas.getAttribute('data-type'),
                text: canvas.getAttribute('data-content'),
                scale: 3, height: 10, includetext: true
            });
        } catch (e) { console.error(e); }
    });
};
</script>
</body>
</html>
