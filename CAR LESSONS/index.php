<?php
session_start();
$apply_link = isset($_SESSION['id']) ? 'apply.php' : 'register.php';
$plate_letters = ['A', 'B', 'C', 'D', 'E'];
$sample_plate = $plate_letters[((int) date('n') - 1) % count($plate_letters)] . str_pad((int) date('z') + 12000, 5, '0', STR_PAD_LEFT);
$sample_month = strtoupper(date('M'));
$sample_year = date('y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transport Union Authority - Republic of Liberia</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/public-footer.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        /* Navbar */
        .navbar {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 15px 50px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo-section img {
            height: 40px;
        }

        .logo-text h1 {
            color: #0a2463;
            font-size: 16px;
            font-weight: 700;
        }

        .logo-text p {
            color: #c41e3a;
            font-size: 12px;
            font-weight: 600;
        }

        .nav-links {
            display: flex;
            gap: 35px;
            align-items: center;
        }

        .nav-links a {
            text-decoration: none;
            color: #1a1a1a;
            font-size: 14px;
            font-weight: 600;
            transition: color 0.3s;
        }

        .nav-links a:hover {
            color: #c41e3a;
        }

        .apply-btn {
            background: linear-gradient(135deg, #0a2463 0%, #0f3460 100%);
            color: white;
            padding: 10px 25px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .apply-btn:hover {
            background: linear-gradient(135deg, #0f3460 0%, #0a2463 100%);
        }

        /* Hero Section */
        .hero {
            background: linear-gradient(135deg, rgba(10,36,99,0.95) 0%, rgba(15,52,96,0.9) 100%), 
                        url('https://images.unsplash.com/photo-1542362567-b07e54358753?w=1600') center/cover;
            color: white;
            padding: 60px 50px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 50px;
            align-items: center;
        }

        .hero-content h1 {
            font-size: 48px;
            line-height: 1.2;
            margin-bottom: 20px;
        }

        .hero-content p {
            font-size: 18px;
            margin-bottom: 30px;
            opacity: 0.9;
        }

        .hero-features {
            display: flex;
            gap: 30px;
            margin-bottom: 35px;
        }

        .feature-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
        }

        .feature-item i {
            background: rgba(255,255,255,0.2);
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .feature-item span {
            font-size: 12px;
            font-weight: 600;
        }

        .hero-buttons {
            display: flex;
            gap: 20px;
        }

        .btn-primary {
            background: #c41e3a;
            color: white;
            padding: 14px 30px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            border: none;
            cursor: pointer;
        }

        .btn-secondary {
            background: transparent;
            color: white;
            padding: 14px 30px;
            border: 2px solid white;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 700;
        }

        .hero-visual {
            position: relative;
        }

        .hero-visual img {
            width: 100%;
            max-height: 400px;
            object-fit: contain;
            border-radius: 15px;
        }

        .plate-card {
            position: relative;
            display: grid;
            grid-template-columns: 86px 1fr;
            width: min(100%,  );
            min-height: 300px;
            margin-top: 20px;
            border-radius: 18px;
            overflow: hidden;
            background:
                linear-gradient(180deg, rgba(255,255,255,0.18), transparent 18%),
                linear-gradient(180deg, #ef8d2f 0%, #ea7f1c 100%);
            border: 6px solid #d96f12;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.35);
        }

        .plate-card .plate-bolt {
            position: absolute;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: radial-gradient(circle at 30% 30%, #ffffff 0%, #d9d9d9 38%, #9ca3af 100%);
            border: 2px solid rgba(15, 23, 42, 0.28);
            box-shadow: inset 0 1px 2px rgba(255,255,255,0.85);
        }

        .plate-card .bolt-top-left {
            top: 18px;
            left: 32px;
        }

        .plate-card .bolt-top-right {
            top: 18px;
            right: 32px;
        }

        .plate-card::before,
        .plate-card::after {
            content: "";
            position: absolute;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: #d9d9d9;
            border: 2px solid rgba(15, 23, 42, 0.28);
            box-shadow: inset 0 1px 2px rgba(255,255,255,0.75);
            bottom: 16px;
        }

        .plate-card::before {
            left: 30px;
        }

        .plate-card::after {
            right: 30px;
        }

        .plate-side {
            background: linear-gradient(180deg, #f8fafc 0%, #e5eef7 100%);
            border-right: 2px solid rgba(15, 23, 42, 0.18);
            padding: 16px 10px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
        }

        .plate-header {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .plate-header img {
            width: 42px;
            height: 30px;
            object-fit: cover;
            border-radius: 3px;
            border: 1px solid rgba(15, 23, 42, 0.12);
        }

        .plate-badge {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            border: 2px solid #64748b;
            color: #0f172a;
            font-weight: 800;
            font-size: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255,255,255,0.85);
        }

        .plate-main {
            display: flex;
            flex-direction: column;
            position: relative;
            padding: 14px 22px 22px;
            background-image: linear-gradient(90deg, rgba(255,255,255,0.06), transparent 15%, transparent 85%, rgba(0,0,0,0.05));
        }

        .plate-topline {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .plate-country {
            color: #22170f;
            font-size: 40px;
            font-weight: 900;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            padding-left: 8px;
            text-shadow: 0 1px 0 rgba(255,255,255,0.18);
        }

        .plate-sticker,
        .plate-year {
            min-width: 76px;
            padding: 8px 12px;
            background: linear-gradient(180deg, #2d6ee7 0%, #1f55c8 100%);
            color: white;
            border-radius: 8px;
            font-weight: 900;
            text-align: center;
            box-shadow: inset 0 -2px 0 rgba(0,0,0,0.12), 0 3px 6px rgba(15, 23, 42, 0.15);
            border: 1px solid rgba(255,255,255,0.18);
        }

        .plate-sticker {
            font-size: 28px;
            line-height: 1;
        }

        .plate-sticker small {
            display: block;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 0.08em;
            margin-top: 3px;
        }

        .plate-number {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #111827;
            font-family: Impact, Haettenschweiler, 'Arial Narrow Bold', sans-serif;
            font-size: 138px;
            line-height: 0.86;
            font-weight: 900;
            letter-spacing: 0.025em;
            text-transform: uppercase;
            text-shadow: 1px 2px 0 rgba(255,255,255,0.14), -1px -1px 0 rgba(0,0,0,0.06);
            padding: 12px 0 4px;
            transform: scaleY(1.04);
        }

        .plate-footer {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: auto;
        }

        .plate-code {
            color: rgba(17, 24, 39, 0.78);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.22em;
            text-transform: uppercase;
            padding-left: 8px;
        }

        .plate-year {
            font-size: 34px;
            line-height: 1;
            min-width: 72px;
        }

        .plate-year small {
            display: block;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 0.08em;
            margin-top: 3px;
        }

        /* Services Section */
        .services {
            padding: 80px 50px;
            background: #f8fafc;
        }

        .services-header {
            text-align: center;
            margin-bottom: 50px;
        }

        .services-header h3 {
            color: #c41e3a;
            font-size: 14px;
            margin-bottom: 10px;
            letter-spacing: 2px;
        }

        .services-header h2 {
            color: #1a1a1a;
            font-size: 32px;
            font-weight: 700;
        }

        .services-grid .service-card:nth-child(3) i {
            color: #c41e3a;
        }

        .services-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }

        .service-card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .service-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .service-card i {
            font-size: 30px;
            color: #0a2463;
            margin-bottom: 15px;
        }

        .service-card h3 {
            color: #1a1a1a;
            font-size: 18px;
            margin-bottom: 10px;
        }

        .service-card p {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 15px;
        }

        .service-card a {
            color: #c41e3a;
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Why Choose Section */
        .why-choose {
            background: linear-gradient(135deg, #0a2463 0%, #0f3460 100%);
            color: white;
            padding: 80px 50px;
            text-align: center;
        }

        .why-choose h3 {
            color: #ffc107;
            font-size: 14px;
            margin-bottom: 10px;
            letter-spacing: 2px;
        }

        .why-choose h2 {
            font-size: 32px;
            margin-bottom: 50px;
        }

        .why-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 40px;
        }

        .why-item {
            text-align: center;
        }

        .why-item i {
            font-size: 40px;
            margin-bottom: 15px;
        }

        .why-item h4 {
            margin-bottom: 10px;
        }

        .why-item p {
            font-size: 14px;
            opacity: 0.85;
        }

        .quick-access {
            background: white;
            padding: 70px 50px;
            border-top: 1px solid #e2e8f0;
        }

        .quick-access-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            max-width: 1100px;
            margin: 0 auto;
        }

        .quick-access-card {
            background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 28px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.04);
        }

        .quick-access-card i {
            font-size: 26px;
            color: #0a2463;
            margin-bottom: 12px;
        }

        .quick-access-card h3 {
            color: #0a2463;
            font-size: 18px;
            margin-bottom: 8px;
        }

        .quick-access-card p {
            color: #64748b;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 12px;
        }

        .quick-access-card a {
            color: #c41e3a;
            text-decoration: none;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        @media (max-width: 1024px) {
            .hero {
                grid-template-columns: 1fr;
                padding: 50px 24px;
            }

            .services-grid,
            .why-grid,
            .quick-access-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .navbar {
                padding: 16px 20px;
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }

            .nav-links {
                flex-wrap: wrap;
                gap: 16px;
            }

            .hero {
                padding: 40px 20px;
            }

            .hero-content h1 {
                font-size: 32px;
            }

            .hero-features,
            .hero-buttons {
                flex-direction: column;
                align-items: flex-start;
            }

            .services,
            .why-choose,
            .quick-access {
                padding: 50px 20px;
            }

            .services-grid,
            .why-grid,
            .quick-access-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .logo-section img {
                height: 34px;
            }

            .logo-text h1 {
                font-size: 14px;
            }

            .plate-number {
                font-size: 72px;
            }

            .service-card,
            .quick-access-card {
                padding: 22px;
            }

            .plate-card {
                grid-template-columns: 70px 1fr;
                min-height: 210px;
            }

            .plate-country {
                font-size: 22px;
                letter-spacing: 0.1em;
            }

            .plate-sticker,
            .plate-year {
                min-width: 56px;
                font-size: 20px;
            }

            .plate-code {
                font-size: 9px;
                letter-spacing: 0.12em;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="logo-section">
            <img src="assets/images/logo.png" alt="TUA Logo">
            <div class="logo-text">
                <h1>TRANSPORT UNION AUTHORITY</h1>
                <p>REPUBLIC OF LIBERIA</p>
            </div>
        </div>
        <div class="nav-links">
            <a href="index.php">HOME</a>
            <a href="about.php">ABOUT US</a>
            <a href="services.php">SERVICES</a>
            <a href="faq.php">FAQ</a>
            <a href="contact.php">CONTACT US</a>
            <?php if (isset($_SESSION['id'])): ?>
                <a href="user-dashboard.php" class="apply-btn"><i class="fa-solid fa-gauge-high"></i> DASHBOARD</a>
            <?php else: ?>
                <a href="login.php" class="apply-btn"><i class="fa-solid fa-user"></i> LOGIN / REGISTER</a>
            <?php endif; ?>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <h1>Drive Learning.<br>Drive the Future.</h1>
            <p>Official Car License Plates issued by the Transport Union Authority of Liberia.</p>
            
            <div class="hero-features">
                <div class="feature-item">
                    <i class="fa-solid fa-shield-halved"></i>
                    <span>Authorized<br>by TUA</span>
                </div>
                <div class="feature-item">
                    <i class="fa-solid fa-check-circle"></i>
                    <span>Secure &<br>Reliable</span>
                </div>
                <div class="feature-item">
                    <i class="fa-solid fa-clock"></i>
                    <span>Fast<br>Processing</span>
                </div>
            </div>

            <div class="hero-buttons">
                <a href="<?php echo $apply_link; ?>" class="btn-primary">APPLY FOR LICENSE PLATE <i class="fa-solid fa-arrow-right"></i></a>
                <a href="about.php" class="btn-secondary">LEARN MORE</a>
            </div>
        </div>
        <div class="hero-visual">
            <img src="assets/images/carim.png">
            <div class="plate-card">
                <span class="plate-bolt bolt-top-left"></span>
                <span class="plate-bolt bolt-top-right"></span>
                <div class="plate-side">
                    <div class="plate-header">
                        <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/b/b8/Flag_of_Liberia.svg/1280px-Flag_of_Liberia.svg.png" alt="Liberian Flag">
                    </div>
                    <div class="plate-badge">LB</div>
                </div>
                <div class="plate-main">
                    <div class="plate-topline">
                        <div class="plate-country">LIBERIA</div>
                        <div class="plate-sticker"><?php echo $sample_month; ?><small>2026</small></div>
                    </div>
                    <div class="plate-number"><?php echo htmlspecialchars($sample_plate); ?></div>
                    <div class="plate-footer">
                        <div class="plate-code">Transport Union Authority</div>
                        <div class="plate-year"><?php echo $sample_year; ?><small>REG</small></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section class="services">
        <div class="services-header">
            <h3>OUR SERVICES</h3>
            <h2>Everything you need,<br>in one place.</h2>
        </div>
        <div class="services-grid">
            <div class="service-card">
                <i class="fa-solid fa-file-lines"></i>
                <h3>Step 1: Apply Online</h3>
                <p>Submit your application quickly and receive your tracking details in a few simple steps.</p>
                <a href="<?php echo $apply_link; ?>"><i class="fa-solid fa-arrow-right-long"></i> Start Application</a>
            </div>
            <div class="service-card">
                <i class="fa-solid fa-credit-card"></i>
                <h3>Step 2: Make Secure Payment</h3>
                <p>Payments are recorded before approval, keeping the issuance process transparent and reliable.</p>
                <a href="payments.php"><i class="fa-solid fa-arrow-right-long"></i> Learn More</a>
            </div>
            <div class="service-card">
                <i class="fa-solid fa-qrcode"></i>
                <h3>Step 3: Verify Your Plate</h3>
                <p>Every issued plate can be verified with full details including issue and expiry dates.</p>
                <a href="verify.php"><i class="fa-solid fa-arrow-right-long"></i> Verify Now</a>
            </div>
            <div class="service-card">
                <i class="fa-solid fa-circle-info"></i>
                <h3>Need Help?</h3>
                <p>Visit the FAQ section or contact our support team for guidance on renewals and applications.</p>
                <a href="faq.php"><i class="fa-solid fa-arrow-right-long"></i> View Support</a>
            </div>
        </div>
    </section>

    <!-- Why Choose Section -->
    <section class="why-choose">
        <h3>WHY CHOOSE TUA</h3>
        <h2>Built for Safety. Driven by Trust.</h2>
        <div class="why-grid">
            <div class="why-item">
                <i class="fa-solid fa-shield-halved"></i>
                <h4>Government Authorized</h4>
                <p>Issued and regulated by the Transport Union Authority, Republic of Liberia.</p>
            </div>
            <div class="why-item">
                <i class="fa-solid fa-user-friends"></i>
                <h4>Promoting Road Safety</h4>
                <p>Supporting safe driving education and responsible learners on our roads.</p>
            </div>
            <div class="why-item">
                <i class="fa-solid fa-award"></i>
                <h4>Quality & Standard</h4>
                <p>Our plates meet official standards for visibility, durability and security.</p>
            </div>
            <div class="why-item">
                <i class="fa-solid fa-headset"></i>
                <h4>Customer Support</h4>
                <p>We are here to help you every step of the way.</p>
            </div>
        </div>
    </section>

    <section class="quick-access">
        <div class="quick-access-grid">
            <div class="quick-access-card">
                <i class="fa-solid fa-file-signature"></i>
                <h3>Apply for a Plate</h3>
                <p>Begin your license plate application in just a few steps and track progress online.</p>
                <a href="<?php echo $apply_link; ?>">Start Application <i class="fa-solid fa-arrow-right"></i></a>
            </div>
            <div class="quick-access-card">
                <i class="fa-solid fa-car-side"></i>
                <h3>Register Existing Plate</h3>
                <p>Submit a current or previously owned plate for verification and admin review before activation.</p>
                <a href="register-existing-plate.php">Register Now <i class="fa-solid fa-arrow-right"></i></a>
            </div>
            <div class="quick-access-card">
                <i class="fa-solid fa-rotate-right"></i>
                <h3>Renew Existing Plate</h3>
                <p>Keep your learner plate active by renewing with your current plate details.</p>
                <a href="renew.php">Renew Now <i class="fa-solid fa-arrow-right"></i></a>
            </div>
            <div class="quick-access-card">
                <i class="fa-solid fa-qrcode"></i>
                <h3>Verify Plate Details</h3>
                <p>Scan or search to confirm authenticity, validity, issue and expiry dates instantly.</p>
                <a href="verify.php">Verify Plate <i class="fa-solid fa-arrow-right"></i></a>
            </div>
        </div>
    </section>

    <?php include "partials/public-footer.php"; ?>
</body>
</html>
