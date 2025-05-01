<?php
// Index.php - Startsida med inloggningsformulär för både admin och staff
// Aktivera felrapportering för felsökning
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Starta session
session_start();

// Kontrollera om användaren redan är inloggad
if (isset($_SESSION['admin_login']) && $_SESSION['admin_login'] === true) {
    header('Location: company_dashboard.php');
    exit;
} elseif (isset($_SESSION['staff_login']) && $_SESSION['staff_login'] === true) {
    header('Location: staff_dashboard.php');
    exit;
}

// Inkludera nödvändiga filer
require_once 'includes/db_connect.php';
require_once '../Auth.php';

// Skapa Auth-objekt
$auth = new Auth($pdo);

// Hantera inloggningsförsök
$admin_error = '';
$staff_error = '';

// Admin inloggning
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_login'])) {
    $email = $_POST['admin_email'] ?? '';
    $password = $_POST['admin_password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $admin_error = 'Både e-post och lösenord krävs.';
    } else {
        // Autentisera användaren
        $result = $auth->login($email, $password);
        
        if ($result['success']) {
            // Kontrollera om användaren är företagsadministratör
            if ($auth->is
