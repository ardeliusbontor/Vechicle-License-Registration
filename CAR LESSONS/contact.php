<?php
session_start();
include "config.php";
include "partials/admin-toast.php";

$message = '';
$message_type = '';

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS admin_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(50) NOT NULL,
    message VARCHAR(255) NOT NULL,
    reference_id INT DEFAULT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(30) DEFAULT '',
    subject VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('New','Replied') NOT NULL DEFAULT 'New',
    admin_reply TEXT DEFAULT NULL,
    reply_method VARCHAR(20) DEFAULT NULL,
    reply_to_email VARCHAR(100) DEFAULT NULL,
    reply_from_email VARCHAR(100) DEFAULT 'ardeliusnet@outlook.com',
    replied_at DATETIME DEFAULT NULL,
    replied_by INT DEFAULT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$reply_to_col = mysqli_query($conn, "SHOW COLUMNS FROM contact_messages LIKE 'reply_to_email'");
if ($reply_to_col && mysqli_num_rows($reply_to_col) == 0) {
    mysqli_query($conn, "ALTER TABLE contact_messages ADD COLUMN reply_to_email VARCHAR(100) DEFAULT NULL");
}

$reply_from_col = mysqli_query($conn, "SHOW COLUMNS FROM contact_messages LIKE 'reply_from_email'");
if ($reply_from_col && mysqli_num_rows($reply_from_col) == 0) {
    mysqli_query($conn, "ALTER TABLE contact_messages ADD COLUMN reply_from_email VARCHAR(100) DEFAULT 'ardeliusnet@outlook.com'");
}

