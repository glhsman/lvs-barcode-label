<?php
require_once 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $projectId = $_POST['project_id'] ?? null;
    $objectsJson = $_POST['objects'] ?? '[]';
    
    if (!$projectId) {
        echo json_encode(['success' => false, 'message' => 'Keine Projekt-ID']);
        exit;
    }

    $objects = json_decode($objectsJson, true);

    try {
        $pdo->beginTransaction();

        // 1. Alte Objekte löschen
        $stmt = $pdo->prepare("DELETE FROM label_objects WHERE project_id = ?");
        $stmt->execute([$projectId]);

        // 2. Neue Objekte einfügen
        $stmt = $pdo->prepare("
            INSERT INTO label_objects 
            (project_id, type, x_mm, y_mm, width_mm, height_mm, z_order, properties) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($objects as $index => $obj) {
            $stmt->execute([
                $projectId,
                $obj['type'],
                (float)$obj['x_mm'],
                (float)$obj['y_mm'],
                (float)$obj['width_mm'],
                (float)$obj['height_mm'],
                (int)($obj['z_order'] ?? $index),
                json_encode($obj['properties'])
            ]);
        }

        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
