<?php
require_once 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $projectId = $_POST['project_id'] ?? null;
    
    if (!$projectId) {
        echo json_encode(['success' => false, 'message' => 'Keine Projekt-ID']);
        exit;
    }

    try {
        $pId = $projectId;
        $stmt = $pdo->prepare("
            UPDATE label_formats SET 
            width_mm = ?, 
            height_mm = ?, 
            `cols` = ?, 
            `rows` = ?,
            margin_top_mm = ?,
            margin_bottom_mm = ?,
            margin_left_mm = ?,
            margin_right_mm = ?,
            col_gap_mm = ?,
            row_gap_mm = ?,
            template_id = ?
            WHERE project_id = ?
        ");
        
        $stmt->execute([
            (float)($_POST["width_mm_$pId"] ?? 0),
            (float)($_POST["height_mm_$pId"] ?? 0),
            (int)($_POST["cols_$pId"] ?? 1),
            (int)($_POST["rows_$pId"] ?? 1),
            (float)($_POST["margin_top_mm_$pId"] ?? 0),
            (float)($_POST["margin_bottom_mm_$pId"] ?? 0),
            (float)($_POST["margin_left_mm_$pId"] ?? 0),
            (float)($_POST["margin_right_mm_$pId"] ?? 0),
            (float)($_POST["col_gap_mm_$pId"] ?? 0),
            (float)($_POST["row_gap_mm_$pId"] ?? 0),
            ($_POST["template_id_$pId"] ? (int)$_POST["template_id_$pId"] : null),
            $pId
        ]);

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
