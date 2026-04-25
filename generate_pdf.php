<?php
session_start();
require_once 'config.php';

$projectId = $_GET['id'] ?? null;
$startIndex = isset($_GET['start']) ? (int)$_GET['start'] : 1;
$copies = isset($_GET['copies']) ? (int)$_GET['copies'] : 1;
$showCalibration = isset($_GET['cal']) && $_GET['cal'] == '1';

if (!$projectId) die("Ungültige Projekt-ID");

// Projekt laden
$stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
$stmt->execute([$projectId]);
$project = $stmt->fetch();

// Format laden
$stmt = $pdo->prepare("SELECT * FROM label_formats WHERE project_id = ?");
$stmt->execute([$projectId]);
$format = $stmt->fetch();

$isRollFormat = (($format['media_type'] ?? 'sheet') === 'roll');
$pageWidthMm = $isRollFormat ? (float)$format['width_mm'] : 210.0;
$pageHeightMm = $isRollFormat ? (float)$format['height_mm'] : 297.0;

// Objekte laden
$stmt = $pdo->prepare("SELECT * FROM label_objects WHERE project_id = ? ORDER BY z_order ASC");
$stmt->execute([$projectId]);
$objects = $stmt->fetchAll();

// Gewählte Datensätze laden
$records = [];
// Projekt-Felder laden (werden für beide Modi benötigt)
$stmt = $pdo->prepare("SELECT * FROM project_fields WHERE project_id = ? ORDER BY position ASC");
$stmt->execute([$projectId]);
$fields = $stmt->fetchAll();

