<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms and Conditions - Transport Union Authority</title>
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
            max-width: 900px;
            margin: 0 auto;
        }

        .card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            padding: 40px;
            margin-bottom: 30px;
        }

        .card h2 {
            color: #0a2463;
            font-size: 24px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card h3 {
            color: #0a2463;
            font-size: 18px;
            margin: 20px 0 10px;
        }

        .card p {
            color: #475569;
            font-size: 16px;
            line-height: 1.8;
            margin-bottom: 15px;
        }

        .card ul {
            margin: 15px 0;
            padding-left: 30px;
            color: #475569;
            font-size: 16px;
            line-height: 1.8;
        }

        .card li {
            margin-bottom: 10px;
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
        <h1><i class="fa-solid fa-file-contract"></i> Terms and Conditions</h1>
        <p>Legal terms and conditions for using TUA's services</p>
    </div>

    <!-- Content -->
    <div class="content">
        <div class="container">
            <div class="card">
                <h2><i class="fa-solid fa-scale-balanced"></i> Introduction</h2>
                <p>Welcome to the Transport Union Authority (TUA) Car License Plate Registration System. By accessing or using our services, you agree to be bound by these Terms and Conditions. Please read them carefully before using our services.</p>
            </div>

            <div class="card">
                <h2><i class="fa-solid fa-user-check"></i> Eligibility</h2>
                <p>To apply for a license plate, you must:</p>
                <ul>
                    <li>Be at least 18 years of age</li>
                    <li>Be a resident of Liberia</li>
                    <li>Have a valid learner's permit or driver's license</li>
                    <li>Provide accurate and truthful information in your application</li>
                </ul>
            </div>

            <div class="card">
                <h2><i class="fa-solid fa-id-card"></i> Application Process</h2>
                <p>All applications are subject to review and approval by TUA. We reserve the right to reject any application without providing a reason. You are responsible for ensuring all information provided is accurate and up-to-date.</p>
            </div>

            <div class="card">
                <h2><i class="fa-solid fa-credit-card"></i> Payments</h2>
                <p>All fees are non-refundable unless required by law. Payment must be made in full before your application is processed. We accept various payment methods as indicated on our website.</p>
            </div>

            <div class="card">
                <h2><i class="fa-solid fa-gavel"></i> Compliance</h2>
                <p>License plates must be used in accordance with Liberian traffic laws. Misuse or alteration of license plates may result in revocation and legal action.</p>
            </div>

            <div class="card">
                <h2><i class="fa-solid fa-circle-exclamation"></i> Disclaimer</h2>
                <p>TUA provides services on an "as is" and "as available" basis. We do not warrant that our services will be uninterrupted or error-free.</p>
            </div>

            <div class="card">
                <h2><i class="fa-solid fa-sync-alt"></i> Changes to Terms</h2>
                <p>We reserve the right to update these Terms and Conditions at any time without prior notice. Your continued use of our services constitutes acceptance of the updated terms.</p>
            </div>
        </div>
    </div>

    <?php include "partials/public-footer.php"; ?>
</body>
</html>