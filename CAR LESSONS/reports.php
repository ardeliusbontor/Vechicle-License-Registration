<?php
session_start();
include "staff_auth.php";
include "config.php";
include "partials/admin-toast.php";
include "partials/admin-notifications.php";

$unread_admin_notifications = admin_get_unread_notification_count($conn);
$selected_payment_status = isset($_GET['payment_status']) ? strtoupper(trim((string) $_GET['payment_status'])) : '';
if (!in_array($selected_payment_status, ['PAID', 'PENDING'], true)) {
    $selected_payment_status = '';
}

if (isset($_GET['export']) && in_array($_GET['export'], ['paid_plates_csv', 'pending_plates_csv'], true)) {
    $export_type = $_GET['export'];
    $export_status = $export_type === 'paid_plates_csv' ? 'Paid' : 'Pending';
    $csv_result = mysqli_query($conn, "SELECT a.id, v.plate_number, a.applicant_name AS owner_name, a.applicant_contact AS owner_contact, v.vehicle_type, v.official_vehicle_category, a.county, a.status, CASE WHEN EXISTS (SELECT 1 FROM payments p WHERE p.application_id = a.id AND p.status = 'Paid') THEN 'Paid' WHEN a.vehicle_id IS NOT NULL AND a.id = (SELECT a2.id FROM applications a2 WHERE a2.vehicle_id = a.vehicle_id ORDER BY a2.application_date ASC, a2.id ASC LIMIT 1) AND EXISTS (SELECT 1 FROM payments p WHERE p.vehicle_id = a.vehicle_id AND p.status = 'Paid') THEN 'Paid' ELSE 'Pending' END AS payment_status, a.application_date AS created_at FROM applications a LEFT JOIN vehicles v ON v.id = a.vehicle_id WHERE CASE WHEN EXISTS (SELECT 1 FROM payments p WHERE p.application_id = a.id AND p.status = 'Paid') THEN 'Paid' WHEN a.vehicle_id IS NOT NULL AND a.id = (SELECT a2.id FROM applications a2 WHERE a2.vehicle_id = a.vehicle_id ORDER BY a2.application_date ASC, a2.id ASC LIMIT 1) AND EXISTS (SELECT 1 FROM payments p WHERE p.vehicle_id = a.vehicle_id AND p.status = 'Paid') THEN 'Paid' ELSE 'Pending' END = '$export_status' ORDER BY a.application_date DESC");
    $filename = $export_type === 'paid_plates_csv' ? 'paid-plates-export-' . date('Ymd-His') . '.csv' : 'pending-plates-export-' . date('Ymd-His') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Plate Number', 'Owner Name', 'Owner Contact', 'Vehicle Type', 'Vehicle Category', 'County', 'Status', 'Payment Status', 'Created At']);

    if ($csv_result) {
        while ($row = mysqli_fetch_assoc($csv_result)) {
            fputcsv($output, [
                $row['id'],
                $row['plate_number'],
                $row['owner_name'],
                $row['owner_contact'],
                $row['vehicle_type'],
                $row['official_vehicle_category'],
                $row['county'],
                $row['status'],
                $row['payment_status'],
                $row['created_at']
            ]);
        }
    }

    fclose($output);
    exit();
}

$search_term = trim((string) ($_GET['search'] ?? ''));
$status_filter = trim((string) ($_GET['status'] ?? ''));

$app_filter_sql = '';
if ($search_term !== '') {
    $safe_search = mysqli_real_escape_string($conn, $search_term);
    $app_filter_sql .= " AND (a.applicant_name LIKE '%$safe_search%' OR a.application_number LIKE '%$safe_search%' OR a.county LIKE '%$safe_search%' OR a.license_plate_number LIKE '%$safe_search%') ";
}
if (in_array($status_filter, ['Pending', 'Approved', 'Rejected'], true)) {
    $safe_status = mysqli_real_escape_string($conn, $status_filter);
    $app_filter_sql .= " AND a.status = '$safe_status' ";
}

// Get statistics
$total_users_result = mysqli_query($conn, "SELECT COUNT(*) as count FROM users");
$total_users = mysqli_fetch_assoc($total_users_result)['count'];

