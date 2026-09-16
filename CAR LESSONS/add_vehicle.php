<?php
session_start();
include "staff_auth.php";
include "config.php";
include "partials/admin-toast.php";
include "partials/admin-notifications.php";

$unread_admin_notifications = admin_get_unread_notification_count($conn);

$message = '';
$message_type = '';

$vehicle_types = [
    ['value' => 'small_passenger_car', 'label' => 'A1 - Small Passenger Car', 'classification' => 'A1', 'fee' => 90],
    ['value' => 'medium_passenger_car', 'label' => 'A2 - Medium Passenger Car', 'classification' => 'A2', 'fee' => 120],
    ['value' => 'large_passenger_car_minivan', 'label' => 'A3 - Large Passenger Car / Minivan', 'classification' => 'A3', 'fee' => 170],
    ['value' => 'small_suv_light_pickup', 'label' => 'A4 - Small SUV / Light Pickup', 'classification' => 'A4', 'fee' => 190],
    ['value' => 'medium_suv_pickup', 'label' => 'A5 - Medium SUV / Pickup', 'classification' => 'A5', 'fee' => 220],
    ['value' => 'large_heavy_suv_pickup', 'label' => 'A6 - Large / Heavy SUV / Pickup', 'classification' => 'A6', 'fee' => 260],
];
$vehicle_use_options = ['Private Vehicle', 'Commercial Vehicle'];
$official_vehicle_categories = ['Motorcycle', 'Private Vehicle', 'Commercial Vehicle'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $plate_number = mysqli_real_escape_string($conn, $_POST['plate_number']);
    $vehicle_type = mysqli_real_escape_string($conn, $_POST['vehicle_type'] ?? 'sedan');
    $vehicle_use_class = in_array($_POST['vehicle_use_class'] ?? '', ['Private Vehicle', 'Commercial Vehicle'], true) ? mysqli_real_escape_string($conn, $_POST['vehicle_use_class']) : 'Private Vehicle';
    $official_vehicle_category = in_array($_POST['official_vehicle_category'] ?? '', ['Motorcycle', 'Private Vehicle', 'Commercial Vehicle'], true) ? mysqli_real_escape_string($conn, $_POST['official_vehicle_category']) : 'Private Vehicle';
    $registration_status = mysqli_real_escape_string($conn, $_POST['status'] ?? 'Pending');
    $expiry_date = mysqli_real_escape_string($conn, $_POST['expiry_date'] ?? '');
    $make = mysqli_real_escape_string($conn, $_POST['make']);
    $model = mysqli_real_escape_string($conn, $_POST['model']);
    $year = mysqli_real_escape_string($conn, $_POST['year']);
    $color = mysqli_real_escape_string($conn, $_POST['color']);
    $owner_name = mysqli_real_escape_string($conn, $_POST['owner_name']);
    $owner_contact = mysqli_real_escape_string($conn, $_POST['owner_contact']);
    $created_by = $_SESSION['id'];

    $existing_vehicle_query = mysqli_query($conn, "SELECT * FROM vehicles WHERE plate_number = '$plate_number' LIMIT 1");
    if (mysqli_num_rows($existing_vehicle_query) > 0) {
        $existing_vehicle = mysqli_fetch_assoc($existing_vehicle_query);
        $update_query = "UPDATE vehicles SET
            vehicle_type = '$vehicle_type',
            vehicle_use_class = '$vehicle_use_class',
            official_vehicle_category = '$official_vehicle_category',
            status = '$registration_status',
            expiry_date = " . ($expiry_date !== '' ? "'$expiry_date'" : "NULL") . ",
            make = '$make',
            model = '$model',
            year = '$year',
            color = '$color',
            owner_name = '$owner_name',
            owner_contact = '$owner_contact',
            created_by = '$created_by'
            WHERE id = '{$existing_vehicle['id']}'";

        if (mysqli_query($conn, $update_query)) {
            $message = "Existing plate details updated successfully!";
            $message_type = 'success';
        } else {
            $message = "Error: " . mysqli_error($conn);
            $message_type = 'danger';
        }
    } else {
        $insert_query = "INSERT INTO vehicles (plate_number, vehicle_type, vehicle_use_class, official_vehicle_category, status, expiry_date, make, model, year, color, owner_name, owner_contact, created_by, created_by_applicant) 
                         VALUES ('$plate_number', '$vehicle_type', '$vehicle_use_class', '$official_vehicle_category', '$registration_status', " . ($expiry_date !== '' ? "'$expiry_date'" : "NULL") . ", '$make', '$model', '$year', '$color', '$owner_name', '$owner_contact', '$created_by', NULL)";
        if (mysqli_query($conn, $insert_query)) {
            $message = "Vehicle registered successfully!";
            $message_type = 'success';
        } else {
            $message = "Error: " . mysqli_error($conn);
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
    <title>Add Vehicle - Transport Union Authority</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin-toast.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        /* Sidebar */
        .sidebar {
            width: 260px;
            background: linear-gradient(180deg, #0a2463 0%, #0f3460 100%);
            color: white;
            padding: 25px 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 0 25px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.15);
            margin-bottom: 20px;
        }

        .sidebar-header img {
            height: 80px;
            margin-bottom: 15px;
        }

        .sidebar-header h2 {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .sidebar-header p {
            font-size: 11px;
            opacity: 0.8;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0 15px;
        }

        .sidebar-menu li {
            margin-bottom: 5px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 13px 18px;
            color: rgba(255,255,255,0.9);
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        .sidebar-menu a:hover, .sidebar-menu a.active {
            background: rgba(255,255,255,0.15);
        }

        .sidebar-menu i {
            margin-right: 14px;
            width: 22px;
            text-align: center;
            font-size: 16px;
        }

        /* Main Content */
        .main-content {
            margin-left: 260px;
            min-height: 100vh;
            background: #f1f5f9;
        }

        /* Top Header */
        .top-header {
            background: white;
            padding: 18px 40px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-title h1 {
            color: #0a2463;
            font-size: 24px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .user-info .role {
            background: linear-gradient(135deg, #c41e3a 0%, #a01830 100%);
            color: white;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .user-info .name {
            color: #0a2463;
            font-weight: 600;
        }

        .user-info a {
            background: #f1f5f9;
            color: #c41e3a;
            padding: 10px 18px;
            border-radius: 8px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
        }

        /* Page Content */
        .page-content {
            padding: 35px 40px;
        }

        .content-card {
            background: white;
            padding: 35px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .card-header h2 {
            color: #0a2463;
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn {
            padding: 11px 22px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-secondary {
            background: #64748b;
            color: white;
        }

        .btn-primary {
            background: linear-gradient(135deg, #0a2463 0%, #0f3460 100%);
            color: white;
            width: 100%;
            justify-content: center;
            padding: 14px;
            font-size: 15px;
        }

        .btn-primary:hover {
            opacity: 0.92;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 22px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #0f172a;
            font-weight: 600;
            font-size: 14px;
        }

        .form-group input, .form-group select {
            width: 100%;
            padding: 13px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            outline: none;
            transition: border-color 0.3s;
        }

        .form-group input:focus, .form-group select:focus {
            border-color: #0a2463;
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

        /* Alert */
        .alert {
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 25px;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
        }

        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <img src="assets/images/logo.png" alt="TUA">
            <h2>TUA PORTAL</h2>
            <p>REPUBLIC OF LIBERIA</p>
        </div>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php"><i class="fa-solid fa-gauge-high"></i> Dashboard</a></li>
            <li><a href="users.php"><i class="fa-solid fa-users"></i> Users Management</a></li>
            <li><a href="vehicles.php" class="active"><i class="fa-solid fa-car"></i> Vehicles</a></li>
            <li><a href="applications.php"><i class="fa-solid fa-file-lines"></i> Applications<?php if ($unread_admin_notifications > 0): ?><span style="margin-left:8px;background:#c41e3a;color:#fff;border-radius:999px;padding:2px 8px;font-size:11px;font-weight:700;"><?php echo $unread_admin_notifications; ?></span><?php endif; ?></a></li>
            <li><a href="messages.php"><i class="fa-solid fa-envelope-open-text"></i> Messages<?php if (admin_get_unread_contact_message_count($conn) > 0): ?><span style="margin-left:8px;background:#c41e3a;color:#fff;border-radius:999px;padding:2px 8px;font-size:11px;font-weight:700;"><?php echo admin_get_unread_contact_message_count($conn); ?></span><?php endif; ?></a></li>
            <li><a href="payments.php"><i class="fa-solid fa-credit-card"></i> Payments</a></li>
            <li><a href="reports.php"><i class="fa-solid fa-chart-line"></i> Reports</a></li>
            <li><a href="generate-plate.php"><i class="fa-solid fa-qrcode"></i> Generate Plate</a></li>
            <li><a href="add_vehicle.php" class="active"><i class="fa-solid fa-plus-circle"></i> Register Existing Plate</a></li>
            <li><a href="index.php" target="_blank"><i class="fa-solid fa-globe"></i> View Website</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Header -->
        <div class="top-header">
            <div class="page-title">
                <h1><i class="fa-solid fa-car-side"></i> Add New Vehicle</h1>
            </div>
            <div class="user-info">
                <span class="role"><?php echo $_SESSION['role']; ?></span>
                <span class="name"><?php echo $_SESSION['fullname']; ?></span>
                <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
            </div>
        </div>

        <!-- Page Content -->
        <div class="page-content">
            <div class="content-card">
                <div class="card-header">
                    <h2><i class="fa-solid fa-file-circle-plus"></i> Vehicle Details</h2>
                    <a href="vehicles.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Back to Vehicles</a>
                </div>

                <form method="POST" action="">
                    <div class="form-group">
                        <label>Plate Number</label>
                        <input type="text" name="plate_number" required placeholder="Enter vehicle plate number">
                    </div>

                    <div class="form-group">
                        <label>Registration Status</label>
                        <select name="status" required>
                            <option value="Pending">Pending</option>
                            <option value="Active">Active</option>
                            <option value="Expired">Expired</option>
                            <option value="Rejected">Rejected</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Expiry Date</label>
                        <input type="date" name="expiry_date" placeholder="Select expiry date">
                    </div>

                    <div class="form-group">
                        <label>Vehicle Type</label>
                        <select name="vehicle_type" id="vehicleType" required>
                            <option value="">Select vehicle type</option>
                            <?php foreach ($vehicle_types as $type): ?>
                                <option value="<?php echo $type['value']; ?>" data-fee="<?php echo $type['fee']; ?>" data-classification="<?php echo $type['classification']; ?>" <?php echo (($_POST['vehicle_type'] ?? 'sedan') === $type['value']) ? 'selected' : ''; ?>>
                                    <?php echo $type['label']; ?> (<?php echo $type['classification']; ?>) - $<?php echo number_format($type['fee'], 2); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="fee-display-box" style="margin-top: 12px; background: #eef6ff; border: 1px solid #cfe0ff; border-radius: 12px; padding: 14px 16px; display: flex; justify-content: space-between; align-items: center;">
                            <span style="color: #0a2463; font-weight: 700;">Selected fee</span>
                            <strong id="vehicleFeeAmount" style="color: #0a2463; font-size: 1.1rem;">$0.00</strong>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label id="adminVehicleMakeLabel">Make</label>
                            <input type="text" name="make" id="adminVehicleMakeInput" required placeholder="e.g. Toyota">
                        </div>
                        <div class="form-group">
                            <label id="adminVehicleModelLabel">Model</label>
                            <input type="text" name="model" id="adminVehicleModelInput" required placeholder="e.g. Camry">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Year (Optional)</label>
                            <input type="number" name="year" placeholder="e.g. 2020">
                        </div>
                        <div class="form-group">
                            <label>Color (Optional)</label>
                            <input type="text" name="color" placeholder="e.g. Red">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Owner Name</label>
                        <input type="text" name="owner_name" required placeholder="Enter owner's full name">
                    </div>

                    <div class="form-group">
                        <label>Owner Contact (Optional)</label>
                        <input type="text" name="owner_contact" placeholder="Enter owner's contact number">
                    </div>

                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Vehicle</button>
                </form>
            </div>
        </div>
    </div>
    <?php if (!empty($page_toasts)): ?>
        <script>
            window.adminToastQueue = <?php echo json_encode($page_toasts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        </script>
    <?php endif; ?>
    <script src="assets/js/admin-toast.js"></script>
    <script>
        const vehicleTypeSelect = document.getElementById('vehicleType');
        const vehicleFeeAmount = document.getElementById('vehicleFeeAmount');
        const adminVehicleMakeLabel = document.getElementById('adminVehicleMakeLabel');
        const adminVehicleModelLabel = document.getElementById('adminVehicleModelLabel');
        const adminVehicleMakeInput = document.getElementById('adminVehicleMakeInput');
        const adminVehicleModelInput = document.getElementById('adminVehicleModelInput');

        function updateVehicleFee() {
            const selectedOption = vehicleTypeSelect.selectedOptions[0];
            const fee = selectedOption ? Number(selectedOption.dataset.fee || 0) : 0;
            vehicleFeeAmount.textContent = '$' + fee.toFixed(2);

            if (selectedOption) {
                const isMotorcycle = (selectedOption.dataset.classification || '').toString().toLowerCase() === 'motorcycle';
                if (adminVehicleMakeLabel) {
                    adminVehicleMakeLabel.textContent = isMotorcycle ? 'Bike Name' : 'Make';
                }
                if (adminVehicleModelLabel) {
                    adminVehicleModelLabel.textContent = isMotorcycle ? 'Bike Model' : 'Model';
                }
                if (adminVehicleMakeInput) {
                    adminVehicleMakeInput.placeholder = isMotorcycle ? 'e.g. Honda XR' : 'e.g. Toyota';
                }
                if (adminVehicleModelInput) {
                    adminVehicleModelInput.placeholder = isMotorcycle ? 'e.g. CRF 250R' : 'e.g. Camry';
                }
            }
        }

        if (vehicleTypeSelect) {
            vehicleTypeSelect.addEventListener('change', updateVehicleFee);
            updateVehicleFee();
        }
    </script>
</body>
</html>
