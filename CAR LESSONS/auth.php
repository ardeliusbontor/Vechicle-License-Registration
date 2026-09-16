<?php
session_start();
include "config.php";
include "partials/admin-toast.php";

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['username']) && isset($_POST['password'])) {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = (string) $_POST['password'];

    $staff_sql = "SELECT * FROM users WHERE username='$username' AND status='Active' LIMIT 1";
    $staff_result = mysqli_query($conn, $staff_sql);

    if ($staff_result && mysqli_num_rows($staff_result) == 1) {
        $row = mysqli_fetch_assoc($staff_result);
        $storedPassword = (string) ($row['password'] ?? '');

        if (appPasswordVerify($password, $storedPassword)) {
            if ($storedPassword !== '' && function_exists('password_get_info') && !password_get_info($storedPassword)['algo']) {
                $newHash = appPasswordHash($password);
                mysqli_query($conn, "UPDATE users SET password = '" . mysqli_real_escape_string($conn, $newHash) . "' WHERE id = '{$row['id']}' LIMIT 1");
            }

            session_regenerate_id(true);
            $_SESSION['id'] = $row['id'];
            $_SESSION['fullname'] = $row['fullname'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['role'] = $row['role'] ?? 'Applicant';
            $_SESSION['account_type'] = 'staff';
            $_SESSION['last_activity'] = time();
            admin_toast_add("Welcome back, " . $row['fullname'] . "!", 'success');
            header("Location: dashboard.php");
            exit();
        }
    }

    $applicant_sql = "SELECT * FROM applicants WHERE username='$username' AND status='Active' LIMIT 1";
    $applicant_result = mysqli_query($conn, $applicant_sql);

    if ($applicant_result && mysqli_num_rows($applicant_result) == 1) {
        $row = mysqli_fetch_assoc($applicant_result);
        $storedPassword = (string) ($row['password'] ?? '');

        if (appPasswordVerify($password, $storedPassword)) {
            if ($storedPassword !== '' && function_exists('password_get_info') && !password_get_info($storedPassword)['algo']) {
                $newHash = appPasswordHash($password);
                mysqli_query($conn, "UPDATE applicants SET password = '" . mysqli_real_escape_string($conn, $newHash) . "' WHERE id = '{$row['id']}' LIMIT 1");
            }

            session_regenerate_id(true);
            $_SESSION['id'] = $row['id'];
            $_SESSION['fullname'] = $row['fullname'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['role'] = 'Applicant';
            $_SESSION['account_type'] = 'applicant';
            $_SESSION['last_activity'] = time();
            admin_toast_add("Welcome back, " . $row['fullname'] . "!", 'success');
            header("Location: user-dashboard.php");
            exit();
        }
    }

    admin_toast_add("Invalid username or password.", 'danger');
    header("Location: login.php");
    exit();
} else {
    admin_toast_add("Please use the login form to continue.", 'warning');
    header("Location: login.php");
    exit();
}
?>
