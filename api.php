<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if (!$action) {
    echo json_encode(['success' => false, 'message' => 'Keine Action angegeben']);
    exit;
}

try {
    switch ($action) {
        case 'get_objects':
            $projectId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
            if (!$projectId) throw new Exception('Keine Projekt-ID');
            
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
            break;

        case 'get_template':
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) throw new Exception('Invalid ID');
            
            $stmt = $pdo->prepare("SELECT * FROM global_label_templates WHERE id = ?");
            $stmt->execute([$id]);
            $tpl = $stmt->fetch();
            
            if (!$tpl) throw new Exception('Not found');
            echo json_encode($tpl);
            break;

        case 'save_objects':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('POST required');
            $projectId = $_POST['project_id'] ?? null;
            $objectsJson = $_POST['objects'] ?? '[]';
            if (!$projectId) throw new Exception('Keine Projekt-ID');
            
            $objects = json_decode($objectsJson, true);
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM label_objects WHERE project_id = ?")->execute([$projectId]);
            $stmt = $pdo->prepare("INSERT INTO label_objects (project_id, type, x_mm, y_mm, width_mm, height_mm, z_order, properties) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($objects as $index => $obj) {
                $stmt->execute([
                    $projectId, $obj['type'], (float)$obj['x_mm'], (float)$obj['y_mm'],
                    (float)$obj['width_mm'], (float)$obj['height_mm'], (int)($obj['z_order'] ?? $index),
                    json_encode($obj['properties'])
                ]);
            }
            $pdo->commit();
            echo json_encode(['success' => true]);
            break;

        case 'update_format':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('POST required');
            $projectId = $_POST['project_id'] ?? null;
            if (!$projectId) throw new Exception('Keine Projekt-ID');
            
            $pId = $projectId;
            $stmt = $pdo->prepare("UPDATE label_formats SET width_mm = ?, height_mm = ?, `cols` = ?, `rows` = ?, margin_top_mm = ?, margin_bottom_mm = ?, margin_left_mm = ?, margin_right_mm = ?, col_gap_mm = ?, row_gap_mm = ?, template_id = ?, show_calibration_border = ?, print_scale = ?, media_type = ? WHERE project_id = ?");
            
            $mediaType = $_POST["media_type_$pId"] ?? 'sheet';
            if (!in_array($mediaType, ['sheet', 'roll'], true)) $mediaType = 'sheet';
            
            $stmt->execute([
                (float)str_replace(',', '.', $_POST["width_mm_$pId"] ?? 0),
                (float)str_replace(',', '.', $_POST["height_mm_$pId"] ?? 0),
                (int)($_POST["cols_$pId"] ?? 1),
                (int)($_POST["rows_$pId"] ?? 1),
                (float)str_replace(',', '.', $_POST["margin_top_mm_$pId"] ?? 0),
                (float)str_replace(',', '.', $_POST["margin_bottom_mm_$pId"] ?? 0),
                (float)str_replace(',', '.', $_POST["margin_left_mm_$pId"] ?? 0),
                (float)str_replace(',', '.', $_POST["margin_right_mm_$pId"] ?? 0),
                (float)str_replace(',', '.', $_POST["col_gap_mm_$pId"] ?? 0),
                (float)str_replace(',', '.', $_POST["row_gap_mm_$pId"] ?? 0),
                ($_POST["template_id_$pId"] ? (int)$_POST["template_id_$pId"] : null),
                (isset($_POST["show_calibration_border_$pId"]) ? 1 : 0),
                (float)str_replace(',', '.', $_POST["print_scale_$pId"] ?? 100.0),
                $mediaType,
                $pId
            ]);
            echo json_encode(['success' => true]);
            break;

        case 'get_records':
            $projectId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
            if (!$projectId) throw new Exception('Keine Projekt-ID');
            $stmt = $pdo->prepare("SELECT * FROM project_data_records WHERE project_id = ? ORDER BY id ASC");
            $stmt->execute([$projectId]);
            $rows = $stmt->fetchAll();
            foreach ($rows as &$row) {
                $row['values'] = json_decode($row['data_json'], true) ?? [];
                $row['selected'] = $_SESSION["db_selected_{$projectId}"][$row['id']] ?? true;
            }
            echo json_encode(['success' => true, 'records' => $rows]);
            break;

        case 'add_record':
            $projectId = $_POST['project_id'] ?? null;
            $dataJson = $_POST['data_json'] ?? '{}';
            if (!$projectId) throw new Exception('Keine Projekt-ID');
            $stmt = $pdo->prepare("INSERT INTO project_data_records (project_id, data_json) VALUES (?, ?)");
            $stmt->execute([$projectId, $dataJson]);
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
            break;

        case 'update_record':
            $id = $_POST['id'] ?? null;
            $dataJson = $_POST['data_json'] ?? '{}';
            if (!$id) throw new Exception('Keine ID');
            $stmt = $pdo->prepare("UPDATE project_data_records SET data_json = ? WHERE id = ?");
            $stmt->execute([$dataJson, $id]);
            echo json_encode(['success' => true]);
            break;

        case 'delete_record':
            $id = $_POST['id'] ?? null;
            if (!$id) throw new Exception('Keine ID');
            $stmt = $pdo->prepare("DELETE FROM project_data_records WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
            break;

        case 'save_fields':
            $projectId = $_POST['project_id'] ?? null;
            $fields = json_decode($_POST['fields'] ?? '[]', true);
            if (!$projectId) throw new Exception('Keine Projekt-ID');
            
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM project_fields WHERE project_id = ?")->execute([$projectId]);
            $stmt = $pdo->prepare("INSERT INTO project_fields (project_id, name, position) VALUES (?, ?, ?)");
            foreach ($fields as $idx => $fName) {
                if (trim($fName)) $stmt->execute([$projectId, trim($fName), $idx]);
            }
            $pdo->commit();
            echo json_encode(['success' => true]);
            break;

        case 'update_selection_batch':
            $ids = json_decode($_POST['ids'] ?? '[]', true);
            $selected = isset($_POST['selected']) ? (bool)$_POST['selected'] : true;
            $projectId = $_POST['project_id'] ?? null;
            $isDbMode = isset($_POST['is_db_mode']) && $_POST['is_db_mode'] == '1';
            $sessionKey = $isDbMode ? "db_selected_{$projectId}" : "csv_selected_{$projectId}";
            foreach ($ids as $id) {
                $_SESSION[$sessionKey][(int)$id] = $selected;
            }
            echo json_encode(['success' => true]);
            break;

        case 'update_selection':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('POST required');
            $recordId = $_POST['record_id'] ?? null;
            $selected = isset($_POST['selected']) ? (int)$_POST['selected'] : 1;
            $projectId = $_POST['project_id'] ?? null;
            $isDbMode = isset($_POST['is_db_mode']) && $_POST['is_db_mode'] == '1';
            
            if ($recordId === null && !isset($_POST['action_all'])) throw new Exception('Keine Record-ID oder Projekt-ID');
            
            $sessionKey = $isDbMode ? "db_selected_{$projectId}" : "csv_selected_{$projectId}";

            if (isset($_POST['action_all'])) {
                if ($isDbMode) {
                    $stmt = $pdo->prepare("SELECT id FROM project_data_records WHERE project_id = ?");
                    $stmt->execute([$projectId]);
                    while($row = $stmt->fetch()) $_SESSION[$sessionKey][$row['id']] = (bool)$selected;
                } else if (isset($_SESSION["csv_raw_13k_project_{$projectId}"])) {
                    $linesCount = count(explode("\n", trim($_SESSION["csv_raw_13k_project_{$projectId}"]))) - 1;
                    for ($i = 0; $i < $linesCount; $i++) $_SESSION[$sessionKey][$i] = (bool)$selected;
                }
            } else {
                $_SESSION[$sessionKey][(int)$recordId] = (bool)$selected;
            }
            echo json_encode(['success' => true]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Unbekannte Action']);
            break;
    }
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
