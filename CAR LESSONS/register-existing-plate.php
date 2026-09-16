<?php
session_start();
include "config.php";
include "partials/admin-toast.php";

if (!isset($_SESSION['id'])) {
    admin_toast_add("Please log in to register an existing plate.", 'warning');
    header("Location: login.php");
    exit();
}

if (($_SESSION['role'] ?? '') !== 'Applicant') {
    admin_toast_add("Only applicants can use this page.", 'info');
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['id'];
$message = '';
$message_type = '';

function normalizePlateNumber($value) {
    return strtoupper(trim(preg_replace('/\s+/', '', (string) $value)));
}

function isValidExistingPlateNumber($plate) {
    $normalized = normalizePlateNumber($plate);

    if ($normalized === '') {
        return false;
    }

    if (!preg_match('/^[A-Z]{1,2}\d{4,6}$/', $normalized)) {
        return false;
    }

    if (preg_match('/^[A-Z]{2}\d{4,6}$/', $normalized) && substr($normalized, 0, 2) === 'LB') {
        return false;
    }

    return true;
}

$county_list = [
    'Bomi',
    'Bong',
    'Gbarpolu',
    'Grand Bassa',
    'Grand Cape Mount',
    'Grand Gedeh',
    'Grand Kru',
    'Lofa',
    'Margibi',
    'Maryland',
    'Montserrado',
    'Nimba',
    'Rivercess',
    'River Gee',
    'Sinoe'
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

$county_check = mysqli_query($conn, "SHOW COLUMNS FROM vehicles LIKE 'county'");
if ($county_check && mysqli_num_rows($county_check) === 0) {
    mysqli_query($conn, "ALTER TABLE vehicles ADD COLUMN county VARCHAR(100) NULL AFTER owner_contact");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $plate_number = normalizePlateNumber($_POST['plate_number'] ?? '');
    $vehicle_type = mysqli_real_escape_string($conn, $_POST['vehicle_type'] ?? 'sedan');
    $vehicle_use_class = in_array($_POST['vehicle_use_class'] ?? '', ['Private Vehicle', 'Commercial Vehicle'], true) ? mysqli_real_escape_string($conn, $_POST['vehicle_use_class']) : 'Private Vehicle';
    $official_vehicle_category = in_array($_POST['official_vehicle_category'] ?? '', ['Motorcycle', 'Private Vehicle', 'Commercial Vehicle'], true) ? mysqli_real_escape_string($conn, $_POST['official_vehicle_category']) : 'Private Vehicle';
    if ($official_vehicle_category === 'Motorcycle') {
        $vehicle_use_class = 'Private Vehicle';
    }
    $county = mysqli_real_escape_string($conn, trim((string) ($_POST['county'] ?? '')));
    $expiry_date = trim((string) ($_POST['expiry_date'] ?? ''));
    $make = mysqli_real_escape_string($conn, trim((string) ($_POST['make'] ?? '')));
    $model = mysqli_real_escape_string($conn, trim((string) ($_POST['model'] ?? '')));
    $year = mysqli_real_escape_string($conn, trim((string) ($_POST['year'] ?? '')));
    $color = mysqli_real_escape_string($conn, trim((string) ($_POST['color'] ?? '')));
    $owner_name = mysqli_real_escape_string($conn, trim((string) ($_POST['owner_name'] ?? '')));
    $owner_contact = mysqli_real_escape_string($conn, trim((string) ($_POST['owner_contact'] ?? '')));

    if ($plate_number === '' || !in_array($county, $county_list, true) || !isValidExistingPlateNumber($plate_number) || $make === '' || $model === '' || $year === '' || $color === '' || $owner_name === '' || $owner_contact === '') {
        $message = 'Please select a valid county and enter a valid existing plate number in the correct format (for example: A1234 or A21234).';
        $message_type = 'danger';
    } else {
        $existing_vehicle = mysqli_query($conn, "SELECT * FROM vehicles WHERE plate_number = '$plate_number' LIMIT 1");

        if (mysqli_num_rows($existing_vehicle) > 0) {
            $vehicle = mysqli_fetch_assoc($existing_vehicle);
            if (!empty($vehicle['created_by_applicant']) && (int) $vehicle['created_by_applicant'] !== (int) $user_id) {
                $message = 'This plate number is already associated with another account and must be reviewed by the authority before it can be activated.';
                $message_type = 'warning';
            } else {
                $update_sql = "UPDATE vehicles SET
                    vehicle_type = '$vehicle_type',
                    vehicle_use_class = '$vehicle_use_class',
                    official_vehicle_category = '$official_vehicle_category',
                    status = 'Pending',
                    payment_status = 'Pending',
                    county = '$county',
                    expiry_date = " . ($expiry_date !== '' ? "'$expiry_date'" : "NULL") . ",
                    make = '$make',
                    model = '$model',
                    year = '$year',
                    color = '$color',
                    owner_name = '$owner_name',
                    owner_contact = '$owner_contact',
                    created_by_applicant = '$user_id'
                    WHERE id = '{$vehicle['id']}'";

                if (mysqli_query($conn, $update_sql)) {
                    $vehicle_id = (int) $vehicle['id'];
                    $existing_application = mysqli_query($conn, "SELECT id FROM applications WHERE vehicle_id = '$vehicle_id' LIMIT 1");
                    $application_number = 'TUA-EXISTING-' . date('Y') . '-' . str_pad((string) rand(1, 9999), 4, '0', STR_PAD_LEFT);

                    if ($existing_application && mysqli_num_rows($existing_application) > 0) {
                        $existing_application_row = mysqli_fetch_assoc($existing_application);
                        mysqli_query($conn, "UPDATE applications SET
                            application_type = 'Existing Plate',
                            applicant_id = '$user_id',
                            user_id = NULL,
                            county = '$county',
                            applicant_name = '$owner_name',
                            applicant_contact = '$owner_contact',
                            applicant_address = '',
                            license_plate_number = '$plate_number',
                            vehicle_use_class = '$vehicle_use_class',
                            official_vehicle_category = '$official_vehicle_category',
                            status = 'Pending',
                            application_number = '$application_number'
                            WHERE id = '{$existing_application_row['id']}'");
                    } else {
                        mysqli_query($conn, "INSERT INTO applications (applicant_id, user_id, application_number, county, applicant_name, applicant_contact, applicant_address, vehicle_id, license_plate_number, vehicle_use_class, official_vehicle_category, status, application_type)
                            VALUES ('$user_id', NULL, '$application_number', '$county', '$owner_name', '$owner_contact', '', '$vehicle_id', '$plate_number', '$vehicle_use_class', '$official_vehicle_category', 'Pending', 'Existing Plate')");
                    }

                    $client_name = trim((string) ($_SESSION['fullname'] ?? $owner_name ?? 'Client'));
                    $message = 'Hello ' . $client_name . ', your existing plate registration request has been submitted successfully. It is currently under review and will be approved manually by the authority.';
                    $message_type = 'success';

                    $admin_message = "New existing plate registration submitted for review: {$plate_number} by {$owner_name}.";
                    mysqli_query($conn, "INSERT INTO admin_notifications (event_type, message, reference_id) VALUES ('existing_plate_submission', '" . mysqli_real_escape_string($conn, $admin_message) . "', '{$vehicle_id}')");
                } else {
                    $message = 'Unable to update your existing plate details: ' . mysqli_error($conn);
                    $message_type = 'danger';
                }
            }
        } else {
            $insert_sql = "INSERT INTO vehicles (plate_number, vehicle_type, vehicle_use_class, official_vehicle_category, status, payment_status, county, expiry_date, make, model, year, color, owner_name, owner_contact, created_by_applicant, created_by)
                VALUES ('$plate_number', '$vehicle_type', '$vehicle_use_class', '$official_vehicle_category', 'Pending', 'Pending', '$county', " . ($expiry_date !== '' ? "'$expiry_date'" : "NULL") . ", '$make', '$model', '$year', '$color', '$owner_name', '$owner_contact', '$user_id', NULL)";

            if (mysqli_query($conn, $insert_sql)) {
                $new_vehicle_id = mysqli_insert_id($conn);
                $application_number = 'TUA-EXISTING-' . date('Y') . '-' . str_pad((string) rand(1, 9999), 4, '0', STR_PAD_LEFT);
                mysqli_query($conn, "INSERT INTO applications (applicant_id, user_id, application_number, county, applicant_name, applicant_contact, applicant_address, vehicle_id, license_plate_number, vehicle_use_class, official_vehicle_category, status, application_type)
                    VALUES ('$user_id', NULL, '$application_number', '$county', '$owner_name', '$owner_contact', '', '$new_vehicle_id', '$plate_number', '$vehicle_use_class', '$official_vehicle_category', 'Pending', 'Existing Plate')");

                $client_name = trim((string) ($_SESSION['fullname'] ?? $owner_name ?? 'Client'));
                $message = 'Hello ' . $client_name . ', your existing plate registration request has been submitted successfully. It is currently under review and will be approved manually by the authority.';
                $message_type = 'success';

                $admin_message = "New existing plate registration submitted for review: {$plate_number} by {$owner_name}.";
                mysqli_query($conn, "INSERT INTO admin_notifications (event_type, message, reference_id) VALUES ('existing_plate_submission', '" . mysqli_real_escape_string($conn, $admin_message) . "', '{$new_vehicle_id}')");
            } else {
                $message = 'Unable to register your existing plate: ' . mysqli_error($conn);
                $message_type = 'danger';
            }
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
    <title>Register Existing Plate - Transport Union Authority</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/public-footer.css">
    <link rel="stylesheet" href="assets/css/admin-toast.css">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #eef4ff 0%, #f8fafc 40%, #e2e8f0 100%);
            color: #0f172a;
        }

        .navbar {
            background: rgba(255, 255, 255, 0.96);
            box-shadow: 0 10px 25px rgba(15, 23, 42, 0.08);
            padding: 14px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 20;
            backdrop-filter: blur(10px);
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo-section img {
            height: 62px;
            width: auto;
            border-radius: 14px;
        }

        .logo-text h1 {
            color: #0a2463;
            font-size: 16px;
            font-weight: 800;
            letter-spacing: 0.04em;
        }

        .logo-text p {
            color: #c41e3a;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .nav-links {
            display: flex;
            gap: 14px;
            align-items: center;
            flex-wrap: wrap;
        }

        .nav-links a {
            text-decoration: none;
            color: #0a2463;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 10px;
            transition: 0.2s ease;
        }

        .nav-links a:hover {
            background: #eef4ff;
        }

        .content-wrapper {
            padding: 42px 20px 72px;
        }

        .container {
            max-width: 1100px;
            margin: 0 auto;
        }

        .page-header {
            text-align: center;
            margin-bottom: 28px;
        }

        .page-header h1 {
            color: #0a2463;
            font-size: clamp(2rem, 4vw, 3.1rem);
            font-weight: 800;
            margin-bottom: 10px;
        }

        .page-header p {
            color: #475569;
            font-size: 1.02rem;
        }

        .form-card {
            background: rgba(255, 255, 255, 0.96);
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: 24px;
            box-shadow: 0 20px 50px rgba(15, 23, 42, 0.08);
            padding: clamp(22px, 3vw, 38px);
        }

        .info-box {
            background: linear-gradient(135deg, #eff6ff 0%, #e0f2fe 100%);
            border: 1px solid #bfdbfe;
            color: #1e3a8a;
            border-radius: 16px;
            padding: 18px 20px;
            margin-bottom: 28px;
            font-size: 14px;
            line-height: 1.7;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
        }

        .form-group {
            margin-bottom: 4px;
        }

        .form-group.full {
            grid-column: 1 / -1;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #0f172a;
            font-weight: 700;
            font-size: 14px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 14px 16px;
            border: 1.5px solid #dfe7f1;
            border-radius: 12px;
            background: #f8fafc;
            font-size: 15px;
            color: #0f172a;
            outline: none;
            transition: all 0.2s ease;
        }

        .form-group input::placeholder {
            color: #94a3b8;
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: #2563eb;
            background: white;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.08);
        }

        .form-group small {
            display: block;
            margin-top: 6px;
            color: #64748b;
            font-size: 12px;
        }

        .btn {
            width: 100%;
            border: none;
            border-radius: 12px;
            padding: 16px 20px;
            cursor: pointer;
            font-size: 1.04rem;
            font-weight: 800;
            color: white;
            background: linear-gradient(135deg, #0a2463 0%, #1d4ed8 100%);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 24px rgba(37, 99, 235, 0.2);
        }

        .meta-strip {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin: 24px 0 18px;
        }

        .meta-strip .chip {
            background: linear-gradient(135deg, #f8fafc, #eef2ff);
            border: 1px solid #dfe7f1;
            border-radius: 999px;
            padding: 9px 14px;
            color: #334155;
            font-size: 12px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        @media (max-width: 768px) {
            .navbar {
                padding: 16px 18px;
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }

            .logo-section {
                width: 100%;
            }

            .logo-text h1 {
                font-size: 14px;
            }

            .nav-links {
                width: 100%;
                gap: 8px;
            }

            .nav-links a {
                flex: 1 1 calc(50% - 8px);
                justify-content: center;
                padding: 10px 12px;
                background: #f8fafc;
            }

            .content-wrapper {
                padding: 30px 14px 56px;
            }

            .page-header {
                margin-bottom: 20px;
            }

            .form-card {
                padding: 20px 16px;
                border-radius: 18px;
            }

            .form-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .meta-strip {
                gap: 8px;
            }

            .meta-strip .chip {
                width: 100%;
                justify-content: center;
            }
        }
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
            <a href="user-dashboard.php"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
            <a href="apply.php"><i class="fa-solid fa-file-contract"></i> New Application</a>
            <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
        </div>
    </nav>

    <div class="content-wrapper">
        <div class="container">
            <div class="page-header">
                <h1><i class="fa-solid fa-car-side"></i> Register Existing Plate</h1>
                <p>Submit an already-owned plate for validation and admin approval.</p>
            </div>

            <div class="form-card">
                <div class="info-box">
                    Please complete the details below. Your request will be reviewed by the authority, and you must visit the nearest office for document verification before the plate can be approved.
                </div>

                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group full">
                            <label for="plate_number">Plate Number</label>
                            <input id="plate_number" name="plate_number" type="text" placeholder="e.g. A12345" required>
                        </div>

                        <div class="form-group">
                            <label for="vehicle_use_class">Vehicle Use / Class</label>
                            <select id="vehicle_use_class" name="vehicle_use_class" required>
                                <option value="">Select vehicle class</option>
                                <option value="Private Vehicle">Private Vehicle</option>
                                <option value="Commercial Vehicle">Commercial Vehicle</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="vehicle_type">Vehicle Type</label>
                            <input type="hidden" name="official_vehicle_category" id="officialVehicleCategory" value="Private Vehicle">
                            <select id="vehicle_type" name="vehicle_type" required>
                                <option value="">Select vehicle type</option>
                                <?php foreach ($vehicle_types as $type): ?>
                                    <option value="<?php echo htmlspecialchars($type['value']); ?>" data-fee="<?php echo $type['fee']; ?>" data-classification="<?php echo htmlspecialchars($type['classification']); ?>"><?php echo htmlspecialchars($type['label']); ?> (<?php echo htmlspecialchars($type['classification']); ?>) - $<?php echo number_format($type['fee'], 2); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="expiry_date">Expiry Date (Optional)</label>
                            <input id="expiry_date" name="expiry_date" type="date">
                        </div>

                        <div class="form-group">
                            <label id="makeLabel" for="make">Vehicle Make</label>
                            <input id="make" name="make" type="text" placeholder="e.g. Toyota" required>
                        </div>

                        <div class="form-group">
                            <label id="modelLabel" for="model">Vehicle Model</label>
                            <input id="model" name="model" type="text" placeholder="e.g. Corolla" required>
                        </div>

                        <div class="form-group">
                            <label for="year">Year</label>
                            <input id="year" name="year" type="number" min="1900" max="2100" placeholder="2024" required>
                        </div>

                        <div class="form-group">
                            <label for="color">Color</label>
                            <input id="color" name="color" type="text" placeholder="e.g. White" required>
                        </div>

                        <div class="form-group">
                            <label for="county">County</label>
                            <select id="county" name="county" required>
                                <option value="">Select your county</option>
                                <?php foreach ($county_list as $county_name): ?>
                                    <option value="<?php echo htmlspecialchars($county_name); ?>"><?php echo htmlspecialchars($county_name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="owner_name">Owner Name</label>
                            <input id="owner_name" name="owner_name" type="text" placeholder="Full name" required>
                        </div>

                        <div class="form-group full">
                            <label for="owner_contact">Owner Contact</label>
                            <input id="owner_contact" name="owner_contact" type="text" placeholder="e.g. +231-880000000" required>
                        </div>
                    </div>

                    <div class="meta-strip" style="margin-top: 24px; margin-bottom: 16px;">
                        <span class="chip"><i class="fa-solid fa-shield-halved"></i> Verification Required</span>
                        <span class="chip"><i class="fa-solid fa-clock"></i> Pending Review</span>
                        <span class="chip"><i class="fa-solid fa-check-circle"></i> Approved by Admin</span>
                    </div>

                    <button type="submit" class="btn"><i class="fa-solid fa-paper-plane"></i> Submit for Approval</button>
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
    <script>
        const vehicleTypeSelect = document.getElementById('vehicle_type');
        const vehicleUseClassSelect = document.getElementById('vehicle_use_class');
        const officialVehicleCategoryInput = document.getElementById('officialVehicleCategory');

        function syncVehicleCategory() {
            if (!vehicleTypeSelect || !officialVehicleCategoryInput) return;
            const selectedOption = vehicleTypeSelect.selectedOptions[0];
            const classification = selectedOption ? (selectedOption.dataset.classification || '').toString() : '';
            const selectedClass = vehicleUseClassSelect ? (vehicleUseClassSelect.value || 'Private Vehicle') : 'Private Vehicle';
            const isMotorcycle = classification.toLowerCase() === 'motorcycle';
            officialVehicleCategoryInput.value = isMotorcycle ? 'Motorcycle' : selectedClass;

            const makeLabel = document.getElementById('makeLabel');
            const modelLabel = document.getElementById('modelLabel');
            const makeInput = document.getElementById('make');
            const modelInput = document.getElementById('model');

            if (makeLabel) {
                makeLabel.textContent = isMotorcycle ? 'Bike Name' : 'Vehicle Make';
            }
            if (modelLabel) {
                modelLabel.textContent = isMotorcycle ? 'Bike Model' : 'Vehicle Model';
            }
            if (makeInput) {
                makeInput.placeholder = isMotorcycle ? 'e.g. Honda XR' : 'e.g. Toyota';
            }
            if (modelInput) {
                modelInput.placeholder = isMotorcycle ? 'e.g. CRF 250R' : 'e.g. Corolla';
            }
        }

        if (vehicleTypeSelect) {
            vehicleTypeSelect.addEventListener('change', syncVehicleCategory);
        }
        if (vehicleUseClassSelect) {
            vehicleUseClassSelect.addEventListener('change', syncVehicleCategory);
        }
        syncVehicleCategory();
    </script>
</body>
</html>
