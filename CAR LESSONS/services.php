<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Our Services - Transport Union Authority</title>
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
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo-section img {
            height: 70px;
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

        .nav-links a {
            text-decoration: none;
            color: #0a2463;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Hero */
        .hero {
            background: linear-gradient(135deg, #0a2463 0%, #0f3460 100%);
            color: white;
            padding: 80px 50px;
            text-align: center;
        }

        .hero h1 {
            font-size: 42px;
            margin-bottom: 15px;
        }

        .hero p {
            font-size: 18px;
            opacity: 0.9;
        }

        /* Content */
        .content {
            background: #f8fafc;
            padding: 60px 50px;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
        }

        .services-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 30px;
        }

        .service-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            padding: 35px;
        }

        .service-card i {
            font-size: 40px;
            color: #0a2463;
            margin-bottom: 20px;
        }

        .service-card h2 {
            color: #0a2463;
            margin-bottom: 15px;
        }

        .service-card p {
            color: #475569;
            line-height: 1.7;
            margin-bottom: 20px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #c41e3a 0%, #a01830 100%);
            color: white;
            padding: 12px 25px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(196, 30, 58, 0.3);
        }

        .policy-board {
            margin-top: 32px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            padding: 28px;
        }

        .policy-board h3 {
            color: #0a2463;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .policy-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .policy-item {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 14px;
            background: #f8fafc;
        }

        .policy-item h4 {
            color: #0f172a;
            margin-bottom: 8px;
            font-size: 15px;
        }

        .policy-item p {
            color: #475569;
            line-height: 1.65;
            font-size: 14px;
            margin: 0;
        }

        .policy-note {
            margin-top: 14px;
            color: #334155;
            font-size: 14px;
        }

        .policy-note a {
            color: #0a2463;
            font-weight: 700;
            text-decoration: none;
        }

        .policy-note a:hover {
            text-decoration: underline;
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
                gap: 12px;
            }

            .hero {
                padding: 50px 20px;
            }

            .hero h1 {
                font-size: 32px;
            }

            .content {
                padding: 40px 20px;
            }

            .services-grid {
                grid-template-columns: 1fr;
            }

            .service-card {
                padding: 24px;
            }

            .policy-grid {
                grid-template-columns: 1fr;
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
            <a href="index.php"><i class="fa-solid fa-arrow-left"></i> Back to Homepage</a>
        </div>
    </nav>

    <!-- Hero -->
    <div class="hero">
        <h1><i class="fa-solid fa-concierge-bell"></i> Our Services</h1>
        <p>Discover all the services we offer</p>
    </div>

    <!-- Content -->
    <div class="content">
        <div class="container">
            <div class="services-grid">
                <div class="service-card">
                    <i class="fa-solid fa-file-lines"></i>
                    <h2>Apply for License Plate</h2>
                    <p>Submit your application online and get your official license plate with ease. Our process is quick and straightforward.</p>
                    <a href="apply.php" class="btn-primary"><i class="fa-solid fa-arrow-right"></i> Apply Now</a>
                </div>
                <div class="service-card">
                    <i class="fa-solid fa-sync-alt"></i>
                    <h2>Renew License Plate</h2>
                    <p>Renew your existing license plate quickly and stay compliant with our regulations. Simple and fast renewal process.</p>
                    <a href="renew.php" class="btn-primary"><i class="fa-solid fa-arrow-right"></i> Renew Now</a>
                </div>
                <div class="service-card">
                    <i class="fa-solid fa-search"></i>
                    <h2>Verify License Plate</h2>
                    <p>Check the authenticity of any license plate issued by the Transport Union Authority. Verify online instantly.</p>
                    <a href="verify.php" class="btn-primary"><i class="fa-solid fa-arrow-right"></i> Verify Now</a>
                </div>
                <div class="service-card">
                    <i class="fa-solid fa-question-circle"></i>
                    <h2>Guidelines & Support</h2>
                    <p>Find all the information you need about our requirements, processes, and get support if you need assistance.</p>
                    <a href="faq.php" class="btn-primary"><i class="fa-solid fa-arrow-right"></i> Learn More</a>
                </div>
            </div>

            <div class="policy-board">
                <h3><i class="fa-solid fa-shield-halved"></i> Service Standards and Policies</h3>
                <div class="policy-grid">
                    <div class="policy-item">
                        <h4>Processing Timelines</h4>
                        <p>Applications are reviewed within 1-2 working days and finalized in 3-5 working days after document and payment verification.</p>
                    </div>
                    <div class="policy-item">
                        <h4>Official Fees</h4>
                        <p>Standard plate processing is USD 100.00. Renewal and special services may carry approved administrative fees.</p>
                    </div>
                    <div class="policy-item">
                        <h4>Refund and Rejection Policy</h4>
                        <p>Payments are non-refundable once an application is approved or a plate is generated. Rejected applications can be corrected and resubmitted.</p>
                    </div>
                    <div class="policy-item">
                        <h4>Fraud Prevention</h4>
                        <p>Always verify plate authenticity via the verification portal. Report suspicious requests or unofficial fees immediately to support.</p>
                    </div>
                </div>
                <p class="policy-note">Use these services under <a href="terms.php">Terms & Conditions</a> and <a href="privacy.php">Privacy Policy</a>.</p>
            </div>
        </div>
    </div>

    <?php include "partials/public-footer.php"; ?>
</body>
</html>
