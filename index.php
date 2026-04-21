<?php
session_start();
require_once 'config.php';

// Standort abrufen (falls gewählt)
$locationId = $_GET['location_id'] ?? null;
$location = null;

if ($locationId) {
    $stmt = $pdo->prepare("SELECT * FROM locations WHERE id = ?");
    $stmt->execute([$locationId]);
    $location = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_project_id'])) {
    $delId = (int)$_POST['delete_project_id'];
    $pdo->prepare("DELETE FROM projects WHERE id = ?")->execute([$delId]);

    unset($_SESSION["csv_raw_13k_project_{$delId}"]);
    unset($_SESSION["csv_selected_{$delId}"]);

    $redirectUrl = $locationId ? "index.php?location_id=$locationId" : "index.php";
    header("Location: $redirectUrl");
    exit;
}

// Daten abrufen
if ($locationId) {
    // Projekte für diesen Standort inkl. Formatgröße
    $stmt = $pdo->prepare("
        SELECT p.*, lf.width_mm, lf.height_mm
        FROM projects p
        LEFT JOIN label_formats lf ON lf.project_id = p.id
        WHERE p.location_id = ?
        ORDER BY p.modified_at DESC
    ");
    $stmt->execute([$locationId]);
    $projects = $stmt->fetchAll();
} else {
    // Alle Standorte auflisten
    $stmt = $pdo->query("
        SELECT l.id, l.name, l.logo_data, COUNT(p.id) as project_count
        FROM locations l
        LEFT JOIN projects p ON l.id = p.location_id
        GROUP BY l.id
        ORDER BY l.name ASC
    ");
    $locationsList = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barcode Etiketten - Projekte</title>
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
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
        <div class="ms-auto d-flex align-items-center">
            <a href="admin/index.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3 shadow-sm text-secondary me-2"><i class="bi bi-gear me-1"></i> Admin</a>
            <a href="handbuch.html" class="btn btn-outline-info btn-sm rounded-pill px-3 shadow-sm border-info text-info"><i class="bi bi-question-circle me-1"></i> Hilfe</a>
        </div>
    </div>
</nav>

<div class="container pb-5">
    <?php if (!$locationId): ?>
        <!-- STANDORT AUSWAHL -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-1">Standort wählen</h2>
                <p class="text-secondary small">Bitte wählen Sie einen Standort aus, um die zugehörigen Projekte zu sehen.</p>
            </div>
        </div>

        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
            <?php foreach ($locationsList as $loc): ?>
                <div class="col">
                    <div class="card h-100 project-card" onclick="location.href='index.php?location_id=<?= $loc['id'] ?>'">
                        <div class="card-body d-flex align-items-center p-4">
                            <div class="location-icon-wrapper me-4 bg-primary bg-opacity-10 text-primary rounded-4 d-flex align-items-center justify-content-center overflow-hidden" style="width: 60px; height: 60px; min-width: 60px;">
                                <?php if ($loc['logo_data']): ?>
                                    <img src="<?= $loc['logo_data'] ?>" alt="Logo" class="w-100 h-100 p-2" style="object-fit: contain; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));">
                                <?php else: ?>
                                    <i class="bi bi-geo-alt-fill" style="font-size: 1.8rem;"></i>
                                <?php endif; ?>
                            </div>
                            <div>
                                <h5 class="card-title mb-1"><?php echo htmlspecialchars($loc['name']); ?></h5>
                                <div class="badge rounded-pill bg-dark border border-secondary text-secondary small fw-normal px-3">
                                    <?= $loc['project_count'] ?> Projekte
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    <?php else: ?>
        <!-- PROJEKT ÜBERSICHT FÜR STANDORT -->
        <div class="d-flex align-items-center mb-4">
            <a href="index.php" class="btn btn-outline-light btn-sm rounded-pill px-3 me-3 border-secondary">
                <i class="bi bi-arrow-left me-1"></i> Alle Standorte
            </a>
            <div class="ms-1">
                <div class="small text-muted text-uppercase fw-bold" style="font-size: 0.65rem; letter-spacing: 1px;">Standort</div>
                <h2 class="fw-bold m-0"><?= htmlspecialchars($location['name']) ?></h2>
            </div>
            <div class="ms-auto">
                <button class="btn btn-primary shadow-sm px-4 rounded-pill" data-bs-toggle="modal" data-bs-target="#newProjectModal">
                    <i class="bi bi-plus-lg me-1"></i> Neues Projekt anlegen
                </button>
            </div>
        </div>

        <div class="row row-cols-1 row-cols-md-3 g-4">
            <?php if (empty($projects)): ?>
                <div class="col-12">
                    <div class="card p-5 text-center bg-dark border-secondary bg-opacity-50 dashed-border">
                        <i class="bi bi-folder2-open display-4 text-secondary mb-3 opacity-25"></i>
                        <h5 class="text-secondary">Noch keine Projekte an diesem Standort</h5>
                        <p class="small text-muted mb-4">Importieren Sie eine CSV-Datei, um das erste Projekt zu erstellen.</p>
                        <button class="btn btn-outline-primary btn-sm rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#newProjectModal">Jetzt Importieren</button>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($projects as $project): ?>
                    <div class="col">
                        <div class="card h-100 project-card" onclick="location.href='project_view.php?id=<?= $project['id'] ?>'">
                            <div class="card-body position-relative p-4">
                                <h5 class="card-title pe-4"><?php echo htmlspecialchars($project['name']); ?></h5>
                                <div class="d-flex flex-wrap gap-2 mt-2">
                                    <?php if ($project['width_mm'] && $project['height_mm']): ?>
                                        <span class="badge rounded-pill bg-dark border border-secondary text-secondary fw-normal px-3" style="font-size:0.72rem;">
                                            <i class="bi bi-rulers me-1"></i><?= (int)$project['width_mm'] ?> &times; <?= (int)$project['height_mm'] ?> mm
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!empty($project['csv_filename'])): ?>
                                        <span class="badge rounded-pill bg-dark border border-success text-success fw-normal px-3" style="font-size:0.72rem;" title="<?= htmlspecialchars($project['csv_filename']) ?>">
                                            <i class="bi bi-file-earmark-spreadsheet me-1"></i><?= htmlspecialchars($project['csv_filename']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge rounded-pill bg-dark border border-secondary text-secondary fw-normal px-3" style="font-size:0.72rem; opacity:0.6;">
                                            <i class="bi bi-pencil me-1"></i>Statisch (kein CSV)
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <!-- Löschen-Button -->
                                <form method="POST" action="index.php?location_id=<?= $locationId ?>" class="position-absolute" style="right: 15px; top: 15px;" onsubmit="event.stopPropagation();">
                                    <input type="hidden" name="delete_project_id" value="<?= $project['id'] ?>">
                                    <button type="submit" class="btn btn-outline-danger btn-sm border-0 px-2" onclick="return confirm('Projekt wirklich löschen?')" title="Projekt löschen">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                </form>
                            </div>
                            <div class="card-footer bg-transparent border-top-0 px-4 pb-4">
                                <small class="text-muted">Geändert: <?php echo date('d.m.Y H:i', strtotime($project['modified_at'])); ?></small>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Modal für neues Projekt -->
<div class="modal fade" id="newProjectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-light border-secondary shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <h5 class="modal-title fw-bold">Neues Projekt erstellen</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="import_csv.php" method="post" enctype="multipart/form-data">
                <input type="hidden" name="location_id" value="<?= $locationId ?>">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label text-secondary small">Projektname</label>
                        <input type="text" class="form-control bg-dark text-light border-secondary" name="project_name" placeholder="z.B. Paletten-Etiketten" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label text-secondary small">CSV-Datei auswählen <span style="color: #64748b;">(optional)</span></label>
                        <input class="form-control bg-dark text-light border-secondary" type="file" name="csv_file" accept=".csv">
                        <div class="form-text mt-2" style="font-size: 0.75rem; color: #94a3b8 !important;">Kodierung: UTF-8 oder Excel-CSV (Windows-1252). Ohne Datei wird ein leeres Projekt angelegt.</div>
                    </div>
                </div>
                <div class="modal-footer border-0 pb-4 px-4">
                    <button type="button" class="btn btn-link text-secondary text-decoration-none" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-primary px-4 rounded-pill fw-bold">Erstellen</button>
                </div>
            </form>
        </div>
    </div>
</div>

<footer class="text-center py-4 mt-2" style="font-size: 0.8rem; color: #64748b;">
    <p class="mb-1">© 2026 Drinkport KG - Barcode & Etiketten-System. <a href="mailto:it-service@drinkport.de?subject=Unterst%C3%BCtzung%20f%C3%BCr%20das%20Barcode-Tool%20ben%C3%B6tigt&body=...%7Bbitte%20beschreiben%20Sie%20ihr%20Problem%7D..." class="text-secondary">IT-Support</a> kontaktieren bei Fragen zur Einrichtung.</p>
    <div style="font-size: 0.7rem; opacity: 0.5;">UI-System-STABLE v2.7.0</div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
