<?php
session_start();
include "staff_auth.php";
include "config.php";
include "partials/admin-toast.php";
include "partials/admin-notifications.php";

$unread_admin_notifications = admin_get_unread_notification_count($conn);

if (isset($_GET['export']) && $_GET['export'] === 'payments_csv') {
    $csv_result = mysqli_query($conn, "SELECT p.id, p.receipt_number, a.application_number, a.applicant_name, p.amount, p.payment_currency, p.payment_method, p.status, p.payment_date FROM payments p LEFT JOIN applications a ON p.application_id = a.id ORDER BY p.payment_date DESC");
    $filename = 'payments-export-' . date('Ymd-His') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Receipt Number', 'Application Number', 'Applicant Name', 'Amount', 'Currency', 'Payment Method', 'Status', 'Payment Date']);

    if ($csv_result) {
        while ($row = mysqli_fetch_assoc($csv_result)) {
            fputcsv($output, [
                $row['id'],
                $row['receipt_number'],
                $row['application_number'],
                $row['applicant_name'],
                appFormatPaymentAmount($row['amount'], $row['payment_currency']),
                $row['payment_currency'] ?: 'USD',
                $row['payment_method'],
                $row['status'],
                $row['payment_date']
            ]);
        }
    }

    fclose($output);
    exit();
}

// Get all payments
$payments_query = mysqli_query($conn, "SELECT p.*, 
    COALESCE(a.application_number, vapp.application_number) AS application_number,
    COALESCE(a.applicant_name, vapp.applicant_name) AS applicant_name,
    COALESCE(p.payment_currency, 'USD') AS payment_currency
    FROM payments p
    LEFT JOIN applications a ON a.id = p.application_id
    LEFT JOIN vehicles v ON v.id = p.vehicle_id
    LEFT JOIN applications vapp ON vapp.vehicle_id = v.id AND vapp.application_type = 'Existing Plate'
    ORDER BY p.payment_date DESC");

$payments_export_link = 'payments.php?export=payments_csv';
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
    <title>Payments - Transport Union Authority</title>
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

        .header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .action-btn {
            text-decoration: none;
            padding: 10px 14px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 1px solid transparent;
            transition: all 0.25s ease;
        }

        .action-btn.download {
            background: linear-gradient(135deg, #0f766e 0%, #0f766e 45%, #115e59 100%);
            color: white;
            box-shadow: 0 6px 16px rgba(15, 118, 110, 0.25);
        }

        .action-btn.download:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 22px rgba(15, 118, 110, 0.3);
        }

        .action-btn.refresh {
            background: white;
            border-color: #cbd5e1;
            color: #0a2463;
        }

        .action-btn.refresh:hover {
            background: #f8fafc;
        }

        /* Page Content */
        .page-content {
            padding: 35px 40px;
        }

        .content-card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
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
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #0a2463 0%, #0f3460 100%);
            color: white;
        }

        .btn-primary:hover {
            opacity: 0.92;
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

        .status-paid {
            background: #dcfce7;
            color: #166534;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
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

        @media (max-width: 980px) {
            .top-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 14px;
            }

            .user-info {
                flex-wrap: wrap;
            }

            .page-content {
                padding: 24px 20px;
            }
        }

        @media (max-width: 720px) {
            .main-content {
                margin-left: 0;
            }

            .sidebar {
                position: static;
                width: 100%;
                height: auto;
            }

            .content-card {
                padding: 20px;
                overflow-x: auto;
            }

            .card-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }

            table {
                min-width: 980px;
            }
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
                <h1><i class="fa-solid fa-credit-card"></i> Payment Records</h1>
            </div>
            <div class="user-info">
                <div class="header-actions">
                    <a href="<?php echo htmlspecialchars($payments_export_link); ?>" class="action-btn download">
                        <i class="fa-solid fa-file-csv"></i> Download CSV
                    </a>
                    <a href="payments.php" class="action-btn refresh">
                        <i class="fa-solid fa-rotate"></i> Refresh
                    </a>
                </div>
                <span class="role"><?php echo $_SESSION['role']; ?></span>
                <span class="name"><?php echo $_SESSION['fullname']; ?></span>
                <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
            </div>
        </div>

        <!-- Page Content -->
        <div class="page-content">
            <div class="content-card">
                <div class="card-header">
                    <h2><i class="fa-solid fa-money-bill-transfer"></i> All Payments</h2>
                    <div class="header-actions">
                        <a href="<?php echo htmlspecialchars($payments_export_link); ?>" class="action-btn download"><i class="fa-solid fa-download"></i> Export List</a>
                        <a href="add_payment.php" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Record New Payment</a>
                    </div>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Receipt #</th>
                            <th>Application</th>
                            <th>Applicant</th>
                            <th>Amount</th>
                            <th>Currency</th>
                            <th>Method</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($payment = mysqli_fetch_assoc($payments_query)): ?>
                        <tr>
                            <td>#<?php echo $payment['id']; ?></td>
                            <td><strong><?php echo $payment['receipt_number']; ?></strong></td>
                            <td><?php echo $payment['application_number'] ? $payment['application_number'] : '<span style="color:#94a3b8">—</span>'; ?></td>
                            <td><?php echo $payment['applicant_name'] ? $payment['applicant_name'] : '<span style="color:#94a3b8">—</span>'; ?></td>
                            <td><?php echo htmlspecialchars(appFormatPaymentAmount($payment['amount'], $payment['payment_currency'] ?? 'USD')); ?></td>
                            <td><?php echo htmlspecialchars(appNormalizePaymentCurrency($payment['payment_currency'] ?? 'USD')); ?></td>
                            <td><?php echo $payment['payment_method']; ?></td>
                            <td><span class="status-<?php echo strtolower($payment['status']); ?>"><?php echo $payment['status']; ?></span></td>
                            <td><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></td>
                            <td><a href="receipt.php?payment_id=<?php echo $payment['id']; ?>" class="btn btn-primary" target="_blank" style="padding:8px 12px; font-size:12px; border-radius:8px;"><i class="fa-solid fa-print"></i> Receipt</a></td>
                        </tr>
                        <?php endwhile; ?>
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
