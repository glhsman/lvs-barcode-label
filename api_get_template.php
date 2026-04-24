<?php
require_once 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['error' => 'Invalid ID']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM global_label_templates WHERE id = ?");
$stmt->execute([$id]);
$tpl = $stmt->fetch();

if (!$tpl) {
    echo json_encode(['error' => 'Not found']);
    exit;
}

echo json_encode($tpl);
