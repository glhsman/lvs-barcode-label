<?php
require_once '../config.php';
require_once 'auth_check.php';

$message = '';
$error = '';

// Standort hinzufügen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_location'])) {
    $name = trim($_POST['location_name']);
    $logoData = null;

    // Logo-Upload verarbeiten
    if (isset($_FILES['location_logo']) && $_FILES['location_logo']['error'] === UPLOAD_ERR_OK) {
        $type = pathinfo($_FILES['location_logo']['name'], PATHINFO_EXTENSION);
        $data = file_get_contents($_FILES['location_logo']['tmp_name']);
        $logoData = 'data:image/' . $type . ';base64,' . base64_encode($data);
    }

    if (!empty($name)) {
        $stmt = $pdo->prepare("INSERT INTO locations (name, logo_data) VALUES (?, ?)");
        $stmt->execute([$name, $logoData]);
        $message = "Standort '$name' wurde erfolgreich angelegt.";
    } else {
        $error = "Bitte geben Sie einen Namen für den Standort ein.";
    }
}

// Standort bearbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_location'])) {
    $id = (int)$_POST['location_id'];
    $name = trim($_POST['location_name']);
    
    if (!empty($name)) {
        // Logo-Upload verarbeiten (optional)
        if (isset($_FILES['location_logo']) && $_FILES['location_logo']['error'] === UPLOAD_ERR_OK) {
            $type = pathinfo($_FILES['location_logo']['name'], PATHINFO_EXTENSION);
            $data = file_get_contents($_FILES['location_logo']['tmp_name']);
            $logoData = 'data:image/' . $type . ';base64,' . base64_encode($data);
            
            $stmt = $pdo->prepare("UPDATE locations SET name = ?, logo_data = ? WHERE id = ?");
            $stmt->execute([$name, $logoData, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE locations SET name = ? WHERE id = ?");
            $stmt->execute([$name, $id]);
        }
        $message = "Standort wurde erfolgreich aktualisiert.";
    } else {
        $error = "Der Name darf nicht leer sein.";
    }
}

// Standort löschen
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // Sicherheitscheck: Wie viele Standorte gibt es insgesamt?
    $totalLocs = $pdo->query("SELECT COUNT(*) FROM locations")->fetchColumn();
    
    if ($totalLocs <= 1) {
        $error = "Abbruch: Der letzte Standort kann nicht gelöscht werden. Es muss mindestens 1 Standort im System verbleiben.";
    } else {
        // Prüfen, ob noch Projekte am Standort hängen
        $projects = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE location_id = ?");
        $projects->execute([$id]);
        $count = $projects->fetchColumn();
        
        if ($count > 0) {
            $error = "Der Standort kann nicht gelöscht werden, da noch $count Projekte zugewiesen sind. Verschieben Sie diese erst!";
        } else {
            $pdo->prepare("DELETE FROM locations WHERE id = ?")->execute([$id]);
            $message = "Standort wurde erfolgreich gelöscht.";
        }
    }
}

