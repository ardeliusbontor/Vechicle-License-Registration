<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FAQ - Transport Union Authority</title>
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

        .faq-item {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            padding: 30px;
            margin-bottom: 25px;
        }

        .faq-item h3 {
            color: #0a2463;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .faq-item h3 i {
            color: #c41e3a;
        }

        .faq-item p {
            color: #475569;
            font-size: 16px;
            line-height: 1.8;
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
        <h1><i class="fa-solid fa-circle-question"></i> Frequently Asked Questions</h1>
        <p>Find answers to common questions about our services</p>
    </div>

    <!-- Content -->
    <div class="content">
        <div class="container">
            <div class="faq-item">
                <h3><i class="fa-solid fa-question-circle"></i> What is a license plate?</h3>
                <p>A license plate is an official identification plate issued by the Transport Union Authority for vehicles being used for driving instruction and practice by learner drivers.</p>
            </div>

            <div class="faq-item">
                <h3><i class="fa-solid fa-file-alt"></i> What documents do I need to apply?</h3>
                <p>You will need valid vehicle registration documents, proof of ownership, and a valid driver's license of the supervising driver.</p>
            </div>

            <div class="faq-item">
                <h3><i class="fa-solid fa-clock"></i> How long does it take to process an application?</h3>
                <p>Applications are typically processed within 3-5 working days. You will receive your application number immediately upon submission.</p>
            </div>

            <div class="faq-item">
                <h3><i class="fa-solid fa-sync"></i> How often do I need to renew my license plate?</h3>
                <p>License plates must be renewed annually. You can apply for renewal online up to 30 days before your current plate expires.</p>
            </div>

            <div class="faq-item">
                <h3><i class="fa-solid fa-car"></i> Can I transfer my license plate to another vehicle?</h3>
                <p>Yes, license plates can be transferred to another vehicle. You will need to submit a transfer application along with the new vehicle's registration documents.</p>
            </div>

            <div class="faq-item">
                <h3><i class="fa-solid fa-diagram-project"></i> What do application statuses mean?</h3>
                <p><strong>Pending</strong> means your request is under review. <strong>Approved</strong> means your request passed verification. <strong>Rejected</strong> means required information was incomplete or did not pass checks. <strong>Paid</strong> confirms your payment was recorded.</p>
            </div>

            <div class="faq-item">
                <h3><i class="fa-solid fa-gavel"></i> What if my application is rejected?</h3>
                <p>You can correct the issue and submit again. If you believe the decision was incorrect, contact support with your application number and supporting documents for appeal review.</p>
            </div>

            <div class="faq-item">
                <h3><i class="fa-solid fa-receipt"></i> Are payments refundable?</h3>
                <p>Payments are generally non-refundable after approval or plate generation. Failed or duplicate payment incidents are reviewed by the finance team after ticket submission.</p>
            </div>

            <div class="faq-item">
                <h3><i class="fa-solid fa-database"></i> How is my data used and retained?</h3>
                <p>Your data is used for registration, verification, and legal compliance. Records are retained according to regulatory requirements and protected under our privacy controls. See our Privacy Policy for details.</p>
            </div>

            <div class="faq-item">
                <h3><i class="fa-solid fa-user-shield"></i> How do I avoid fraud?</h3>
                <p>Only use official TUA channels for applications and payments. Never share payment proof with unofficial agents, and verify all issued plates using the Verify License Plate service.</p>
            </div>
        </div>
    </div>

    <?php include "partials/public-footer.php"; ?>
</body>
</html>
