<?php
require_once 'config.php';

header('Content-Type: application/json');

$projectId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;

if (!$projectId) {
    echo json_encode(['success' => false, 'message' => 'Keine Projekt-ID']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM label_objects WHERE project_id = ? ORDER BY z_order ASC");
$stmt->execute([$projectId]);
$objects = $stmt->fetchAll();

foreach ($objects as &$obj) {
    if (is_string($obj['properties'])) {
        $obj['properties'] = json_decode($obj['properties'], true) ?? [];
    }
}
unset($obj);

echo json_encode(['success' => true, 'objects' => $objects]);
