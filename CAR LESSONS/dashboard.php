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

if (isset($_GET['export']) && in_array($_GET['export'], ['applications_csv', 'paid_plates_csv', 'pending_plates_csv'], true)) {
    $export_type = $_GET['export'];

    if ($export_type === 'paid_plates_csv' || $export_type === 'pending_plates_csv') {
        $export_status = $export_type === 'paid_plates_csv' ? 'Paid' : 'Pending';
        $csv_query = "SELECT a.id, v.plate_number, a.applicant_name AS owner_name, a.applicant_contact AS owner_contact, v.vehicle_type, v.official_vehicle_category, a.county, a.status, CASE WHEN EXISTS (SELECT 1 FROM payments p WHERE p.application_id = a.id AND p.status = 'Paid') THEN 'Paid' WHEN a.vehicle_id IS NOT NULL AND a.id = (SELECT a2.id FROM applications a2 WHERE a2.vehicle_id = a.vehicle_id ORDER BY a2.application_date ASC, a2.id ASC LIMIT 1) AND EXISTS (SELECT 1 FROM payments p WHERE p.vehicle_id = a.vehicle_id AND p.status = 'Paid') THEN 'Paid' ELSE 'Pending' END AS payment_status, a.application_date AS created_at FROM applications a LEFT JOIN vehicles v ON v.id = a.vehicle_id WHERE CASE WHEN EXISTS (SELECT 1 FROM payments p WHERE p.application_id = a.id AND p.status = 'Paid') THEN 'Paid' WHEN a.vehicle_id IS NOT NULL AND a.id = (SELECT a2.id FROM applications a2 WHERE a2.vehicle_id = a.vehicle_id ORDER BY a2.application_date ASC, a2.id ASC LIMIT 1) AND EXISTS (SELECT 1 FROM payments p WHERE p.vehicle_id = a.vehicle_id AND p.status = 'Paid') THEN 'Paid' ELSE 'Pending' END = '$export_status' ORDER BY a.application_date DESC";
        $csv_result = mysqli_query($conn, $csv_query);
        $filename = $export_type === 'paid_plates_csv' ? 'dashboard-paid-plates-' . date('Ymd-His') . '.csv' : 'dashboard-pending-plates-' . date('Ymd-His') . '.csv';

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

    $csv_search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
    $csv_status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
    $csv_query = "SELECT a.application_number, a.applicant_name, a.county, a.applicant_contact, a.status, a.license_plate_number, a.application_date, v.plate_number AS vehicle_plate FROM applications a LEFT JOIN vehicles v ON a.vehicle_id = v.id";
    $csv_conditions = [];

    if ($csv_search_term !== '') {
        $csv_safe_search = mysqli_real_escape_string($conn, $csv_search_term);
        $csv_conditions[] = "(a.applicant_name LIKE '%$csv_safe_search%' OR a.application_number LIKE '%$csv_safe_search%' OR a.applicant_contact LIKE '%$csv_safe_search%' OR a.status LIKE '%$csv_safe_search%')";
    }

    if ($csv_status_filter !== '') {
        $csv_safe_status = mysqli_real_escape_string($conn, $csv_status_filter);
        $csv_conditions[] = "a.status = '$csv_safe_status'";
    }

    if (!empty($csv_conditions)) {
        $csv_query .= " WHERE " . implode(' AND ', $csv_conditions);
    }

    $csv_query .= " ORDER BY a.application_date DESC";
    $csv_result = mysqli_query($conn, $csv_query);

    $file_label = $csv_search_term !== '' ? 'filtered' : 'all';
    $filename = "dashboard-applications-{$file_label}-" . date('Ymd-His') . ".csv";

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Application ID', 'Applicant Name', 'County', 'Contact', 'Status', 'Plate Number', 'Vehicle Plate', 'Application Date']);

    if ($csv_result) {
        while ($row = mysqli_fetch_assoc($csv_result)) {
            $csv_status = $row['status'];
            if ($row['status'] === 'Approved' && !empty($row['license_plate_number'])) {
                $csv_status = 'Plate Accepted';
            } elseif ($row['status'] === 'Rejected') {
                $csv_status = 'Plate Rejected';
            } elseif ($row['status'] === 'Pending' && !empty($row['license_plate_number'])) {
                $csv_status = 'Plate Pending';
            }

            fputcsv($output, [
                $row['application_number'],
                $row['applicant_name'],
                $row['county'],
                $row['applicant_contact'],
                $csv_status,
                $row['license_plate_number'] ?: 'Pending',
                $row['vehicle_plate'] ?: '-',
                $row['application_date']
            ]);
        }
    }

    fclose($output);
    exit();
}

// Get statistics
$total_users_result = mysqli_query($conn, "SELECT COUNT(*) as count FROM users");
$total_users_row = mysqli_fetch_assoc($total_users_result);
$total_users = $total_users_row['count'];

$total_vehicles_result = mysqli_query($conn, "SELECT COUNT(*) as count FROM vehicles WHERE status IN ('Active', 'Approved') AND plate_number IS NOT NULL AND TRIM(plate_number) <> ''");
$total_vehicles_row = mysqli_fetch_assoc($total_vehicles_result);
$total_vehicles = (int) ($total_vehicles_row['count'] ?? 0);

$total_applications_result = mysqli_query($conn, "SELECT COUNT(*) as count FROM applications");
$total_applications_row = mysqli_fetch_assoc($total_applications_result);
$total_applications = (int) ($total_applications_row['count'] ?? 0);

$pending_applications_result = mysqli_query($conn, "SELECT COUNT(*) as count FROM applications WHERE status='Pending'");
$pending_applications_row = mysqli_fetch_assoc($pending_applications_result);
$pending_applications = $pending_applications_row['count'];

$approved_applications_result = mysqli_query($conn, "SELECT COUNT(*) as count FROM applications WHERE status='Approved'");
$approved_applications_row = mysqli_fetch_assoc($approved_applications_result);
$approved_applications = $approved_applications_row['count'];

$rejected_applications_result = mysqli_query($conn, "SELECT COUNT(*) as count FROM applications WHERE status='Rejected'");
$rejected_applications_row = mysqli_fetch_assoc($rejected_applications_result);
$rejected_applications = $rejected_applications_row['count'];

$blocked_applications_result = mysqli_query($conn, "SELECT COUNT(*) as count FROM applications WHERE status='Blocked'");
$blocked_applications_row = mysqli_fetch_assoc($blocked_applications_result);
$blocked_applications = $blocked_applications_row['count'];

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
    replied_at DATETIME DEFAULT NULL,
    replied_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$new_messages_result = mysqli_query($conn, "SELECT COUNT(*) as count FROM contact_messages WHERE status='New'");
$new_messages_row = mysqli_fetch_assoc($new_messages_result);
$new_messages = $new_messages_row['count'];

$usd_cash_total = 0;
$usd_card_total = 0;
$usd_mobile_money_total = 0;
$lrd_cash_total = 0;
$lrd_card_total = 0;
$lrd_mobile_money_total = 0;
$usd_total = 0;
$lrd_total = 0;
$payment_stats_result = mysqli_query($conn, "SELECT payment_method, payment_currency, SUM(amount) as total FROM payments GROUP BY payment_method, payment_currency");
while ($payment_row = mysqli_fetch_assoc($payment_stats_result)) {
    $method = (string) ($payment_row['payment_method'] ?? '');
    $currency = appNormalizePaymentCurrency($payment_row['payment_currency'] ?? 'USD');
    $total = (float) $payment_row['total'];

    if ($currency === 'USD') {
        if ($method == 'Cash') {
            $usd_cash_total += $total;
        } elseif ($method == 'Card') {
            $usd_card_total += $total;
        } elseif ($method == 'Mobile Money') {
            $usd_mobile_money_total += $total;
        }
        $usd_total += $total;
    } elseif ($currency === 'LRD') {
        if ($method == 'Cash') {
            $lrd_cash_total += $total;
        } elseif ($method == 'Card') {
            $lrd_card_total += $total;
        } elseif ($method == 'Mobile Money') {
            $lrd_mobile_money_total += $total;
        }
        $lrd_total += $total;
    }
}

$expiry_column_exists = false;
$expiry_check = mysqli_query($conn, "SHOW COLUMNS FROM applications LIKE 'expires_at'");
if ($expiry_check && mysqli_num_rows($expiry_check) > 0) {
    $expiry_column_exists = true;
}

if ($expiry_column_exists) {
    $active_licenses = 0;
    $expired_licenses = 0;
    $expiring_soon_licenses = 0;
    $expiring_soon_plates = [];

    $plate_status_result = mysqli_query($conn, "SELECT id, plate_number, status, expiry_date FROM vehicles WHERE plate_number IS NOT NULL AND plate_number <> ''");
    if ($plate_status_result) {
        while ($plate_row = mysqli_fetch_assoc($plate_status_result)) {
            $effective_status = appEffectivePlateStatus($plate_row['status'] ?? 'Pending', $plate_row['expiry_date'] ?? null);
            $expiry_value = $plate_row['expiry_date'] ?? null;

            if ($effective_status === 'Expired') {
                $expired_licenses++;
            } elseif (in_array($effective_status, ['Active', 'Approved'], true)) {
                $active_licenses++;
            }

            if (!empty($expiry_value) && strtotime((string) $expiry_value) >= time() && strtotime((string) $expiry_value) <= strtotime('+30 days')) {
                $expiring_soon_licenses++;
                $expiring_soon_plates[] = [
                    'license_plate_number' => $plate_row['plate_number'],
                    'expires_at' => $plate_row['expiry_date']
                ];
            }
        }
    }
} else {
    $active_licenses_result = mysqli_query($conn, "SELECT COUNT(*) as count FROM vehicles WHERE plate_number IS NOT NULL AND plate_number <> ''");
    $active_licenses_row = mysqli_fetch_assoc($active_licenses_result);
    $active_licenses = $active_licenses_row['count'];
    $expired_licenses = 0;
    $expiring_soon_licenses = 0;
    $expiring_soon_plates = [];
}

$renewal_pending_count = 0;
$renewal_pending_result = mysqli_query($conn, "SELECT COUNT(*) as count FROM applications WHERE application_type = 'Renewal' AND status = 'Pending'");
if ($renewal_pending_result && mysqli_num_rows($renewal_pending_result) > 0) {
    $renewal_pending_count = (int) mysqli_fetch_assoc($renewal_pending_result)['count'];
}

$search_term = '';
$search_results = null;
if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['search']) && trim($_GET['search']) !== '') {
    $search_term = mysqli_real_escape_string($conn, trim($_GET['search']));
    $search_results = mysqli_query($conn, "SELECT a.*, v.plate_number as vehicle_plate FROM applications a LEFT JOIN vehicles v ON a.vehicle_id = v.id WHERE a.applicant_name LIKE '%$search_term%' OR a.application_number LIKE '%$search_term%' OR a.applicant_contact LIKE '%$search_term%' OR a.status LIKE '%$search_term%' ORDER BY a.application_date DESC");
}