// Standorte abrufen
$stmt = $pdo->query("
    SELECT l.*, COUNT(p.id) as project_count 
    FROM locations l 
    LEFT JOIN projects p ON l.id = p.location_id 
    GROUP BY l.id 
    ORDER BY l.name ASC
");
$locations = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Standorte verwalten - Admin</title>
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark mb-4 shadow-sm">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="index.php">
            <div class="logo-wrapper me-2 d-flex align-items-center justify-content-center bg-danger bg-gradient rounded shadow-sm" style="width: 32px; height: 32px;">
                <i class="bi bi-shield-lock text-white" style="font-size: 1.1rem;"></i>
            </div>
            <span>ADMIN BEREICH</span>
        </a>
        <div class="ms-auto">
            <a href="logout.php" class="btn btn-outline-danger btn-sm rounded-pill px-3 shadow-sm border-danger me-2">
                <i class="bi bi-power me-1"></i> Logout
            </a>
            <a href="index.php" class="btn btn-outline-light btn-sm rounded-pill px-3 shadow-sm border-secondary">
                <i class="bi bi-arrow-left me-1"></i> Dashboard
            </a>
        </div>
    </div>
</nav>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2 class="fw-bold m-0"><i class="bi bi-geo-alt me-2 text-info"></i>Standorte</h2>
            <p class="text-secondary small mt-1">Hier können Sie die 18 Standorte verwalten, die in der User-Ansicht zur Auswahl stehen.</p>
        </div>
        <div class="col-md-4 text-end d-flex align-items-center justify-content-end">
             <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addLocationModal">
                 <i class="bi bi-plus-lg me-1"></i> Neuen Standort anlegen
             </button>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success border-success bg-success bg-opacity-10 text-success rounded-3 mb-4">
            <i class="bi bi-check-circle me-2"></i> <?= $message ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger border-danger bg-danger bg-opacity-10 text-danger rounded-3 mb-4">
            <i class="bi bi-exclamation-triangle me-2"></i> <?= $error ?>
        </div>
    <?php endif; ?>

    <div class="card bg-dark border-secondary rounded-4 overflow-hidden shadow-lg mb-5">
        <div class="table-responsive">
            <table class="table table-dark table-hover mb-0 align-middle">
                <thead class="bg-dark text-secondary small text-uppercase">
                    <tr>
                        <th class="px-4 py-3">ID</th>
                        <th class="py-3">Logo</th>
                        <th class="py-3">Standortbezeichnung</th>
                        <th class="py-3 text-center">Aktive Projekte</th>
                        <th class="px-4 py-3 text-end">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($locations as $loc): ?>
                        <tr>
                            <td class="px-4 text-muted"><?= $loc['id'] ?></td>
                            <td class="py-2">
                                <?php if ($loc['logo_data']): ?>
                                    <img src="<?= $loc['logo_data'] ?>" alt="Logo" class="rounded bg-white p-1" style="width: 40px; height: 40px; object-fit: contain;">
                                <?php else: ?>
                                    <div class="rounded bg-secondary bg-opacity-10 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                        <i class="bi bi-geo-alt text-secondary"></i>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="fw-bold"><?= htmlspecialchars($loc['name']) ?></td>
                            <td class="text-center">
                                <span class="badge rounded-pill bg-info bg-opacity-10 text-info px-3 border border-info border-opacity-25">
                                    <?= $loc['project_count'] ?> Projekte
                                </span>
                            </td>
                            <td class="px-4 text-end">
                                <button class="btn btn-outline-info btn-sm border-0 me-1" 
                                        onclick="openEditModal(<?= $loc['id'] ?>, '<?= htmlspecialchars($loc['name'], ENT_QUOTES) ?>')">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                                <a href="?delete=<?= $loc['id'] ?>" class="btn btn-outline-danger btn-sm border-0" 
                                   onclick="return confirm('Möchten Sie diesen Standort wirklich löschen?')">
                                    <i class="bi bi-trash3"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="addLocationModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-light border-secondary shadow-lg" style="border-radius: 20px;">
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-header border-0 pb-0 pt-4 px-4">
                    <h5 class="modal-title fw-bold">Neuer Standort</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label text-secondary small">Bezeichnung / Name</label>
                        <input type="text" name="location_name" class="form-control bg-dark text-light border-secondary shadow-none" placeholder="z.B. Standort Berlin" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary small">Logo / Pictogramm (Optional)</label>
                        <input type="file" name="location_logo" class="form-control bg-dark text-light border-secondary shadow-none" accept="image/*">
                        <div class="form-text mt-2" style="font-size: 0.7rem; color: #94a3b8 !important;">Ideal: Quadratisch (PNG, SVG, JPG). Empfohlene Größe: 128x128 oder 256x256 Pixel.</div>
                    </div>
                </div>
                <div class="modal-footer border-0 pb-4 px-4">
                    <button type="button" class="btn btn-link text-secondary text-decoration-none" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" name="add_location" class="btn btn-primary px-4 rounded-pill">Standort erstellen</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Standort bearbeiten -->
<div class="modal fade" id="editLocationModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-light border-secondary shadow-lg" style="border-radius: 20px;">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="location_id" id="edit_location_id">
                <div class="modal-header border-0 pb-0 pt-4 px-4">
                    <h5 class="modal-title fw-bold">Standort bearbeiten</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label text-secondary small">Bezeichnung / Name</label>
                        <input type="text" name="location_name" id="edit_location_name" class="form-control bg-dark text-light border-secondary shadow-none" placeholder="z.B. Standort Berlin" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary small">Logo aktualisieren (Optional)</label>
                        <input type="file" name="location_logo" class="form-control bg-dark text-light border-secondary shadow-none" accept="image/*">
                        <div class="form-text mt-2" style="font-size: 0.7rem; color: #94a3b8 !important;">Ideal: Quadratisch (PNG, SVG, JPG). Empf. Größe: 128x128 oder 256x256 Pixel. Leer lassen, um aktuelles Logo zu behalten.</div>
                    </div>
                </div>
                <div class="modal-footer border-0 pb-4 px-4">
                    <button type="button" class="btn btn-link text-secondary text-decoration-none" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" name="edit_location" class="btn btn-info px-4 rounded-pill text-white">Änderungen speichern</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openEditModal(id, name) {
    document.getElementById('edit_location_id').value = id;
    document.getElementById('edit_location_name').value = name;
    
    var myModal = new bootstrap.Modal(document.getElementById('editLocationModal'));
    myModal.show();
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
