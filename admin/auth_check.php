<?php
session_start();
/**
 * admin/auth_check.php
 * Einfacher Schutz für den Admin-Bereich
 */
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    // Falls wir uns nicht schon auf der Login-Seite befinden, umleiten
    if (basename($_SERVER['PHP_SELF']) !== 'login.php') {
        header("Location: login.php");
        exit;
    }
}
