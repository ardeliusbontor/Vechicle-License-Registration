<?php
session_start();
include "config.php";
include "partials/admin-toast.php";

$page_toasts = admin_toast_pull();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $email = trim((string) $_POST['email']);
    $email = mysqli_real_escape_string($conn, $email);

    $userFound = null;
    $userType = null;

    $userQuery = mysqli_query($conn, "SELECT id, fullname, email, status FROM users WHERE email = '$email' LIMIT 1");
    if ($userQuery && mysqli_num_rows($userQuery) === 1) {
        $userFound = mysqli_fetch_assoc($userQuery);
        $userType = 'users';
    } else {
        $applicantQuery = mysqli_query($conn, "SELECT id, fullname, email, status FROM applicants WHERE email = '$email' LIMIT 1");
        if ($applicantQuery && mysqli_num_rows($applicantQuery) === 1) {
            $userFound = mysqli_fetch_assoc($applicantQuery);
            $userType = 'applicants';
        }
    }

    if ($userFound && ($userFound['status'] ?? 'Active') === 'Active') {
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() + 3600);

        mysqli_query($conn, "DELETE FROM password_reset_tokens WHERE user_type = '$userType' AND user_id = '{$userFound['id']}'");
        $insertSql = "INSERT INTO password_reset_tokens (user_type, user_id, token_hash, expires_at) VALUES ('$userType', '{$userFound['id']}', '$tokenHash', '$expiresAt')";
        mysqli_query($conn, $insertSql);

        $resetLink = appBaseUrl() . '/reset-password.php?token=' . rawurlencode($token);

        $subject = 'Password Reset Request';
        $message = "Hello {$userFound['fullname']},\r\n\r\n";
        $message .= "We received a request to reset your password for the Transport Union Authority portal.\r\n";
        $message .= "Use the link below to reset it within 1 hour:\r\n\r\n";
        $message .= $resetLink . "\r\n\r\n";
        $message .= "If you did not request this, you can safely ignore this email.\r\n";

        $fromEmail = appEnv('SMTP_FROM', appEnv('SMTP_USERNAME', 'no-reply@localhost'));
        $mailSent = appSendSmtpEmail($email, $subject, $message, $fromEmail, 'Transport Union Authority');

        $_SESSION['password_reset_debug_link'] = $resetLink;

        if ($mailSent) {
            admin_toast_add('A password reset link has been sent to your email address.', 'success');
        } else {
            admin_toast_add('Email delivery is not configured on this server, so your reset link has been prepared below for testing.', 'warning');
        }

        header('Location: forgot-password.php?sent=1');
        exit();
    }

    admin_toast_add('No active account was found with that email address.', 'error');
    header('Location: forgot-password.php');
    exit();
}

if (isset($_GET['sent'])) {
    $debugLink = $_SESSION['password_reset_debug_link'] ?? null;
    $safeMessage = 'A password reset link has been sent to your email address.';

    if ($debugLink) {
        $page_toasts[] = [
            'message' => $safeMessage,
            'type' => 'success'
        ];
        $_SESSION['password_reset_debug_link'] = $debugLink;
    } else {
        $page_toasts[] = [
            'message' => $safeMessage,
            'type' => 'success'
        ];
    }
}

$debugLink = $_SESSION['password_reset_debug_link'] ?? null;
unset($_SESSION['password_reset_debug_link']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin-toast.css">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, rgba(7, 26, 52, 0.72), rgba(11, 75, 146, 0.42)), url('backbround image.jpeg') center/cover no-repeat;
            padding: 24px;
        }
        .card {
            width: min(100%, 470px);
            background: rgba(255,255,255,0.97);
            border-radius: 26px;
            box-shadow: 0 30px 60px rgba(6, 22, 52, 0.22);
            padding: 30px 24px;
        }
        .brand {
            display: flex;
            justify-content: center;
            margin-bottom: 18px;
        }
        .brand-circle {
            width: 110px;
            height: 110px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #edf4ff, #dfeaf9);
            border: 4px solid rgba(245,199,106,0.9);
        }
        .brand-circle img {
            width: 72px;
            height: 72px;
            object-fit: contain;
        }
        h1 {
            margin: 0;
            text-align: center;
            color: #0d2d5f;
            font-size: clamp(1.9rem, 2.4vw, 2.4rem);
        }
        .subtitle {
            text-align: center;
            color: #52657d;
            margin: 10px 0 22px;
            line-height: 1.6;
        }
        .field label {
            display: block;
            color: #0d2d5f;
            font-weight: 800;
            margin-bottom: 8px;
        }
        .input-shell {
            position: relative;
        }
        .input-shell i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #8aa0bb;
        }
        .input-shell input {
            width: 100%;
            height: 54px;
            padding: 16px 14px 16px 42px;
            border: 1px solid #d2ddef;
            border-radius: 12px;
            background: #f7fafd;
            font-size: 1rem;
            outline: none;
        }
        .input-shell input:focus {
            border-color: rgba(13,45,95,0.8);
            box-shadow: 0 0 0 4px rgba(13,45,95,0.08);
            background: #fff;
        }
        .submit-btn {
            margin-top: 20px;
            width: 100%;
            min-height: 52px;
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, #0d2d5f 0%, #184a9c 100%);
            color: white;
            font-weight: 900;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            cursor: pointer;
        }
        .links {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            margin-top: 18px;
            font-size: 0.92rem;
            flex-wrap: wrap;
        }
        .links a {
            color: #0d2d5f;
            text-decoration: none;
            font-weight: 700;
        }
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

        <h1>Forgot Password</h1>
        <p class="subtitle">Enter the email address associated with your account and we will send you a reset link.</p>

        <?php if ($debugLink): ?>
            <div style="margin-bottom: 18px; padding: 12px 14px; border-radius: 12px; background: #edf4ff; border: 1px solid #d2ddef; color: #0d2d5f; word-break: break-all; font-size: 0.88rem; line-height: 1.5;">
                <strong>Reset link for local testing:</strong><br>
                <a href="<?php echo htmlspecialchars($debugLink, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($debugLink, ENT_QUOTES, 'UTF-8'); ?></a>
            </div>
        <?php endif; ?>

        <form method="POST" action="forgot-password.php">
            <div class="field">
                <label for="email">Email Address</label>
                <div class="input-shell">
                    <i class="fa-solid fa-envelope"></i>
                    <input type="email" id="email" name="email" placeholder="you@example.com" required>
                </div>
            </div>

            <button type="submit" class="submit-btn">Send Reset Link</button>
        </form>

        <div class="links">
            <a href="login.php"><i class="fa-solid fa-arrow-left"></i> Back to Login</a>
            <a href="index.php">Home Page</a>
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