$csv_export_link = 'dashboard.php?export=applications_csv';
if ($search_term !== '') {
    $csv_export_link .= '&search=' . urlencode($search_term);
}

$total_applications_export_link = 'dashboard.php?export=applications_csv';
$pending_applications_export_link = 'dashboard.php?export=applications_csv&status=Pending';
$approved_applications_export_link = 'dashboard.php?export=applications_csv&status=Approved';
$rejected_applications_export_link = 'dashboard.php?export=applications_csv&status=Rejected';
$paid_plates_count = 0;
$pending_plates_count = 0;

$app_payment_summary_query = mysqli_query($conn, "SELECT
    SUM(CASE WHEN effective_payment_status = 'Paid' THEN 1 ELSE 0 END) AS paid_count,
    SUM(CASE WHEN effective_payment_status = 'Pending' THEN 1 ELSE 0 END) AS pending_count
FROM (
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

if ($app_payment_summary_query) {
    $app_payment_summary = mysqli_fetch_assoc($app_payment_summary_query);
    $paid_plates_count = (int) ($app_payment_summary['paid_count'] ?? 0);
    $pending_plates_count = (int) ($app_payment_summary['pending_count'] ?? 0);
}

$paid_plates_export_link = 'dashboard.php?export=paid_plates_csv';
$pending_plates_export_link = 'dashboard.php?export=pending_plates_csv';

$dashboard_alert_count = (int) $unread_admin_notifications + (int) admin_get_unread_contact_message_count($conn);
$dashboard_alerts = [];

if (!empty($expiring_soon_plates)) {
    $dashboard_alerts[] = [
        'title' => 'License Expiry Warning',
        'message' => count($expiring_soon_plates) . ' plate(s) are expiring within the next 30 days. Review renewals now.',
        'link' => 'applications.php',
        'time' => date('Y-m-d H:i:s'),
        'kind' => 'application'
    ];
}

$admin_alert_result = mysqli_query($conn, "SELECT message, event_type, created_at FROM admin_notifications WHERE is_read = 0 ORDER BY created_at DESC LIMIT 5");
if ($admin_alert_result) {
    while ($admin_alert = mysqli_fetch_assoc($admin_alert_result)) {
        $dashboard_alerts[] = [
            'title' => (($admin_alert['event_type'] === 'new_application' || $admin_alert['event_type'] === 'renewal_application') ? 'Application Update' : 'System Alert'),
            'message' => $admin_alert['message'],
            'link' => 'applications.php',
            'time' => $admin_alert['created_at'],
            'kind' => 'application'
        ];
    }
}

$contact_alert_result = mysqli_query($conn, "SELECT id, full_name, subject, created_at FROM contact_messages WHERE is_read = 0 ORDER BY created_at DESC LIMIT 5");
if ($contact_alert_result) {
    while ($contact_alert = mysqli_fetch_assoc($contact_alert_result)) {
        $dashboard_alerts[] = [
            'title' => 'New Message',
            'message' => $contact_alert['full_name'] . ' • ' . $contact_alert['subject'],
            'link' => 'messages.php?id=' . (int) $contact_alert['id'],
            'time' => $contact_alert['created_at'],
            'kind' => 'message'
        ];
    }
}

usort($dashboard_alerts, function ($left, $right) {
    return strtotime($right['time']) <=> strtotime($left['time']);
});
$dashboard_alerts = array_slice($dashboard_alerts, 0, 8);

$page_toasts = admin_toast_pull();
foreach (admin_collect_unread_notification_toasts($conn, 6) as $notification_toast) {
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
    <title>Dashboard - Transport Union Authority</title>
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

        .sidebar::-webkit-scrollbar {
            width: 8px;
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.2);
            border-radius: 10px;
        }

        .sidebar-header {
            padding: 0 25px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.15);
            margin-bottom: 18px;
        }

        .sidebar-header h2 {
            letter-spacing: 0.08em;
            text-shadow: 0 2px 12px rgba(255,255,255,0.12);
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
            background: rgba(255,255,255,0.01);
        }

        .sidebar-menu a:hover {
            background: rgba(255,255,255,0.08);
            border-color: rgba(255,255,255,0.08);
            transform: translateX(2px);
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.08);
        }

        .sidebar-menu a.active {
            background: linear-gradient(135deg, rgba(255,255,255,0.14) 0%, rgba(59,130,246,0.18) 100%);
            border-color: rgba(255,255,255,0.12);
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.12), 0 10px 20px rgba(15, 118, 110, 0.12);
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
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .page-title h1 {
            color: #0a2463;
            font-size: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 800;
        }

        .header-meta {
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }

        .live-clock {
            position: relative;
            background: linear-gradient(135deg, #081a3d 0%, #143b8f 45%, #1d4ed8 100%);
            color: white;
            border-radius: 14px;
            padding: 10px 18px 9px;
            min-width: 255px;
            box-shadow: 0 16px 26px rgba(10, 36, 99, 0.22), inset 0 1px 0 rgba(255,255,255,0.18);
            text-align: center;
            overflow: hidden;
            animation: livePulse 2s ease-in-out infinite;
            border: 1px solid rgba(255,255,255,0.14);
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .live-clock::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg, rgba(255,255,255,0.14), rgba(255,255,255,0), rgba(255,255,255,0.08));
            animation: liveShimmer 3s linear infinite;
        }

        .live-clock::after {
            content: "";
            position: absolute;
            top: 11px;
            right: 14px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #4ade80;
            box-shadow: 0 0 0 4px rgba(74, 222, 128, 0.18);
            animation: liveDot 1.5s ease-in-out infinite;
        }

        @keyframes livePulse {
            0%, 100% { transform: translateY(0); box-shadow: 0 16px 26px rgba(10, 36, 99, 0.22), inset 0 1px 0 rgba(255,255,255,0.18); }
            50% { transform: translateY(-1px); box-shadow: 0 18px 30px rgba(10, 36, 99, 0.26), inset 0 1px 0 rgba(255,255,255,0.18); }
        }

        @keyframes liveShimmer {
            0% { transform: translateX(-120%); }
            100% { transform: translateX(120%); }
        }

        @keyframes liveDot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.7; transform: scale(0.88); }
        }

        .live-clock .date {
            position: relative;
            z-index: 1;
            display: block;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.18em;
            opacity: 0.94;
            white-space: nowrap;
            font-weight: 700;
        }

        .live-clock .time {
            position: relative;
            z-index: 1;
            display: block;
            font-size: 29px;
            font-weight: 800;
            letter-spacing: 0.12em;
            line-height: 1.15;
            text-shadow: 0 0 12px rgba(255,255,255,0.22);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .user-info {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 14px;
            flex-wrap: wrap;
        }

        .alert-menu-wrap {
            position: relative;
        }

        .alert-trigger {
            position: relative;
            width: 46px;
            height: 46px;
            border: 1px solid #dfe8f4;
            border-radius: 14px;
            background: linear-gradient(135deg, #ffffff 0%, #eef4ff 100%);
            color: #0a2463;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.06);
            transition: transform 0.25s ease, box-shadow 0.25s ease;
            animation: alarmPulse 1.8s ease-in-out infinite;
        }

        .alert-trigger:hover,
        .alert-trigger.active {
            transform: translateY(-2px) scale(1.02);
            box-shadow: 0 12px 22px rgba(15, 23, 42, 0.12);
        }

        @keyframes alarmPulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(196, 30, 58, 0.12), 0 8px 18px rgba(15, 23, 42, 0.06); }
            50% { box-shadow: 0 0 0 10px rgba(196, 30, 58, 0.04), 0 10px 20px rgba(15, 23, 42, 0.08); }
        }

        .alert-badge {
            position: absolute;
            top: -6px;
            right: -6px;
            min-width: 22px;
            height: 22px;
            padding: 0 6px;
            border-radius: 999px;
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            font-size: 11px;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid white;
            box-shadow: 0 8px 18px rgba(220, 38, 38, 0.28);
            animation: countBounce 1.6s ease-in-out infinite;
        }

        @keyframes countBounce {
            0%, 100% { transform: scale(1); }
            25% { transform: scale(1.12); }
            50% { transform: scale(0.96); }
            75% { transform: scale(1.08); }
        }

        .alert-panel {
            position: absolute;
            right: 0;
            top: calc(100% + 12px);
            width: min(360px, 86vw);
            background: rgba(255, 255, 255, 0.98);
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            box-shadow: 0 20px 45px rgba(15, 23, 42, 0.18);
            overflow: hidden;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-12px) scale(0.96);
            transition: opacity 0.25s ease, visibility 0.25s ease, transform 0.25s ease;
            z-index: 30;
        }

        .alert-panel.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0) scale(1);
        }

        .alert-panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 16px;
            background: linear-gradient(135deg, #0a2463 0%, #123e80 100%);
            color: white;
            font-weight: 700;
        }

        .alert-panel-list {
            max-height: 360px;
            overflow-y: auto;
        }

        .alert-panel-item {
            display: block;
            padding: 14px 16px;
            text-decoration: none;
            border-bottom: 1px solid #edf2f7;
            color: #0f172a;
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            transition: background 0.2s ease, transform 0.2s ease;
        }

        .alert-panel-item:hover {
            background: #f1f5f9;
            transform: translateX(2px);
        }

        .alert-panel-item:last-child {
            border-bottom: none;
        }

        .alert-item-label {
            display: inline-block;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            padding: 5px 8px;
            border-radius: 999px;
            margin-bottom: 8px;
            background: #e0f2fe;
            color: #075985;
        }

        .alert-item-kind.message .alert-item-label {
            background: #dcfce7;
            color: #166534;
        }

        .alert-item-message {
            display: block;
            font-size: 14px;
            line-height: 1.5;
            color: #0f172a;
            font-weight: 600;
        }

        .alert-item-time {
            display: block;
            margin-top: 8px;
            font-size: 11px;
            color: #64748b;
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

        .header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .search-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            margin: 12px 0 18px;
            flex-wrap: wrap;
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

        /* Dashboard Content */
        .dashboard-content {
            padding: 35px 40px;
            background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
        }

        /* Stat Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 25px;
            margin-bottom: 35px;
        }

        .stat-card-item {
            position: relative;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .stat-card-link {
            display: block;
            text-decoration: none;
            color: inherit;
            border: none;
            outline: none;
        }

        .stat-card {
            position: relative;
            overflow: hidden;
            background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
            padding: 28px;
            border-radius: 18px;
            box-shadow: 0 10px 22px rgba(15, 23, 42, 0.06);
            border: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 22px;
            transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
        }

        .stat-card::before {
            content: "";
            position: absolute;
            inset: 0 auto 0 0;
            width: 6px;
            background: linear-gradient(180deg, #0a2463 0%, #5b72b9 100%);
            border-radius: 18px 0 0 18px;
        }

        .stat-card-link:hover .stat-card,
        .stat-card-link:focus .stat-card {
            transform: translateY(-3px);
            box-shadow: 0 16px 28px rgba(15, 23, 42, 0.09);
            border-color: rgba(10, 36, 99, 0.14);
        }

        .stat-card-export {
            margin-top: 2px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            width: fit-content;
            padding: 7px 12px;
            border: 1px solid rgba(10, 36, 99, 0.12);
            border-radius: 999px;
            background: rgba(10, 36, 99, 0.03);
            color: #0a2463;
            text-decoration: none;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.02em;
            opacity: 0;
            transform: translateY(6px);
            transition: all 0.22s ease;
            box-shadow: 0 6px 14px rgba(15, 23, 42, 0.04);
        }

        .stat-card-item:hover .stat-card-export,
        .stat-card-item:focus-within .stat-card-export {
            opacity: 1;
            transform: translateY(0);
        }

        .stat-card-export:hover,
        .stat-card-export:focus {
            background: linear-gradient(135deg, #0a2463 0%, #0f3460 100%);
            color: #ffffff;
            text-decoration: none;
            box-shadow: 0 10px 18px rgba(10, 36, 99, 0.16);
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
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
        }

        .stat-icon.orange {
            background: linear-gradient(135deg, #ea580c 0%, #c2410c 100%);
        }

        .stat-icon.red {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
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
            background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
            padding: 30px;
            border-radius: 18px;
            box-shadow: 0 10px 22px rgba(15, 23, 42, 0.06);
            border: 1px solid #e2e8f0;
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

        .content-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-bottom: 18px;
        }

        .content-card-header h2 {
            margin-bottom: 0;
        }

        .search-form {
            display: flex;
            gap: 12px;
            margin-bottom: 18px;
        }

        .search-form input {
            flex: 1;
            padding: 12px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            background: #f8fafc;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .search-form input:focus {
            outline: none;
            border-color: rgba(10, 36, 99, 0.4);
            box-shadow: 0 0 0 4px rgba(10, 36, 99, 0.08);
            background: white;
        }

        .search-form button {
            background: linear-gradient(135deg, #c41e3a 0%, #a01830 100%);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 12px 18px;
            cursor: pointer;
            font-weight: 700;
            box-shadow: 0 8px 18px rgba(196, 30, 58, 0.2);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .search-form button:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 20px rgba(196, 30, 58, 0.25);
        }

        .search-results-summary {
            color: #64748b;
            margin-bottom: 16px;
        }

        .results-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }

        .results-table th, .results-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
        }

        .summary-card {
            background: linear-gradient(180deg, #f8fafc 0%, #eef4ff 100%);
            border-radius: 12px;
            padding: 18px;
            border: 1px solid #e2e8f0;
        }

        .summary-card h4 {
            color: #64748b;
            font-size: 13px;
            margin-bottom: 8px;
        }

        .summary-card .value {
            color: #0f172a;
            font-size: 24px;
            font-weight: 700;
        }

        .empty-state {
            color: #64748b;
            padding: 12px 0;
        }

        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 980px) {
            .stats-grid,
            .summary-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .top-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 14px;
            }

            .user-info {
                flex-wrap: wrap;
            }

            .dashboard-content {
                padding: 26px 20px;
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

            .stats-grid,
            .summary-grid {
                grid-template-columns: 1fr;
            }

            .content-card {
                padding: 22px;
            }

            .content-card-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .search-form {
                flex-direction: column;
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
            <li><a href="dashboard.php" class="active"><i class="fa-solid fa-gauge-high"></i> Dashboard</a></li>
            <?php if (($_SESSION['role'] ?? '') === 'Admin'): ?>
                <li><a href="users.php"><i class="fa-solid fa-users"></i> Users Management</a></li>
            <?php endif; ?>
            <li><a href="vehicles.php"><i class="fa-solid fa-car"></i> Vehicles</a></li>
            <li><a href="applications.php"><i class="fa-solid fa-file-lines"></i> Applications<?php if ($unread_admin_notifications > 0): ?><span style="margin-left:8px;background:#c41e3a;color:#fff;border-radius:999px;padding:2px 8px;font-size:11px;font-weight:700;"><?php echo $unread_admin_notifications; ?></span><?php endif; ?></a></li>
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
                <h1><i class="fa-solid fa-gauge-high"></i> Dashboard Overview</h1>
            </div>
            <div class="user-info">
                <div class="live-clock" aria-live="polite">
                    <span class="date" id="adminLiveDate">-- -- ----</span>
                    <span class="time" id="adminLiveTime">--:--:--</span>
                </div>
                <div class="alert-menu-wrap">
                    <button type="button" class="alert-trigger" id="adminAlertButton" aria-label="Open admin alerts" aria-expanded="false">
                        <i class="fa-solid fa-bell"></i>
                        <?php if ($dashboard_alert_count > 0): ?>
                            <span class="alert-badge"><?php echo $dashboard_alert_count > 9 ? '9+' : $dashboard_alert_count; ?></span>
                        <?php endif; ?>
                    </button>
                    <?php if (!empty($dashboard_alerts)): ?>
                        <div class="alert-panel" id="adminAlertPanel">
                            <div class="alert-panel-header">
                                <span>Notifications</span>
                                <span><?php echo $dashboard_alert_count; ?> new</span>
                            </div>
                            <div class="alert-panel-list">
                                <?php foreach ($dashboard_alerts as $alert): ?>
                                    <a class="alert-panel-item alert-item-kind <?php echo htmlspecialchars($alert['kind']); ?>" href="<?php echo htmlspecialchars($alert['link']); ?>">
                                        <span class="alert-item-label"><?php echo htmlspecialchars($alert['title']); ?></span>
                                        <span class="alert-item-message"><?php echo htmlspecialchars($alert['message']); ?></span>
                                        <span class="alert-item-time"><?php echo htmlspecialchars(date('M d, Y • h:i A', strtotime($alert['time']))); ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="admin-user-panel">
                    <span class="role"><?php echo $_SESSION['role']; ?></span>
                    <span class="name">Welcome <?php echo htmlspecialchars($_SESSION['fullname']); ?>.</span>
                </div>
                <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
            </div>
        </div>

        <!-- Dashboard Content -->
        <div class="dashboard-content">
            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card-item">
                    <a href="users.php" class="stat-card-link" aria-label="View all system users">
                        <div class="stat-card">
                            <div class="stat-icon blue">
                                <i class="fa-solid fa-users"></i>
                            </div>
                            <div class="stat-details">
                                <h3>Total System Users</h3>
                                <div class="number"><?php echo $total_users; ?></div>
                            </div>
                        </div>
                    </a>
                </div>

                <div class="stat-card-item">
                    <a href="vehicles.php" class="stat-card-link" aria-label="View all registered vehicles">
                        <div class="stat-card">
                            <div class="stat-icon green">
                                <i class="fa-solid fa-car"></i>
                            </div>
                            <div class="stat-details">
                                <h3>Registered Vehicles</h3>
                                <div class="number"><?php echo $total_vehicles; ?></div>
                            </div>
                        </div>
                    </a>
                </div>

                <div class="stat-card-item">
                    <a href="applications.php" class="stat-card-link" aria-label="View all applications">
                        <div class="stat-card">
                            <div class="stat-icon orange">
                                <i class="fa-solid fa-file-lines"></i>
                            </div>
                            <div class="stat-details">
                                <h3>Total Applications</h3>
                                <div class="number"><?php echo $total_applications; ?></div>
                            </div>
                        </div>
                    </a>
                    <a href="<?php echo htmlspecialchars($total_applications_export_link); ?>" class="stat-card-export" aria-label="Download total applications data">
                        <i class="fa-solid fa-download"></i> CSV
                    </a>
                </div>

                <div class="stat-card-item">
                    <a href="applications.php?status=Pending" class="stat-card-link" aria-label="View pending applications">
                        <div class="stat-card">
                            <div class="stat-icon red">
                                <i class="fa-solid fa-clock"></i>
                            </div>
                            <div class="stat-details">
                                <h3>Pending Applications</h3>
                                <div class="number"><?php echo $pending_applications; ?></div>
                            </div>
                        </div>
                    </a>
                    <a href="<?php echo htmlspecialchars($pending_applications_export_link); ?>" class="stat-card-export" aria-label="Download pending applications data">
                        <i class="fa-solid fa-download"></i> CSV
                    </a>
                </div>

                <div class="stat-card-item">
                    <a href="applications.php?status=Approved" class="stat-card-link" aria-label="View approved applications">
                        <div class="stat-card">
                            <div class="stat-icon green">
                                <i class="fa-solid fa-check-circle"></i>
                            </div>
                            <div class="stat-details">
                                <h3>Approved Applications</h3>
                                <div class="number"><?php echo $approved_applications; ?></div>
                            </div>
                        </div>
                    </a>
                    <a href="<?php echo htmlspecialchars($approved_applications_export_link); ?>" class="stat-card-export" aria-label="Download approved applications data">
                        <i class="fa-solid fa-download"></i> CSV
                    </a>
                </div>

                <div class="stat-card-item">
                    <a href="applications.php?status=Rejected" class="stat-card-link" aria-label="View rejected applications">
                        <div class="stat-card">
                            <div class="stat-icon red">
                                <i class="fa-solid fa-ban"></i>
                            </div>
                            <div class="stat-details">
                                <h3>Rejected Applications</h3>
                                <div class="number"><?php echo $rejected_applications; ?></div>
                            </div>
                        </div>
                    </a>
                    <a href="<?php echo htmlspecialchars($rejected_applications_export_link); ?>" class="stat-card-export" aria-label="Download rejected applications data">
                        <i class="fa-solid fa-download"></i> CSV
                    </a>
                </div>

                <div class="stat-card-item">
                    <a href="applications.php?status=Blocked" class="stat-card-link" aria-label="View blocked applications">
                        <div class="stat-card">
                            <div class="stat-icon red" style="background: linear-gradient(135deg, #4b5563 0%, #374151 100%);">
                                <i class="fa-solid fa-user-slash"></i>
                            </div>
                            <div class="stat-details">
                                <h3>Blocked Applications</h3>
                                <div class="number"><?php echo $blocked_applications; ?></div>
                            </div>
                        </div>
                    </a>
                </div>

                <div class="stat-card-item">
                    <a href="messages.php?view=unread" class="stat-card-link" aria-label="View unread messages">
                        <div class="stat-card">
                            <div class="stat-icon blue">
                                <i class="fa-solid fa-envelope"></i>
                            </div>
                            <div class="stat-details">
                                <h3>New Messages</h3>
                                <div class="number"><?php echo $new_messages; ?></div>
                            </div>
                        </div>
                    </a>
                </div>

                <div class="stat-card-item">
                    <a href="vehicles.php?view=all&status=Paid" class="stat-card-link" aria-label="View paid plates">
                        <div class="stat-card">
                            <div class="stat-icon green">
                                <i class="fa-solid fa-circle-check"></i>
                            </div>
                            <div class="stat-details">
                                <h3>Plates Paid</h3>
                                <div class="number"><?php echo $paid_plates_count; ?></div>
                            </div>
                        </div>
                    </a>
                    <a href="<?php echo htmlspecialchars($paid_plates_export_link); ?>" class="stat-card-export" aria-label="Download paid plates data">
                        <i class="fa-solid fa-download"></i> CSV
                    </a>
                </div>

                <div class="stat-card-item">
                    <a href="vehicles.php?view=all&status=Pending" class="stat-card-link" aria-label="View pending payment plates">
                        <div class="stat-card">
                            <div class="stat-icon red">
                                <i class="fa-solid fa-clock"></i>
                            </div>
                            <div class="stat-details">
                                <h3>Plates Pending Payment</h3>
                                <div class="number"><?php echo $pending_plates_count; ?></div>
                            </div>
                        </div>
                    </a>
                    <a href="<?php echo htmlspecialchars($pending_plates_export_link); ?>" class="stat-card-export" aria-label="Download pending payment plates data">
                        <i class="fa-solid fa-download"></i> CSV
                    </a>
                </div>

            </div>

            <div class="content-card">
                <div class="content-card-header">
                    <h2><i class="fa-solid fa-filter"></i> Plate Filters</h2>
                </div>
                <div style="display:flex; flex-wrap:wrap; gap:12px; margin:18px 0 8px;">
                    <a href="vehicles.php?view=all&status=Active" style="display:inline-flex; align-items:center; justify-content:center; padding:12px 18px; border-radius:12px; background:linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%); border:1px solid #86efac; color:#166534; font-weight:800; text-decoration:none;">ACTIVE PLATE</a>
                    <a href="vehicles.php?view=all&status=Rejected" style="display:inline-flex; align-items:center; justify-content:center; padding:12px 18px; border-radius:12px; background:linear-gradient(135deg, #fee2e2 0%, #fecaca 100%); border:1px solid #fca5a5; color:#991b1b; font-weight:800; text-decoration:none;">REJECTED PLATE</a>
                    <a href="vehicles.php?view=all&status=Blocked" style="display:inline-flex; align-items:center; justify-content:center; padding:12px 18px; border-radius:12px; background:linear-gradient(135deg, #e5e7eb 0%, #d1d5db 100%); border:1px solid #9ca3af; color:#111827; font-weight:800; text-decoration:none;"><i class="fa-solid fa-user-slash" style="margin-right:8px;"></i> BLOCKED PLATE</a>
                    <a href="vehicles.php?view=all&status=Expired" style="display:inline-flex; align-items:center; justify-content:center; padding:12px 18px; border-radius:12px; background:linear-gradient(135deg, #fee2e2 0%, #ffe4e6 100%); border:1px solid #fda4af; color:#be123c; font-weight:800; text-decoration:none;">EXPIRED PLATE</a>
                    <a href="vehicles.php?view=all&status=Approved" style="display:inline-flex; align-items:center; justify-content:center; padding:12px 18px; border-radius:12px; background:linear-gradient(135deg, #dcfce7 0%, #d1fae5 100%); border:1px solid #86efac; color:#166534; font-weight:800; text-decoration:none;">APPROVED PLATE</a>
                </div>
            </div>

            <div class="content-card">
                <div class="content-card-header">
                    <h2><i class="fa-solid fa-magnifying-glass"></i> Search Applicants</h2>
                    <a href="<?php echo htmlspecialchars($csv_export_link); ?>" class="action-btn download">
                        <i class="fa-solid fa-download"></i> Export Current View
                    </a>
                </div>
                <form method="GET" class="search-form">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search_term); ?>" placeholder="Search by applicant name, application ID, contact or status">
                    <button type="submit"><i class="fa-solid fa-search"></i> Search</button>
                </form>
                <div class="search-actions">
                    <a href="<?php echo htmlspecialchars($csv_export_link); ?>" class="action-btn download">
                        <i class="fa-solid fa-file-csv"></i> Download CSV
                    </a>
                    <a href="dashboard.php" class="action-btn refresh">
                        <i class="fa-solid fa-rotate"></i> Refresh
                    </a>
                </div>
                <?php if ($search_term): ?>
                    <div class="search-results-summary">Showing results for "<?php echo htmlspecialchars($search_term); ?>".</div>
                    <?php if ($search_results && mysqli_num_rows($search_results) > 0): ?>
                        <table class="results-table">
                            <thead>
                                <tr>
                                    <th>Application ID</th>
                                    <th>Applicant</th>
                                    <th>County</th>
                                    <th>Contact</th>
                                    <th>Status</th>
                                    <th>Plate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($app = mysqli_fetch_assoc($search_results)): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($app['application_number']); ?></td>
                                        <td><?php echo htmlspecialchars($app['applicant_name']); ?></td>
                                        <td><?php echo htmlspecialchars($app['county']); ?></td>
                                        <td><?php echo htmlspecialchars($app['applicant_contact']); ?></td>
                                        <td>
                                            <?php
                                                $search_status = $app['status'];
                                                if ($app['status'] == 'Approved' && !empty($app['license_plate_number'])) {
                                                    $search_status = 'Plate Accepted';
                                                } elseif ($app['status'] == 'Rejected') {
                                                    $search_status = 'Plate Rejected';
                                                } elseif ($app['status'] == 'Pending' && !empty($app['license_plate_number'])) {
                                                    $search_status = 'Plate Pending';
                                                }
                                                echo htmlspecialchars($search_status);
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($app['license_plate_number'] ?: 'Pending'); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="empty-state">No applicants matched your search.</p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <div class="content-card">
                <h2><i class="fa-solid fa-money-bill-wave"></i> Payment Summary</h2>
                <div class="summary-grid">
                    <div class="summary-card">
                        <h4>Cash (USD)</h4>
                        <div class="value"><?php echo htmlspecialchars(appFormatPaymentAmount($usd_cash_total, 'USD')); ?></div>
                    </div>
                    <div class="summary-card">
                        <h4>Card (USD)</h4>
                        <div class="value"><?php echo htmlspecialchars(appFormatPaymentAmount($usd_card_total, 'USD')); ?></div>
                    </div>
                    <div class="summary-card">
                        <h4>Mobile Money (USD)</h4>
                        <div class="value"><?php echo htmlspecialchars(appFormatPaymentAmount($usd_mobile_money_total, 'USD')); ?></div>
                    </div>
                    <div class="summary-card">
                        <h4>Total USD Revenue</h4>
                        <div class="value"><?php echo htmlspecialchars(appFormatPaymentAmount($usd_total, 'USD')); ?></div>
                    </div>
                    <div class="summary-card">
                        <h4>Cash (LRD)</h4>
                        <div class="value"><?php echo htmlspecialchars(appFormatPaymentAmount($lrd_cash_total, 'LRD')); ?></div>
                    </div>
                    <div class="summary-card">
                        <h4>Card (LRD)</h4>
                        <div class="value"><?php echo htmlspecialchars(appFormatPaymentAmount($lrd_card_total, 'LRD')); ?></div>
                    </div>
                    <div class="summary-card">
                        <h4>Mobile Money (LRD)</h4>
                        <div class="value"><?php echo htmlspecialchars(appFormatPaymentAmount($lrd_mobile_money_total, 'LRD')); ?></div>
                    </div>
                    <div class="summary-card">
                        <h4>Total LRD Revenue</h4>
                        <div class="value"><?php echo htmlspecialchars(appFormatPaymentAmount($lrd_total, 'LRD')); ?></div>
                    </div>
                </div>
            </div>

            <?php if (!empty($expiring_soon_plates)): ?>
                <div class="content-card" style="border-left: 5px solid #f59e0b; background: linear-gradient(180deg, #fff7ed 0%, #ffffff 100%);">
                    <h2 style="color:#92400e;"><i class="fa-solid fa-triangle-exclamation"></i> Renewal Alert</h2>
                    <p style="margin-top:10px; color:#92400e; font-weight:600; line-height:1.7;">
                        <?php echo count($expiring_soon_plates); ?> plate(s) will expire within 30 days. Review these renewals immediately:
                        <?php $plate_alert_names = []; foreach ($expiring_soon_plates as $alert_plate) { $plate_alert_names[] = htmlspecialchars($alert_plate['license_plate_number'] . ' (' . date('M d, Y', strtotime($alert_plate['expires_at'])) . ')'); } echo implode(', ', $plate_alert_names); ?>
                    </p>
                </div>
            <?php endif; ?>

            <div class="content-card">
                <h2><i class="fa-solid fa-calendar-check"></i> Plate Validity Summary</h2>
                <div class="summary-grid">
                    <div class="summary-card">
                        <h4>Active Plates</h4>
                        <div class="value"><?php echo $active_licenses; ?></div>
                    </div>
                    <div class="summary-card">
                        <h4>Expired Plates</h4>
                        <div class="value"><?php echo $expired_licenses; ?></div>
                    </div>
                    <div class="summary-card">
                        <h4>Expiring Soon</h4>
                        <div class="value"><?php echo $expiring_soon_licenses; ?></div>
                    </div>
                    <div class="summary-card">
                        <h4>Renewal Requests</h4>
                        <div class="value"><?php echo $renewal_pending_count; ?></div>
                    </div>
                    <div class="summary-card">
                        <h4>Validity Period</h4>
                        <div class="value">3 Years</div>
                    </div>
                </div>
                <p style="color:#64748b; margin-top:16px;">Plates are issued for 3 years from the date of generation. The system automatically shows expired and soon-to-expire plates based on the actual expiry date.</p>
            </div>

            <div class="content-card">
                <h2><i class="fa-solid fa-circle-info"></i> Welcome to TUA Portal</h2>
                <p style="color: #475569; line-height: 1.7;">This is the official Transport Union Authority (TUA) portal for managing car license plate registrations. Use the sidebar to navigate through the different modules of the system.</p>
            </div>
        </div>
    </div>
    <?php if (!empty($page_toasts)): ?>
        <script>
            window.adminToastQueue = <?php echo json_encode($page_toasts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        </script>
    <?php endif; ?>
    <script>
        (function startAdminClock() {
            const timeEl = document.getElementById('adminLiveTime');
            const dateEl = document.getElementById('adminLiveDate');

            if (!timeEl || !dateEl) {
                return;
            }

            function updateClock() {
                const now = new Date();

                timeEl.textContent = now.toLocaleTimeString([], {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: false
                });

                dateEl.textContent = now.toLocaleDateString([], {
                    weekday: 'short',
                    month: 'short',
                    day: 'numeric',
                    year: 'numeric'
                });
            }

            updateClock();
            setInterval(updateClock, 1000);
        })();

        const adminAlertButton = document.getElementById('adminAlertButton');
        const adminAlertPanel = document.getElementById('adminAlertPanel');

        if (adminAlertButton && adminAlertPanel) {
            adminAlertButton.addEventListener('click', function (event) {
                event.stopPropagation();
                const isOpen = adminAlertPanel.classList.contains('show');
                adminAlertPanel.classList.toggle('show', !isOpen);
                adminAlertButton.classList.toggle('active', !isOpen);
                adminAlertButton.setAttribute('aria-expanded', String(!isOpen));
            });

            document.addEventListener('click', function (event) {
                if (!adminAlertPanel.contains(event.target) && !adminAlertButton.contains(event.target)) {
                    adminAlertPanel.classList.remove('show');
                    adminAlertButton.classList.remove('active');
                    adminAlertButton.setAttribute('aria-expanded', 'false');
                }
            });
        }
    </script>
    <script src="assets/js/admin-toast.js"></script>
</body>
</html>
