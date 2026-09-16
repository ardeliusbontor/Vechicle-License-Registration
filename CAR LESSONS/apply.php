<?php
session_start();
include "partials/admin-toast.php";
if (!isset($_SESSION['id'])) {
    admin_toast_add("Please log in to submit an application.", 'warning');
    header("Location: login.php");
    exit();
}

if ($_SESSION['role'] !== 'Applicant') {
    admin_toast_add("You are already signed in as staff. Use the admin dashboard.", 'info');
    header("Location: dashboard.php");
    exit();
}

include "config.php";

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
$user_id = $_SESSION['id'];
$user_query = mysqli_query($conn, "SELECT fullname, contact_number, county, address FROM applicants WHERE id = '$user_id' LIMIT 1");
$user_profile = $user_query && mysqli_num_rows($user_query) > 0 ? mysqli_fetch_assoc($user_query) : [
    'fullname' => '',
    'contact_number' => '',
    'county' => '',
    'address' => ''
];

// List of counties with codes
$counties = [
    'BM' => 'Bomi',
    'BG' => 'Bong',
    'GP' => 'Gbarpolu',
    'GB' => 'Grand Bassa',
    'GC' => 'Grand Cape Mount',
    'GG' => 'Grand Gedeh',
    'GK' => 'Grand Kru',
    'LF' => 'Lofa',
    'MG' => 'Margibi',
    'MY' => 'Maryland',
    'MS' => 'Montserrado',
    'NB' => 'Nimba',
    'RC' => 'Rivercess',
    'RG' => 'River Gee',
    'SN' => 'Sinoe'
];

$vehicle_types = [
    ['value' => 'small_passenger_car', 'label' => 'A1 - Small Passenger Car', 'classification' => 'A1', 'fee' => 90],
    ['value' => 'medium_passenger_car', 'label' => 'A2 - Medium Passenger Car', 'classification' => 'A2', 'fee' => 120],
    ['value' => 'large_passenger_car_minivan', 'label' => 'A3 - Large Passenger Car / Minivan', 'classification' => 'A3', 'fee' => 170],
    ['value' => 'small_suv_light_pickup', 'label' => 'A4 - Small SUV / Light Pickup', 'classification' => 'A4', 'fee' => 190],
    ['value' => 'medium_suv_pickup', 'label' => 'A5 - Medium SUV / Pickup', 'classification' => 'A5', 'fee' => 220],
    ['value' => 'large_heavy_suv_pickup', 'label' => 'A6 - Large / Heavy SUV / Pickup', 'classification' => 'A6', 'fee' => 260],
    ['value' => 'motorcycle', 'label' => 'Motorcycle', 'classification' => 'Motorcycle', 'fee' => 50],
];

$vehicle_use_options = ['Private Vehicle', 'Commercial Vehicle'];
$official_vehicle_categories = ['Motorcycle', 'Private Vehicle', 'Commercial Vehicle'];

function generateTemporaryVehiclePlateNumber($conn, $user_id) {
    $prefix = 'FIRSTTIME';
    $candidate = strtoupper($prefix . '-' . date('YmdHis') . '-' . $user_id);
    $unique = $candidate;
    $counter = 1;

    while (true) {
        $exists = mysqli_query($conn, "SELECT id FROM vehicles WHERE plate_number = '" . mysqli_real_escape_string($conn, $unique) . "' LIMIT 1");
        if (!$exists || mysqli_num_rows($exists) === 0) {
            return $unique;
        }

        $unique = strtoupper($prefix . '-' . date('YmdHis') . '-' . $user_id . '-' . $counter);
        $counter++;
    }
}

