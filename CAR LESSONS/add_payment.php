<?php
session_start();
include "staff_auth.php";
include "config.php";
include "partials/admin-toast.php";
include "partials/admin-notifications.php";

$unread_admin_notifications = admin_get_unread_notification_count($conn);

$message = '';
$message_type = '';

// Get applications for dropdown
$applications_query = mysqli_query($conn, "SELECT id, application_number, applicant_name FROM applications ORDER BY application_date DESC");
$vehicles_query = mysqli_query($conn, "SELECT id, plate_number, owner_name, status, created_by_applicant FROM vehicles WHERE created_by_applicant IS NOT NULL ORDER BY created_at DESC");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $application_id = isset($_POST['application_id']) && $_POST['application_id'] !== '' ? (int) $_POST['application_id'] : null;
    $vehicle_id = isset($_POST['vehicle_id']) && $_POST['vehicle_id'] !== '' ? (int) $_POST['vehicle_id'] : null;
    $amount = (float) $_POST['amount'];
    $payment_currency = mysqli_real_escape_string($conn, appNormalizePaymentCurrency($_POST['payment_currency'] ?? 'USD'));
    $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $received_by = (int) $_SESSION['id'];

    if (($application_id === null) && ($vehicle_id === null)) {
        $message = "Please select either an application or an existing plate request.";
        $message_type = 'warning';
    } else {
        $duplicate_check = mysqli_query($conn, "SELECT id FROM payments WHERE 
            (application_id IS NOT NULL AND application_id = " . ($application_id !== null ? (int) $application_id : 'NULL') . ") OR
            (vehicle_id IS NOT NULL AND vehicle_id = " . ($vehicle_id !== null ? (int) $vehicle_id : 'NULL') . ")
            LIMIT 1");

        if ($duplicate_check && mysqli_num_rows($duplicate_check) > 0) {
            $message = "This application or existing plate request already has a recorded payment. Duplicate payment is not allowed.";
            $message_type = 'warning';
        } else {
            $receipt_number = 'RCP-' . date('Ymd') . '-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
            $application_sql = $application_id !== null ? (string) $application_id : 'NULL';
            $vehicle_sql = $vehicle_id !== null ? (string) $vehicle_id : 'NULL';

            $insert_query = "INSERT INTO payments (application_id, vehicle_id, amount, payment_currency, payment_method, receipt_number, status, received_by)
                             VALUES ($application_sql, $vehicle_sql, '$amount', '$payment_currency', '$payment_method', '$receipt_number', '$status', '$received_by')";

            if (mysqli_query($conn, $insert_query)) {
                if ($vehicle_id !== null) {
                    mysqli_query($conn, "UPDATE vehicles SET payment_status = 'Paid' WHERE id = '$vehicle_id'");
                }

                $message = "Payment recorded successfully! Receipt #: " . $receipt_number;
                $message_type = 'success';
            } else {
                $message = "Error: " . mysqli_error($conn);
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
    <title>Add Payment - Transport Union Authority</title>
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
            <li><a href="vehicles.php"><i class="fa-solid fa-car"></i> Vehicles</a></li>
            <li><a href="applications.php"><i class="fa-solid fa-file-lines"></i> Applications<?php if ($unread_admin_notifications > 0): ?><span style="margin-left:8px;background:#c41e3a;color:#fff;border-radius:999px;padding:2px 8px;font-size:11px;font-weight:700;"><?php echo $unread_admin_notifications; ?></span><?php endif; ?></a></li>
            <li><a href="messages.php"><i class="fa-solid fa-envelope-open-text"></i> Messages<?php if (admin_get_unread_contact_message_count($conn) > 0): ?><span style="margin-left:8px;background:#c41e3a;color:#fff;border-radius:999px;padding:2px 8px;font-size:11px;font-weight:700;"><?php echo admin_get_unread_contact_message_count($conn); ?></span><?php endif; ?></a></li>
            <li><a href="payments.php" class="active"><i class="fa-solid fa-credit-card"></i> Payments</a></li>
            <li><a href="reports.php"><i class="fa-solid fa-chart-line"></i> Reports</a></li>
            <li><a href="generate-plate.php"><i class="fa-solid fa-qrcode"></i> Generate Plate</a></li>
            <li><a href="add_vehicle.php"><i class="fa-solid fa-plus-circle"></i> Register Existing Plate</a></li>
            <li><a href="index.php" target="_blank"><i class="fa-solid fa-globe"></i> View Website</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Header -->
        <div class="top-header">
            <div class="page-title">
                <h1><i class="fa-solid fa-receipt"></i> Record Payment</h1>
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
                    <h2><i class="fa-solid fa-file-invoice-dollar"></i> Payment Details</h2>
                    <a href="payments.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Back to Payments</a>
                </div>

                <form method="POST" action="">
                    <div class="form-group">
                        <label>Application</label>
                        <select name="application_id" id="application_id">
                            <option value="">Select Application (optional)</option>
                            <?php while ($app = mysqli_fetch_assoc($applications_query)): ?>
                                <option value="<?php echo $app['id']; ?>">
                                    <?php echo $app['application_number']; ?> - <?php echo $app['applicant_name']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Existing Plate Request</label>
                        <select name="vehicle_id" id="vehicle_id">
                            <option value="">Select Existing Plate Request (optional)</option>
                            <?php while ($vehicle = mysqli_fetch_assoc($vehicles_query)): ?>
                                <option value="<?php echo $vehicle['id']; ?>">
                                    <?php echo htmlspecialchars($vehicle['plate_number']); ?> - <?php echo htmlspecialchars($vehicle['owner_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Amount</label>
                            <input type="number" step="0.01" name="amount" required placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label>Currency</label>
                            <select name="payment_currency" required>
                                <option value="">Select Currency</option>
                                <option value="USD">USD</option>
                                <option value="LRD">LRD</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Payment Method</label>
                            <select name="payment_method" required>
                                <option value="">Select Method</option>
                                <option>Cash</option>
                                <option>Card</option>
                                <option>Mobile Money</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" required>
                            <option value="Paid">Paid</option>
                            <option value="Pending">Pending</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Record Payment</button>
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
</body>
</html>
