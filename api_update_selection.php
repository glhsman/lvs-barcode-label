<?php
require_once 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recordId = $_POST['record_id'] ?? null;
    $selected = isset($_POST['selected']) ? (int)$_POST['selected'] : 1;
    
    if (!$recordId) {
        echo json_encode(['success' => false, 'message' => 'Keine Record-ID']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE data_records SET selected = ? WHERE id = ?");
        $stmt->execute([$selected, $recordId]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
