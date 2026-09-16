<?php
session_start();
include "partials/admin-toast.php";

$page_toasts = admin_toast_pull();
if (isset($_GET['error'])) {
    $page_toasts[] = [
        'message' => 'Invalid username or password.',
        'type' => 'error'
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Transport Union Authority</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin-toast.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        :root {
            --primary: #0d2d5f;
            --primary-strong: #041d45;
            --accent: #c71d35;
            --gold: #f5c76a;
            --soft: #edf4ff;
            --dark: #0f172a;
            --muted: #52657d;
            --white: #ffffff;
            --shadow: 0 28px 70px rgba(16, 35, 70, 0.18);
        }

        html, body {
            margin: 0;
            min-height: 100%;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--dark);
            background: #edf4ff;
        }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 28px;
            background:
                linear-gradient(135deg, rgba(7, 26, 52, 0.72), rgba(11, 75, 146, 0.42)),
                url('backbround image.jpeg') center center / cover no-repeat;
        }

        .page-shell {
            position: relative;
            width: min(1120px, 94vw);
            min-height: 680px;
            display: grid;
            grid-template-columns: 1.6fr 0.95fr;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255,255,255,0.22);
            border-radius: 30px;
            overflow: hidden;
            box-shadow: 0 32px 80px rgba(6, 22, 52, 0.28);
            backdrop-filter: blur(4px);
            animation: fadeUp 0.8s ease;
        }

        .page-shell::before {
            content: "";
            position: absolute;
            inset: 0;
            background:
                linear-gradient(90deg, rgba(245,199,106,0.08), transparent 20%, transparent 80%, rgba(245,199,106,0.08)),
                repeating-linear-gradient(
                    135deg,
                    rgba(255,255,255,0.02),
                    rgba(255,255,255,0.02) 2px,
                    transparent 2px,
                    transparent 10px
                );
            pointer-events: none;
        }

        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(18px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .left-panel {
            position: relative;
            padding: 56px 42px 0;
            background: linear-gradient(180deg, rgba(255,255,255,0.04), rgba(9,35,69,0.12));
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .left-panel::before {
            content: "";
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 18% 12%, rgba(255,255,255,0.26), transparent 18%);
            pointer-events: none;
        }

        .left-panel::after {
            content: "";
            position: absolute;
            inset: 20px 0 auto 0;
            height: 160px;
            background: linear-gradient(180deg, rgba(255,255,255,0.08), transparent);
            border-bottom: 1px solid rgba(255,255,255,0.08);
            pointer-events: none;
        }

        .hero-copy {
            position: relative;
            z-index: 1;
            max-width: 620px;
        }

        .welcome-text {
            color: #fff;
            font-size: clamp(2.2rem, 2.7vw, 4rem);
            line-height: 1.05;
            letter-spacing: -0.06em;
            font-weight: 700;
            text-shadow: 0 6px 22px rgba(5, 18, 40, 0.34);
        }

        .welcome-text strong {
            display: block;
            font-weight: 900;
            text-shadow: 0 10px 24px rgba(6,22,52,0.28);
        }

        .sub-title {
            margin-top: 18px;
            color: rgba(255,255,255,0.96);
            font-size: 0.78rem;
            font-weight: 900;
            letter-spacing: 0.18em;
            text-transform: uppercase;
        }

        .title-divider {
            width: 180px;
            height: 3px;
            border-radius: 999px;
            margin: 18px 0 18px;
            background: linear-gradient(90deg, #f4c85e 0%, #c71d35 100%);
        }

        .supporting-copy {
            max-width: 540px;
            color: rgba(255,255,255,0.96);
            line-height: 1.7;
            font-size: 1.05rem;
            font-weight: 600;
        }

        .art-stage {
            position: relative;
            z-index: 1;
            margin-top: 20px;
            flex: 1;
            display: flex;
            align-items: flex-end;
            justify-content: center;
            min-height: 300px;
        }

        .plate-wrap {
            position: relative;
            width: min(86%, 760px);
            min-height: 176px;
            border-radius: 18px;
            background: linear-gradient(180deg, #f4a150 0%, #ef8d2a 100%);
            border: 4px solid rgba(13,45,95,0.95);
            box-shadow: 0 24px 32px rgba(0,0,0,0.14);
            overflow: hidden;
            animation: floatPlate 4s ease-in-out infinite;
            transform-origin: center center;
        }

        .plate-wrap::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(255,255,255,0.08), rgba(0,0,0,0.02));
            pointer-events: none;
        }

        @keyframes floatPlate {
            0%, 100% {
                transform: translateY(0) rotate(-0.3deg);
            }
            50% {
                transform: translateY(-10px) rotate(0.3deg);
            }
        }

        .plate-inner {
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: 88px 1fr 98px;
            min-height: 170px;
        }

        .plate-side {
            background: linear-gradient(180deg, #f7fafc 0%, #ebf1f8 100%);
            border-right: 2px solid rgba(15,23,42,0.18);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
            padding: 12px 8px 10px;
        }

        .plate-side.right {
            border-right: none;
            border-left: 2px solid rgba(15,23,42,0.18);
            justify-content: center;
            gap: 12px;
            padding-top: 18px;
            padding-bottom: 18px;
        }

        .plate-flag {
            width: 52px;
            height: 30px;
            border-radius: 4px;
            border: 1px solid rgba(15,23,42,0.08);
            box-shadow: inset 0 0 0 2px rgba(255,255,255,0.18);
            overflow: hidden;
            background: url('https://static.vecteezy.com/system/resources/previews/006/601/539/large_2x/liberia-flag-with-waving-effect-official-proportion-vector.jpg') center center / cover no-repeat;
            display: block;
        }

        .plate-badge {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 900;
            font-size: 1rem;
            color: #0f172a;
            background: rgba(255,255,255,0.9);
            border: 2px solid rgba(15,23,42,0.2);
            box-shadow: inset 0 0 0 1px rgba(15,23,42,0.06);
        }

        .plate-center {
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 16px 18px 12px;
        }

        .plate-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
        }

        .plate-country {
            color: #1f2937;
            font-size: clamp(2.4rem, 3vw, 4.3rem);
            font-weight: 900;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            line-height: 1;
        }

        .plate-code {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 90px;
            padding: 12px 12px;
            background: linear-gradient(180deg, #2d6ee7 0%, #1f55c8 100%);
            color: #fff;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 900;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            box-shadow: inset 0 -2px 0 rgba(0,0,0,0.12);
        }

        .plate-number {
            display: block;
            color: #081327;
            font-size: clamp(4.8rem, 8vw, 8.3rem);
            letter-spacing: 0.04em;
            font-weight: 900;
            line-height: 0.9;
            font-family: Impact, Haettenschweiler, 'Arial Narrow Bold', sans-serif;
            text-transform: uppercase;
            text-shadow: 1px 2px 0 rgba(255,255,255,0.18), -1px -1px 0 rgba(0,0,0,0.03);
        }

        .plate-bottom {
            display: flex;
            justify-content: space-between;
            align-items: end;
            gap: 12px;
            margin-top: 8px;
        }

        .plate-holder {
            color: rgba(17,24,39,0.82);
            font-size: 0.68rem;
            font-weight: 900;
            letter-spacing: 0.16em;
            text-transform: uppercase;
        }

        .plate-tag {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 74px;
            padding: 10px 12px;
            background: linear-gradient(180deg, #2d6ee7 0%, #1f55c8 100%);
            color: #fff;
            border-radius: 10px;
            font-size: 1.15rem;
            font-weight: 900;
            letter-spacing: 0.08em;
            box-shadow: inset 0 -2px 0 rgba(0,0,0,0.12);
        }

        .feature-strip {
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            padding: 14px 14px 16px;
            margin-top: auto;
            background: linear-gradient(180deg, rgba(10,38,75,0.78), rgba(7,27,56,0.97));
            border-top: 1px solid rgba(255,255,255,0.12);
        }

        .feature-box {
            min-height: 90px;
            padding: 12px 8px;
            border-radius: 14px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.12);
            color: #fff;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            gap: 6px;
            transition: transform 0.25s ease, background 0.25s ease, border-color 0.25s ease, box-shadow 0.25s ease;
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.04);
        }

        .feature-box:hover {
            transform: translateY(-3px);
            background: rgba(255,255,255,0.09);
            border-color: rgba(245,199,106,0.5);
            box-shadow: 0 12px 24px rgba(9, 24, 47, 0.12);
        }

        .feature-box i {
            font-size: 1.5rem;
            color: var(--gold);
        }

        .feature-box strong {
            font-size: 0.92rem;
            font-weight: 800;
            color: #fff;
        }

        .feature-box span {
            font-size: 0.7rem;
            line-height: 1.5;
            color: rgba(255,255,255,0.9);
            font-weight: 600;
        }

        .right-panel {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px 20px;
            background: linear-gradient(180deg, rgba(255,255,255,0.30), rgba(255,255,255,0.18));
        }

        .login-wrap {
            width: min(100%, 390px);
            background: rgba(255,255,255,0.97);
            border-radius: 28px;
            border: 1px solid rgba(12,41,83,0.08);
            box-shadow: 0 26px 48px rgba(12,41,83,0.18), inset 0 1px 0 rgba(255,255,255,0.8);
            padding: 28px 22px 22px;
            backdrop-filter: blur(12px);
        }

        .brand-block {
            display: flex;
            justify-content: center;
            margin-bottom: 16px;
        }

        .brand-circle {
            width: 122px;
            height: 122px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            background: radial-gradient(circle at 35% 30%, #ffffff 0%, #edf4ff 20%, #dfeaf9 52%, #eef3fb 100%);
            border: 4px solid rgba(245,199,106,0.96);
            box-shadow: 0 18px 30px rgba(13,45,95,0.12), inset 0 0 0 5px rgba(13,45,95,0.04);
            position: relative;
            overflow: hidden;
        }

        .brand-circle::before {
            content: "";
            position: absolute;
            inset: 10px;
            border: 2px solid rgba(13,45,95,0.18);
            border-radius: 50%;
        }

        .brand-circle::after {
            content: "";
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at center, transparent 52%, rgba(13,45,95,0.08) 100%);
            pointer-events: none;
        }

        .brand-circle img {
            width: 78px;
            height: 78px;
            object-fit: contain;
            z-index: 1;
            filter: drop-shadow(0 4px 10px rgba(13,45,95,0.14));
            border-radius: 50%;
        }

        .login-heading {
            text-align: center;
            color: var(--primary);
            font-size: clamp(1.8rem, 2vw, 2.25rem);
            line-height: 1.2;
            font-weight: 900;
            letter-spacing: -0.04em;
        }

        .login-rule {
            width: 126px;
            height: 3px;
            margin: 12px auto 12px;
            border-radius: 999px;
            background: linear-gradient(90deg, var(--accent), rgba(199,29,53,0.22));
        }

        .login-subtext {
            text-align: center;
            color: var(--primary);
            font-size: 0.96rem;
            font-weight: 700;
            margin-bottom: 22px;
        }

        form {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .field label {
            color: var(--primary);
            font-size: 0.95rem;
            font-weight: 800;
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
            font-size: 1.05rem;
        }

        .input-shell input {
            width: 100%;
            height: 54px;
            border-radius: 13px;
            border: 1px solid #d2ddef;
            background: #f7fafd;
            color: var(--dark);
            padding: 16px 42px 16px 44px;
            font-size: 1.02rem;
            font-weight: 600;
            outline: none;
            transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
        }

        .input-shell input:focus {
            border-color: rgba(13,45,95,0.82);
            background: #fff;
            box-shadow: 0 0 0 4px rgba(13,45,95,0.08);
        }

        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            border: none;
            background: transparent;
            color: var(--muted);
            font-size: 1rem;
            cursor: pointer;
        }

        .row-flex {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-top: 4px;
        }

        .remember-me {
            color: var(--muted);
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .remember-me input {
            accent-color: var(--primary);
        }

        .forgot-link {
            color: var(--primary);
            text-decoration: none;
            font-weight: 700;
            font-size: 0.9rem;
        }

        .login-btn {
            width: 100%;
            min-height: 54px;
            border: none;
            border-radius: 14px;
            background: linear-gradient(135deg, #0d2d5f 0%, #184a9c 50%, #123f88 100%);
            color: #fff;
            font-size: 0.96rem;
            font-weight: 900;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            cursor: pointer;
            box-shadow: 0 18px 30px rgba(13,45,95,0.26), 0 0 0 1px rgba(255,255,255,0.08) inset;
            transition: transform 0.2s ease, box-shadow 0.2s ease, filter 0.2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 22px 34px rgba(13,45,95,0.32), 0 0 0 1px rgba(255,255,255,0.08) inset;
            filter: brightness(1.04);
        }

        .divider {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            color: var(--muted);
            font-size: 0.88rem;
            margin: 18px 0 8px;
        }

        .divider::before,
        .divider::after {
            content: "";
            flex: 1;
            height: 1px;
            background: #dfeaf7;
        }

        .register-link,
        .home-link {
            display: block;
            text-align: center;
            text-decoration: none;
            font-weight: 800;
            color: var(--primary);
            margin: 8px auto 0;
            transition: color 0.2s ease, transform 0.2s ease, text-shadow 0.2s ease;
        }

        .register-link:hover,
        .home-link:hover,
        .forgot-link:hover {
            color: var(--accent);
            transform: translateY(-1px);
            text-shadow: 0 4px 10px rgba(199, 29, 53, 0.12);
        }

        .home-link {
            margin-top: 12px;
            color: var(--primary);
        }

        @media (max-width: 980px) {
            .page-shell {
                grid-template-columns: 1fr;
            }

            .left-panel {
                padding-bottom: 20px;
            }

            .hero-copy {
                text-align: center;
                margin: 0 auto;
            }

            .title-divider {
                margin-left: auto;
                margin-right: auto;
            }

            .feature-strip {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 640px) {
            body {
                padding: 18px;
            }

            .page-shell {
                width: min(100%, 480px);
                border-radius: 22px;
            }

            .left-panel {
                padding: 26px 18px 0;
            }

            .right-panel {
                padding: 18px 14px 18px;
            }

            .welcome-text {
                font-size: 2.3rem;
            }

            .supporting-copy {
                font-size: 0.95rem;
            }

            .plate-wrap {
                width: 78%;
                height: 100px;
            }

            .feature-box {
                min-height: 78px;
            }
        }
    </style>
</head>
<body>
    <div class="page-shell">
        <section class="left-panel">
            <div class="hero-copy">
                <h1 class="welcome-text">Welcome to <strong>TUA OFFICIAL PORTAL</strong></h1>
                <div class="sub-title">Car License Plate Registration System</div>
                <div class="title-divider"></div>
                <p class="supporting-copy">A secure and efficient platform for managing vehicle license plate registration and records.</p>
            </div>

            <div class="art-stage" aria-label="Generated vehicle registration plate preview">
                <div class="plate-wrap" aria-label="Generated plate preview">
                    <div class="plate-inner">
                        <div class="plate-side">
                            <span class="plate-flag" aria-hidden="true"></span>
                            <span class="plate-badge">LB</span>
                        </div>

                        <div class="plate-center">
                            <div class="plate-head">
                                <span class="plate-country">Liberia</span>
                                <span class="plate-code">AUG</span>
                            </div>
                            <span class="plate-number">C12229</span>
                            <div class="plate-bottom">
                                <span class="plate-holder">Transport Union Authority</span>
                                <span class="plate-tag">26</span>
                            </div>
                        </div>

                        <div class="plate-side right">
                            <span class="plate-badge" style="background:#fff;">T</span>
                            <span class="plate-tag" style="min-width:62px; font-size:1.1rem;">26</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="feature-strip">
                <div class="feature-box">
                    <i class="fa-solid fa-shield-halved"></i>
                    <strong>Secure &amp; Reliable</strong>
                    <span>Advanced security for your data protection</span>
                </div>
                <div class="feature-box">
                    <i class="fa-solid fa-bolt"></i>
                    <strong>Fast &amp; Efficient</strong>
                    <span>Quick processing and real-time updates</span>
                </div>
                <div class="feature-box">
                    <i class="fa-solid fa-user-check"></i>
                    <strong>User Friendly</strong>
                    <span>Intuitive design for seamless experience</span>
                </div>
            </div>
        </section>

        <aside class="right-panel">
            <div class="login-wrap">
                <div class="brand-block">
                    <div class="brand-circle">
                        <img src="assets/images/logo.png" alt="TUA Logo">
                    </div>
                </div>

                <h2 class="login-heading">Administrator &amp; Staff Login</h2>
                <div class="login-rule"></div>
                <div class="login-subtext">Access your account to continue</div>

                <form action="auth.php" method="POST">
                    <div class="field">
                        <label for="username">Username</label>
                        <div class="input-shell">
                            <i class="fa-solid fa-user"></i>
                            <input type="text" id="username" name="username" placeholder="admin" required>
                        </div>
                    </div>

                    <div class="field">
                        <label for="password">Password</label>
                        <div class="input-shell">
                            <i class="fa-solid fa-lock"></i>
                            <input type="password" id="password" name="password" placeholder="••••••••" required>
                            <button type="button" class="toggle-password" id="togglePassword" aria-label="Show password">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="row-flex">
                        <label class="remember-me">
                            <input type="checkbox" name="remember">
                            <span>Remember Me</span>
                        </label>
                        <a href="forgot-password.php" class="forgot-link">Forgot Password?</a>
                    </div>

                    <button type="submit" class="login-btn"><i class="fa-solid fa-right-to-bracket"></i> Login to Portal</button>
                </form>

                <div class="divider">or</div>

                <a href="register.php" class="register-link">Don't have an account? Register here</a>
                <a href="index.php" class="home-link"><i class="fa-solid fa-house"></i> Return to Homepage</a>
            </div>
        </aside>
    </div>

    <?php if (!empty($page_toasts)): ?>
        <script>
            window.adminToastQueue = <?php echo json_encode($page_toasts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        </script>
    <?php endif; ?>
    <script src="assets/js/admin-toast.js"></script>
    <script src="assets/js/form-toast-validation.js"></script>
    <script src="assets/js/script.js"></script>
</body>
</html>
