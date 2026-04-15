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
            row_gap_mm = ?
            WHERE project_id = ?
        ");
        
        $stmt->execute([
            (float)$_POST['width_mm'],
            (float)$_POST['height_mm'],
            (int)$_POST['cols'],
            (int)$_POST['rows'],
            (float)$_POST['margin_top_mm'],
            (float)$_POST['margin_bottom_mm'],
            (float)$_POST['margin_left_mm'],
            (float)$_POST['margin_right_mm'],
            (float)$_POST['col_gap_mm'],
            (float)$_POST['row_gap_mm'],
            $projectId
        ]);

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
