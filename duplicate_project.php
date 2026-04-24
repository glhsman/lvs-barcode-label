<?php
session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

$projectId = (int)($_POST['project_id'] ?? 0);
$newName    = trim($_POST['new_name'] ?? '');
$locationId = (int)($_POST['location_id'] ?? 0);

if (!$projectId || $newName === '' || !$locationId) {
    header("Location: index.php");
    exit;
}

// Quell-Projekt laden
$stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
$stmt->execute([$projectId]);
$srcProject = $stmt->fetch();
if (!$srcProject) {
    header("Location: index.php");
    exit;
}

// Quell-Format laden
$stmt = $pdo->prepare("SELECT * FROM label_formats WHERE project_id = ?");
$stmt->execute([$projectId]);
$srcFormat = $stmt->fetch();

// Quell-Objekte laden
$stmt = $pdo->prepare("SELECT * FROM label_objects WHERE project_id = ? ORDER BY z_order ASC");
$stmt->execute([$projectId]);
$srcObjects = $stmt->fetchAll();

// Quell-Felder laden
$stmt = $pdo->prepare("SELECT * FROM project_fields WHERE project_id = ? ORDER BY position ASC");
$stmt->execute([$projectId]);
$srcFields = $stmt->fetchAll();

try {
    $pdo->beginTransaction();

    // Neues Projekt anlegen
    $stmt = $pdo->prepare("INSERT INTO projects (location_id, name, csv_filename) VALUES (?, ?, ?)");
    $stmt->execute([$srcProject['location_id'], $newName, $srcProject['csv_filename']]);
    $newProjectId = $pdo->lastInsertId();

    // Format kopieren
    if ($srcFormat) {
        $stmt = $pdo->prepare("
            INSERT INTO label_formats
                (project_id, template_id, manufacturer, product_name,
                 width_mm, height_mm,
                 margin_top_mm, margin_bottom_mm, margin_left_mm, margin_right_mm,
                 `cols`, `rows`, col_gap_mm, row_gap_mm,
                 show_calibration_border, print_scale, media_type)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $newProjectId,
            $srcFormat['template_id'] ?? null,
            $srcFormat['manufacturer'] ?? null,
            $srcFormat['product_name'] ?? null,
            $srcFormat['width_mm'],
            $srcFormat['height_mm'],
            $srcFormat['margin_top_mm'],
            $srcFormat['margin_bottom_mm'],
            $srcFormat['margin_left_mm'],
            $srcFormat['margin_right_mm'],
            $srcFormat['cols'],
            $srcFormat['rows'],
            $srcFormat['col_gap_mm'],
            $srcFormat['row_gap_mm'],
            $srcFormat['show_calibration_border'] ?? 0,
            $srcFormat['print_scale'] ?? 100.0,
            $srcFormat['media_type'] ?? 'sheet',
        ]);
    }

    // Felder kopieren
    $stmtField = $pdo->prepare("INSERT INTO project_fields (project_id, name, position) VALUES (?, ?, ?)");
    foreach ($srcFields as $field) {
        $stmtField->execute([$newProjectId, $field['name'], $field['position']]);
    }

    // Objekte kopieren
    $stmtObj = $pdo->prepare("
        INSERT INTO label_objects
            (project_id, type, x_mm, y_mm, width_mm, height_mm, rotation, z_order, properties)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    foreach ($srcObjects as $obj) {
        $stmtObj->execute([
            $newProjectId,
            $obj['type'],
            $obj['x_mm'],
            $obj['y_mm'],
            $obj['width_mm'],
            $obj['height_mm'],
            $obj['rotation'],
            $obj['z_order'],
            $obj['properties'],
        ]);
    }

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    die("Fehler beim Duplizieren: " . htmlspecialchars($e->getMessage()));
}

header("Location: index.php?location_id=" . $locationId);
exit;
