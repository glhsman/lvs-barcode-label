<?php
session_start();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    
    // Hardcoded Passwort wie gewünscht
    if ($password === 'IchDarfDas') {
        $_SESSION['admin_logged_in'] = true;
        header("Location: index.php");
        exit;
    } else {
        $error = 'Ungültiges Administratoren-Passwort.';
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin-Login - Barcode System</title>
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
    <style>
        .login-card {
            max-width: 400px;
            margin-top: 10vh;
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 24px;
        }
        .login-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #ef4444, #991b1b);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: -40px auto 20px;
            box-shadow: 0 10px 20px rgba(239, 68, 68, 0.3);
        }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center" style="min-height: 100vh;">

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card login-card p-4 shadow-lg">
                <div class="login-icon">
                    <i class="bi bi-shield-lock-fill text-white fs-1"></i>
                </div>
                
                <h3 class="text-center fw-bold mb-2">Admin Login</h3>
                <p class="text-center text-secondary small mb-4">Bitte identifizieren Sie sich um fortzufahren.</p>

                <?php if ($error): ?>
                    <div class="alert alert-danger bg-danger bg-opacity-10 border-danger text-danger small py-2">
                        <i class="bi bi-exclamation-circle me-2"></i> <?= $error ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="mb-4">
                        <label class="form-label text-secondary small">Passwort</label>
                        <input type="password" name="password" class="form-control bg-dark border-secondary text-white p-3 rounded-3" placeholder="Passwort eingeben" autofocus required>
                    </div>
                    
                    <button type="submit" class="btn btn-danger w-100 py-3 rounded-3 fw-bold shadow-sm">
                        Anmelden
                    </button>
                    
                    <div class="text-center mt-4">
                        <a href="../index.php" class="text-secondary text-decoration-none small">
                            <i class="bi bi-arrow-left me-1"></i> Zurück zum Hauptsystem
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

</body>
</html>
