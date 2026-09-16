<?php
session_start();
include "staff_auth.php";
include "config.php";
include "partials/admin-toast.php";
include "partials/admin-notifications.php";

$unread_admin_notifications = admin_get_unread_notification_count($conn);

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['block_plate_action'])) {
    $plate_number = trim((string) ($_POST['plate_number'] ?? ''));
    $block_reason = trim((string) ($_POST['block_reason'] ?? ''));

    if ($plate_number === '') {
        $message = 'Please enter a plate number to block.';
        $message_type = 'danger';
    } else {
        $normalized_plate = preg_replace('/\s+/', '', strtoupper(trim((string) $plate_number)));
        $plate_lookup = mysqli_real_escape_string($conn, $normalized_plate);

        $match_query = mysqli_query($conn, "SELECT v.id, v.plate_number, v.status AS vehicle_status, a.id AS application_id, a.status AS application_status, a.license_plate_number AS application_plate
            FROM vehicles v
            LEFT JOIN applications a ON a.vehicle_id = v.id
            WHERE REPLACE(UPPER(TRIM(v.plate_number)), ' ', '') = '$plate_lookup'
               OR REPLACE(UPPER(TRIM(a.license_plate_number)), ' ', '') = '$plate_lookup'
            ORDER BY a.id DESC
            LIMIT 1");

        if ($match_query && mysqli_num_rows($match_query) > 0) {
            $matched = mysqli_fetch_assoc($match_query);
            $vehicle_id = (int) ($matched['id'] ?? 0);
            $application_id = (int) ($matched['application_id'] ?? 0);
            $reason_value = $block_reason !== '' ? $block_reason : 'Plate blocked by administrator. Client must visit the nearest office for verification.';
            $reason_sql = mysqli_real_escape_string($conn, $reason_value);

            $update_vehicle = mysqli_query($conn, "UPDATE vehicles SET status = 'Blocked', rejection_reason = '$reason_sql', expiry_date = NULL WHERE id = '$vehicle_id'");
            if ($update_vehicle && $application_id > 0) {
                mysqli_query($conn, "UPDATE applications SET status = 'Blocked', processed_by = '{$_SESSION['id']}', processed_at = NOW(), issued_at = NULL, expires_at = NULL WHERE id = '$application_id'");
            }

            if ($update_vehicle) {
                mysqli_query($conn, "INSERT INTO plate_status_history (vehicle_id, previous_status, new_status, reason, changed_by, changed_by_role)
                    VALUES ('$vehicle_id', '{$matched['vehicle_status']}', 'Blocked', '$reason_sql', '{$_SESSION['id']}', '{$_SESSION['role']}')");

                logAuditEvent(
                    $conn,
                    'manual_plate_block',
                    'Admin manually blocked plate number: ' . $plate_number,
                    $_SESSION['id'] ?? null,
                    $_SESSION['role'] ?? 'Admin',
                    'vehicle',
                    $vehicle_id,
                    ['plate_number' => $plate_number, 'reason' => $reason_value]
                );

                $message = 'Plate number ' . htmlspecialchars($plate_number) . ' has been blocked successfully.';
                $message_type = 'success';
            } else {
                $message = 'Unable to block the plate number. Please try again.';
                $message_type = 'danger';
            }
        } else {
            $message = 'No matching plate number was found in the system.';
            $message_type = 'danger';
        }
    }

    admin_toast_add($message, $message_type);
    header('Location: vehicles.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vehicle_action'])) {
    $vehicle_id = (int) ($_POST['vehicle_id'] ?? 0);
    $vehicle_action = $_POST['vehicle_action'] ?? '';
    $redirect_view = isset($_POST['redirect_view']) ? trim((string) $_POST['redirect_view']) : 'all';
    $redirect_status = isset($_POST['redirect_status']) ? trim((string) $_POST['redirect_status']) : 'all';
    $redirect_search = isset($_POST['redirect_search']) ? trim((string) $_POST['redirect_search']) : '';
    $rejection_reason = isset($_POST['rejection_reason']) ? trim((string) $_POST['rejection_reason']) : '';

    if ($vehicle_id > 0 && in_array($vehicle_action, ['approve', 'reject', 'block', 'unblock'], true)) {
        $vehicle_before = mysqli_query($conn, "SELECT status, rejection_reason FROM vehicles WHERE id = '$vehicle_id' LIMIT 1");
        $prior_status = $vehicle_before && mysqli_num_rows($vehicle_before) > 0 ? mysqli_fetch_assoc($vehicle_before) : ['status' => 'Pending', 'rejection_reason' => null];
        $previous_status = $prior_status['status'] ?? 'Pending';

        if ($vehicle_action === 'unblock') {
            $new_status = 'Active';
            $application_status = 'Approved';
            $reason_value = 'Admin unblocked the plate after review.';
            $message = 'Vehicle unblocked successfully.';
            $message_type = 'success';
        } elseif ($vehicle_action === 'approve') {
            $new_status = 'Active';
            $application_status = 'Approved';
            $reason_value = '';
            $message = 'Vehicle approved successfully.';
            $message_type = 'success';
        } elseif ($vehicle_action === 'block') {
            $new_status = 'Blocked';
            $application_status = 'Blocked';
            $reason_value = $rejection_reason !== '' ? $rejection_reason : 'Plate blocked by administrator. Client must visit the nearest office for verification.';
            $message = 'Vehicle blocked successfully.';
            $message_type = 'success';
        } else {
            $new_status = 'Rejected';
            $application_status = 'Rejected';
            $reason_value = $rejection_reason;
            $message = 'Vehicle rejected successfully.';
            $message_type = 'success';
        }

        $reason_sql = mysqli_real_escape_string($conn, $reason_value);
        $update_vehicle = mysqli_query($conn, "UPDATE vehicles SET status = '$new_status', rejection_reason = '$reason_sql', expiry_date = NULL WHERE id = '$vehicle_id'");

        if ($update_vehicle) {
            $linked_applications = mysqli_query($conn, "SELECT id FROM applications WHERE vehicle_id = '$vehicle_id' LIMIT 1");
            if ($linked_applications && mysqli_num_rows($linked_applications) > 0) {
                $linked_application = mysqli_fetch_assoc($linked_applications);
                mysqli_query($conn, "UPDATE applications SET status = '$application_status', processed_by = '{$_SESSION['id']}', processed_at = NOW(), issued_at = NULL, expires_at = NULL WHERE id = '{$linked_application['id']}'");
            }

            mysqli_query($conn, "INSERT INTO plate_status_history (vehicle_id, previous_status, new_status, reason, changed_by, changed_by_role)
                VALUES ('$vehicle_id', '$previous_status', '$new_status', '$reason_sql', '{$_SESSION['id']}', '{$_SESSION['role']}')");

            logAuditEvent(
                $conn,
                'vehicle_status_change',
                $vehicle_action === 'approve' ? 'Vehicle approved manually by admin.' : ($vehicle_action === 'block' ? 'Vehicle blocked manually by admin. This plate now warns clients to visit the nearest office.' : ($vehicle_action === 'unblock' ? 'Vehicle unblocked by admin after review.' : 'Vehicle rejected manually by admin.')),
                $_SESSION['id'] ?? null,
                $_SESSION['role'] ?? 'Admin',
                'vehicle',
                $vehicle_id,
                ['action' => $vehicle_action, 'from_status' => $previous_status, 'to_status' => $new_status, 'reason' => $reason_value]
            );
        } else {
            $message = 'Unable to update vehicle status.';
            $message_type = 'danger';
        }
    }

    admin_toast_add($message, $message_type);

    $redirect_url = 'vehicles.php';
    $redirect_params = [];
    if ($redirect_view !== 'all') {
        $redirect_params[] = 'view=' . urlencode($redirect_view);
    }
    if ($redirect_status !== 'all') {
        $redirect_params[] = 'status=' . urlencode($redirect_status);
    }
    if ($redirect_search !== '') {
        $redirect_params[] = 'search=' . urlencode($redirect_search);
    }
    if (!empty($redirect_params)) {
        $redirect_url .= '?' . implode('&', $redirect_params);
    }

    header('Location: ' . $redirect_url);
    exit();
}

if (isset($_GET['export']) && $_GET['export'] === 'vehicles_csv') {
    $csv_result = mysqli_query($conn, "SELECT id, plate_number, vehicle_type, make, model, year, color, owner_name, created_at FROM vehicles ORDER BY created_at DESC");
    $filename = 'vehicles-export-' . date('Ymd-His') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Plate Number', 'Vehicle Type', 'Make', 'Model', 'Year', 'Color', 'Owner', 'Created On']);

    if ($csv_result) {
        while ($row = mysqli_fetch_assoc($csv_result)) {
            fputcsv($output, [
                $row['id'],
                $row['plate_number'],
                $row['vehicle_type'] ?? '—',
                $row['make'],
                $row['model'],
                $row['year'],
                $row['color'],
                $row['owner_name'],
                $row['created_at']
            ]);
        }
    }

    fclose($output);
    exit();
}

$filter_status = isset($_GET['status']) ? trim((string) $_GET['status']) : 'all';
$view_filter = isset($_GET['view']) && trim((string) $_GET['view']) !== '' ? trim((string) $_GET['view']) : 'all';
$search = isset($_GET['search']) ? trim((string) $_GET['search']) : '';

$conditions = [];
if ($view_filter === 'existing_plate') {
    $conditions[] = "created_by_applicant IS NOT NULL";
} elseif ($view_filter === 'registered') {
    $conditions[] = "created_by_applicant IS NULL";
}
if ($search !== '') {
    $search_sql = mysqli_real_escape_string($conn, $search);
    $conditions[] = "(
        plate_number LIKE '%$search_sql%' OR
        owner_name LIKE '%$search_sql%' OR
        owner_contact LIKE '%$search_sql%' OR
        make LIKE '%$search_sql%' OR
        model LIKE '%$search_sql%'
    )";
}

$vehicle_filter_sql = '';
if (!empty($conditions)) {
    $vehicle_filter_sql = ' WHERE ' . implode(' AND ', $conditions);
}

$vehicles_query = mysqli_query($conn, "SELECT a.id AS application_id, a.application_number, a.status AS application_status, v.*, a.applicant_name AS owner_name, a.applicant_contact AS owner_contact, a.county, a.application_date, a.applicant_name AS applicant_display_name, a.applicant_contact AS applicant_display_contact, a.applicant_address, a.user_id, a.applicant_id, app.email AS owner_email, app.contact_number AS owner_contact_detail, CASE WHEN EXISTS (SELECT 1 FROM payments p WHERE p.application_id = a.id AND p.status = 'Paid') THEN 'Paid' WHEN a.vehicle_id IS NOT NULL AND a.id = (SELECT a2.id FROM applications a2 WHERE a2.vehicle_id = a.vehicle_id ORDER BY a2.application_date ASC, a2.id ASC LIMIT 1) AND EXISTS (SELECT 1 FROM payments p WHERE p.vehicle_id = a.vehicle_id AND p.status = 'Paid') THEN 'Paid' ELSE 'Pending' END AS effective_payment_status FROM applications a LEFT JOIN vehicles v ON v.id = a.vehicle_id LEFT JOIN applicants app ON app.id = a.applicant_id" . $vehicle_filter_sql . " ORDER BY a.application_date DESC");

$filtered_vehicle_rows = [];
if ($vehicles_query) {
    while ($vehicle_row = mysqli_fetch_assoc($vehicles_query)) {
        $effective_status = appEffectivePlateStatus($vehicle_row['application_status'] ?? ($vehicle_row['status'] ?? 'Pending'), $vehicle_row['expiry_date'] ?? null);
        $matches_status = $filter_status === 'all' ? $effective_status !== 'Blocked' : true;

        if ($filter_status !== 'all') {
            if ($filter_status === 'Approved') {
                $matches_status = in_array($effective_status, ['Approved', 'Active'], true);
            } elseif ($filter_status === 'Active') {
                $matches_status = $effective_status === 'Active';
            } elseif ($filter_status === 'Expired') {
                $matches_status = $effective_status === 'Expired';
            } elseif ($filter_status === 'Rejected') {
                $matches_status = $effective_status === 'Rejected';
            } elseif ($filter_status === 'Blocked') {
                $matches_status = $effective_status === 'Blocked';
            } elseif ($filter_status === 'Pending') {
                $matches_status = $effective_status === 'Pending';
            } elseif ($filter_status === 'Paid') {
                $matches_status = appNormalizePaymentStatus($vehicle_row['effective_payment_status'] ?? ($vehicle_row['payment_status'] ?? 'Pending')) === 'Paid';
            } elseif ($filter_status === 'PendingPayment') {
                $matches_status = appNormalizePaymentStatus($vehicle_row['effective_payment_status'] ?? ($vehicle_row['payment_status'] ?? 'Pending')) === 'Pending';
            } else {
                $matches_status = false;
            }
        }

        if ($matches_status) {
            $filtered_vehicle_rows[] = $vehicle_row;
        }
    }
}

$all_vehicle_rows = mysqli_query($conn, "SELECT a.id AS application_id, a.application_number, a.status AS application_status, v.*, a.applicant_name AS owner_name, a.applicant_contact AS owner_contact, a.county, a.application_date, CASE WHEN EXISTS (SELECT 1 FROM payments p WHERE p.application_id = a.id AND p.status = 'Paid') THEN 'Paid' WHEN a.vehicle_id IS NOT NULL AND a.id = (SELECT a2.id FROM applications a2 WHERE a2.vehicle_id = a.vehicle_id ORDER BY a2.application_date ASC, a2.id ASC LIMIT 1) AND EXISTS (SELECT 1 FROM payments p WHERE p.vehicle_id = a.vehicle_id AND p.status = 'Paid') THEN 'Paid' ELSE 'Pending' END AS effective_payment_status FROM applications a LEFT JOIN vehicles v ON v.id = a.vehicle_id ORDER BY a.application_date DESC");
$all_vehicle_count = 0;
$registered_pending_count = 0;
$registered_active_count = 0;
$registered_approved_count = 0;
$registered_rejected_count = 0;
$registered_blocked_count = 0;
$registered_expired_count = 0;
$existing_plate_count = 0;
$existing_pending_count = 0;
$existing_active_count = 0;
$existing_approved_count = 0;
$existing_rejected_count = 0;
$existing_blocked_count = 0;
$existing_expired_count = 0;
$paid_plate_count = 0;
$pending_payment_plate_count = 0;
$seen_vehicle_ids = [];

if ($all_vehicle_rows) {
    while ($vehicle_row = mysqli_fetch_assoc($all_vehicle_rows)) {
        $vehicle_id = isset($vehicle_row['id']) && $vehicle_row['id'] !== null ? (int) $vehicle_row['id'] : 0;
        if ($vehicle_id > 0 && isset($seen_vehicle_ids[$vehicle_id])) {
            continue;
        }
        if ($vehicle_id > 0) {
            $seen_vehicle_ids[$vehicle_id] = true;
        }

        $effective_status = appEffectivePlateStatus($vehicle_row['application_status'] ?? ($vehicle_row['status'] ?? 'Pending'), $vehicle_row['expiry_date'] ?? null);
        if ($effective_status === 'Blocked') {
            continue;
        }
        $all_vehicle_count++;

        $effective_payment_status = appNormalizePaymentStatus($vehicle_row['effective_payment_status'] ?? ($vehicle_row['payment_status'] ?? 'Pending'));
        if ($effective_payment_status === 'Paid') {
            $paid_plate_count++;
        } else {
            $pending_payment_plate_count++;
        }

        if (!empty($vehicle_row['created_by_applicant'])) {
            $existing_plate_count++;
            if ($effective_status === 'Pending') {
                $existing_pending_count++;
            } elseif ($effective_status === 'Rejected') {
                $existing_rejected_count++;
            } elseif ($effective_status === 'Blocked') {
                $existing_blocked_count++;
            } elseif ($effective_status === 'Expired') {
                $existing_expired_count++;
            } elseif ($effective_status === 'Active') {
                $existing_active_count++;
                $existing_approved_count++;
            } elseif ($effective_status === 'Approved') {
                $existing_approved_count++;
            }
        } else {
            if ($effective_status === 'Pending') {
                $registered_pending_count++;
            } elseif ($effective_status === 'Rejected') {
                $registered_rejected_count++;
            } elseif ($effective_status === 'Blocked') {
                $registered_blocked_count++;
            } elseif ($effective_status === 'Expired') {
                $registered_expired_count++;
            } elseif ($effective_status === 'Active') {
                $registered_active_count++;
                $registered_approved_count++;
            } elseif ($effective_status === 'Approved') {
                $registered_approved_count++;
            }
        }
    }
}

if ($view_filter === 'existing_plate') {
    $pending_count = $existing_pending_count;
    $active_count = $existing_active_count;
    $approved_count = $existing_approved_count;
    $rejected_count = $existing_rejected_count;
    $blocked_count = $existing_blocked_count;
    $expired_count = $existing_expired_count;
} elseif ($view_filter === 'registered') {
    $pending_count = $registered_pending_count;
    $active_count = $registered_active_count;
    $approved_count = $registered_approved_count;
    $rejected_count = $registered_rejected_count;
    $blocked_count = $registered_blocked_count;
    $expired_count = $registered_expired_count;
} else {
    $pending_count = $registered_pending_count + $existing_pending_count;
    $active_count = $registered_active_count + $existing_active_count;
    $approved_count = $registered_approved_count + $existing_approved_count;
    $rejected_count = $registered_rejected_count + $existing_rejected_count;
    $blocked_count = $registered_blocked_count + $existing_blocked_count;
    $expired_count = $registered_expired_count + $existing_expired_count;
}

$vehicles_export_link = 'vehicles.php?export=vehicles_csv';
if ($filter_status !== 'all') {
    $vehicles_export_link .= '&status=' . urlencode($filter_status);
}
if ($view_filter !== 'all') {
    $vehicles_export_link .= '&view=' . urlencode($view_filter);
}
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
    <title>Vehicles - Transport Union Authority</title>
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
            background: rgba(255,255,255,0.96);
            padding: 18px 40px;
            box-shadow: 0 8px 25px rgba(15, 23, 42, 0.06);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 10;
            backdrop-filter: blur(10px);
        }

        .page-title h1 {
            color: #0a2463;
            font-size: clamp(1.5rem, 2.2vw, 2.3rem);
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 18px;
            flex-wrap: wrap;
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
            transition: background 0.3s;
        }

        .user-info a:hover {
            background: #e2e8f0;
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

        /* Content Card */
        .content-card {
            background: rgba(255,255,255,0.97);
            padding: 30px;
            border-radius: 22px;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.07);
            border: 1px solid rgba(148, 163, 184, 0.12);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
            padding-bottom: 18px;
            border-bottom: 2px solid #f1f5f9;
            gap: 12px;
        }

        .card-header h2 {
            color: #0a2463;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filter-banner {
            background: linear-gradient(135deg, #fff7ed 0%, #fef3c7 100%);
            border: 1px solid #fdba74;
            color: #9a5b00;
            border-radius: 14px;
            padding: 14px 16px;
            margin-bottom: 18px;
            font-size: 13px;
            font-weight: 700;
            line-height: 1.6;
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.5);
        }

        .filter-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 22px;
        }

        .filter-chip {
            padding: 10px 16px;
            border-radius: 999px;
            text-decoration: none;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.02em;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            border: 1px solid transparent;
        }

        .filter-chip:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 18px rgba(251, 146, 60, 0.12);
        }

        .filter-chip.pending-style {
            background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%);
            color: #fff;
            border-color: #f59e0b;
            box-shadow: 0 10px 20px rgba(245, 158, 11, 0.2);
        }

        .filter-chip.pending-soft {
            background: #fff7ed;
            color: #9a5b00;
            border-color: #fdba74;
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
            transition: 0.2s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, #0a2463 0%, #0f3460 100%);
            color: white;
        }

        .btn-primary:hover {
            opacity: 0.92;
        }

        .btn-warning {
            background: #f59e0b;
            color: white;
            padding: 7px 14px;
            font-size: 13px;
        }

        .btn-danger {
            background: #dc2626;
            color: white;
            padding: 7px 14px;
            font-size: 13px;
        }

        /* Table Styles */
        .table-wrap {
            width: 100%;
            overflow-x: auto;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 920px;
        }

        th {
            background: linear-gradient(180deg, #f8fafc 0%, #eef2ff 100%);
            color: #475569;
            padding: 14px 16px;
            text-align: left;
            font-weight: 700;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td {
            padding: 16px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
            color: #334155;
            vertical-align: middle;
        }

        tr:hover {
            background: #f8fafc;
        }

        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .status-pill.status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-pill.status-active {
            background: #dcfce7;
            color: #166534;
        }

        .status-pill.status-rejected,
        .status-pill.status-expired,
        .status-pill.status-blocked {
            background: #fee2e2;
            color: #991b1b;
        }

        @media (max-width: 980px) {
            .top-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 14px;
            }

            .user-info {
                width: 100%;
            }

            .page-content {
                padding: 24px 20px;
            }

            .card-header {
                flex-direction: column;
                align-items: flex-start;
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
                padding: 18px 14px;
            }

            .header-actions {
                width: 100%;
                flex-wrap: wrap;
            }

            .action-btn,
            .btn,
            .filter-chip {
                width: 100%;
                justify-content: center;
            }

            .action-buttons {
                display: grid;
                grid-template-columns: 1fr;
            }

            .page-content {
                padding: 18px 14px;
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
            <li><a href="vehicles.php" class="active"><i class="fa-solid fa-car"></i> Vehicles</a></li>
            <li><a href="vehicles.php#block-plate"><i class="fa-solid fa-ban"></i> Block Plate</a></li>
            <li><a href="vehicles.php?view=all&status=Blocked"><i class="fa-solid fa-rectangle-xmark"></i> Blocked Plate</a></li>
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
                <h1><i class="fa-solid fa-car"></i> <?php echo ($view_filter === 'existing_plate') ? 'Existing Plate Requests' : 'Registered Vehicles'; ?></h1>
            </div>
            <div class="user-info">
                <div class="header-actions">
                    <a href="<?php echo htmlspecialchars($vehicles_export_link); ?>" class="action-btn download">
                        <i class="fa-solid fa-file-csv"></i> Download CSV
                    </a>
                    <a href="vehicles.php" class="action-btn refresh">
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
                    <h2><i class="fa-solid fa-warehouse"></i> <?php
                        if ($view_filter === 'existing_plate') {
                            echo 'Existing Plate Requests';
                        } elseif ($filter_status === 'all') {
                            echo 'All Vehicles';
                        } else {
                            echo ucfirst($filter_status) . ' Vehicles';
                        }
                    ?></h2>
                    <div class="header-actions">
                        <a href="vehicles.php?view=existing_plate&status=Pending" class="action-btn refresh pending-style" style="background:linear-gradient(135deg,#f59e0b 0%, #fbbf24 100%);color:#fff;border:1px solid #f59e0b;box-shadow:0 10px 20px rgba(245,158,11,0.18);"><i class="fa-solid fa-hourglass-half"></i> Existing Plate Requests</a>
                        <a href="<?php echo htmlspecialchars($vehicles_export_link); ?>" class="action-btn download"><i class="fa-solid fa-download"></i> Export List</a>
                        <a href="add_vehicle.php" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Add New Vehicle</a>
                    </div>
                </div>

                <?php if ($filter_status === 'Blocked'): ?>
                    <div class="filter-banner" style="background:linear-gradient(135deg,#fef2f2 0%, #fee2e2 100%); border-color:#fca5a5; color:#991b1b;">
                        <i class="fa-solid fa-triangle-exclamation"></i> Blocked Plate List: this view shows only plates that have already been blocked and are no longer valid for site verification.
                    </div>
                <?php else: ?>
                    <div class="filter-banner">
                        <i class="fa-solid fa-circle-info"></i> Existing plate requests are reviewed and approved manually by the administrator after verification. Payment is not required before approval on this page.
                    </div>
                <?php endif; ?>

                <div id="block-plate" style="margin: 0 0 18px; padding: 18px; background: linear-gradient(135deg, #fff7ed 0%, #fff1f2 100%); border: 1px solid #fdba74; border-radius: 16px; box-shadow: inset 0 1px 0 rgba(255,255,255,0.5);">
                    <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-bottom:12px;">
                        <div style="font-size:16px; font-weight:800; color:#7c2d12; display:flex; align-items:center; gap:10px;">
                            <i class="fa-solid fa-ban"></i> Block a Plate
                        </div>
                    </div>
                    <form method="POST" action="vehicles.php" style="display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
                        <input type="hidden" name="block_plate_action" value="1">
                        <input type="text" name="plate_number" placeholder="Enter plate number to block" style="flex:1; min-width:260px; border:1px solid #f9a8d4; background:#fff; border-radius:10px; padding:12px 14px; font-size:14px; color:#0f172a;" required>
                        <input type="text" name="block_reason" placeholder="Reason for block (optional)" style="flex:1; min-width:260px; border:1px solid #f9a8d4; background:#fff; border-radius:10px; padding:12px 14px; font-size:14px; color:#0f172a;">
                        <button type="submit" class="btn btn-danger" style="padding:12px 18px; background:linear-gradient(135deg, #4b5563 0%, #374151 100%); color:#fff; border-radius:10px; font-weight:700;">
                            <i class="fa-solid fa-ban"></i> Block Plate
                        </button>
                    </form>
                </div>

                <div class="filter-bar">
                    <a href="vehicles.php?view=all" class="filter-chip <?php echo ($view_filter === 'all' && $filter_status === 'all') ? 'pending-style' : 'pending-soft'; ?>" style="background:<?php echo $view_filter === 'all' && $filter_status === 'all' ? 'linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%)' : '#fff7ed'; ?>; color:<?php echo $view_filter === 'all' && $filter_status === 'all' ? '#fff' : '#9a5b00'; ?>; border-color:<?php echo $view_filter === 'all' && $filter_status === 'all' ? '#f59e0b' : '#fdba74'; ?>;">All Vehicles (<?php echo $all_vehicle_count; ?>)</a>
                    <a href="vehicles.php?view=registered" class="filter-chip <?php echo $view_filter === 'registered' ? 'pending-style' : 'pending-soft'; ?>" style="background:<?php echo $view_filter === 'registered' ? 'linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%)' : '#fff7ed'; ?>; color:<?php echo $view_filter === 'registered' ? '#fff' : '#9a5b00'; ?>; border-color:<?php echo $view_filter === 'registered' ? '#f59e0b' : '#fdba74'; ?>;">Registered Vehicles (<?php echo ($all_vehicle_count - $existing_plate_count); ?>)</a>
                    <a href="vehicles.php?view=existing_plate" class="filter-chip <?php echo $view_filter === 'existing_plate' ? 'pending-style' : 'pending-soft'; ?>" style="background:<?php echo $view_filter === 'existing_plate' ? 'linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%)' : '#fff7ed'; ?>; color:<?php echo $view_filter === 'existing_plate' ? '#fff' : '#9a5b00'; ?>; border-color:<?php echo $view_filter === 'existing_plate' ? '#f59e0b' : '#fdba74'; ?>;">Existing Plate Requests (<?php echo $existing_plate_count; ?>)</a>
                    <a href="vehicles.php?view=all&status=Pending" class="filter-chip <?php echo ($filter_status === 'Pending' && $view_filter === 'all') ? 'pending-style' : 'pending-soft'; ?>" style="background:<?php echo $filter_status === 'Pending' && $view_filter === 'all' ? 'linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%)' : '#fff7ed'; ?>; color:<?php echo $filter_status === 'Pending' && $view_filter === 'all' ? '#fff' : '#9a5b00'; ?>; border-color:<?php echo $filter_status === 'Pending' && $view_filter === 'all' ? '#f59e0b' : '#fdba74'; ?>;">Pending (<?php echo $pending_count; ?>)</a>
                    <a href="vehicles.php?view=all&status=Paid" class="filter-chip <?php echo ($filter_status === 'Paid' && $view_filter === 'all') ? 'pending-style' : 'pending-soft'; ?>" style="background:<?php echo $filter_status === 'Paid' && $view_filter === 'all' ? 'linear-gradient(135deg, #16a34a 0%, #22c55e 100%)' : '#dcfce7'; ?>; color:<?php echo $filter_status === 'Paid' && $view_filter === 'all' ? '#fff' : '#166534'; ?>; border-color:<?php echo $filter_status === 'Paid' && $view_filter === 'all' ? '#16a34a' : '#86efac'; ?>;">Paid (<?php echo $paid_plate_count; ?>)</a>
                    <a href="vehicles.php?view=all&status=PendingPayment" class="filter-chip <?php echo ($filter_status === 'PendingPayment' && $view_filter === 'all') ? 'pending-style' : 'pending-soft'; ?>" style="background:<?php echo $filter_status === 'PendingPayment' && $view_filter === 'all' ? 'linear-gradient(135deg, #ef4444 0%, #f97316 100%)' : '#fee2e2'; ?>; color:<?php echo $filter_status === 'PendingPayment' && $view_filter === 'all' ? '#fff' : '#991b1b'; ?>; border-color:<?php echo $filter_status === 'PendingPayment' && $view_filter === 'all' ? '#ef4444' : '#fca5a5'; ?>;">Pending Payment (<?php echo $pending_payment_plate_count; ?>)</a>
                    <a href="vehicles.php?view=all&status=Active" class="filter-chip" style="background:<?php echo $filter_status === 'Active' && $view_filter === 'all' ? '#16a34a' : '#dcfce7'; ?>; color:<?php echo $filter_status === 'Active' && $view_filter === 'all' ? '#fff' : '#166534'; ?>; border-color:<?php echo $filter_status === 'Active' && $view_filter === 'all' ? '#16a34a' : '#86efac'; ?>;">Active (<?php echo $active_count; ?>)</a>
                    <a href="vehicles.php?view=all&status=Approved" class="filter-chip" style="background:<?php echo $filter_status === 'Approved' && $view_filter === 'all' ? '#16a34a' : '#dcfce7'; ?>; color:<?php echo $filter_status === 'Approved' && $view_filter === 'all' ? '#fff' : '#166534'; ?>; border-color:<?php echo $filter_status === 'Approved' && $view_filter === 'all' ? '#16a34a' : '#86efac'; ?>;">Approved (<?php echo $approved_count; ?>)</a>
                    <a href="vehicles.php?view=all&status=Expired" class="filter-chip" style="background:<?php echo $filter_status === 'Expired' && $view_filter === 'all' ? '#dc2626' : '#fee2e2'; ?>; color:<?php echo $filter_status === 'Expired' && $view_filter === 'all' ? '#fff' : '#991b1b'; ?>; border-color:<?php echo $filter_status === 'Expired' && $view_filter === 'all' ? '#dc2626' : '#fca5a5'; ?>;">Expired (<?php echo $expired_count; ?>)</a>
                    <a href="vehicles.php?view=all&status=Rejected" class="filter-chip" style="background:<?php echo $filter_status === 'Rejected' && $view_filter === 'all' ? '#dc2626' : '#fee2e2'; ?>; color:<?php echo $filter_status === 'Rejected' && $view_filter === 'all' ? '#fff' : '#991b1b'; ?>; border-color:<?php echo $filter_status === 'Rejected' && $view_filter === 'all' ? '#dc2626' : '#fca5a5'; ?>;">Rejected (<?php echo $rejected_count; ?>)</a>
                </div>

                <div style="margin: 0 0 16px; display:flex; justify-content:flex-end;">
                    <form method="GET" action="vehicles.php" style="display:flex; gap:10px; align-items:center; width:100%; max-width:420px;">
                        <input type="hidden" name="view" value="<?php echo htmlspecialchars($view_filter); ?>">
                        <input type="hidden" name="status" value="<?php echo htmlspecialchars($filter_status); ?>">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search plate, owner, make or model..." style="flex:1; border:1px solid #dbe3f0; border-radius:10px; padding:11px 14px; font-size:14px; background:#f8fafc; color:#0f172a;">
                        <button type="submit" class="btn btn-primary" style="padding:11px 18px; white-space:nowrap;"><i class="fa-solid fa-search"></i> Search</button>
                    </form>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Plate Number</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th>Category</th>
                            <th>Use / Class</th>
                            <th>Vehicle Type</th>
                            <th>Make</th>
                            <th>Model</th>
                            <th>Year</th>
                            <th>Color</th>
                            <th>Owner</th>
                            <th>Contact</th>
                            <th>Email</th>
                            <th>Created On</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($filtered_vehicle_rows as $vehicle): ?>
                        <tr>
                            <td>#<?php echo $vehicle['id']; ?></td>
                            <td><strong><?php echo $vehicle['plate_number']; ?></strong></td>
                            <td>
                                <?php
                                    $status = appEffectivePlateStatus($vehicle['status'] ?? 'Pending', $vehicle['expiry_date'] ?? null);
                                    $status_class = strtolower($status);
                                ?>
                                <span class="status-pill status-<?php echo htmlspecialchars($status_class); ?>" style="display:inline-block;padding:6px 12px;border-radius:999px;font-size:11px;font-weight:700;">
                                    <?php echo htmlspecialchars($status); ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                    $is_existing_plate_row = !empty($vehicle['created_by_applicant']);
                                    if ($is_existing_plate_row) {
                                        $payment_status = in_array(($vehicle['status'] ?? 'Pending'), ['Active', 'Rejected'], true)
                                            ? 'Manual Review'
                                            : 'Manual Review';
                                        $payment_class = 'status-pending';
                                    } else {
                                        $payment_status = $vehicle['payment_status'] ?? 'Pending';
                                        $payment_class = strtolower($payment_status) === 'paid' ? 'status-active' : 'status-pending';
                                    }
                                ?>
                                <span class="status-pill <?php echo htmlspecialchars($payment_class); ?>" style="display:inline-block;padding:6px 12px;border-radius:999px;font-size:11px;font-weight:700;">
                                    <?php echo htmlspecialchars($payment_status); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($vehicle['official_vehicle_category'] ?? 'Private Vehicle'); ?></td>
                            <td><?php echo htmlspecialchars($vehicle['vehicle_use_class'] ?? 'Private Vehicle'); ?></td>
                            <td><?php echo $vehicle['vehicle_type'] ?? '—'; ?></td>
                            <td><?php echo $vehicle['make']; ?></td>
                            <td><?php echo $vehicle['model']; ?></td>
                            <td><?php echo $vehicle['year'] ? $vehicle['year'] : '<span style="color:#94a3b8">—</span>'; ?></td>
                            <td><?php echo $vehicle['color'] ? $vehicle['color'] : '<span style="color:#94a3b8">—</span>'; ?></td>
                            <td><?php echo htmlspecialchars($vehicle['owner_name']); ?></td>
                            <td><?php echo htmlspecialchars(!empty($vehicle['owner_contact_detail']) ? $vehicle['owner_contact_detail'] : ($vehicle['owner_contact'] ?? '—')); ?></td>
                            <td><?php echo htmlspecialchars(!empty($vehicle['owner_email']) ? $vehicle['owner_email'] : '—'); ?></td>
                            <td><?php echo date('M d, Y', strtotime($vehicle['created_at'])); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <?php $current_status = appEffectivePlateStatus($vehicle['status'] ?? 'Pending', $vehicle['expiry_date'] ?? null); ?>
                                    <?php if ($filter_status === 'Blocked' || ($view_filter === 'all' && $filter_status === 'Blocked')): ?>
                                        <form method="POST" action="vehicles.php" style="display:inline;">
                                            <input type="hidden" name="vehicle_id" value="<?php echo $vehicle['id']; ?>">
                                            <input type="hidden" name="vehicle_action" value="block">
                                            <input type="hidden" name="redirect_view" value="<?php echo htmlspecialchars($view_filter); ?>">
                                            <input type="hidden" name="redirect_status" value="<?php echo htmlspecialchars($filter_status); ?>">
                                            <input type="hidden" name="redirect_search" value="<?php echo htmlspecialchars($search); ?>">
                                            <button type="submit" class="btn btn-danger" style="background:<?php echo $current_status === 'Blocked' ? '#94a3b8' : '#4b5563'; ?>;padding:7px 12px;font-size:12px;opacity:<?php echo $current_status === 'Blocked' ? '0.7' : '1'; ?>;cursor:<?php echo $current_status === 'Blocked' ? 'not-allowed' : 'pointer'; ?>;" onclick="return <?php echo $current_status === 'Blocked' ? 'false' : 'confirm(\'Block this vehicle? It will no longer be treated as valid for verification.\')'; ?>;" <?php echo $current_status === 'Blocked' ? 'disabled' : ''; ?>>
                                                <i class="fa-solid fa-ban"></i> Block
                                            </button>
                                        </form>
                                        <form method="POST" action="vehicles.php" style="display:inline;">
                                            <input type="hidden" name="vehicle_id" value="<?php echo $vehicle['id']; ?>">
                                            <input type="hidden" name="vehicle_action" value="unblock">
                                            <input type="hidden" name="redirect_view" value="<?php echo htmlspecialchars($view_filter); ?>">
                                            <input type="hidden" name="redirect_status" value="<?php echo htmlspecialchars($filter_status); ?>">
                                            <input type="hidden" name="redirect_search" value="<?php echo htmlspecialchars($search); ?>">
                                            <button type="submit" class="btn btn-success" style="background:<?php echo $current_status === 'Blocked' ? '#16a34a' : '#a7f3d0'; ?>;color:<?php echo $current_status === 'Blocked' ? '#fff' : '#166534'; ?>;padding:7px 12px;font-size:12px;opacity:<?php echo $current_status === 'Blocked' ? '1' : '0.7'; ?>;cursor:<?php echo $current_status === 'Blocked' ? 'pointer' : 'not-allowed'; ?>;" onclick="return <?php echo $current_status === 'Blocked' ? 'confirm(\'Unblock this vehicle and restore it to active status?\')' : 'false'; ?>;" <?php echo $current_status === 'Blocked' ? '' : 'disabled'; ?>>
                                                <i class="fa-solid fa-lock-open"></i> Unblock
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <?php $is_existing_plate = !empty($vehicle['created_by_applicant']); ?>
                                        <?php $is_payment_ready = true; ?>
                                        <form method="POST" action="vehicles.php" style="display:inline;">
                                            <input type="hidden" name="vehicle_id" value="<?php echo $vehicle['id']; ?>">
                                            <input type="hidden" name="vehicle_action" value="approve">
                                            <input type="hidden" name="redirect_view" value="<?php echo htmlspecialchars($view_filter); ?>">
                                            <input type="hidden" name="redirect_status" value="<?php echo htmlspecialchars($filter_status); ?>">
                                            <input type="hidden" name="redirect_search" value="<?php echo htmlspecialchars($search); ?>">
                                            <button type="submit" class="btn btn-success" style="background:<?php echo $is_payment_ready ? '#16a34a' : '#94a3b8'; ?>;color:white;padding:7px 12px;font-size:12px;opacity:<?php echo $is_payment_ready ? '1' : '0.7'; ?>;cursor:<?php echo $is_payment_ready ? 'pointer' : 'not-allowed'; ?>;" onclick="return <?php echo $is_payment_ready ? 'confirm(\'Approve this vehicle?\')' : 'false'; ?>;" <?php echo $is_payment_ready ? '' : 'disabled'; ?>>
                                                <i class="fa-solid fa-check"></i> Approve
                                            </button>
                                        </form>
                                        <form method="POST" action="vehicles.php" style="display:inline;">
                                            <input type="hidden" name="vehicle_id" value="<?php echo $vehicle['id']; ?>">
                                            <input type="hidden" name="vehicle_action" value="reject">
                                            <input type="hidden" name="redirect_view" value="<?php echo htmlspecialchars($view_filter); ?>">
                                            <input type="hidden" name="redirect_status" value="<?php echo htmlspecialchars($filter_status); ?>">
                                            <input type="hidden" name="redirect_search" value="<?php echo htmlspecialchars($search); ?>">
                                            <button type="submit" class="btn btn-danger" style="padding:7px 12px;font-size:12px;" onclick="return confirm('Reject this vehicle?')"><i class="fa-solid fa-xmark"></i> Reject</button>
                                        </form>
                                    <?php endif; ?>
                                    <a href="edit_vehicle.php?id=<?php echo $vehicle['id']; ?>" class="btn btn-warning"><i class="fa-solid fa-edit"></i> Edit</a>
                                    <a href="delete_vehicle.php?id=<?php echo $vehicle['id']; ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this vehicle?')"><i class="fa-solid fa-trash"></i> Delete</a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
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