if (isset($_SESSION["csv_raw_13k_project_{$projectId}"])) {
    $csvData = $_SESSION["csv_raw_13k_project_{$projectId}"];
    $csvData = normalize_csv_to_utf8($csvData);
    $lines = explode("\n", str_replace("\r", "", $csvData));
    $headerLine = array_shift($lines);
    $delimiter = strpos($headerLine, ';') !== false ? ';' : ',';

    foreach ($lines as $idx => $line) {
        $selected = $_SESSION["csv_selected_{$projectId}"][$idx] ?? false;
        if (!$selected) continue; 

        $line = trim($line);
        if (!$line) continue;
        $row = str_getcsv($line, $delimiter, '"', '');

        $records[$idx] = [];
        foreach ($fields as $colIdx => $field) {
            $records[$idx][$field['name']] = $row[$colIdx] ?? '';
        }
    }
} else {
    // DB Modus
    $fieldMap = [];
    foreach($fields as $f) $fieldMap[$f['id']] = $f['name'];

    $stmt = $pdo->prepare("SELECT * FROM project_data_records WHERE project_id = ? ORDER BY id ASC");
    $stmt->execute([$projectId]);
    $dbRows = $stmt->fetchAll();
    foreach ($dbRows as $row) {
        $selected = $_SESSION["db_selected_{$projectId}"][$row['id']] ?? true;
        if (!$selected) continue;
        $data = json_decode($row['data_json'], true) ?? [];
        $mappedData = [];
        foreach($data as $fid => $val) {
            if(isset($fieldMap[$fid])) $mappedData[$fieldMap[$fid]] = $val;
        }
        $records[$row['id']] = $mappedData;
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Druckvorschau - <?= htmlspecialchars($project['name']) ?></title>
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Inter:wght@400;700&family=Roboto:wght@400;700&family=Montserrat:wght@400;700&display=swap" rel="stylesheet">
    <style>
        @page { size: <?= $pageWidthMm ?>mm <?= $pageHeightMm ?>mm; margin: 0; }
        body { margin: 0; padding: 0; background: #f0f0f0; font-family: 'Outfit', Arial, sans-serif; }
        .page {
            width: <?= $pageWidthMm ?>mm; height: <?= $pageHeightMm ?>mm; background: white; margin: <?= $isRollFormat ? '0 auto' : '10mm auto' ?>;
            position: relative; overflow: hidden; box-shadow: 0 0 10px rgba(0,0,0,0.2);
            page-break-after: always;
        }
        .label {
            position: absolute; width: <?= (float)$format['width_mm'] ?>mm; height: <?= (float)$format['height_mm'] ?>mm;
            box-sizing: border-box; overflow: hidden;
        }
        .calibration-frame {
            position: absolute;
            box-sizing: border-box;
            border: 1mm solid #ff0000;
            pointer-events: none;
            z-index: 1000;
        }
        .label-object { position: absolute; overflow: hidden; display: flex; align-items: center; }
        .label-object.text-center { justify-content: center; }
        .label-object.text-left { justify-content: flex-start; }
        .label-object.text-right { justify-content: flex-end; }
        .text-vertical { writing-mode: vertical-rl; text-orientation: upright; letter-spacing: -2px; }
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
$hasCsvData = isset($_SESSION["csv_raw_13k_project_{$projectId}"]);
if (empty($records)) {
    if ($hasCsvData) {
        // CSV vorhanden, aber keine Zeile ausgewählt
        echo '<div style="padding:50px; text-align:center;"><h2>Keine Datensätze ausgewählt.</h2><p>Bitte haken Sie in der Projektansicht die gewünschten Zeilen an.</p></div>';
        exit;
    }
    // Kein CSV – identisches Etikett mehrfach rendern (Kopien)
    $copyCount = max(1, min($copies, 5000));
    $records = array_fill(0, $copyCount, null);
}

$labelsPerPage = $isRollFormat ? 1 : max(1, (int)$format['cols'] * (int)$format['rows']);
$printQueue = array_values($records);
$startOffset = $isRollFormat ? 0 : max(0, $startIndex - 1);

$pageOpen = false;
$currentPage = -1;
foreach ($printQueue as $idx => $record) {
    $layoutIndex = $idx + $startOffset;
    $targetPage = (int)floor($layoutIndex / $labelsPerPage);

    if (!$pageOpen || $targetPage !== $currentPage) {
        if ($pageOpen) echo '</div>';
        echo '<div class="page">';
        $pageOpen = true;
        $currentPage = $targetPage;
    }

    if ($isRollFormat) {
        $left = 0;
        $top = 0;
    } else {
        $colsOnSheet = max(1, (int)$format['cols']);
        $indexOnPage = $layoutIndex % $labelsPerPage;
        $col = $indexOnPage % $colsOnSheet;
        $row = floor($indexOnPage / $colsOnSheet);

        $left = $format['margin_left_mm'] + ($col * ($format['width_mm'] + $format['col_gap_mm']));
        $top = $format['margin_top_mm'] + ($row * ($format['height_mm'] + $format['row_gap_mm']));
    }

    $scale = (float)($format['print_scale'] ?? 100.0) / 100.0;
    $scaleStyle = ($scale != 1.0) ? "transform: scale($scale); transform-origin: top left;" : "";

    echo "<div class='label' style='left:{$left}mm; top:{$top}mm;'>";
    echo "<div style='width:100%; height:100%; position:relative; {$scaleStyle}'>";
    if ($showCalibration) {
        $frameW = $format['width_mm'] - 1;
        $frameH = $format['height_mm'] - 1;
        echo "<div class='calibration-frame' style='left:0.5mm; top:0.5mm; width:{$frameW}mm; height:{$frameH}mm;'></div>";
    }
    if ($record !== null || !$hasCsvData) {
        foreach ($objects as $obj) {
            $p = $obj['properties'];
            if (is_string($p)) $p = json_decode($p, true) ?: [];

            $txt = $p['content'] ?? '';
            if ($record) {
                foreach ($record as $k => $v) {
                    $txt = str_ireplace("[~$k~]", (string)$v, $txt);
                }
            }

            $rotation = (float)($obj['rotation'] ?? 0);
            $rotStyle = $rotation != 0 ? "transform: rotate({$rotation}deg); transform-origin: center center;" : '';
            $style = "left:{$obj['x_mm']}mm; top:{$obj['y_mm']}mm; width:{$obj['width_mm']}mm; height:{$obj['height_mm']}mm; color:black !important; {$rotStyle}";

            if ($obj['type'] === 'text') {
                $fs = $p['font_size'] ?? 10;
                $ff = $p['font_family'] ?? "'Outfit', sans-serif";
                $bold = !empty($p['bold']) ? 'font-weight:bold;' : '';
                $italic = !empty($p['italic']) ? 'font-style:italic;' : '';
                $vClass = !empty($p['vertical']) ? 'text-vertical' : '';
                $textAlign = $p['text_align'] ?? 'center';
                echo "<div class='label-object $vClass text-{$textAlign}' style='{$style} font-size:{$fs}pt; font-family:{$ff}; {$bold} {$italic}'>".htmlspecialchars($txt)."</div>";
            } elseif ($obj['type'] === 'image') {
                $imgData = $p['image_data'] ?? '';
                // Sicherheitsprüfung: nur erlaubte Data-URLs
                if ($imgData && (strncmp($imgData, 'data:image/jpeg;base64,', 23) === 0 || strncmp($imgData, 'data:image/png;base64,', 22) === 0)) {
                    echo "<div class='label-object' style='{$style}'><img src='" . $imgData . "' style='width:100%; height:100%; object-fit:contain; display:block;'></div>";
                }
            } else {
                $showHTR = isset($p['show_htr']) ? ($p['show_htr'] ? 'true' : 'false') : 'true';
                echo "<div class='label-object' style='{$style}'><canvas class='barcode-render' data-type='".($p['barcode_type']??'code128')."' data-content='".htmlspecialchars($txt)."' data-htr='{$showHTR}' style='width:100%; height:100%;'></canvas></div>";
            }
        }
    }
    echo "</div>"; // End of scale div
    echo "</div>"; // End of label div
}
if ($pageOpen) echo '</div>';
?>

<script src="https://cdn.jsdelivr.net/npm/bwip-js/dist/bwip-js-min.js"></script>
<script>
window.onload = () => {
    document.querySelectorAll('.barcode-render').forEach(canvas => {
        try {
            let bType = canvas.getAttribute('data-type');
            let content = canvas.getAttribute('data-content');

            // EAN Validierung
            let hasError = false;
            let errorMsg = "";
            if (bType === 'ean8' && !/^\d{8}$/.test(content)) {
                hasError = true; errorMsg = "EAN8 ERROR\n(8 Ziffern!)";
            } else if (bType === 'ean13' && !/^\d{12,13}$/.test(content)) {
                hasError = true; errorMsg = "EAN13 ERROR\n(12-13 Ziffern!)";
            }

            if (hasError) {
                const ctx = canvas.getContext('2d');
                canvas.width = 400; canvas.height = 200;
                ctx.fillStyle = "#fef2f2";
                ctx.fillRect(0, 0, 400, 200);
                ctx.strokeStyle = "#ef4444";
                ctx.lineWidth = 10;
                ctx.strokeRect(5, 5, 390, 190);
                ctx.fillStyle = "#ef4444";
                ctx.font = "bold 40px Arial";
                ctx.textAlign = "center";
                ctx.fillText(errorMsg.split('\n')[0], 200, 90);
                ctx.font = "30px Arial";
                ctx.fillText(errorMsg.split('\n')[1], 200, 140);
                return;
            }

            let isQR = bType === 'qr';
            if (isQR) bType = 'qrcode';

            const opts = {
                bcid: bType,
                text: content,
                scale: 3,
                includetext: isQR ? false : (canvas.getAttribute('data-htr') !== 'false')
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
