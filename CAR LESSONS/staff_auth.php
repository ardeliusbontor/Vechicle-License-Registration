<?php
include "partials/admin-toast.php";

if (!isset($_SESSION['id'])) {
    admin_toast_add("Please log in to continue.", 'warning');
    header("Location: login.php");
    exit();
}

$session_timeout_seconds = 1800;
if (isset($_SESSION['last_activity']) && (time() - (int) $_SESSION['last_activity']) > $session_timeout_seconds) {
    session_unset();
    session_destroy();
    admin_toast_add("Your session expired. Please log in again.", 'warning');
    header("Location: login.php");
    exit();
}
$_SESSION['last_activity'] = time();

if (($_SESSION['role'] ?? '') === 'Applicant') {
    admin_toast_add("Access denied. Admin portal is for staff accounts only.", 'warning');
    header("Location: user-dashboard.php");
    exit();
}
?>