<?php
require_once '../config.php';
require_once 'auth_check.php';

// Statistiken abrufen
$totalLocations = $pdo->query("SELECT COUNT(*) FROM locations")->fetchColumn();
$totalProjects = $pdo->query("SELECT COUNT(*) FROM projects")->fetchColumn();

// Standorte mit Projekt-Anzahl abrufen
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
    <title>Admin-Bereich - Etiketten-System</title>
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
    <style>
        .admin-card {
            background: rgba(30, 41, 59, 0.4);
            border: 1px solid rgba(255,255,255,0.05);
            transition: all 0.3s ease;
        }
        .admin-card:hover {
            background: rgba(30, 41, 59, 0.6);
            border-color: var(--accent);
            transform: translateY(-5px);
        }
    </style>
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
            <a href="../index.php" class="btn btn-outline-light btn-sm rounded-pill px-3 shadow-sm border-secondary">
                <i class="bi bi-box-arrow-left me-1"></i> Zum User-Interface
            </a>
        </div>
    </div>
</nav>

<div class="container py-4">
    <div class="row mb-5">
        <div class="col-md-12">
            <h2 class="fw-bold mb-4">Systemübersicht</h2>
            <div class="row g-4">
                <div class="col-md-6 col-lg-3">
                    <div class="card admin-card p-4 h-100">
                        <div class="small text-muted text-uppercase fw-bold mb-1">Standorte</div>
                        <div class="h2 fw-bold text-primary mb-0"><?= $totalLocations ?></div>
                        <i class="bi bi-geo-alt position-absolute end-0 bottom-0 m-3 opacity-25" style="font-size: 3rem;"></i>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="card admin-card p-4 h-100">
                        <div class="small text-muted text-uppercase fw-bold mb-1">Gesamtprojekte</div>
                        <div class="h2 fw-bold text-success mb-0"><?= $totalProjects ?></div>
                        <i class="bi bi-collection-play position-absolute end-0 bottom-0 m-3 opacity-25" style="font-size: 3rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="fw-bold m-0 text-info">Verwaltung</h3>
            </div>
            <div class="row g-4">
                <div class="col-md-6 col-lg-4">
                    <a href="locations.php" class="text-decoration-none h-100">
                        <div class="card admin-card p-4 h-100 text-light border-0">
                            <i class="bi bi-geo-fill text-info mb-3" style="font-size: 2rem;"></i>
                            <h5 class="fw-bold">Standorte verwalten</h5>
                            <p class="text-secondary small mb-0">Anlegen und Bearbeiten der 18+ Standorte.</p>
                        </div>
                    </a>
                </div>
                <div class="col-md-6 col-lg-4">
                    <a href="templates.php" class="text-decoration-none h-100">
                        <div class="card admin-card p-4 h-100 text-light border-0">
                            <i class="bi bi-grid-1x2-fill text-secondary mb-3" style="font-size: 2rem;"></i>
                            <h5 class="fw-bold">Templates & Vorlagen</h5>
                            <p class="text-secondary small mb-0">Zentrale Verwaltung der globalen Etiketten-Formate.</p>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