$total_vehicles_result = mysqli_query($conn, "SELECT COUNT(*) as count FROM vehicles WHERE status IN ('Active', 'Approved') AND plate_number IS NOT NULL AND TRIM(plate_number) <> ''");
$total_vehicles = (int) (mysqli_fetch_assoc($total_vehicles_result)['count'] ?? 0);

$total_applications_result = mysqli_query($conn, "SELECT COUNT(*) as count FROM applications");
$total_applications = (int) (mysqli_fetch_assoc($total_applications_result)['count'] ?? 0);

$pending_applications_result = mysqli_query($conn, "SELECT COUNT(*) as count FROM applications WHERE status='Pending'");
$pending_applications = mysqli_fetch_assoc($pending_applications_result)['count'];

$approved_applications_result = mysqli_query($conn, "SELECT COUNT(*) as count FROM applications WHERE status='Approved'");
$approved_applications = mysqli_fetch_assoc($approved_applications_result)['count'];

$rejected_applications_result = mysqli_query($conn, "SELECT COUNT(*) as count FROM applications WHERE status='Rejected'");
$rejected_applications = mysqli_fetch_assoc($rejected_applications_result)['count'];

$expiring_soon_result = mysqli_query($conn, "SELECT COUNT(*) as count FROM applications WHERE license_plate_number IS NOT NULL AND license_plate_number <> '' AND expires_at IS NOT NULL AND expires_at BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)");
$expiring_soon_count = $expiring_soon_result ? (int) mysqli_fetch_assoc($expiring_soon_result)['count'] : 0;

$expired_plates_result = mysqli_query($conn, "SELECT COUNT(*) as count FROM applications WHERE license_plate_number IS NOT NULL AND license_plate_number <> '' AND expires_at IS NOT NULL AND expires_at < CURDATE()");
$expired_plates_count = $expired_plates_result ? (int) mysqli_fetch_assoc($expired_plates_result)['count'] : 0;

$paid_plates_result = mysqli_query($conn, "SELECT SUM(CASE WHEN effective_payment_status = 'Paid' THEN 1 ELSE 0 END) AS count FROM (
    SELECT a.id,
           CASE
               WHEN EXISTS (SELECT 1 FROM payments p WHERE p.application_id = a.id AND p.status = 'Paid') THEN 'Paid'
               WHEN a.vehicle_id IS NOT NULL
                    AND a.id = (
                        SELECT a2.id
                        FROM applications a2
                        WHERE a2.vehicle_id = a.vehicle_id
                        ORDER BY a2.application_date ASC, a2.id ASC
                        LIMIT 1
                    )
                    AND EXISTS (SELECT 1 FROM payments p WHERE p.vehicle_id = a.vehicle_id AND p.status = 'Paid') THEN 'Paid'
               ELSE 'Pending'
           END AS effective_payment_status
    FROM applications a
) summary");
$paid_plates_count = $paid_plates_result ? (int) mysqli_fetch_assoc($paid_plates_result)['count'] : 0;

$pending_plates_result = mysqli_query($conn, "SELECT SUM(CASE WHEN effective_payment_status = 'Pending' THEN 1 ELSE 0 END) AS count FROM (
    SELECT a.id,
           CASE
               WHEN EXISTS (SELECT 1 FROM payments p WHERE p.application_id = a.id AND p.status = 'Paid') THEN 'Paid'
               WHEN a.vehicle_id IS NOT NULL
                    AND a.id = (
                        SELECT a2.id
                        FROM applications a2
                        WHERE a2.vehicle_id = a.vehicle_id
                        ORDER BY a2.application_date ASC, a2.id ASC
                        LIMIT 1
                    )
                    AND EXISTS (SELECT 1 FROM payments p WHERE p.vehicle_id = a.vehicle_id AND p.status = 'Paid') THEN 'Paid'
               ELSE 'Pending'
           END AS effective_payment_status
    FROM applications a
) summary");
$pending_plates_count = $pending_plates_result ? (int) mysqli_fetch_assoc($pending_plates_result)['count'] : 0;

