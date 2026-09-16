<?php
session_start();
include "partials/admin-toast.php";

if (!isset($_SESSION['id'])) {
    admin_toast_add("Please log in to renew your license plate.", 'warning');
    header("Location: login.php");
    exit();
}

if (($_SESSION['role'] ?? '') !== 'Applicant') {
    admin_toast_add("Only applicants can renew plates. Please use the staff dashboard to manage requests.", 'info');
    header("Location: dashboard.php");
    exit();
}

include "config.php";

if (!function_exists('normalizePlateNumber')) {
    function normalizePlateNumber($plate) {
        $normalized = strtoupper(trim((string) $plate));
        $normalized = preg_replace('/\s+/', '', $normalized);
        $normalized = preg_replace('/[^A-Z0-9]/', '', $normalized);

        return $normalized;
    }
}

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS admin_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(50) NOT NULL,
    message VARCHAR(255) NOT NULL,
    reference_id INT DEFAULT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$message = '';
$message_type = '';

$default_plate = trim((string) ($_GET['plate'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $posted_plate = isset($_POST['plate_number']) ? $_POST['plate_number'] : '';
    $posted_name = isset($_POST['applicant_name']) ? $_POST['applicant_name'] : '';
    $plate_number = normalizePlateNumber(mysqli_real_escape_string($conn, $posted_plate));
    $fallback_name = isset($_SESSION['fullname']) ? $_SESSION['fullname'] : '';
    $applicant_name = mysqli_real_escape_string($conn, ($posted_name !== '') ? $posted_name : $fallback_name);

    if ($plate_number === '') {
        $message = "Please enter a valid current plate number.";
        $message_type = 'danger';
    } else {
        $check_plate = mysqli_query($conn, "SELECT * FROM vehicles WHERE UPPER(TRIM(plate_number)) = '$plate_number' LIMIT 1");

        if (mysqli_num_rows($check_plate) > 0) {
            $app_number = 'TUA-RN-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $vehicle = mysqli_fetch_assoc($check_plate);
            $owner_name = trim((string) (($applicant_name !== '') ? $applicant_name : (isset($vehicle['owner_name']) ? $vehicle['owner_name'] : '')));
            $county_value = trim((string) (isset($vehicle['county']) ? $vehicle['county'] : ''));
            $contact_value = isset($_SESSION['contact_number']) ? $_SESSION['contact_number'] : '';

            $insert = "INSERT INTO applications (user_id, applicant_id, application_number, applicant_name, applicant_contact, vehicle_id, county, license_plate_number, status, application_type, renewal_for_plate_number)
                       VALUES ('{$_SESSION['id']}', '{$_SESSION['id']}', '$app_number', '$owner_name', '$contact_value', " . (int)$vehicle['id'] . ", '$county_value', '$plate_number', 'Pending', 'Renewal', '$plate_number')";

            if (mysqli_query($conn, $insert)) {
                $application_record_id = mysqli_insert_id($conn);
                $admin_message = "New renewal application from $owner_name for plate $plate_number (Application ID: $app_number).";
                $admin_message = mysqli_real_escape_string($conn, $admin_message);
                mysqli_query($conn, "INSERT INTO admin_notifications (event_type, message, reference_id) VALUES ('renewal_application', '$admin_message', $application_record_id)");
                $message = "Renewal application submitted successfully! Your application number is <strong>$app_number</strong>";
                $message_type = 'success';
            } else {
                $message = "Error: " . mysqli_error($conn);
                $message_type = 'danger';
            }
        } else {
            $message = "Plate number not found. Please verify that it matches your registered plate.";
            $message_type = 'danger';
        }
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
    <title>Renew License Plate - Transport Union Authority</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/public-footer.css">
    <link rel="stylesheet" href="assets/css/admin-toast.css">
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

        /* Main Content */
        .content-wrapper {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            min-height: calc(100vh - 86px);
            padding: 80px 50px;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
        }

        .page-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .page-header h1 {
            color: #0a2463;
            font-size: 36px;
            margin-bottom: 10px;
        }

        .page-header p {
            color: #64748b;
            font-size: 16px;
        }

        .form-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.08);
            padding: 40px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #0f172a;
            font-weight: 600;
            font-size: 14px;
        }

        .form-group input {
            width: 100%;
            padding: 16px 20px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 16px;
            outline: none;
            transition: all 0.3s;
        }

        .form-group input:focus {
            border-color: #0a2463;
            box-shadow: 0 0 0 4px rgba(10, 36, 99, 0.05);
        }

        .btn-submit {
            width: 100%;
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            color: white;
            padding: 16px;
            border: none;
            border-radius: 12px;
            font-size: 17px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(5, 150, 105, 0.4);
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

            .content-wrapper {
                padding: 50px 20px;
            }

            .form-card {
                padding: 24px;
            }

            .essentials-grid {
                grid-template-columns: 1fr;
            }

            .page-header h1 {
                font-size: 28px;
            }
        }

        /* Alert */
        .alert {
            padding: 18px 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
        }

        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .essentials-grid {
            margin-top: 24px;
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .essentials-card {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 14px;
            background: #f8fafc;
        }

        .essentials-card h4 {
            color: #0a2463;
            margin-bottom: 8px;
            font-size: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .essentials-card ul {
            margin-left: 18px;
            color: #334155;
            line-height: 1.6;
            font-size: 14px;
        }

        .essentials-note {
            margin-top: 16px;
            border-left: 4px solid #047857;
            background: #ecfdf5;
            padding: 12px 14px;
            color: #065f46;
            border-radius: 10px;
            font-size: 14px;
            line-height: 1.6;
        }

        .essentials-note a {
            color: #065f46;
            font-weight: 700;
            text-decoration: none;
        }

        .essentials-note a:hover {
            text-decoration: underline;
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

    <!-- Content -->
    <div class="content-wrapper">
        <div class="container">
            <div class="page-header">
                <h1><i class="fa-solid fa-sync-alt"></i> Renew License Plate</h1>
                <p>Enter your current plate number and name to request a renewal</p>
            </div>

            <div class="form-card">
                <form method="POST" action="">
                    <div class="form-group">
                        <label>Current Plate Number</label>
                        <input type="text" name="plate_number" required value="<?php echo htmlspecialchars($default_plate); ?>" placeholder="Enter your current plate number">
                    </div>
                    <div class="form-group">
                        <label>Your Name</label>
                        <input type="text" name="applicant_name" required placeholder="Enter your full name">
                    </div>
                    <button type="submit" class="btn-submit">
                        <i class="fa-solid fa-rotate-right"></i> Renew Now
                    </button>
                </form>

                <div class="essentials-grid">
                    <div class="essentials-card">
                        <h4><i class="fa-solid fa-file-shield"></i> Renewal Requirements</h4>
                        <ul>
                            <li>Existing plate number must be valid and traceable.</li>
                            <li>Owner details should match registration records.</li>
                            <li>Outstanding compliance issues must be cleared first.</li>
                        </ul>
                    </div>
                    <div class="essentials-card">
                        <h4><i class="fa-solid fa-money-check-dollar"></i> Renewal Fees</h4>
                        <ul>
                            <li>Renewal processing fee is charged before approval.</li>
                            <li>Approved renewals are non-refundable.</li>
                            <li>Official payment receipt is issued by the system.</li>
                        </ul>
                    </div>
                    <div class="essentials-card">
                        <h4><i class="fa-solid fa-clock"></i> Turnaround Time</h4>
                        <ul>
                            <li>Most renewals are resolved in 2-4 working days.</li>
                            <li>Manual verification can extend processing time.</li>
                            <li>Use your dashboard to track pending and approved status.</li>
                        </ul>
                    </div>
                    <div class="essentials-card">
                        <h4><i class="fa-solid fa-circle-info"></i> Status Outcomes</h4>
                        <ul>
                            <li>Pending: under review by TUA staff.</li>
                            <li>Approved: plate renewal accepted for issuance.</li>
                            <li>Rejected: renewal declined; contact support for correction.</li>
                        </ul>
                    </div>
                </div>

                <div class="essentials-note">
                    Renewal submissions are processed under our official service rules. Read <a href="privacy.php">Privacy Policy</a> and <a href="terms.php">Terms & Conditions</a> for data usage and obligations.
                </div>
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
