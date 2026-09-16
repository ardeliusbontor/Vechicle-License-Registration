<?php
session_start();
include "staff_auth.php";
include "config.php";
include "partials/admin-toast.php";

if (($_SESSION['role'] ?? '') !== 'Admin') {
    admin_toast_add("Only the system administrator can reset applicant passwords.", 'warning');
    header('Location: dashboard.php');
    exit();
}

if (!isset($_GET['id'])) {
    header('Location: applicants.php');
    exit();
}

$applicant_id = (int) $_GET['id'];
$applicant_query = mysqli_query($conn, "SELECT id, fullname, username, email FROM applicants WHERE id = $applicant_id LIMIT 1");
if (!$applicant_query || mysqli_num_rows($applicant_query) === 0) {
    admin_toast_add("Applicant not found.", 'error');
    header('Location: applicants.php');
    exit();
}
$applicant = mysqli_fetch_assoc($applicant_query);
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = (string) ($_POST['new_password'] ?? '');
    $confirm_password = (string) ($_POST['confirm_password'] ?? '');
    $reset_reason = trim((string) ($_POST['reset_reason'] ?? ''));

    if ($new_password === '' || $confirm_password === '') {
        $message = 'Please enter and confirm the new password.';
        $message_type = 'danger';
    } elseif (strlen($new_password) < 6) {
        $message = 'Password must be at least 6 characters long.';
        $message_type = 'danger';
    } elseif ($new_password !== $confirm_password) {
        $message = 'Passwords do not match.';
        $message_type = 'danger';
    } else {
        $hashedPassword = appPasswordHash($new_password);
        $safePassword = mysqli_real_escape_string($conn, $hashedPassword);
        $safeReason = mysqli_real_escape_string($conn, $reset_reason);

        $update_sql = "UPDATE applicants SET password = '$safePassword' WHERE id = $applicant_id LIMIT 1";
        if (mysqli_query($conn, $update_sql)) {
            mysqli_query($conn, "INSERT INTO admin_notifications (event_type, message, reference_id, is_read, created_at) VALUES ('applicant_password_reset', 'Applicant password reset for " . mysqli_real_escape_string($conn, $applicant['fullname']) . "', $applicant_id, 0, NOW())");

            admin_toast_add('Applicant password reset successfully.', 'success');
            header('Location: applicants.php');
            exit();
        }

        $message = 'Unable to reset applicant password. Please try again.';
        $message_type = 'danger';
    }
}

$page_toasts = admin_toast_pull();
if ($message !== '') {
    $page_toasts[] = [
        'message' => trim(strip_tags((string) $message)),
        'type' => admin_toast_normalize_type($message_type)
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Applicant Password - Transport Union Authority</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin-toast.css">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, rgba(10,36,99,0.96) 0%, rgba(15,52,96,0.9) 100%);
            min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 30px;
        }
        .card {
            width: min(100%, 560px); background: rgba(255,255,255,0.97); border-radius: 22px; box-shadow: 0 30px 60px rgba(6,22,52,0.22); padding: 32px 28px;
        }
        .brand { display: flex; justify-content: center; margin-bottom: 18px; }
        .brand-circle {
            width: 110px; height: 110px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #edf4ff, #dfeaf9);
            border: 4px solid rgba(245,199,106,0.9);
        }
        .brand-circle img { width: 72px; height: 72px; object-fit: contain; }
        h1 { margin: 0; text-align: center; color: #0d2d5f; font-size: clamp(1.9rem, 2.4vw, 2.4rem); }
        .subtitle { text-align: center; color: #52657d; margin: 10px 0 22px; line-height: 1.6; }
        .field { margin-bottom: 18px; }
        .field label { display: block; color: #0d2d5f; font-weight: 800; margin-bottom: 8px; }
        .input-shell { position: relative; }
        .input-shell i {
            position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #8aa0bb;
        }
        .input-shell input, .input-shell textarea {
            width: 100%; padding: 16px 14px 16px 42px; border: 1px solid #d2ddef; border-radius: 12px; background: #f7fafd; font-size: 1rem; outline: none;
        }
        .input-shell input:focus, .input-shell textarea:focus {
            border-color: rgba(13,45,95,0.8); box-shadow: 0 0 0 4px rgba(13,45,95,0.08); background: #fff;
        }
        .submit-btn {
            width: 100%; min-height: 52px; border: none; border-radius: 12px; background: linear-gradient(135deg, #0d2d5f 0%, #184a9c 100%); color: white; font-weight: 900; letter-spacing: 0.08em; text-transform: uppercase; cursor: pointer;
        }
        .links { display: flex; justify-content: space-between; gap: 12px; margin-top: 18px; font-size: 0.92rem; flex-wrap: wrap; }
        .links a { color: #0d2d5f; text-decoration: none; font-weight: 700; }
        .links a:hover { color: #c71d35; }
    </style>
</head>
<body>
    <div class="card">
        <div class="brand">
            <div class="brand-circle">
                <img src="assets/images/logo.png" alt="TUA Logo">
            </div>
        </div>

        <h1>Reset Applicant Password</h1>
        <p class="subtitle">Set a new password for <strong><?php echo htmlspecialchars($applicant['fullname']); ?></strong> without viewing the old one.</p>

        <form method="POST" action="reset_applicant_password.php?id=<?php echo (int) $applicant['id']; ?>">
            <div class="field">
                <label for="new_password">New Password</label>
                <div class="input-shell">
                    <i class="fa-solid fa-lock"></i>
                    <input type="password" id="new_password" name="new_password" placeholder="Enter new password" required>
                </div>
            </div>

            <div class="field">
                <label for="confirm_password">Confirm Password</label>
                <div class="input-shell">
                    <i class="fa-solid fa-lock"></i>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password" required>
                </div>
            </div>

            <div class="field">
                <label for="reset_reason">Reason (optional)</label>
                <div class="input-shell">
                    <i class="fa-solid fa-note-sticky"></i>
                    <textarea id="reset_reason" name="reset_reason" rows="3" placeholder="Password reset reason"></textarea>
                </div>
            </div>

            <button type="submit" class="submit-btn"><i class="fa-solid fa-key"></i> Reset Password</button>
        </form>

        <div class="links">
            <a href="applicants.php"><i class="fa-solid fa-arrow-left"></i> Back to Applicants</a>
            <a href="dashboard.php"><i class="fa-solid fa-house"></i> Dashboard</a>
        </div>
    </div>

    <?php if (!empty($page_toasts)): ?>
        <script>
            window.adminToastQueue = <?php echo json_encode($page_toasts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        </script>
    <?php endif; ?>
    <script src="assets/js/admin-toast.js"></script>
</body>
</html>