$filtered_payment_plates_result = null;
if ($selected_payment_status !== '') {
    $selected_payment_status_value = $selected_payment_status === 'PAID' ? 'Paid' : 'Pending';
    $filtered_payment_plates_result = mysqli_query($conn, "SELECT a.id, v.plate_number, a.applicant_name AS owner_name, a.applicant_contact AS owner_contact, v.vehicle_type, v.official_vehicle_category, a.county, a.status, CASE WHEN EXISTS (SELECT 1 FROM payments p WHERE p.application_id = a.id AND p.status = 'Paid') THEN 'Paid' WHEN a.vehicle_id IS NOT NULL AND a.id = (SELECT a2.id FROM applications a2 WHERE a2.vehicle_id = a.vehicle_id ORDER BY a2.application_date ASC, a2.id ASC LIMIT 1) AND EXISTS (SELECT 1 FROM payments p WHERE p.vehicle_id = a.vehicle_id AND p.status = 'Paid') THEN 'Paid' ELSE 'Pending' END AS payment_status, a.application_date AS created_at FROM applications a LEFT JOIN vehicles v ON v.id = a.vehicle_id WHERE CASE WHEN EXISTS (SELECT 1 FROM payments p WHERE p.application_id = a.id AND p.status = 'Paid') THEN 'Paid' WHEN a.vehicle_id IS NOT NULL AND a.id = (SELECT a2.id FROM applications a2 WHERE a2.vehicle_id = a.vehicle_id ORDER BY a2.application_date ASC, a2.id ASC LIMIT 1) AND EXISTS (SELECT 1 FROM payments p WHERE p.vehicle_id = a.vehicle_id AND p.status = 'Paid') THEN 'Paid' ELSE 'Pending' END = '$selected_payment_status_value' ORDER BY a.application_date DESC");
}

$total_usd_payments_result = mysqli_query($conn, "SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE status='Paid' AND COALESCE(payment_currency, 'USD') = 'USD'");
$total_usd_payments = (float) mysqli_fetch_assoc($total_usd_payments_result)['total'];

$total_lrd_payments_result = mysqli_query($conn, "SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE status='Paid' AND COALESCE(payment_currency, 'USD') = 'LRD'");
$total_lrd_payments = (float) mysqli_fetch_assoc($total_lrd_payments_result)['total'];

$counties_result = mysqli_query($conn, "SELECT county, COUNT(*) as count FROM applications GROUP BY county ORDER BY count DESC");

$recent_applications_query = mysqli_query($conn, "SELECT a.*, v.plate_number as vehicle_plate FROM applications a LEFT JOIN vehicles v ON a.vehicle_id = v.id WHERE 1=1 $app_filter_sql ORDER BY a.application_date DESC LIMIT 15");

