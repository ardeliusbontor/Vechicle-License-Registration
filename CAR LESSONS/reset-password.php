<?php
session_start();
include "config.php";
include "partials/admin-toast.php";

$page_toasts = admin_toast_pull();

$token = isset($_GET['token']) ? trim((string) $_GET['token']) : '';
$resetError = null;
$tokenValid = false;
$account = null;
$userType = null;

if ($token !== '') {
    $tokenHash = hash('sha256', $token);
    $timeNow = date('Y-m-d H:i:s');

    $tokenQuery = mysqli_query($conn, "SELECT * FROM password_reset_tokens WHERE token_hash = '$tokenHash' AND used_at IS NULL AND expires_at > '$timeNow' LIMIT 1");
    if ($tokenQuery && mysqli_num_rows($tokenQuery) === 1) {
        $tokenRecord = mysqli_fetch_assoc($tokenQuery);
        $userType = $tokenRecord['user_type'];
        $userId = (int) $tokenRecord['user_id'];

        if ($userType === 'users') {
            $accountQuery = mysqli_query($conn, "SELECT id, fullname, username, email, status FROM users WHERE id = $userId LIMIT 1");
        } else {
            $accountQuery = mysqli_query($conn, "SELECT id, fullname, username, email, status FROM applicants WHERE id = $userId LIMIT 1");
        }

        if ($accountQuery && mysqli_num_rows($accountQuery) === 1) {
            $account = mysqli_fetch_assoc($accountQuery);
            $tokenValid = true;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['token']) && isset($_POST['password']) && isset($_POST['confirm_password'])) {
    $postedToken = trim((string) $_POST['token']);
    $newPassword = (string) $_POST['password'];
    $confirmPassword = (string) $_POST['confirm_password'];

    if ($newPassword === '' || $confirmPassword === '') {
        $resetError = 'Please enter and confirm your new password.';
    } elseif (strlen($newPassword) < 6) {
        $resetError = 'Your password must be at least 6 characters long.';
    } elseif ($newPassword !== $confirmPassword) {
        $resetError = 'Your password confirmation does not match.';
    } else {
        $tokenHash = hash('sha256', $postedToken);
        $timeNow = date('Y-m-d H:i:s');

        $tokenQuery = mysqli_query($conn, "SELECT * FROM password_reset_tokens WHERE token_hash = '$tokenHash' AND used_at IS NULL AND expires_at > '$timeNow' LIMIT 1");
        if ($tokenQuery && mysqli_num_rows($tokenQuery) === 1) {
            $tokenRecord = mysqli_fetch_assoc($tokenQuery);
            $userType = $tokenRecord['user_type'];
            $userId = (int) $tokenRecord['user_id'];
            $safePassword = password_hash($newPassword, PASSWORD_DEFAULT);

            if ($userType === 'users') {
                $updateSql = "UPDATE users SET password = '$safePassword' WHERE id = $userId LIMIT 1";
            } else {
                $updateSql = "UPDATE applicants SET password = '$safePassword' WHERE id = $userId LIMIT 1";
            }

            if (mysqli_query($conn, $updateSql)) {
                mysqli_query($conn, "UPDATE password_reset_tokens SET used_at = '$timeNow' WHERE id = '{$tokenRecord['id']}'");
                admin_toast_add('Your password has been reset successfully. You can now log in.', 'success');
                header('Location: login.php');
                exit();
            }

            $resetError = 'We could not update your password. Please try again.';
        } else {
            $resetError = 'This reset link is invalid or has expired.';
        }
    }
}

if ($token === '') {
    $resetError = 'Missing reset token.';
} elseif (!$tokenValid && !$resetError) {
    $resetError = 'This reset link is invalid or has expired.';
}

if ($resetError) {
    $page_toasts[] = ['message' => $resetError, 'type' => 'error'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
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
            margin-bottom: 18px;
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

        <h1>Reset Password</h1>
        <p class="subtitle">Create a new password for your account.</p>

        <?php if ($tokenValid || isset($_POST['token'])): ?>
            <form method="POST" action="reset-password.php">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">

                <div class="field">
                    <label for="password">New Password</label>
                    <div class="input-shell">
                        <i class="fa-solid fa-lock"></i>
                        <input type="password" id="password" name="password" placeholder="Minimum 6 characters" required>
                    </div>
                </div>

                <div class="field">
                    <label for="confirm_password">Confirm Password</label>
                    <div class="input-shell">
                        <i class="fa-solid fa-lock"></i>
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Repeat password" required>
                    </div>
                </div>

                <button type="submit" class="submit-btn">Update Password</button>
            </form>
        <?php else: ?>
            <p class="subtitle" style="color: #b42318; font-weight: 700;">This reset link is invalid or has expired. Please request a new one.</p>
        <?php endif; ?>

        <div class="links">
            <a href="login.php"><i class="fa-solid fa-arrow-left"></i> Back to Login</a>
            <a href="forgot-password.php">Request another link</a>
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
