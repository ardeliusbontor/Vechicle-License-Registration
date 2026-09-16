<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - Transport Union Authority</title>
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

        .card p {
            color: #475569;
            font-size: 16px;
            line-height: 1.8;
            margin-bottom: 15px;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 25px;
            margin-top: 40px;
        }

        .feature-item {
            background: #f8fafc;
            padding: 25px;
            border-radius: 12px;
            text-align: center;
        }

        .feature-item i {
            font-size: 36px;
            color: #c41e3a;
            margin-bottom: 15px;
        }

        .feature-item h3 {
            color: #0a2463;
            margin-bottom: 10px;
        }

        .feature-item p {
            font-size: 14px;
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
        <h1><i class="fa-solid fa-building"></i> About TUA</h1>
        <p>Learn about the Transport Union Authority of the Republic of Liberia</p>
    </div>

    <!-- Content -->
    <div class="content">
        <div class="container">
            <div class="card">
                <h2><i class="fa-solid fa-flag"></i> Our Mission</h2>
                <p>The Transport Union Authority (TUA) is dedicated to promoting safer roads, stronger vehicle accountability, and better governance of registration and licensing in the Republic of Liberia.</p>
                <p>We continue to modernize plate registration by improving verification, tracking ownership, and ensuring that registered vehicles meet national road safety and identity standards.</p>
            </div>

            <div class="card">
                <h2><i class="fa-solid fa-hand-holding-heart"></i> Our Vision</h2>
                <p>To build a transparent and reliable vehicle plate system that supports legal ownership, safer roads, and improved public confidence in Liberia's transport administration.</p>
                <p>As recent reforms continue, the country is increasingly emphasizing accurate documentation, digital verification, and controlled registration procedures to reduce fraud, improve traceability, and strengthen enforcement.</p>
            </div>

            <div class="card">
                <h2><i class="fa-solid fa-car-side"></i> Plate Registration and Recent Reforms</h2>
                <p>In recent years, Liberia has strengthened its motor vehicle plate registration process to keep pace with modern road safety and identity standards. These updates focus on the proper documentation of vehicle ownership, the validation of existing plates, and the approval process before a plate becomes active in the official system.</p>
                <p>Vehicle owners are now expected to provide complete registration details, including the plate number, vehicle information, and ownership records. Existing plate registration follows a review process, where the relevant authority verifies the information before approval. This ensures the plate is recognized as valid, traceable, and compliant with national regulations.</p>
                <p>Applicants who submit an existing plate for review may see that plate remain under Pending status until it is approved by the authority. This process helps prevent duplicate, invalid, or unverified registrations from being entered into the national database.</p>
            </div>

            <div class="card">
                <h2><i class="fa-solid fa-gavel"></i> Rules and Responsibilities</h2>
                <p>Every vehicle owner is required to register and maintain valid ownership details for their vehicle, including plate information, registration records, and update of any ownership changes.</p>
                <p>Plates must be legally issued, properly recorded, and renewed or updated as required. False or incomplete declarations may lead to rejection or delays in the approval process. Road safety, identity control, and legal compliance remain central to Liberia's plate registration framework.</p>
            </div>

            <div class="features-grid">
                <div class="feature-item">
                    <i class="fa-solid fa-shield-check"></i>
                    <h3>Verified</h3>
                    <p>Ownership and plate records are reviewed before activation</p>
                </div>
                <div class="feature-item">
                    <i class="fa-solid fa-bolt"></i>
                    <h3>Updated</h3>
                    <p>Registration standards now follow stronger modern checks</p>
                </div>
                <div class="feature-item">
                    <i class="fa-solid fa-thumbs-up"></i>
                    <h3>Responsible</h3>
                    <p>Road safety and legal compliance remain the priority</p>
                </div>
            </div>
        </div>
    </div>

    <?php include "partials/public-footer.php"; ?>
</body>
</html>