$page_toasts = admin_toast_pull();
foreach (admin_collect_unread_notification_toasts($conn, 3) as $notification_toast) {
    $page_toasts[] = [
        'message' => $notification_toast['message'],
        'type' => $notification_toast['type']
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Transport Union Authority</title>
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

        .mini-export-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 12px;
            background: #0a2463;
            color: white;
            text-decoration: none;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
        }

        .mini-export-btn:hover {
            opacity: 0.92;
        }

        .payment-status-link {
            display: block;
            text-decoration: none;
            color: inherit;
        }

        .payment-status-link .stat-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            cursor: pointer;
        }

        .payment-status-link:hover .stat-card,
        .payment-status-link:focus .stat-card {
            transform: translateY(-2px);
            box-shadow: 0 12px 28px rgba(15, 23, 42, 0.12);
        }

        .filter-bar {
            display: flex;
            gap: 14px;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 24px;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 18px 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.04);
        }

        .filter-bar input,
        .filter-bar select {
            border: 1px solid #dbe3ee;
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 14px;
            color: #0f172a;
            background: #f8fafc;
        }

        .filter-bar input {
            min-width: 260px;
        }

        .filter-bar button {
            background: linear-gradient(135deg, #0a2463 0%, #0f3460 100%);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 11px 18px;
            font-weight: 700;
            cursor: pointer;
        }

        .filter-bar a {
            text-decoration: none;
            color: #0a2463;
            font-weight: 700;
        }

        /* Stat Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 25px;
            margin-bottom: 35px;
        }

        .stat-card {
            background: white;
            padding: 28px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 22px;
        }

        .stat-icon {
            width: 70px;
            height: 70px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: white;
        }

        .stat-icon.blue {
            background: linear-gradient(135deg, #0a2463 0%, #0f3460 100%);
        }

        .stat-icon.green {
            background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
        }

        .stat-icon.orange {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }

        .stat-icon.red {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
        }

        .stat-icon.purple {
            background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%);
        }

        .stat-details h3 {
            color: #64748b;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .stat-details .number {
            color: #0f172a;
            font-size: 34px;
            font-weight: 800;
        }

        /* Content Cards */
        .content-card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            margin-bottom: 25px;
        }

        .content-card h2 {
            color: #0a2463;
            margin-bottom: 20px;
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Table Styles */
        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f8fafc;
            color: #475569;
            padding: 14px 16px;
            text-align: left;
            font-weight: 700;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td {
            padding: 16px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
            color: #334155;
        }

        tr:hover {
            background: #f8fafc;
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
            <?php if (($_SESSION['role'] ?? '') === 'Admin'): ?>
                <li><a href="users.php"><i class="fa-solid fa-users"></i> Users Management</a></li>
            <?php endif; ?>
            <li><a href="vehicles.php"><i class="fa-solid fa-car"></i> Vehicles</a></li>
            <li><a href="applications.php"><i class="fa-solid fa-file-lines"></i> Applications<?php if ($unread_admin_notifications > 0): ?><span style="margin-left:8px;background:#c41e3a;color:#fff;border-radius:999px;padding:2px 8px;font-size:11px;font-weight:700;"><?php echo $unread_admin_notifications; ?></span><?php endif; ?></a></li>
            <li><a href="messages.php"><i class="fa-solid fa-envelope-open-text"></i> Messages<?php if (admin_get_unread_contact_message_count($conn) > 0): ?><span style="margin-left:8px;background:#c41e3a;color:#fff;border-radius:999px;padding:2px 8px;font-size:11px;font-weight:700;"><?php echo admin_get_unread_contact_message_count($conn); ?></span><?php endif; ?></a></li>
            <li><a href="payments.php"><i class="fa-solid fa-credit-card"></i> Payments</a></li>
            <li><a href="reports.php" class="active"><i class="fa-solid fa-chart-line"></i> Reports</a></li>
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
                <h1><i class="fa-solid fa-chart-line"></i> System Reports</h1>
            </div>
            <div class="user-info">
                <span class="role"><?php echo $_SESSION['role']; ?></span>
                <span class="name"><?php echo $_SESSION['fullname']; ?></span>
                <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
            </div>
        </div>

        <!-- Page Content -->
        <div class="page-content">
            <form method="GET" class="filter-bar">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search_term); ?>" placeholder="Search applicant, plate, county, application ID">
                <select name="status">
                    <option value="">All statuses</option>
                    <option value="Pending" <?php echo $status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="Approved" <?php echo $status_filter === 'Approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="Rejected" <?php echo $status_filter === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                </select>
                <button type="submit"><i class="fa-solid fa-filter"></i> Filter</button>
                <a href="reports.php"><i class="fa-solid fa-rotate-left"></i> Reset</a>
            </form>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon blue">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <div class="stat-details">
                        <h3>Total Users</h3>
                        <div class="number"><?php echo $total_users; ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon green">
                        <i class="fa-solid fa-car"></i>
                    </div>
                    <div class="stat-details">
                        <h3>Registered Vehicles</h3>
                        <div class="number"><?php echo $total_vehicles; ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon orange">
                        <i class="fa-solid fa-file-lines"></i>
                    </div>
                    <div class="stat-details">
                        <h3>Total Applications</h3>
                        <div class="number"><?php echo $total_applications; ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon purple">
                        <i class="fa-solid fa-dollar-sign"></i>
                    </div>
                    <div class="stat-details">
                        <h3>Total USD Revenue</h3>
                        <div class="number"><?php echo htmlspecialchars(appFormatPaymentAmount($total_usd_payments, 'USD')); ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon orange" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                        <i class="fa-solid fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-details">
                        <h3>Total LRD Revenue</h3>
                        <div class="number"><?php echo htmlspecialchars(appFormatPaymentAmount($total_lrd_payments, 'LRD')); ?></div>
                    </div>
                </div>
            </div>

            <div class="stats-grid" style="grid-template-columns: repeat(2, minmax(0, 1fr)); margin-top: 8px;">
                <div class="stat-card">
                    <div class="stat-icon orange" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                    <div class="stat-details">
                        <h3>Expiring Soon</h3>
                        <div class="number"><?php echo $expiring_soon_count; ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon red">
                        <i class="fa-solid fa-ban"></i>
                    </div>
                    <div class="stat-details">
                        <h3>Expired Plates</h3>
                        <div class="number"><?php echo $expired_plates_count; ?></div>
                    </div>
                </div>
            </div>

            <div class="content-card" style="margin-top: 24px;">
                <h2><i class="fa-solid fa-money-check"></i> Payment Status by Plate</h2>
                <div class="stats-grid" style="grid-template-columns: repeat(2, minmax(0, 1fr)); margin-top: 16px;">
                    <a href="reports.php?payment_status=Paid" class="payment-status-link">
                        <div class="stat-card">
                            <div class="stat-icon green">
                                <i class="fa-solid fa-circle-check"></i>
                            </div>
                            <div class="stat-details">
                                <h3>Plates Paid</h3>
                                <div class="number"><?php echo $paid_plates_count; ?></div>
                                <span class="mini-export-btn"><i class="fa-solid fa-download"></i> Download Paid Plates</span>
                            </div>
                        </div>
                    </a>
                    <a href="reports.php?payment_status=Pending" class="payment-status-link">
                        <div class="stat-card">
                            <div class="stat-icon red">
                                <i class="fa-solid fa-clock"></i>
                            </div>
                            <div class="stat-details">
                                <h3>Plates Pending Payment</h3>
                                <div class="number"><?php echo $pending_plates_count; ?></div>
                                <span class="mini-export-btn"><i class="fa-solid fa-download"></i> Download Pending Plates</span>
                            </div>
                        </div>
                    </a>
                </div>
            </div>

            <?php if ($selected_payment_status !== ''): ?>
                <div class="content-card" style="margin-top: 24px; border-left: 5px solid <?php echo $selected_payment_status === 'PAID' ? '#16a34a' : '#dc2626'; ?>;">
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:18px;">
                        <h2 style="margin:0;">
                            <i class="fa-solid <?php echo $selected_payment_status === 'PAID' ? 'fa-circle-check' : 'fa-clock'; ?>"></i>
                            <?php echo $selected_payment_status === 'PAID' ? 'Paid Plate Records' : 'Pending Payment Plate Records'; ?>
                        </h2>
                        <a href="reports.php?export=<?php echo $selected_payment_status === 'PAID' ? 'paid_plates_csv' : 'pending_plates_csv'; ?>" class="mini-export-btn" style="margin-top:0;"><i class="fa-solid fa-download"></i> Export CSV</a>
                    </div>

                    <?php if ($filtered_payment_plates_result && mysqli_num_rows($filtered_payment_plates_result) > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Plate Number</th>
                                    <th>Owner</th>
                                    <th>Contact</th>
                                    <th>Vehicle Type</th>
                                    <th>Category</th>
                                    <th>County</th>
                                    <th>Status</th>
                                    <th>Payment Status</th>
                                    <th>Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($plate = mysqli_fetch_assoc($filtered_payment_plates_result)): ?>
                                    <tr>
                                        <td>#<?php echo (int) $plate['id']; ?></td>
                                        <td><?php echo htmlspecialchars($plate['plate_number'] ?? '—'); ?></td>
                                        <td><?php echo htmlspecialchars($plate['owner_name'] ?? '—'); ?></td>
                                        <td><?php echo htmlspecialchars($plate['owner_contact'] ?? '—'); ?></td>
                                        <td><?php echo htmlspecialchars($plate['vehicle_type'] ?? '—'); ?></td>
                                        <td><?php echo htmlspecialchars($plate['official_vehicle_category'] ?? '—'); ?></td>
                                        <td><?php echo htmlspecialchars($plate['county'] ?? '—'); ?></td>
                                        <td><?php echo htmlspecialchars($plate['status'] ?? '—'); ?></td>
                                        <td><span style="background: <?php echo $plate['payment_status'] === 'Paid' ? '#dcfce7' : '#fee2e2'; ?>; color: <?php echo $plate['payment_status'] === 'Paid' ? '#166534' : '#991b1b'; ?>; padding:4px 10px; border-radius:20px; font-size:12px; font-weight:600;">
                                            <?php echo htmlspecialchars($plate['payment_status'] ?? 'Pending'); ?>
                                        </span></td>
                                        <td><?php echo htmlspecialchars(date('M d, Y', strtotime($plate['created_at']))); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p style="margin: 12px 0 0; color: #64748b;">No records found for this payment status.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Applications Status Report -->
            <div class="content-card">
                <h2><i class="fa-solid fa-chart-pie"></i> Application Status Summary</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th>Count</th>
                            <th>Percentage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><span style="background:#dcfce7; color:#166534; padding:4px 12px; border-radius:20px; font-size:12px; font-weight:600;">Approved</span></td>
                            <td><?php echo $approved_applications; ?></td>
                            <td><?php echo $total_applications > 0 ? round(($approved_applications / $total_applications) * 100, 1) : 0; ?>%</td>
                        </tr>
                        <tr>
                            <td><span style="background:#fef3c7; color:#92400e; padding:4px 12px; border-radius:20px; font-size:12px; font-weight:600;">Pending</span></td>
                            <td><?php echo $pending_applications; ?></td>
                            <td><?php echo $total_applications > 0 ? round(($pending_applications / $total_applications) * 100, 1) : 0; ?>%</td>
                        </tr>
                        <tr>
                            <td><span style="background:#fee2e2; color:#991b1b; padding:4px 12px; border-radius:20px; font-size:12px; font-weight:600;">Rejected</span></td>
                            <td><?php echo $rejected_applications; ?></td>
                            <td><?php echo $total_applications > 0 ? round(($rejected_applications / $total_applications) * 100, 1) : 0; ?>%</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Applications by County -->
            <div class="content-card">
                <h2><i class="fa-solid fa-location-dot"></i> Applications by County</h2>
                <table>
                    <thead>
                        <tr>
                            <th>County</th>
                            <th>Number of Applications</th>
                            <th>Percentage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($county = mysqli_fetch_assoc($counties_result)): ?>
                        <tr>
                            <td><?php echo $county['county']; ?></td>
                            <td><?php echo $county['count']; ?></td>
                            <td><?php echo $total_applications > 0 ? round(($county['count'] / $total_applications) * 100, 1) : 0; ?>%</td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <div class="content-card">
                <h2><i class="fa-solid fa-list"></i> Recent Applications</h2>
                <table>
                    <thead>
                        <tr>
                            <th>App No.</th>
                            <th>Applicant</th>
                            <th>County</th>
                            <th>Plate</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recent_applications_query && mysqli_num_rows($recent_applications_query) > 0): ?>
                            <?php while ($recent = mysqli_fetch_assoc($recent_applications_query)): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($recent['application_number'] ?? '—'); ?></td>
                                    <td><?php echo htmlspecialchars($recent['applicant_name'] ?? '—'); ?></td>
                                    <td><?php echo htmlspecialchars($recent['county'] ?? '—'); ?></td>
                                    <td><?php echo htmlspecialchars($recent['license_plate_number'] ?: ($recent['vehicle_plate'] ?: '—')); ?></td>
                                    <td>
                                        <span style="background: <?php echo $recent['status'] === 'Approved' ? '#dcfce7' : ($recent['status'] === 'Rejected' ? '#fee2e2' : '#fef3c7'); ?>; color: <?php echo $recent['status'] === 'Approved' ? '#166534' : ($recent['status'] === 'Rejected' ? '#991b1b' : '#92400e'); ?>; padding:4px 10px; border-radius:20px; font-size:12px; font-weight:600;">
                                            <?php echo htmlspecialchars($recent['status'] ?? 'Pending'); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($recent['application_date'] ? date('M d, Y', strtotime($recent['application_date'])) : '—'); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align:center; color:#64748b; padding:24px;">No applications match the current filter.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
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
