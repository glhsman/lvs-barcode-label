<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recordId = $_POST['record_id'] ?? null;
    $selected = isset($_POST['selected']) ? (int)$_POST['selected'] : 1;
    $projectId = $_POST['project_id'] ?? null;
    
    if ($recordId === null && !isset($_POST['action_all'])) {
        echo json_encode(['success' => false, 'message' => 'Keine Record-ID oder Projekt-ID']);
        exit;
    }

    try {
        if (isset($_POST['action_all'])) {
            if (isset($_SESSION["csv_raw_13k_project_{$projectId}"])) {
                $linesCount = count(explode("\n", trim($_SESSION["csv_raw_13k_project_{$projectId}"]))) - 1;
                for ($i = 0; $i < $linesCount; $i++) {
                    $_SESSION["csv_selected_{$projectId}"][$i] = (bool)$selected;
                }
            }
        } else {
            $_SESSION["csv_selected_{$projectId}"][(int)$recordId] = (bool)$selected;
        }
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
