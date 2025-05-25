<?php
require_once 'includes/header.php';
require_once 'db/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Destroy the session
session_unset();
session_destroy();

// Redirect to login page
header("Location: index.php");
exit;
?>

<?php require_once 'includes/footer.php'; ?>