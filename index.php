<?php
session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_project_id'])) {
    $delId = (int)$_POST['delete_project_id'];
    // Löscht dank ON DELETE CASCADE auch formats, objects und project_fields!
    $pdo->prepare("DELETE FROM projects WHERE id = ?")->execute([$delId]);
    
    // RAM-Leichen löschen
    unset($_SESSION["csv_raw_13k_project_{$delId}"]);
    unset($_SESSION["csv_selected_{$delId}"]);
    
    header("Location: index.php");
    exit;
}

// Alle Projekte abrufen
$stmt = $pdo->query("SELECT * FROM projects ORDER BY modified_at DESC");
$projects = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barcode Etiketten - Projekte</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark mb-4">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="index.php">
            <div class="logo-wrapper me-2 d-flex align-items-center justify-content-center bg-primary bg-gradient rounded shadow-sm" style="width: 32px; height: 32px;">
                <i class="bi bi-upc-scan text-white" style="font-size: 1.1rem;"></i>
            </div>
            <span>BARCODE SYSTEM</span>
        </a>
    </div>
</nav>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Meine Projekte</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newProjectModal">Neues Projekt / CSV Import</button>
    </div>

    <div class="row row-cols-1 row-cols-md-3 g-4">
        <?php if (empty($projects)): ?>
            <div class="col-12">
                <div class="alert alert-info">Keine Projekte gefunden. Importieren Sie eine CSV-Datei, um zu starten.</div>
            </div>
        <?php else: ?>
            <?php foreach ($projects as $project): ?>
                <div class="col">
                    <div class="card h-100 project-card" onclick="location.href='project_view.php?id=<?= $project['id'] ?>'">
                        <div class="card-body position-relative">
                            <h5 class="card-title pe-4"><?php echo htmlspecialchars($project['name']); ?></h5>
                            <p class="card-text text-muted small"><?php echo htmlspecialchars($project['description'] ?? ''); ?></p>
                            
                            <!-- Löschen-Button -->
                            <form method="POST" action="index.php" class="position-absolute" style="right: 15px; top: 15px;" onsubmit="event.stopPropagation();">
                                <input type="hidden" name="delete_project_id" value="<?= $project['id'] ?>">
                                <button type="submit" class="btn btn-outline-danger btn-sm border-0 px-2" onclick="return confirm('Möchtest du das Projekt \'<?= htmlspecialchars($project['name']) ?>\' inklusive aller Layouts und Designs wirklich endgültig löschen?')" title="Projekt unwiderruflich löschen">
                                    <i class="bi bi-trash3"></i>
                                </button>
                            </form>
                        </div>
                        <div class="card-footer bg-transparent border-top-0">
                            <small class="text-muted">Geändert: <?php echo date('d.m.Y H:i', strtotime($project['modified_at'])); ?></small>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal für neues Projekt (Platzhalter) -->
<div class="modal fade" id="newProjectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Neues Projekt erstellen</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
            </div>
            <form action="import_csv.php" method="post" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="projectName" class="form-label">Projektname</label>
                        <input type="text" class="form-control" id="projectName" name="project_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="csvFile" class="form-label">CSV-Datei auswählen</label>
                        <input class="form-control" type="file" id="csvFile" name="csv_file" accept=".csv" required>
                    </div>
                    <div class="form-text">Die CSV sollte Spalten wie MatNr, Bezeichnung und EAN enthalten.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-primary">Hochladen & Importieren</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