$is_read_col = mysqli_query($conn, "SHOW COLUMNS FROM contact_messages LIKE 'is_read'");
if ($is_read_col && mysqli_num_rows($is_read_col) == 0) {
    mysqli_query($conn, "ALTER TABLE contact_messages ADD COLUMN is_read TINYINT(1) NOT NULL DEFAULT 0");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = mysqli_real_escape_string($conn, trim($_POST['full_name'] ?? ''));
    $email = mysqli_real_escape_string($conn, trim($_POST['email'] ?? ''));
    $phone = mysqli_real_escape_string($conn, trim($_POST['phone'] ?? ''));
    $subject = mysqli_real_escape_string($conn, trim($_POST['subject'] ?? ''));
    $message_text = mysqli_real_escape_string($conn, trim($_POST['message'] ?? ''));

    if ($full_name !== '' && $email !== '' && $subject !== '' && $message_text !== '') {
        $insert_query = mysqli_query($conn, "INSERT INTO contact_messages (full_name, email, phone, subject, message) VALUES ('$full_name', '$email', '$phone', '$subject', '$message_text')");
        if ($insert_query) {
            $message_id = mysqli_insert_id($conn);
            $notification_text = "New client message from $full_name (Subject: $subject).";
            mysqli_query($conn, "INSERT INTO admin_notifications (event_type, message, reference_id) VALUES ('new_contact_message', '" . mysqli_real_escape_string($conn, $notification_text) . "', $message_id)");
            $message = "Your message has been sent successfully. Our team will get back to you shortly.";
            $message_type = 'success';
        } else {
            $message = "Unable to send your message right now. Please try again later.";
            $message_type = 'danger';
        }
    } else {
        $message = "Please complete all required fields before sending your message.";
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
    <title>Contact Us - Transport Union Authority</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/public-footer.css">
    <link rel="stylesheet" href="assets/css/admin-toast.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .navbar { background: white; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 15px 50px; display: flex; justify-content: space-between; align-items: center; }
        .logo-section { display: flex; align-items: center; gap: 15px; }
        .logo-section img { height: 70px; }
        .logo-text h1 { color: #0a2463; font-size: 16px; font-weight: 700; }
        .logo-text p { color: #c41e3a; font-size: 12px; font-weight: 600; }
        .nav-links a { text-decoration: none; color: #0a2463; font-weight: 600; display: flex; align-items: center; gap: 8px; }
        .hero { background: linear-gradient(135deg, #0a2463 0%, #0f3460 100%); color: white; padding: 80px 50px; text-align: center; }
        .hero h1 { font-size: 42px; margin-bottom: 15px; }
        .hero p { font-size: 18px; opacity: 0.9; }
        .content { background: #f8fafc; padding: 60px 50px; }
        .container { max-width: 1000px; margin: 0 auto; display: grid; grid-template-columns: 1fr 1fr; gap: 40px; }
        .card { background: white; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); padding: 35px; }
        .card h2 { color: #0a2463; margin-bottom: 25px; font-size: 22px; display: flex; align-items: center; gap: 10px; }
        .contact-item { display: flex; align-items: flex-start; gap: 15px; margin-bottom: 25px; }
        .contact-item i { font-size: 24px; color: #c41e3a; margin-top: 5px; min-width: 30px; }
        .contact-item h4 { color: #0a2463; margin-bottom: 5px; }
        .contact-item p { color: #475569; }
        .alert { padding: 14px 16px; border-radius: 10px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: #dcfce7; color: #166534; }
        .alert-danger { background: #fee2e2; color: #991b1b; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; color: #0f172a; font-weight: 600; font-size: 14px; }
        .form-group input, .form-group textarea { width: 100%; padding: 14px 16px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 15px; outline: none; transition: border-color 0.3s; }
        .form-group input:focus, .form-group textarea:focus { border-color: #0a2463; }
        .btn-send { width: 100%; background: linear-gradient(135deg, #0a2463 0%, #0f3460 100%); color: white; padding: 15px; border: none; border-radius: 10px; font-size: 16px; font-weight: 700; cursor: pointer; transition: all 0.3s; }
        .btn-send:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(10, 36, 99, 0.3); }
        .support-note { margin-top: 22px; border-top: 1px solid #e2e8f0; padding-top: 18px; }
        .support-note h3 { color: #0a2463; margin-bottom: 12px; font-size: 18px; display: flex; align-items: center; gap: 8px; }
        .support-note ul { margin-left: 18px; color: #334155; line-height: 1.7; font-size: 14px; }
        .support-note p { color: #475569; font-size: 14px; line-height: 1.7; margin-top: 10px; }
        .support-note a { color: #0a2463; font-weight: 700; text-decoration: none; }
        .support-note a:hover { text-decoration: underline; }
        @media (max-width: 768px) { .navbar { padding: 16px 20px; flex-direction: column; align-items: flex-start; gap: 12px; } .content { padding: 40px 20px; } .container { grid-template-columns: 1fr; } .hero { padding: 50px 20px; } .hero h1 { font-size: 32px; } }
    </style>
</head>
<body>
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

    <div class="hero">
        <h1><i class="fa-solid fa-envelope"></i> Contact Us</h1>
        <p>Get in touch with us</p>
    </div>

    <div class="content">
        <div class="container">
            <div class="card">
                <h2><i class="fa-solid fa-location-dot"></i> Contact Information</h2>
                <div class="contact-item"><i class="fa-solid fa-building"></i><div><h4>Head Office</h4><p>Broad Street, Monrovia<br>Republic of Liberia</p></div></div>
                <div class="contact-item"><i class="fa-solid fa-phone"></i><div><h4>Phone</h4><p>+231 20 123 4567</p></div></div>
                <div class="contact-item"><i class="fa-solid fa-envelope"></i><div><h4>Email</h4><p>info@tua.gov.lr</p></div></div>
                <div class="contact-item"><i class="fa-solid fa-clock"></i><div><h4>Business Hours</h4><p>Monday - Friday: 8:00 AM - 5:00 PM<br>Saturday: 9:00 AM - 1:00 PM</p></div></div>
                <div class="support-note">
                    <h3><i class="fa-solid fa-headset"></i> Support Standards</h3>
                    <ul>
                        <li>General inquiries: response within 1 working day.</li>
                        <li>Application and payment disputes: response within 2 working days.</li>
                        <li>Fraud or impersonation reports: priority escalation within same day.</li>
                    </ul>
                    <p>Need accessibility help with forms or verification? Request assisted support through this page or visit during business hours.</p>
                    <p>See <a href="privacy.php">Privacy Policy</a> and <a href="terms.php">Terms & Conditions</a> for official communication and data handling rules.</p>
                </div>
            </div>

            <div class="card">
                <h2><i class="fa-solid fa-paper-plane"></i> Send us a Message</h2>
                <form method="POST" action="">
                    <div class="form-group"><label>Full Name</label><input type="text" name="full_name" placeholder="Your name" required></div>
                    <div class="form-group"><label>Email Address</label><input type="email" name="email" placeholder="Your email" required></div>
                    <div class="form-group"><label>Phone Number</label><input type="text" name="phone" placeholder="Your phone number"></div>
                    <div class="form-group"><label>Subject</label><input type="text" name="subject" placeholder="Message subject" required></div>
                    <div class="form-group"><label>Message</label><textarea name="message" rows="4" placeholder="Your message" required></textarea></div>
                    <button type="submit" class="btn-send"><i class="fa-solid fa-send"></i> Send Message</button>
                </form>
            </div>
        </div>
    </div>

    <?php include "partials/public-footer.php"; ?>
    <?php if (!empty($page_toasts)): ?>
        <script>
            window.adminToastQueue = <?php echo json_encode($page_toasts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        </script>
    <?php endif; ?>
    <script src="assets/js/admin-toast.js"></script>
    <script src="assets/js/form-toast-validation.js"></script>
</body>
</html>
