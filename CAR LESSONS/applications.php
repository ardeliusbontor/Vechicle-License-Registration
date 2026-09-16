<?php
session_start();
include "staff_auth.php";
include "config.php";
include "partials/admin-toast.php";
include "partials/admin-notifications.php";

$unread_admin_notifications = admin_get_unread_notification_count($conn);

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

if (!function_exists('normalizePlateNumber')) {
    function normalizePlateNumber($plate) {
        $normalized = strtoupper(trim((string) $plate));
        $normalized = preg_replace('/\s+/', '', $normalized);
        $normalized = preg_replace('/[^A-Z0-9]/', '', $normalized);

        return $normalized;
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $application_id = mysqli_real_escape_string($conn, $_POST['application_id'] ?? 0);
    $action = mysqli_real_escape_string($conn, $_POST['action'] ?? '');
    $processed_by = $_SESSION['id'];

    if ($action == 'approve') {
        $application_info = mysqli_query($conn, "SELECT * FROM applications WHERE id = '$application_id' LIMIT 1");
        $application_data = $application_info && mysqli_num_rows($application_info) > 0 ? mysqli_fetch_assoc($application_info) : null;
        $update_query = "UPDATE applications SET status = 'Approved', processed_by = '$processed_by', processed_at = NOW() WHERE id = '$application_id'";

        if (mysqli_query($conn, $update_query)) {
            if ($application_data && (($application_data['application_type'] ?? 'New') === 'Renewal') && !empty($application_data['renewal_for_plate_number'])) {
                $renewal_plate = normalizePlateNumber($application_data['renewal_for_plate_number']);
                $renewal_vehicle = mysqli_query($conn, "SELECT * FROM vehicles WHERE UPPER(TRIM(plate_number)) = '$renewal_plate' LIMIT 1");
                if ($renewal_vehicle && mysqli_num_rows($renewal_vehicle) > 0) {
                    $renewal_vehicle_data = mysqli_fetch_assoc($renewal_vehicle);
                    $new_expiry = date('Y-m-d', strtotime('+3 years'));
                    mysqli_query($conn, "UPDATE vehicles SET status = 'Active', expiry_date = '$new_expiry' WHERE id = '{$renewal_vehicle_data['id']}'");
                    mysqli_query($conn, "INSERT INTO plate_status_history (vehicle_id, previous_status, new_status, reason, changed_by, changed_by_role) VALUES ('{$renewal_vehicle_data['id']}', '{$renewal_vehicle_data['status']}', 'Active', 'Renewal approved by admin', '$processed_by', '{$_SESSION['role']}')");
                    mysqli_query($conn, "UPDATE applications SET expires_at = '$new_expiry', issued_at = NOW() WHERE id = '$application_id'");
                }
            }
            $message = "Application approved successfully!";
            $message_type = 'success';
        }
    } elseif ($action == 'reject') {
        $update_query = "UPDATE applications SET status = 'Rejected', processed_by = '$processed_by', processed_at = NOW() WHERE id = '$application_id'";
        if (mysqli_query($conn, $update_query)) {
            $message = "Application rejected successfully!";
            $message_type = 'success';
        }
    } elseif ($action == 'delete') {
        mysqli_begin_transaction($conn);

        try {
            $application_info = mysqli_query($conn, "SELECT vehicle_id FROM applications WHERE id = '$application_id' LIMIT 1");
            $application_data = $application_info && mysqli_num_rows($application_info) > 0 ? mysqli_fetch_assoc($application_info) : null;
            $linked_vehicle_id = $application_data['vehicle_id'] ?? null;

            $delete_payments = mysqli_query($conn, "DELETE FROM payments WHERE application_id = '$application_id'");
            if (!$delete_payments) {
                throw new Exception('Unable to delete linked payments.');
            }

            if (!empty($linked_vehicle_id)) {
                $other_applications = mysqli_query($conn, "SELECT id FROM applications WHERE vehicle_id = '$linked_vehicle_id' AND id != '$application_id' LIMIT 1");
                if (!$other_applications || mysqli_num_rows($other_applications) === 0) {
                    mysqli_query($conn, "DELETE FROM plate_status_history WHERE vehicle_id = '$linked_vehicle_id'");
                    $delete_vehicle = mysqli_query($conn, "DELETE FROM vehicles WHERE id = '$linked_vehicle_id'");
                    if (!$delete_vehicle) {
                        throw new Exception('Unable to remove linked vehicle from the client dashboard.');
                    }
                }
            }

            $delete_query = mysqli_query($conn, "DELETE FROM applications WHERE id = '$application_id'");
            if (!$delete_query) {
                throw new Exception('Unable to delete application.');
            }

            mysqli_commit($conn);
            $message = "Application deleted successfully!";
            $message_type = 'success';
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $message = "Unable to delete application right now.";
            $message_type = 'danger';
        }
    } elseif ($action == 'save_edit') {
        $applicant_name = mysqli_real_escape_string($conn, $_POST['applicant_name'] ?? '');
        $applicant_contact = mysqli_real_escape_string($conn, $_POST['applicant_contact'] ?? '');
        $applicant_address = mysqli_real_escape_string($conn, $_POST['applicant_address'] ?? '');
        $county = mysqli_real_escape_string($conn, $_POST['county'] ?? '');
        $status = mysqli_real_escape_string($conn, $_POST['status'] ?? 'Pending');
        $license_plate_number = mysqli_real_escape_string($conn, $_POST['license_plate_number'] ?? '');
        $issued_at = mysqli_real_escape_string($conn, $_POST['issued_at'] ?? '');
        $expires_at = mysqli_real_escape_string($conn, $_POST['expires_at'] ?? '');

        $update_query = "UPDATE applications SET applicant_name = '$applicant_name', applicant_contact = '$applicant_contact', applicant_address = '$applicant_address', county = '$county', status = '$status', license_plate_number = '$license_plate_number', issued_at = '$issued_at', expires_at = '$expires_at' WHERE id = '$application_id'";
        if (mysqli_query($conn, $update_query)) {
            $message = "Application updated successfully!";
            $message_type = 'success';
        } else {
            $message = "Error: " . mysqli_error($conn);
            $message_type = 'danger';
        }
    }

    if ($message) {
        admin_toast_add($message, $message_type);
        header("Location: applications.php");
        exit();
    }
}

$view_application = null;
if (isset($_GET['view_id']) && $_GET['view_id'] != '') {
    $view_id = mysqli_real_escape_string($conn, $_GET['view_id']);
    $view_result = mysqli_query($conn, "SELECT a.*, v.plate_number as vehicle_plate FROM applications a LEFT JOIN vehicles v ON a.vehicle_id = v.id WHERE a.id = '$view_id' LIMIT 1");
    if ($view_result && mysqli_num_rows($view_result) > 0) {
        $view_application = mysqli_fetch_assoc($view_result);
    }
}

$edit_application = null;
if (isset($_GET['edit_id']) && $_GET['edit_id'] != '') {
    $edit_id = mysqli_real_escape_string($conn, $_GET['edit_id']);
    $edit_result = mysqli_query($conn, "SELECT * FROM applications WHERE id = '$edit_id' LIMIT 1");
    if ($edit_result && mysqli_num_rows($edit_result) > 0) {
        $edit_application = mysqli_fetch_assoc($edit_result);
    }
}

$status_filter = isset($_GET['status']) ? trim((string) $_GET['status']) : '';
$status_filter_sql = '';
if (in_array($status_filter, ['Pending', 'Approved', 'Rejected', 'Blocked'], true)) {
    $status_filter_sql = " WHERE a.status = '" . mysqli_real_escape_string($conn, $status_filter) . "'";
}

// Get all applications
$applications_query = mysqli_query($conn, "SELECT a.*, v.plate_number as vehicle_plate, p.email as applicant_email FROM applications a LEFT JOIN vehicles v ON a.vehicle_id = v.id LEFT JOIN applicants p ON p.id = a.applicant_id" . $status_filter_sql . " ORDER BY a.application_date DESC");

$county_stats = mysqli_query($conn, "SELECT county, COUNT(*) as total FROM applications GROUP BY county ORDER BY total DESC");
$pending_by_county = mysqli_query($conn, "SELECT county, COUNT(*) as total FROM applications WHERE status = 'Pending' GROUP BY county ORDER BY total DESC");
$rejected_by_county = mysqli_query($conn, "SELECT county, COUNT(*) as total FROM applications WHERE status = 'Rejected' GROUP BY county ORDER BY total DESC");

$total_pending = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM applications WHERE status='Pending'"))['count'];
$total_approved = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM applications WHERE status='Approved'"))['count'];
$total_rejected = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM applications WHERE status='Rejected'"))['count'];

$all_plates_for_status = mysqli_query($conn, "SELECT plate_number AS plate_value, status, expiry_date FROM vehicles WHERE plate_number IS NOT NULL AND plate_number <> '' UNION ALL SELECT license_plate_number AS plate_value, status, expires_at AS expiry_date FROM applications WHERE license_plate_number IS NOT NULL AND license_plate_number <> ''");
$active_plates = 0;
$expired_plates = 0;
if ($all_plates_for_status) {
    while ($plate_status_row = mysqli_fetch_assoc($all_plates_for_status)) {
        $effective_status = appEffectivePlateStatus($plate_status_row['status'] ?? 'Pending', $plate_status_row['expiry_date'] ?? null);
        if ($effective_status === 'Expired') {
            $expired_plates++;
        } elseif (in_array($effective_status, ['Active', 'Approved'], true)) {
            $active_plates++;
        }
    }
}

$page_toasts = admin_toast_pull();
$notification_ids_to_mark = [];
foreach (admin_collect_unread_notification_toasts($conn, 6) as $notification_toast) {
    $notification_ids_to_mark[] = $notification_toast['id'];
    $page_toasts[] = [
        'message' => $notification_toast['message'],
        'type' => $notification_toast['type']
    ];
}
admin_mark_notifications_read($conn, $notification_ids_to_mark);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applications - Transport Union Authority</title>
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
            background: linear-gradient(180deg, #081a3d 0%, #0e2d5f 35%, #133d7a 100%);
            color: white;
            padding: 25px 0 18px;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 8px 0 24px rgba(8, 16, 35, 0.18);
            border-right: 1px solid rgba(255,255,255,0.12);
        }

        .sidebar-header {
            padding: 0 25px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.15);
            margin-bottom: 18px;
        }

        .sidebar-header img {
            height: 76px;
            margin-bottom: 14px;
            filter: drop-shadow(0 8px 18px rgba(0,0,0,0.18));
        }

        .sidebar-header h2 {
            font-size: 16px;
            font-weight: 800;
            margin-bottom: 5px;
            letter-spacing: 0.08em;
        }

        .sidebar-header p {
            font-size: 10px;
            opacity: 0.82;
            letter-spacing: 0.16em;
            text-transform: uppercase;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0 15px;
        }

        .sidebar-menu li {
            margin-bottom: 6px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            color: rgba(255,255,255,0.92);
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.25s ease;
            font-weight: 600;
            border: 1px solid transparent;
        }

        .sidebar-menu a:hover {
            background: rgba(255,255,255,0.08);
            border-color: rgba(255,255,255,0.08);
            transform: translateX(2px);
        }

        .sidebar-menu a.active {
            background: linear-gradient(135deg, rgba(255,255,255,0.14) 0%, rgba(59,130,246,0.18) 100%);
            border-color: rgba(255,255,255,0.12);
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.12);
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
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            padding: 18px 40px;
            box-shadow: 0 2px 12px rgba(15, 23, 42, 0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 18px;
            border-bottom: 1px solid #e2e8f0;
        }

        .page-title h1 {
            color: #0a2463;
            font-size: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 800;
        }

        .user-info {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 14px;
            flex-wrap: wrap;
        }

        .admin-user-panel {
            display: flex;
            align-items: center;
            gap: 10px;
            background: linear-gradient(135deg, #f8fafc 0%, #eef4ff 100%);
            border: 1px solid #dfe8f4;
            border-radius: 12px;
            padding: 8px 12px;
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.7);
        }

        .user-info .role {
            background: linear-gradient(135deg, #c41e3a 0%, #a01830 100%);
            color: white;
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .user-info .name {
            color: #0a2463;
            font-weight: 700;
            font-size: 14px;
        }

        .user-info a {
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            color: #c41e3a;
            padding: 10px 16px;
            border-radius: 10px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 700;
            transition: all 0.25s ease;
            border: 1px solid #dfe8f4;
        }

        .user-info a:hover {
            background: linear-gradient(135deg, #e2e8f0 0%, #dbeafe 100%);
            transform: translateY(-1px);
        }

        /* Page Content */
        .page-content {
            padding: 35px 40px;
        }

        .content-card {
            background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.06);
            border: 1px solid #e2e8f0;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f1f5f9;
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
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
        }

        .btn-success {
            background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
            color: white;
            padding: 7px 14px;
            font-size: 13px;
            box-shadow: 0 8px 18px rgba(22, 163, 74, 0.2);
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            color: white;
            padding: 7px 14px;
            font-size: 13px;
            box-shadow: 0 8px 18px rgba(220, 38, 38, 0.2);
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

        .status-pending {
            background: #fef3c7;
            color: #92400e;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .status-approved {
            background: #dcfce7;
            color: #166534;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .status-rejected {
            background: #fee2e2;
            color: #991b1b;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .btn-sm {
            padding: 7px 12px;
            font-size: 12px;
        }

        .btn-info {
            background: #2563eb;
            color: white;
        }

        .btn-secondary {
            background: #64748b;
            color: white;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }

        .stats-card {
            background: linear-gradient(135deg, #f8fafc 0%, #eef2ff 100%);
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 16px;
        }

        .stats-card h4 {
            color: #64748b;
            font-size: 13px;
            margin-bottom: 8px;
        }

        .stats-card .value {
            font-size: 26px;
            font-weight: 800;
            color: #0f172a;
        }

        .panel-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 24px;
        }

        .mini-card {
            background: #f8fafc;
            border-radius: 14px;
            padding: 18px;
            border: 1px solid #e2e8f0;
        }

        .mini-card h3 {
            color: #0a2463;
            margin-bottom: 12px;
            font-size: 16px;
        }

        .mini-card ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .mini-card li {
            display: flex;
            justify-content: space-between;
            padding: 7px 0;
            border-bottom: 1px solid #e2e8f0;
            color: #334155;
            font-size: 14px;
        }

        .bar {
            background: #cbd5e1;
            border-radius: 999px;
            height: 8px;
            overflow: hidden;
            margin-top: 4px;
        }

        .bar span {
            display: block;
            height: 100%;
            background: linear-gradient(90deg, #c41e3a 0%, #0a2463 100%);
        }

        .view-box, .edit-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 18px;
            margin-top: 20px;
        }

        .view-box p, .edit-box p {
            margin-bottom: 8px;
            color: #334155;
        }

        .edit-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        .edit-grid input, .edit-grid select, .edit-grid textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
        }

        .edit-grid textarea {
            grid-column: 1 / -1;
            min-height: 90px;
        }

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
            <?php if (($_SESSION['role'] ?? '') === 'Admin'): ?>
                <li><a href="users.php"><i class="fa-solid fa-users"></i> Users Management</a></li>
            <?php endif; ?>
            <li><a href="vehicles.php"><i class="fa-solid fa-car"></i> Vehicles</a></li>
            <li><a href="applications.php" class="active"><i class="fa-solid fa-file-lines"></i> Applications<?php if ($unread_admin_notifications > 0): ?><span style="margin-left:8px;background:#c41e3a;color:#fff;border-radius:999px;padding:2px 8px;font-size:11px;font-weight:700;"><?php echo $unread_admin_notifications; ?></span><?php endif; ?></a></li>
            <li><a href="messages.php"><i class="fa-solid fa-envelope-open-text"></i> Messages<?php if (admin_get_unread_contact_message_count($conn) > 0): ?><span style="margin-left:8px;background:#c41e3a;color:#fff;border-radius:999px;padding:2px 8px;font-size:11px;font-weight:700;"><?php echo admin_get_unread_contact_message_count($conn); ?></span><?php endif; ?></a></li>
            <li><a href="payments.php"><i class="fa-solid fa-credit-card"></i> Payments</a></li>
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
                <h1><i class="fa-solid fa-file-lines"></i> License Plate Applications</h1>
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
                    <h2><i class="fa-solid fa-list-check"></i> All Applications</h2>
                </div>

                <div class="stats-grid">
                    <div class="stats-card">
                        <h4>Pending</h4>
                        <div class="value"><?php echo $total_pending; ?></div>
                    </div>
                    <div class="stats-card">
                        <h4>Approved</h4>
                        <div class="value"><?php echo $total_approved; ?></div>
                    </div>
                    <div class="stats-card">
                        <h4>Rejected</h4>
                        <div class="value"><?php echo $total_rejected; ?></div>
                    </div>
                    <div class="stats-card">
                        <h4>Active Plates</h4>
                        <div class="value"><?php echo $active_plates; ?></div>
                    </div>
                </div>

                <div class="panel-grid">
                    <div class="mini-card">
                        <h3><i class="fa-solid fa-chart-column"></i> Applications by County</h3>
                        <ul>
                            <?php while ($stat = mysqli_fetch_assoc($county_stats)): ?>
                            <li>
                                <span><?php echo htmlspecialchars($stat['county'] ?: 'Unknown'); ?></span>
                                <strong><?php echo $stat['total']; ?></strong>
                            </li>
                            <?php endwhile; ?>
                        </ul>
                    </div>
                    <div class="mini-card">
                        <h3><i class="fa-solid fa-clipboard-list"></i> Status Summary</h3>
                        <ul>
                            <li><span>Pending by County</span><strong><?php echo $total_pending; ?></strong></li>
                            <li><span>Rejected by County</span><strong><?php echo $total_rejected; ?></strong></li>
                            <li><span>Expired Plates</span><strong><?php echo $expired_plates; ?></strong></li>
                        </ul>
                    </div>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>App. Number</th>
                            <th>License Plate</th>
                            <th>County</th>
                            <th>Applicant</th>
                            <th>Contact</th>
                            <th>Email</th>
                            <th>Vehicle</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Issued / Expires</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($app = mysqli_fetch_assoc($applications_query)): ?>
                        <tr>
                            <td>#<?php echo $app['id']; ?></td>
                            <td><strong><?php echo $app['application_number']; ?></strong></td>
                            <td><?php echo $app['license_plate_number'] ?: '<span style="color:#94a3b8">Pending</span>'; ?></td>
                            <td><?php echo $app['county']; ?></td>
                            <td><?php echo htmlspecialchars($app['applicant_name']); ?></td>
                            <td><?php echo htmlspecialchars($app['applicant_contact'] ?: '—'); ?></td>
                            <td><?php echo htmlspecialchars($app['applicant_email'] ?: ($app['email'] ?? '—')); ?></td>
                            <td><?php echo $app['vehicle_plate'] ? $app['vehicle_plate'] : '<span style="color:#94a3b8">—</span>'; ?></td>
                            <td>
                                <?php
                                    $app_type = strtoupper((string) ($app['application_type'] ?? 'New'));
                                    $type_label = 'New';
                                    if ($app_type === 'RENEWAL') {
                                        $type_label = 'Renewal';
                                    } elseif ($app_type === 'EXISTING PLATE') {
                                        $type_label = 'Existing Plate';
                                    }
                                ?>
                                <span class="status-<?php echo strtolower($app_type === 'RENEWAL' ? 'pending' : 'approved'); ?>"><?php echo htmlspecialchars($type_label); ?></span>
                            </td>
                            <td>
                                <?php
                                    $plate_label = $app['status'];
                                    $plate_status_class = strtolower($app['status']);
                                    if ($app['status'] == 'Approved' && !empty($app['license_plate_number'])) {
                                        $plate_label = 'Plate Accepted';
                                        $plate_status_class = 'approved';
                                    } elseif ($app['status'] == 'Rejected') {
                                        $plate_label = 'Plate Rejected';
                                        $plate_status_class = 'rejected';
                                    } elseif ($app['status'] == 'Pending' && !empty($app['license_plate_number'])) {
                                        $plate_label = 'Plate Pending';
                                        $plate_status_class = 'pending';
                                    }
                                ?>
                                <span class="status-<?php echo $plate_status_class; ?>"><?php echo htmlspecialchars($plate_label); ?></span>
                            </td>
                            <td><?php echo ($app['issued_at'] ? date('M d, Y', strtotime($app['issued_at'])) : '—') . ' / ' . ($app['expires_at'] ? date('M d, Y', strtotime($app['expires_at'])) : '—'); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="applications.php?view_id=<?php echo $app['id']; ?>" class="btn btn-info btn-sm"><i class="fa-solid fa-eye"></i> View</a>
                                    <a href="applications.php?edit_id=<?php echo $app['id']; ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-edit"></i> Edit</a>
                                    <form method="POST" action="" style="display: inline;">
                                        <input type="hidden" name="application_id" value="<?php echo $app['id']; ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Delete this application?')"><i class="fa-solid fa-trash"></i> Delete</button>
                                    </form>
                                    <?php if ($app['status'] == 'Pending'): ?>
                                        <form method="POST" action="" style="display: inline;">
                                            <input type="hidden" name="application_id" value="<?php echo $app['id']; ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Approve this application?')"><i class="fa-solid fa-check"></i> Approve</button>
                                        </form>
                                        <form method="POST" action="" style="display: inline;">
                                            <input type="hidden" name="application_id" value="<?php echo $app['id']; ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Reject this application?')"><i class="fa-solid fa-xmark"></i> Reject</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>

                <?php if ($view_application): ?>
                <div class="view-box">
                    <h3><i class="fa-solid fa-eye"></i> Application Details</h3>
                    <p><strong>Application Number:</strong> <?php echo htmlspecialchars($view_application['application_number']); ?></p>
                    <p><strong>Applicant:</strong> <?php echo htmlspecialchars($view_application['applicant_name']); ?></p>
                    <p><strong>Contact:</strong> <?php echo htmlspecialchars($view_application['applicant_contact']); ?></p>
                    <p><strong>County:</strong> <?php echo htmlspecialchars($view_application['county']); ?></p>
                    <p><strong>Status:</strong> <?php echo htmlspecialchars($view_application['status']); ?></p>
                    <p><strong>Plate Decision:</strong> <?php
                        if ($view_application['status'] == 'Approved' && !empty($view_application['license_plate_number'])) {
                            echo 'Plate Accepted';
                        } elseif ($view_application['status'] == 'Rejected') {
                            echo 'Plate Rejected';
                        } elseif ($view_application['status'] == 'Pending' && !empty($view_application['license_plate_number'])) {
                            echo 'Plate Pending';
                        } else {
                            echo 'Pending';
                        }
                    ?></p>
                    <p><strong>License Plate:</strong> <?php echo htmlspecialchars($view_application['license_plate_number'] ?: 'Pending'); ?></p>
                    <p><strong>Issued Date:</strong> <?php echo $view_application['issued_at'] ? date('M d, Y', strtotime($view_application['issued_at'])) : '—'; ?></p>
                    <p><strong>Expiry Date:</strong> <?php echo $view_application['expires_at'] ? date('M d, Y', strtotime($view_application['expires_at'])) : '—'; ?></p>
                    <p><strong>Address:</strong> <?php echo htmlspecialchars($view_application['applicant_address']); ?></p>
                </div>
                <?php endif; ?>

                <?php if ($edit_application): ?>
                <div class="edit-box">
                    <h3><i class="fa-solid fa-edit"></i> Edit Application</h3>
                    <form method="POST" action="">
                        <input type="hidden" name="application_id" value="<?php echo $edit_application['id']; ?>">
                        <input type="hidden" name="action" value="save_edit">
                        <div class="edit-grid">
                            <input type="text" name="applicant_name" value="<?php echo htmlspecialchars($edit_application['applicant_name']); ?>" placeholder="Applicant name">
                            <input type="text" name="applicant_contact" value="<?php echo htmlspecialchars($edit_application['applicant_contact']); ?>" placeholder="Contact number">
                            <input type="text" name="county" value="<?php echo htmlspecialchars($edit_application['county']); ?>" placeholder="County">
                            <select name="status">
                                <option value="Pending" <?php echo $edit_application['status'] == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="Approved" <?php echo $edit_application['status'] == 'Approved' ? 'selected' : ''; ?>>Approved</option>
                                <option value="Rejected" <?php echo $edit_application['status'] == 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                            </select>
                            <input type="text" name="license_plate_number" value="<?php echo htmlspecialchars($edit_application['license_plate_number'] ?? ''); ?>" placeholder="License Plate Number">
                            <input type="date" name="issued_at" value="<?php echo $edit_application['issued_at'] ? date('Y-m-d', strtotime($edit_application['issued_at'])) : ''; ?>">
                            <input type="date" name="expires_at" value="<?php echo $edit_application['expires_at'] ? date('Y-m-d', strtotime($edit_application['expires_at'])) : ''; ?>">
                            <textarea name="applicant_address" placeholder="Address"><?php echo htmlspecialchars($edit_application['applicant_address']); ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-success" style="margin-top:12px;"><i class="fa-solid fa-save"></i> Save Changes</button>
                    </form>
                </div>
                <?php endif; ?>
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