if (!function_exists('generateMotorcyclePlateNumber')) {
    function generateMotorcyclePlateNumber($conn) {
        if (function_exists('appGenerateMotorcyclePlateNumber')) {
            return appGenerateMotorcyclePlateNumber($conn);
        }

        $attempts = 0;
        $maxAttempts = 50000;

        while ($attempts < $maxAttempts) {
            $digits = str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);
            if (!preg_match('/^\d{5}$/', $digits)) {
                $attempts++;
                continue;
            }

            $plateCheck = mysqli_query($conn, "SELECT id FROM vehicles WHERE plate_number = '" . mysqli_real_escape_string($conn, $digits) . "' LIMIT 1");
            if (!$plateCheck || mysqli_num_rows($plateCheck) === 0) {
                $appCheck = mysqli_query($conn, "SELECT id FROM applications WHERE license_plate_number = '" . mysqli_real_escape_string($conn, $digits) . "' LIMIT 1");
                if (!$appCheck || mysqli_num_rows($appCheck) === 0) {
                    return $digits;
                }
            }

            $attempts++;
        }

        return null;
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $applicant_name = mysqli_real_escape_string($conn, $_POST['applicant_name']);
    $applicant_contact = mysqli_real_escape_string($conn, $_POST['applicant_contact']);
    $applicant_address = mysqli_real_escape_string($conn, $_POST['applicant_address']);
    $county_code = mysqli_real_escape_string($conn, $_POST['county']);
    $plate_number = trim((string) ($_POST['plate_number'] ?? ''));
    $vehicle_type = mysqli_real_escape_string($conn, $_POST['vehicle_type'] ?? 'sedan');
    $vehicle_make = mysqli_real_escape_string($conn, $_POST['vehicle_make']);
    $vehicle_model = mysqli_real_escape_string($conn, $_POST['vehicle_model']);
    $vehicle_year = mysqli_real_escape_string($conn, $_POST['vehicle_year']);
    $vehicle_color = mysqli_real_escape_string($conn, $_POST['vehicle_color']);
    $vehicle_use_class = in_array($_POST['vehicle_use_class'] ?? '', ['Private Vehicle', 'Commercial Vehicle'], true) ? mysqli_real_escape_string($conn, $_POST['vehicle_use_class']) : 'Private Vehicle';
    $official_vehicle_category = in_array($_POST['official_vehicle_category'] ?? '', ['Motorcycle', 'Private Vehicle', 'Commercial Vehicle'], true) ? mysqli_real_escape_string($conn, $_POST['official_vehicle_category']) : 'Private Vehicle';
    if ($official_vehicle_category === 'Motorcycle') {
        $vehicle_use_class = 'Private Vehicle';
    }
    $owner_name = mysqli_real_escape_string($conn, $_POST['owner_name']);
    $owner_contact = mysqli_real_escape_string($conn, $_POST['owner_contact']);

    $app_number = 'TUA-LP-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    $county_name = $counties[$county_code];

    mysqli_query($conn, "UPDATE applicants SET fullname = '$applicant_name', contact_number = '$applicant_contact', county = '$county_name', address = '$applicant_address' WHERE id = '$user_id'");

    if ($plate_number === '') {
        $plate_number = generateTemporaryVehiclePlateNumber($conn, $user_id);
    }

    if ($official_vehicle_category === 'Motorcycle') {
        $plate_number = generateMotorcyclePlateNumber($conn);
    }

    $check_vehicle = mysqli_query($conn, "SELECT * FROM vehicles WHERE plate_number = '" . mysqli_real_escape_string($conn, $plate_number) . "'");
    if (mysqli_num_rows($check_vehicle) > 0) {
        $vehicle = mysqli_fetch_assoc($check_vehicle);
        $vehicle_id = $vehicle['id'];

        mysqli_query($conn, "UPDATE vehicles SET vehicle_type = '$vehicle_type', vehicle_use_class = '$vehicle_use_class', official_vehicle_category = '$official_vehicle_category', make = '$vehicle_make', model = '$vehicle_model', year = '$vehicle_year', color = '$vehicle_color', owner_name = '$owner_name', owner_contact = '$owner_contact', county = '$county_name' WHERE id = '$vehicle_id'");
    } else {
        mysqli_query($conn, "INSERT INTO vehicles (plate_number, vehicle_type, vehicle_use_class, official_vehicle_category, make, model, year, color, owner_name, owner_contact, created_by, created_by_applicant, status, payment_status, county)
                            VALUES ('" . mysqli_real_escape_string($conn, $plate_number) . "', '$vehicle_type', '$vehicle_use_class', '$official_vehicle_category', '$vehicle_make', '$vehicle_model', '$vehicle_year', '$vehicle_color', '$owner_name', '$owner_contact', NULL, $user_id, 'Pending', 'Pending', '$county_name')");
        $vehicle_id = mysqli_insert_id($conn);
    }

    $insert_app = "INSERT INTO applications (applicant_id, user_id, application_number, county, applicant_name, applicant_contact, applicant_address, vehicle_id, vehicle_use_class, official_vehicle_category, status) 
                   VALUES ($user_id, NULL, '$app_number', '$county_name', '$applicant_name', '$applicant_contact', '$applicant_address', $vehicle_id, '$vehicle_use_class', '$official_vehicle_category', 'Pending')";

    if (mysqli_query($conn, $insert_app)) {
        $application_record_id = mysqli_insert_id($conn);
        $admin_message = "New plate application from $applicant_name (Application ID: $app_number).";
        $admin_message = mysqli_real_escape_string($conn, $admin_message);
        mysqli_query($conn, "INSERT INTO admin_notifications (event_type, message, reference_id) VALUES ('new_application', '$admin_message', $application_record_id)");
        $message = "Application submitted successfully!<br><br>Your Application ID: <strong>$app_number</strong>";
        $message_type = 'success';
    } else {
        $message = "Error: " . mysqli_error($conn);
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
    <title>Apply for License Plate - Transport Union Authority</title>
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
            padding: 40px 50px;
        }

        .container {
            max-width: 900px;
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

        .section-title {
            color: #0a2463;
            font-size: 20px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #0f172a;
            font-weight: 600;
            font-size: 14px;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 15px;
            outline: none;
            transition: all 0.3s;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            border-color: #0a2463;
            box-shadow: 0 0 0 4px rgba(10, 36, 99, 0.05);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .vehicle-type-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 16px;
            margin-top: 12px;
        }

        .vehicle-type-card {
            position: relative;
            border: 2px solid #dfe7f5;
            border-radius: 18px;
            overflow: hidden;
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            cursor: pointer;
            transition: all 0.25s ease;
            display: flex;
            flex-direction: column;
            min-height: 165px;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.04);
        }

        .vehicle-type-card:hover {
            border-color: #0a2463;
            transform: translateY(-3px);
            box-shadow: 0 14px 26px rgba(10, 36, 99, 0.12);
        }

        .vehicle-type-card img {
            width: 100%;
            height: 110px;
            object-fit: cover;
            display: block;
            filter: saturate(0.96) contrast(1.02);
        }

        .vehicle-type-card span {
            display: block;
            padding: 12px 10px;
            text-align: center;
            font-weight: 800;
            letter-spacing: 0.04em;
            color: #0a2463;
            background: linear-gradient(180deg, #ffffff 0%, #eef6ff 100%);
            text-transform: uppercase;
            font-size: 12px;
        }

        .vehicle-type-card input {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
        }

        .vehicle-type-card.selected {
            border-color: #c41e3a;
            box-shadow: 0 0 0 4px rgba(196, 30, 58, 0.08), 0 16px 28px rgba(10, 36, 99, 0.12);
            background: linear-gradient(180deg, #fff8fb 0%, #f8fafc 100%);
        }

        .vehicle-type-card.selected span {
            background: linear-gradient(180deg, #0a2463 0%, #143c8a 100%);
            color: #fff;
        }

        .official-selector-panel {
            background: linear-gradient(135deg, #f8fbff 0%, #eef6ff 100%);
            border: 1px solid #d9e7ff;
            border-radius: 16px;
            padding: 18px;
            margin-top: 12px;
        }

        .selector-note {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            font-size: 12px;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            font-weight: 800;
            margin-bottom: 12px;
        }

        .fee-display-box {
            background: linear-gradient(135deg, #0a2463 0%, #123d8a 100%);
            border: none;
            color: white;
            border-radius: 12px;
            padding: 14px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 12px;
            box-shadow: 0 12px 24px rgba(10, 36, 99, 0.15);
        }

        .fee-display-box span {
            color: rgba(255,255,255,0.8);
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            font-size: 11px;
        }

        .fee-display-box strong {
            color: white;
            font-size: 1.15rem;
        }

        .official-fee-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            margin-top: 16px;
        }

        .official-fee-item {
            background: white;
            border: 1px solid #dfe8f8;
            border-radius: 12px;
            padding: 10px 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            color: #0f172a;
            font-size: 12px;
        }

        .official-fee-item span {
            font-weight: 700;
        }

        .official-fee-item strong {
            color: #0a2463;
            font-size: 12px;
        }

        .classification-guide {
            margin-top: 22px;
            border: 1px solid #dfe8f8;
            border-radius: 16px;
            background: linear-gradient(180deg, #f8fbff 0%, #eef6ff 100%);
            padding: 20px;
        }

        .classification-guide h4 {
            color: #0a2463;
            margin-bottom: 14px;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .classification-guide-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 12px;
        }

        .classification-guide-item {
            background: white;
            border: 1px solid #dbeafe;
            border-radius: 12px;
            padding: 12px 14px;
        }

        .classification-guide-item strong {
            display: block;
            color: #0a2463;
            font-size: 15px;
            letter-spacing: 0.04em;
            margin-bottom: 6px;
        }

        .classification-guide-item span {
            display: block;
            color: #334155;
            font-size: 12px;
            line-height: 1.6;
            margin-bottom: 5px;
        }

        .classification-guide-item small {
            color: #475569;
            font-size: 11px;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            font-weight: 700;
        }

        .btn-submit {
            width: 100%;
            background: linear-gradient(135deg, #c41e3a 0%, #a01830 100%);
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
            box-shadow: 0 5px 20px rgba(196, 30, 58, 0.4);
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
                padding: 30px 20px;
            }

            .form-card {
                padding: 24px;
            }

            .form-row {
                grid-template-columns: 1fr;
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
            margin-top: 28px;
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .essentials-card {
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 16px;
            background: #f8fafc;
        }

        .essentials-card h4 {
            color: #0a2463;
            margin-bottom: 10px;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .essentials-card ul {
            margin-left: 18px;
            color: #334155;
            line-height: 1.7;
            font-size: 14px;
        }

        .essentials-note {
            margin-top: 18px;
            border-left: 4px solid #0a2463;
            background: #eff6ff;
            padding: 12px 14px;
            color: #1e3a8a;
            border-radius: 10px;
            font-size: 14px;
            line-height: 1.6;
        }

        .essentials-note a {
            color: #0a2463;
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
            <?php if (isset($_SESSION['id'])): ?>
                <a href="user-dashboard.php"><i class="fa-solid fa-gauge-high"></i> My Dashboard</a>
            <?php else: ?>
                <a href="login.php"><i class="fa-solid fa-user"></i> Login</a>
                <a href="register.php"><i class="fa-solid fa-user-plus"></i> Register</a>
            <?php endif; ?>
            <a href="index.php"><i class="fa-solid fa-arrow-left"></i> Back to Homepage</a>
        </div>
    </nav>

    <!-- Content -->
    <div class="content-wrapper">
        <div class="container">
            <div class="page-header">
                <h1><i class="fa-solid fa-file-lines"></i> Apply for License Plate</h1>
                <p>Fill out the form below to apply for an official license plate</p>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $message_type === 'danger' ? 'danger' : ($message_type === 'warning' ? 'warning' : 'success'); ?>" style="margin-bottom: 24px; background: <?php echo $message_type === 'danger' ? '#fee2e2' : ($message_type === 'warning' ? '#fff7ed' : '#dcfce7'); ?>; color: <?php echo $message_type === 'danger' ? '#991b1b' : ($message_type === 'warning' ? '#9a5b00' : '#166534'); ?>; padding: 18px 20px; border-radius: 14px; display: flex; align-items: center; gap: 12px; border-left: 4px solid <?php echo $message_type === 'danger' ? '#dc2626' : ($message_type === 'warning' ? '#f59e0b' : '#16a34a'); ?>;">
                    <i class="fa-solid fa-<?php echo $message_type === 'danger' ? 'circle-xmark' : ($message_type === 'warning' ? 'triangle-exclamation' : 'circle-check'); ?>"></i>
                    <span><?php echo $message; ?></span>
                </div>

                <?php if (!empty($app_number ?? '')): ?>
                    <div id="applicationSummaryCard"
                         data-app-id="<?php echo htmlspecialchars($app_number); ?>"
                         data-applicant="<?php echo htmlspecialchars($applicant_name ?? $user_profile['fullname']); ?>"
                         data-county="<?php echo htmlspecialchars($county_name ?? ($user_profile['county'] ?? 'Pending')); ?>"
                         style="background: linear-gradient(135deg, #f8fafc 0%, #eef6ff 100%); border: 1px solid #dbeafe; border-radius: 18px; padding: 28px; margin-bottom: 24px; box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);">
                        <div style="display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; margin-bottom: 18px;">
                            <div>
                                <div style="font-size: 12px; letter-spacing: 0.12em; text-transform: uppercase; color: #64748b; font-weight: 700;">Application ID</div>
                                <div style="font-size: clamp(1.8rem, 3vw, 2.5rem); font-weight: 900; color: #0a2463; margin-top: 6px; letter-spacing: 0.08em;"><?php echo htmlspecialchars($app_number); ?></div>
                            </div>
                            <button type="button" onclick="downloadApplicationSummary()" style="background: linear-gradient(135deg, #0a2463 0%, #0f3460 100%); color: white; border: none; border-radius: 12px; padding: 14px 22px; font-weight: 800; cursor: pointer; box-shadow: 0 10px 20px rgba(10,36,99,0.18);">
                                <i class="fa-solid fa-download"></i> Download Summary
                            </button>
                        </div>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px;">
                            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 16px;">
                                <div style="font-size: 12px; color: #64748b; text-transform: uppercase; letter-spacing: 0.08em; font-weight: 700; margin-bottom: 6px;">Status</div>
                                <div style="font-weight: 700; color: #0f172a;">Submitted</div>
                            </div>
                            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 16px;">
                                <div style="font-size: 12px; color: #64748b; text-transform: uppercase; letter-spacing: 0.08em; font-weight: 700; margin-bottom: 6px;">Applicant</div>
                                <div style="font-weight: 700; color: #0f172a;"><?php echo htmlspecialchars($applicant_name ?? $user_profile['fullname']); ?></div>
                            </div>
                            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 16px;">
                                <div style="font-size: 12px; color: #64748b; text-transform: uppercase; letter-spacing: 0.08em; font-weight: 700; margin-bottom: 6px;">County</div>
                                <div style="font-weight: 700; color: #0f172a;"><?php echo htmlspecialchars($county_name ?? ($user_profile['county'] ?? 'Pending')); ?></div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <div class="form-card">
                <form method="POST" action="">
                    <h3 class="section-title"><i class="fa-solid fa-user"></i> Applicant Information</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" name="applicant_name" required placeholder="Enter your full name" value="<?php echo htmlspecialchars($_POST['applicant_name'] ?? $user_profile['fullname']); ?>">
                        </div>
                        <div class="form-group">
                            <label>Contact Number</label>
                            <input type="text" name="applicant_contact" required placeholder="Phone number" value="<?php echo htmlspecialchars($_POST['applicant_contact'] ?? $user_profile['contact_number']); ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>County <span style="color:#c41e3a;">*</span></label>
                            <select name="county" id="county" required>
                                <option value="">Select your county</option>
                                <?php foreach ($counties as $code => $name): ?>
                                    <option value="<?php echo $code; ?>" <?php echo (($_POST['county'] ?? '') === $code || (empty($_POST['county']) && ($user_profile['county'] ?? '') === $name)) ? 'selected' : ''; ?>><?php echo $name; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Address</label>
                        <textarea name="applicant_address" rows="3" required placeholder="Enter your complete address"><?php echo htmlspecialchars($_POST['applicant_address'] ?? $user_profile['address']); ?></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Vehicle Use / Class</label>
                            <select name="vehicle_use_class" required>
                                <option value="">Select vehicle class</option>
                                <?php foreach ($vehicle_use_options as $option): ?>
                                    <option value="<?php echo htmlspecialchars($option); ?>" <?php echo (($_POST['vehicle_use_class'] ?? 'Private Vehicle') === $option) ? 'selected' : ''; ?>><?php echo htmlspecialchars($option); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Vehicle Type</label>
                        <input type="hidden" name="official_vehicle_category" id="officialVehicleCategory" value="<?php echo htmlspecialchars($_POST['official_vehicle_category'] ?? 'Private Vehicle'); ?>">
                        <div class="official-selector-panel">
                            <div class="selector-note">
                                <span>Vehicle type</span>
                                <span>Service fee</span>
                            </div>
                            <select name="vehicle_type" id="vehicleType" required>
                                <option value="">Select vehicle type</option>
                                <?php foreach ($vehicle_types as $type): ?>
                                    <option value="<?php echo $type['value']; ?>" data-fee="<?php echo $type['fee']; ?>" data-classification="<?php echo $type['classification']; ?>" <?php echo (($_POST['vehicle_type'] ?? 'sedan') === $type['value']) ? 'selected' : ''; ?>>
                                        <?php echo $type['label']; ?> (<?php echo $type['classification']; ?>) - $<?php echo number_format($type['fee'], 2); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="fee-display-box">
                                <span>Selected fee</span>
                                <strong id="vehicleFeeAmount">$0.00</strong>
                            </div>
                        </div>
                        <div class="official-fee-grid">
                            <?php foreach ($vehicle_types as $type): ?>
                                <div class="official-fee-item">
                                    <span><?php echo htmlspecialchars($type['label']); ?></span>
                                    <strong>$<?php echo number_format($type['fee'], 2); ?></strong>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="classification-guide">
                            <h4><i class="fa-solid fa-clipboard-check"></i> Official Vehicle Classification Guide</h4>
                            <div class="classification-guide-grid">
                                <div class="classification-guide-item">
                                    <strong>A1</strong>
                                    <span>Small Passenger Car</span>
                                    <small>Small, compact cars mainly designed for personal transportation.</small>
                                </div>
                                <div class="classification-guide-item">
                                    <strong>A2</strong>
                                    <span>Medium Passenger Car</span>
                                    <small>Standard passenger cars, especially small and medium-sized sedans.</small>
                                </div>
                                <div class="classification-guide-item">
                                    <strong>A3</strong>
                                    <span>Large Passenger Car / Minivan</span>
                                    <small>Larger passenger cars, luxury sedans, station wagons and minivans designed to carry more passengers.</small>
                                </div>
                                <div class="classification-guide-item">
                                    <strong>A4</strong>
                                    <span>Small SUV / Light Pickup</span>
                                    <small>Small SUVs, crossovers and light-duty pickups used for personal transportation or light utility work.</small>
                                </div>
                                <div class="classification-guide-item">
                                    <strong>A5</strong>
                                    <span>Medium SUV / Pickup</span>
                                    <small>Medium-sized SUVs and pickup trucks designed for passenger transportation and moderate utility work.</small>
                                </div>
                                <div class="classification-guide-item">
                                    <strong>A6</strong>
                                    <span>Large / Heavy SUV / Pickup</span>
                                    <small>Large SUVs and heavy-duty pickups designed to carry more passengers, equipment or heavier loads.</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Current Plate Number <span style="color:#64748b; font-weight:500;">(optional)</span></label>
                            <input type="text" name="plate_number" placeholder="Leave blank if this is your first plate application">
                        </div>
                        <div class="form-group">
                            <label id="vehicleMakeLabel">Vehicle Make</label>
                            <input type="text" name="vehicle_make" id="vehicleMakeInput" required placeholder="e.g., Toyota">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label id="vehicleModelLabel">Vehicle Model</label>
                            <input type="text" name="vehicle_model" id="vehicleModelInput" required placeholder="e.g., Camry">
                        </div>
                        <div class="form-group">
                            <label>Year</label>
                            <input type="number" name="vehicle_year" min="1990" max="2025" placeholder="e.g., 2020">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Vehicle Color</label>
                        <input type="text" name="vehicle_color" required placeholder="e.g., White">
                    </div>

                    <h3 class="section-title" style="margin-top: 35px;"><i class="fa-solid fa-id-card"></i> Owner Information</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Owner's Name</label>
                            <input type="text" name="owner_name" required placeholder="Vehicle owner's full name">
                        </div>
                        <div class="form-group">
                            <label>Owner's Contact</label>
                            <input type="text" name="owner_contact" required placeholder="Owner's phone number">
                        </div>
                    </div>

                    <div style="margin-top: 40px;">
                        <button type="submit" class="btn-submit">
                            <i class="fa-solid fa-paper-plane"></i> Submit Application
                        </button>
                    </div>
                </form>

                <div class="essentials-grid">
                    <div class="essentials-card">
                        <h4><i class="fa-solid fa-list-check"></i> Required Documents</h4>
                        <ul>
                            <li>Valid vehicle registration certificate.</li>
                            <li>Proof of ownership (sale deed or transfer document).</li>
                            <li>National ID or passport of applicant.</li>
                            <li>Active contact number for verification callbacks.</li>
                        </ul>
                    </div>
                    <div class="essentials-card">
                        <h4><i class="fa-solid fa-scale-balanced"></i> Eligibility & Screening</h4>
                        <ul>
                            <li>Vehicle records must match submitted details.</li>
                            <li>Duplicate or blacklisted plate applications are rejected.</li>
                            <li>Missing documents may place request on hold.</li>
                            <li>False declarations may trigger legal reporting.</li>
                        </ul>
                    </div>
                    <div class="essentials-card">
                        <h4><i class="fa-solid fa-money-bill-wave"></i> Fees & Payment Policy</h4>
                        <ul>
                            <li>Standard processing fee: USD 100.00.</li>
                            <li>Payment is required before final plate generation.</li>
                            <li>Non-refundable after approval is completed.</li>
                            <li>Official receipt is generated for every payment.</li>
                        </ul>
                    </div>
                    <div class="essentials-card">
                        <h4><i class="fa-solid fa-hourglass-half"></i> Processing Timeline</h4>
                        <ul>
                            <li>Initial review: 1-2 working days.</li>
                            <li>Verification and decision: 3-5 working days.</li>
                            <li>Complex cases may extend beyond 5 days.</li>
                            <li>Track status in your user dashboard after submission.</li>
                        </ul>
                    </div>
                </div>

                <div class="essentials-note">
                    By submitting this form, you agree to our data processing and service terms. Please review <a href="privacy.php">Privacy Policy</a> and <a href="terms.php">Terms & Conditions</a> before completing your application.
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
    <script>
        const vehicleTypeSelect = document.getElementById('vehicleType');
        const vehicleFeeAmount = document.getElementById('vehicleFeeAmount');
        const officialVehicleCategoryInput = document.getElementById('officialVehicleCategory');
        const vehicleUseClassSelect = document.querySelector('select[name="vehicle_use_class"]');
        const vehicleMakeLabel = document.getElementById('vehicleMakeLabel');
        const vehicleModelLabel = document.getElementById('vehicleModelLabel');
        const vehicleMakeInput = document.getElementById('vehicleMakeInput');
        const vehicleModelInput = document.getElementById('vehicleModelInput');

        function updateVehicleFee() {
            const selectedOption = vehicleTypeSelect.selectedOptions[0];
            const fee = selectedOption ? Number(selectedOption.dataset.fee || 0) : 0;
            vehicleFeeAmount.textContent = '$' + fee.toFixed(2);

            if (officialVehicleCategoryInput && selectedOption) {
                const classification = (selectedOption.dataset.classification || '').toString();
                const selectedClass = vehicleUseClassSelect ? (vehicleUseClassSelect.value || 'Private Vehicle') : 'Private Vehicle';
                const isMotorcycle = classification.toLowerCase() === 'motorcycle';
                officialVehicleCategoryInput.value = isMotorcycle ? 'Motorcycle' : selectedClass;

                if (vehicleMakeLabel) {
                    vehicleMakeLabel.textContent = isMotorcycle ? 'Bike Name' : 'Vehicle Make';
                }
                if (vehicleModelLabel) {
                    vehicleModelLabel.textContent = isMotorcycle ? 'Bike Model' : 'Vehicle Model';
                }
                if (vehicleMakeInput) {
                    vehicleMakeInput.placeholder = isMotorcycle ? 'e.g., Honda XR' : 'e.g., Toyota';
                }
                if (vehicleModelInput) {
                    vehicleModelInput.placeholder = isMotorcycle ? 'e.g., CRF 250R' : 'e.g., Camry';
                }
            }
        }

        if (vehicleTypeSelect) {
            vehicleTypeSelect.addEventListener('change', updateVehicleFee);
            if (vehicleUseClassSelect) {
                vehicleUseClassSelect.addEventListener('change', updateVehicleFee);
            }
            updateVehicleFee();
        }

        function downloadApplicationSummary() {
            const card = document.getElementById('applicationSummaryCard');
            if (!card) return;

            const appId = card.dataset.appId || 'N/A';
            const applicant = card.dataset.applicant || 'N/A';
            const county = card.dataset.county || 'N/A';

            const text = [
                'TRANSPORT UNION AUTHORITY',
                'Application Summary',
                'Application ID: ' + appId,
                'Status: Submitted',
                'Applicant: ' + applicant,
                'County: ' + county
            ].join('\n');

            const blob = new Blob([text], { type: 'text/plain;charset=utf-8' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'application-summary.txt';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }
    </script>
</body>
</html>
