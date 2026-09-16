<?php
session_start();
include "partials/admin-toast.php";
if (!isset($_SESSION['id'])) {
    admin_toast_add("Please log in to view your dashboard.", 'warning');
    header("Location: login.php");
    exit();
}

include "config.php";

// Check if user is an applicant or admin/officer/clerk
if ($_SESSION['role'] !== 'Applicant') {
    // Send admins/staff to admin dashboard
    admin_toast_add("You are signed in as staff. Redirecting to admin dashboard.", 'info');
    header("Location: dashboard.php");
    exit();
}

// Get user's applications
$user_id = $_SESSION['id'];
$applications_query = mysqli_query($conn, "SELECT * FROM applications WHERE applicant_id = '$user_id' OR user_id = '$user_id' ORDER BY application_date DESC");

$applicant_profile = mysqli_fetch_assoc(mysqli_query($conn, "SELECT fullname, contact_number FROM applicants WHERE id = '$user_id' LIMIT 1")) ?: ['fullname' => '', 'contact_number' => ''];
$applicant_fullname = trim((string) ($applicant_profile['fullname'] ?? ''));
$applicant_contact = trim((string) ($applicant_profile['contact_number'] ?? ''));

$vehicle_owner_match = "(
    v.created_by_applicant = '$user_id'
    OR v.created_by = '$user_id'
    OR v.owner_name = '" . mysqli_real_escape_string($conn, $applicant_fullname) . "'
    OR v.owner_contact = '" . mysqli_real_escape_string($conn, $applicant_contact) . "'
    OR EXISTS (
        SELECT 1 FROM applications a_match
        WHERE a_match.vehicle_id = v.id
          AND (a_match.applicant_id = '$user_id' OR a_match.user_id = '$user_id')
    )
)";

$pending_vehicles_count = 0;
$approved_vehicles_count = 0;
$rejected_vehicles_count = 0;

$my_vehicles_query = mysqli_query($conn, "SELECT DISTINCT v.*, 
    CASE
        WHEN v.status IN ('Active','Rejected','Expired') THEN v.status
        WHEN (SELECT a.status FROM applications a WHERE a.vehicle_id = v.id AND a.applicant_id = '$user_id' ORDER BY a.id DESC LIMIT 1) IS NOT NULL THEN (SELECT a.status FROM applications a WHERE a.vehicle_id = v.id AND a.applicant_id = '$user_id' ORDER BY a.id DESC LIMIT 1)
        WHEN (SELECT a.status FROM applications a WHERE a.vehicle_id = v.id AND a.user_id = '$user_id' ORDER BY a.id DESC LIMIT 1) IS NOT NULL THEN (SELECT a.status FROM applications a WHERE a.vehicle_id = v.id AND a.user_id = '$user_id' ORDER BY a.id DESC LIMIT 1)
        ELSE v.status
    END AS plate_status,
    COALESCE((SELECT a.issued_at FROM applications a WHERE a.vehicle_id = v.id AND a.applicant_id = '$user_id' ORDER BY a.id DESC LIMIT 1),
        (SELECT a.issued_at FROM applications a WHERE a.vehicle_id = v.id AND a.user_id = '$user_id' ORDER BY a.id DESC LIMIT 1),
        v.created_at,
        NULL) AS plate_issued_at,
    COALESCE((SELECT a.expires_at FROM applications a WHERE a.vehicle_id = v.id AND a.applicant_id = '$user_id' ORDER BY a.id DESC LIMIT 1),
        (SELECT a.expires_at FROM applications a WHERE a.vehicle_id = v.id AND a.user_id = '$user_id' ORDER BY a.id DESC LIMIT 1),
        v.expiry_date,
        NULL) AS plate_expiry
    FROM vehicles v
    LEFT JOIN applications a ON a.vehicle_id = v.id
    WHERE v.created_by_applicant = '$user_id'
       OR v.created_by = '$user_id'
       OR a.applicant_id = '$user_id'
       OR a.user_id = '$user_id'
    GROUP BY v.id
    ORDER BY v.created_at DESC");

$user_expiring_vehicles = [];
if ($my_vehicles_query) {
    mysqli_data_seek($my_vehicles_query, 0);
    while ($vehicle = mysqli_fetch_assoc($my_vehicles_query)) {
        $resolved_status = appResolvePlateStatus(
            $vehicle['status'] ?? 'Pending',
            $vehicle['expiry_date'] ?? null,
            $vehicle['plate_status'] ?? 'Pending',
            $vehicle['plate_expiry'] ?? null
        );

        if ($resolved_status === 'Pending') {
            $pending_vehicles_count++;
        } elseif (in_array($resolved_status, ['Active', 'Approved'], true)) {
            $approved_vehicles_count++;
        } elseif ($resolved_status === 'Rejected') {
            $rejected_vehicles_count++;
        }

        $vehicle_expiry = !empty($vehicle['plate_expiry']) ? strtotime($vehicle['plate_expiry']) : null;
        if ($vehicle_expiry && $vehicle_expiry >= time() && $vehicle_expiry <= strtotime('+30 days')) {
            $user_expiring_vehicles[] = $vehicle;
        }
    }
}

$pending_renewal_count = 0;
$pending_renewal_result = mysqli_query($conn, "SELECT COUNT(*) as count FROM applications WHERE (applicant_id = '$user_id' OR user_id = '$user_id') AND application_type = 'Renewal' AND status = 'Pending'");
if ($pending_renewal_result && mysqli_num_rows($pending_renewal_result) > 0) {
    $pending_renewal_count = (int) mysqli_fetch_assoc($pending_renewal_result)['count'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dashboard - Transport Union Authority</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/public-footer.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(180deg, #eef4ff 0%, #f8fafc 100%);
            color: #0f172a;
        }

        a {
            text-decoration: none;
        }

        /* Navbar */
        .navbar {
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(10px);
            box-shadow: 0 12px 30px rgba(10, 36, 99, 0.08);
            padding: 18px 50px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 1000;
            border-bottom: 1px solid rgba(148, 163, 184, 0.2);
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .logo-section img {
            height: 68px;
            filter: drop-shadow(0 10px 18px rgba(10, 36, 99, 0.15));
        }

        .logo-text h1 {
            color: #0a2463;
            font-size: 15px;
            font-weight: 800;
            letter-spacing: 0.04em;
        }

        .logo-text p {
            color: #c41e3a;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
        }

        .nav-links {
            display: flex;
            gap: 24px;
            align-items: center;
            flex-wrap: wrap;
            justify-content: center;
        }

        .nav-links a {
            color: #1e293b;
            font-size: 14px;
            font-weight: 700;
            padding: 10px 14px;
            border-radius: 10px;
            transition: all 0.25s ease;
        }

        .nav-links a:hover {
            color: #0a2463;
            background: #eef4ff;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .user-info .welcome {
            color: #0a2463;
            font-weight: 700;
            font-size: 14px;
        }

        .logout-btn {
            background: linear-gradient(135deg, #fef2f2 0%, #fff1f2 100%);
            color: #c41e3a;
            padding: 10px 18px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 700;
            border: 1px solid rgba(196, 30, 58, 0.1);
            box-shadow: 0 8px 18px rgba(196, 30, 58, 0.08);
        }

        /* Content */
        .content {
            padding: 36px 50px 50px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .hero {
            background: linear-gradient(135deg, #0a2463 0%, #163d7a 45%, #0f3460 100%);
            color: white;
            padding: 38px 34px;
            border-radius: 24px;
            margin-bottom: 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 18px 32px rgba(10, 36, 99, 0.18);
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.14), transparent 60%);
            pointer-events: none;
        }

        .hero > * {
            position: relative;
            z-index: 1;
        }

        .hero h2 {
            font-size: clamp(24px, 2.5vw, 36px);
            margin-bottom: 8px;
            line-height: 1.2;
        }

        .hero p {
            opacity: 0.92;
            font-size: 15px;
            max-width: 560px;
        }

        .hero .btn {
            background: linear-gradient(135deg, #c41e3a 0%, #e23b58 100%);
            color: white;
            padding: 14px 22px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 12px 22px rgba(196, 30, 58, 0.2);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .hero .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 16px 26px rgba(196, 30, 58, 0.24);
        }

        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
            padding: 22px 20px;
            border-radius: 18px;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
            display: flex;
            align-items: center;
            gap: 18px;
            border: 1px solid rgba(148, 163, 184, 0.18);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .stat-card:hover,
        .app-card:hover,
        .vehicles-table-wrap:hover {
            transform: translateY(-3px);
            box-shadow: 0 14px 28px rgba(15, 23, 42, 0.08);
        }

        .stat-icon {
            width: 58px;
            height: 58px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.25);
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

        .stat-details h3 {
            color: #64748b;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .stat-details .number {
            color: #0f172a;
            font-size: 32px;
            font-weight: 800;
            line-height: 1.1;
        }

        /* Applications */
        .section-title {
            color: #0a2463;
            font-size: 22px;
            margin: 10px 0 18px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 800;
        }

        .applications-grid {
            display: grid;
            gap: 20px;
        }

        .app-card {
            background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
            padding: 24px;
            border-radius: 18px;
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.06);
            border: 1px solid rgba(148, 163, 184, 0.18);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .app-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
            padding-bottom: 16px;
            border-bottom: 1px solid #eef2f7;
        }

        .app-title-wrap {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .app-label {
            display: inline-flex;
            align-items: center;
            width: fit-content;
            padding: 5px 10px;
            border-radius: 999px;
            background: #edf4ff;
            color: #0a2463;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .app-number {
            color: #0a2463;
            font-weight: 800;
            font-size: 18px;
            letter-spacing: 0.02em;
        }

        .app-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .download-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: 1px solid rgba(10, 36, 99, 0.15);
            border-radius: 10px;
            background: linear-gradient(135deg, #0a2463 0%, #1f4bb6 100%);
            color: #fff;
            padding: 10px 14px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            box-shadow: 0 12px 22px rgba(10, 36, 99, 0.14);
        }

        .download-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 15px 22px rgba(10, 36, 99, 0.18);
        }

        .status-badge,
        .status-pill {
            padding: 7px 14px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .status-Pending,
        .status-pending {
            background: #fff7e6;
            color: #92400e;
        }

        .status-Approved,
        .status-approved,
        .status-active {
            background: #e8f9ef;
            color: #166534;
        }

        .status-warning,
        .status-expiring,
        .status-expiring-soon {
            background: #fff7ed;
            color: #9a5b00;
        }

        .status-Rejected,
        .status-rejected,
        .status-Expired,
        .status-expired,
        .status-Blocked,
        .status-blocked {
            background: #fee2e2;
            color: #991b1b;
        }

        .app-details {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .detail-item {
            color: #475569;
            background: #f8fafc;
            border: 1px solid #edf2f7;
            border-radius: 12px;
            padding: 12px 14px;
        }

        .detail-item strong {
            display: block;
            color: #0f172a;
            margin-bottom: 5px;
            font-size: 12px;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .app-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .app-date {
            color: #64748b;
            font-size: 13px;
        }

        .plate-number {
            color: #0a2463;
            font-weight: 800;
            font-size: 16px;
        }

        .vehicles-table-wrap {
            background: white;
            border-radius: 18px;
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.06);
            overflow: hidden;
            border: 1px solid rgba(148, 163, 184, 0.18);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .vehicles-table {
            width: 100%;
            border-collapse: collapse;
        }

        .vehicles-table thead th {
            background: #f8fafc;
            color: #0a2463;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            padding: 16px 18px;
            text-align: left;
            border-bottom: 1px solid #edf2f7;
        }

        .vehicles-table tbody td {
            padding: 16px 18px;
            border-bottom: 1px solid #edf2f7;
            color: #334155;
            font-size: 14px;
        }

        .vehicles-table tbody tr:hover {
            background: #f8fbff;
        }

        .empty-vehicles-state {
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            border: 1px dashed #cbd5e1;
            border-radius: 18px;
            box-shadow: 0 10px 20px rgba(15, 23, 42, 0.04);
            padding: 42px 20px;
            text-align: center;
            color: #64748b;
        }

        .empty-vehicles-state i {
            font-size: 42px;
            margin-bottom: 14px;
            color: #cbd5e1;
        }

        .empty-vehicles-state p {
            font-size: 15px;
            margin-top: 6px;
        }

        .guidance-panel {
            margin-bottom: 26px;
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 18px;
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.06);
            padding: 24px;
            border-left: 4px solid #0a2463;
        }

        .alert-emphasis {
            display: inline-block;
            background: rgba(153, 27, 27, 0.12);
            border: 1px solid rgba(153, 27, 27, 0.35);
            border-radius: 10px;
            padding: 4px 10px;
            margin-right: 8px;
            font-size: 0.68em;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            font-weight: 900;
            vertical-align: middle;
        }

        .guidance-panel h3 {
            color: #0a2463;
            font-size: 19px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 9px;
        }

        .guidance-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .guidance-item {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 14px;
        }

        .guidance-item strong {
            color: #0f172a;
            display: block;
            margin-bottom: 6px;
            font-size: 14px;
        }

        .guidance-item p {
            color: #475569;
            font-size: 13px;
            line-height: 1.7;
        }

        .guidance-note {
            margin-top: 12px;
            color: #334155;
            font-size: 13px;
            line-height: 1.7;
        }

        .guidance-note a {
            color: #0a2463;
            text-decoration: none;
            font-weight: 700;
        }

        .guidance-note a:hover {
            text-decoration: underline;
        }

        @media (max-width: 980px) {
            .navbar {
                padding: 18px 20px;
                flex-wrap: wrap;
                gap: 14px;
            }

            .nav-links,
            .user-info {
                justify-content: center;
                width: 100%;
            }

            .content {
                padding: 24px 20px 36px;
            }

            .hero {
                flex-direction: column;
                align-items: flex-start;
                gap: 18px;
            }

            .hero .btn {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 700px) {
            .guidance-grid,
            .app-details {
                grid-template-columns: 1fr;
            }

            .vehicles-table-wrap {
                overflow-x: auto;
            }

            .vehicles-table {
                min-width: 560px;
            }

            .app-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .app-actions {
                justify-content: flex-start;
            }
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
            <a href="user-dashboard.php"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
            <a href="apply.php"><i class="fa-solid fa-file-contract"></i> New Application</a>
            <a href="register-existing-plate.php"><i class="fa-solid fa-car-side"></i> Register Existing Plate</a>
        </div>
        <div class="user-info">
            <span class="welcome"><i class="fa-solid fa-user"></i> Welcome <?php echo htmlspecialchars($_SESSION['fullname']); ?>.</span>
            <a href="logout.php" class="logout-btn"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
        </div>
    </nav>

    <!-- Content -->
    <div class="content">
        <div class="container">
            <div class="hero">
                <div>
                    <h2>Welcome <?php echo htmlspecialchars($_SESSION['fullname']); ?>.</h2>
                    <p>Manage your license plate applications and check their status.</p>
                </div>
                <div style="display:flex; gap:12px; flex-wrap:wrap; justify-content:flex-end;">
                    <a href="apply.php" class="btn">
                        <i class="fa-solid fa-plus"></i> Apply for License Plate
                    </a>
                    <a href="register-existing-plate.php" class="btn" style="background:#0f766e;">
                        <i class="fa-solid fa-car-side"></i> Register Existing Plate
                    </a>
                </div>
            </div>

            <?php
                $blocked_or_rejected_vehicles = [];
                mysqli_data_seek($my_vehicles_query, 0);
                while ($vehicle = mysqli_fetch_assoc($my_vehicles_query)) {
                    $resolved_status = appResolvePlateStatus(
                        $vehicle['status'] ?? 'Pending',
                        $vehicle['expiry_date'] ?? null,
                        $vehicle['plate_status'] ?? 'Pending',
                        $vehicle['plate_expiry'] ?? null
                    );
                    if (in_array($resolved_status, ['Rejected', 'Blocked'], true)) {
                        $blocked_or_rejected_vehicles[] = $vehicle;
                    }
                }
            ?>

            <?php if (!empty($blocked_or_rejected_vehicles)): ?>
                <?php
                    $blocked_plates = array_filter($blocked_or_rejected_vehicles, function ($issue_vehicle) {
                        $resolved_status = appResolvePlateStatus(
                            $issue_vehicle['status'] ?? 'Pending',
                            $issue_vehicle['expiry_date'] ?? null,
                            $issue_vehicle['plate_status'] ?? 'Pending',
                            $issue_vehicle['plate_expiry'] ?? null
                        );
                        return $resolved_status === 'Blocked';
                    });
                    $rejected_plates = array_filter($blocked_or_rejected_vehicles, function ($issue_vehicle) {
                        $resolved_status = appResolvePlateStatus(
                            $issue_vehicle['status'] ?? 'Pending',
                            $issue_vehicle['expiry_date'] ?? null,
                            $issue_vehicle['plate_status'] ?? 'Pending',
                            $issue_vehicle['plate_expiry'] ?? null
                        );
                        return $resolved_status === 'Rejected';
                    });
                ?>
                <div class="guidance-panel" style="border-left: 4px solid #dc2626; background: linear-gradient(180deg, #fef2f2 0%, #ffffff 100%); box-shadow: 0 18px 32px rgba(127, 29, 29, 0.12);">
                    <h3 style="color:#991b1b;"><i class="fa-solid fa-triangle-exclamation"></i> Verification Required</h3>
                    <p style="color:#991b1b; font-weight:800; line-height:1.8; font-size:15px;">
                        <?php if (!empty($blocked_plates)): ?>
                            <span class="alert-emphasis">Blocked</span> URGENT: One or more of your plates have been blocked and are not valid for use. Please visit the nearest office immediately for verification and correction.
                        <?php elseif (!empty($rejected_plates)): ?>
                            <span class="alert-emphasis">Rejected</span> One or more of your plates have been rejected. Please visit the nearest office for verification and correction.
                        <?php else: ?>
                            <span class="alert-emphasis">Action</span> One or more of your plates have been rejected or blocked. Please visit the nearest office for verification and correction.
                        <?php endif; ?>
                        <?php $issue_names = []; foreach ($blocked_or_rejected_vehicles as $issue_vehicle) { $issue_names[] = htmlspecialchars(($issue_vehicle['plate_number'] ?? 'Plate') . ' (' . ($issue_vehicle['plate_status'] ?? 'Rejected') . ')'); } echo implode(', ', $issue_names); ?>
                    </p>
                </div>
            <?php endif; ?>

            <?php if (!empty($user_expiring_vehicles)): ?>
                <div class="guidance-panel" style="border-left: 4px solid #f59e0b; background: linear-gradient(180deg, #fff7ed 0%, #ffffff 100%);">
                    <h3 style="color:#92400e;"><i class="fa-solid fa-triangle-exclamation"></i> Renewal Notice</h3>
                    <p style="color:#92400e; font-weight:600; line-height:1.7;">
                        One or more of your plates are nearing expiry. Please renew them soon to avoid service interruption.
                        <?php $warning_names = []; foreach ($user_expiring_vehicles as $warning_vehicle) { $warning_names[] = htmlspecialchars(($warning_vehicle['plate_number'] ?? 'Plate') . ' (' . date('M d, Y', strtotime($warning_vehicle['plate_expiry'])) . ')'); } echo implode(', ', $warning_names); ?>
                    </p>
                    <div style="margin-top: 16px; display: flex; flex-wrap: wrap; gap: 10px;">
                        <a href="renew.php" class="btn" style="background: linear-gradient(135deg, #f59e0b 0%, #b45309 100%); color: white; padding: 12px 18px; border-radius: 12px; display: inline-flex; align-items: center; gap: 8px; font-weight: 700;">
                            <i class="fa-solid fa-rotate-right"></i> Renew Now
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="stat-grid">
                <div class="stat-card">
                    <div class="stat-icon blue">
                        <i class="fa-solid fa-file-lines"></i>
                    </div>
                    <div class="stat-details">
                        <h3>Total Applications</h3>
                        <div class="number"><?php echo mysqli_num_rows($applications_query); ?></div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon orange">
                        <i class="fa-solid fa-clock"></i>
                    </div>
                    <div class="stat-details">
                        <h3>Pending</h3>
                        <div class="number">
                            <?php echo $pending_vehicles_count; ?>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon green">
                        <i class="fa-solid fa-check-circle"></i>
                    </div>
                    <div class="stat-details">
                        <h3>Approved</h3>
                        <div class="number">
                            <?php echo $approved_vehicles_count; ?>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon red">
                        <i class="fa-solid fa-times-circle"></i>
                    </div>
                    <div class="stat-details">
                        <h3>Rejected</h3>
                        <div class="number">
                            <?php echo $rejected_vehicles_count; ?>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon orange" style="background: linear-gradient(135deg, #f59e0b 0%, #b45309 100%);">
                        <i class="fa-solid fa-rotate-right"></i>
                    </div>
                    <div class="stat-details">
                        <h3>Renewals</h3>
                        <div class="number"><?php echo $pending_renewal_count; ?></div>
                    </div>
                </div>
            </div>

            <div class="guidance-panel">
                <h3><i class="fa-solid fa-circle-info"></i> Application Status and Support Guide</h3>
                <div class="guidance-grid">
                    <div class="guidance-item">
                        <strong>Pending</strong>
                        <p>Your request is under review. Keep your phone active for verification contact.</p>
                    </div>
                    <div class="guidance-item">
                        <strong>Approved</strong>
                        <p>Your application passed review and moves to payment and plate issuance steps.</p>
                    </div>
                    <div class="guidance-item">
                        <strong>Rejected</strong>
                        <p>Your request needs correction. You may reapply with updated records and documents.</p>
                    </div>
                    <div class="guidance-item">
                        <strong>Need Help or Appeal?</strong>
                        <p>Contact support with your application number for dispute review or correction guidance.</p>
                    </div>
                </div>
                <p class="guidance-note">For policy details, review <a href="terms.php">Terms & Conditions</a> and <a href="privacy.php">Privacy Policy</a>. For direct assistance, use <a href="contact.php">Contact Us</a>.</p>
            </div>

            <h3 class="section-title"><i class="fa-solid fa-car-side"></i> My Vehicles</h3>
            <div class="vehicles-table-wrap">
                <?php if ($my_vehicles_query && mysqli_num_rows($my_vehicles_query) > 0): ?>
                    <?php mysqli_data_seek($my_vehicles_query, 0); ?>
                    <table class="vehicles-table">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Use / Class</th>
                                <th>Vehicle</th>
                                <th>Plate</th>
                                <th>Issued</th>
                                <th>Status</th>
                                <th>Expiry</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($vehicle = mysqli_fetch_assoc($my_vehicles_query)): ?>
                                <?php
                                    $vehicle_name = trim(($vehicle['make'] ?? '') . ' ' . ($vehicle['model'] ?? ''));
                                    $vehicle_category_label = trim((string) ($vehicle['official_vehicle_category'] ?? ($vehicle['vehicle_use_class'] ?? 'Private Vehicle')));
                                    $vehicle_is_motorcycle = appIsMotorcycleCategory($vehicle['official_vehicle_category'] ?? null, $vehicle['vehicle_use_class'] ?? null);
                                    $vehicle_title_label = $vehicle_is_motorcycle ? 'Bike' : 'Vehicle';
                                    $vehicle_status_value = strtoupper((string) ($vehicle['plate_status'] ?? 'Pending'));
                                    $vehicle_expiry = !empty($vehicle['plate_expiry']) ? date('Y-m-d', strtotime($vehicle['plate_expiry'])) : null;
                                    $vehicle_expiry_epoch = $vehicle_expiry ? strtotime($vehicle_expiry) : null;
                                    $vehicle_issued = !empty($vehicle['plate_issued_at']) ? date('Y-m-d', strtotime($vehicle['plate_issued_at'])) : null;
                                    $effective_status = appResolvePlateStatus(
                                        $vehicle['status'] ?? 'Pending',
                                        $vehicle['expiry_date'] ?? null,
                                        $vehicle['plate_status'] ?? 'Pending',
                                        $vehicle['plate_expiry'] ?? null
                                    );
                                    $status_label = 'Pending';
                                    $status_class = 'pending';
                                    $action_label = '—';
                                    $action_url = null;
                                    $action_class = 'muted';

                                    if ($effective_status === 'Expired') {
                                        $status_label = 'Expired';
                                        $status_class = 'expired';
                                        $action_label = 'Renew';
                                        $action_url = 'renew.php?plate=' . urlencode((string) ($vehicle['plate_number'] ?? ''));
                                        $action_class = 'renew';
                                    } elseif ($effective_status === 'Rejected') {
                                        $status_label = 'Rejected';
                                        $status_class = 'rejected';
                                        $action_label = '—';
                                    } elseif ($effective_status === 'Blocked') {
                                        $status_label = 'Blocked';
                                        $status_class = 'blocked';
                                        $action_label = 'Visit Office';
                                        $action_url = 'contact.php';
                                        $action_class = 'renew';
                                    } elseif ($effective_status === 'Active') {
                                        $status_label = 'Active';
                                        $status_class = 'active';
                                        $action_label = 'View';
                                        $action_url = 'verify.php?search=' . urlencode((string) ($vehicle['plate_number'] ?? ''));
                                        $action_class = 'view';
                                        if ($vehicle_expiry_epoch !== null && $vehicle_expiry_epoch >= time() && $vehicle_expiry_epoch <= strtotime('+30 days')) {
                                            $status_label = 'Expiring Soon';
                                            $status_class = 'expiring';
                                            $action_label = 'Renew';
                                            $action_url = 'renew.php?plate=' . urlencode((string) ($vehicle['plate_number'] ?? ''));
                                            $action_class = 'renew';
                                        }
                                    } elseif ($effective_status === 'Approved') {
                                        $status_label = 'Active';
                                        $status_class = 'active';
                                        $action_label = 'View';
                                        $action_url = 'verify.php?search=' . urlencode((string) ($vehicle['plate_number'] ?? ''));
                                        $action_class = 'view';
                                    } elseif ($vehicle_expiry_epoch !== null && $vehicle_expiry_epoch >= time() && $vehicle_expiry_epoch <= strtotime('+30 days')) {
                                        $status_label = 'Expiring Soon';
                                        $status_class = 'expiring';
                                        $action_label = 'Renew';
                                        $action_url = 'renew.php?plate=' . urlencode((string) ($vehicle['plate_number'] ?? ''));
                                        $action_class = 'renew';
                                    }
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($vehicle_category_label !== '' ? $vehicle_category_label : 'Private Vehicle'); ?></td>
                                    <td><?php echo htmlspecialchars($vehicle['vehicle_use_class'] ?? 'Private Vehicle'); ?></td>
                                    <td><?php echo htmlspecialchars($vehicle_name !== '' ? $vehicle_name : $vehicle_title_label); ?></td>
                                    <td><?php echo htmlspecialchars($vehicle['plate_number'] ?? '—'); ?></td>
                                    <td><?php echo htmlspecialchars($vehicle_issued ? date('M d, Y', strtotime($vehicle_issued)) : '—'); ?></td>
                                    <td><span class="status-pill status-<?php echo $status_class; ?>"><?php echo htmlspecialchars($status_label); ?></span></td>
                                    <td><?php echo htmlspecialchars($vehicle_expiry ? date('M d, Y', strtotime($vehicle_expiry)) : '—'); ?></td>
                                    <td>
                                        <?php if ($action_url && !empty($vehicle['plate_number'])): ?>
                                            <a href="<?php echo htmlspecialchars($action_url); ?>" class="btn" style="background: <?php echo $action_class === 'renew' ? 'linear-gradient(135deg, #f59e0b 0%, #b45309 100%)' : 'linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%)'; ?>; color: white; padding: 9px 12px; border-radius: 10px; font-size: 12px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px;">
                                                <i class="fa-solid <?php echo $action_class === 'renew' ? 'fa-rotate-right' : 'fa-eye'; ?>"></i> <?php echo htmlspecialchars($action_label); ?>
                                            </a>
                                        <?php else: ?>
                                            <span style="color:#94a3b8; font-size:12px;">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-vehicles-state">
                        <i class="fa-solid fa-car-side"></i>
                        <p>No vehicles are registered for your account yet.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Applications List -->
            <h3 class="section-title"><i class="fa-solid fa-list"></i> My Applications</h3>
            <div class="applications-grid">
                <?php if (mysqli_num_rows($applications_query) > 0): ?>
                    <?php while ($app = mysqli_fetch_assoc($applications_query)): ?>
                        <div class="app-card">
                            <div class="app-header">
                                <div class="app-title-wrap">
                                    <span class="app-label">Application ID</span>
                                    <div class="app-number"><?php echo $app['application_number']; ?></div>
                                </div>
                                <div class="app-actions">
                                    <button
                                        type="button"
                                        class="download-btn"
                                        data-app='<?php echo json_encode([
                                            'number' => $app['application_number'],
                                            'status' => $app['status'],
                                            'county' => $app['county'],
                                            'applicant_name' => $app['applicant_name'],
                                            'applicant_contact' => $app['applicant_contact'] ?: '-',
                                            'application_date' => date('M d, Y', strtotime($app['application_date'])),
                                            'license_plate_number' => $app['license_plate_number'] ?: 'Not assigned yet',
                                        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>'>
                                        <i class="fa-solid fa-download"></i> Download
                                    </button>
                                    <?php
                                        $request_type = strtoupper((string) ($app['application_type'] ?? 'New'));
                                        $request_label = 'New';
                                        if ($request_type === 'RENEWAL') {
                                            $request_label = 'Renewal';
                                        } elseif ($request_type === 'EXISTING PLATE') {
                                            $request_label = 'Existing Plate';
                                        }
                                    ?>
                                    <span class="status-badge status-<?php echo strtolower($request_type === 'RENEWAL' ? 'pending' : $app['status']); ?>"><?php echo htmlspecialchars($request_label); ?></span>
                                    <span class="status-badge status-<?php echo $app['status']; ?>"><?php echo $app['status']; ?></span>
                                </div>
                            </div>
                            <div class="app-details">
                                <div class="detail-item">
                                    <strong>County</strong>
                                    <?php echo $app['county']; ?>
                                </div>
                                <div class="detail-item">
                                    <strong>Applicant</strong>
                                    <?php echo $app['applicant_name']; ?>
                                </div>
                                <div class="detail-item">
                                    <strong>Contact</strong>
                                    <?php echo $app['applicant_contact'] ? $app['applicant_contact'] : '-'; ?>
                                </div>
                            </div>
                            <div class="app-footer">
                                <div class="app-date">
                                    <i class="fa-solid fa-calendar"></i> <?php echo date('M d, Y', strtotime($app['application_date'])); ?>
                                </div>
                                <?php if ($app['license_plate_number']): ?>
                                    <div class="plate-number">
                                        <i class="fa-solid fa-id-card"></i> <?php echo $app['license_plate_number']; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="plate-number" style="color:#64748b; font-weight:600;">
                                        <i class="fa-solid fa-hourglass-half"></i> Awaiting plate
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="text-align: center; padding: 60px; background: white; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
                        <i class="fa-solid fa-folder-open" style="font-size: 60px; color: #cbd5e1; margin-bottom: 20px;"></i>
                        <h3 style="color: #0a2463; margin-bottom: 10px;">No Applications Yet</h3>
                        <p style="color: #64748b; margin-bottom: 25px;">You haven't applied for any license plates yet.</p>
                        <a href="apply.php" style="background: linear-gradient(135deg, #c41e3a 0%, #a01830 100%); color: white; padding: 12px 28px; border-radius: 10px; text-decoration: none; font-weight: 700; display: inline-block;">
                            <i class="fa-solid fa-plus"></i> Apply Now
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include "partials/public-footer.php"; ?>

    <script>
        document.addEventListener('click', function (event) {
            const button = event.target.closest('.download-btn');
            if (!button) return;

            const app = JSON.parse(button.getAttribute('data-app'));
            const html = `
                <!doctype html>
                <html lang="en">
                <head>
                    <meta charset="UTF-8" />
                    <title>Application Record - ${app.number}</title>
                    <style>
                        body {
                            font-family: Arial, sans-serif;
                            margin: 36px;
                            color: #0f172a;
                            background: #ffffff;
                        }
                        .document {
                            max-width: 980px;
                            margin: 0 auto;
                            border: 1px solid #dfe8f4;
                            border-radius: 18px;
                            padding: 28px 30px 22px;
                            background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
                            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
                        }
                        .header {
                            display: flex;
                            justify-content: space-between;
                            align-items: center;
                            gap: 20px;
                            padding-bottom: 18px;
                            border-bottom: 3px solid #0a2463;
                        }
                        .brand {
                            display: flex;
                            align-items: center;
                            gap: 16px;
                        }
                        .seal {
                            width: 62px;
                            height: 62px;
                            border-radius: 50%;
                            display: grid;
                            place-items: center;
                            background: linear-gradient(135deg, #0a2463 0%, #1d4ed8 100%);
                            color: #fff;
                            font-weight: 900;
                            font-size: 18px;
                            letter-spacing: 0.08em;
                        }
                        .title {
                            font-size: 28px;
                            font-weight: 800;
                            letter-spacing: 0.05em;
                            color: #0a2463;
                            text-transform: uppercase;
                            line-height: 1.2;
                        }
                        .subtitle {
                            font-size: 12px;
                            letter-spacing: 0.16em;
                            text-transform: uppercase;
                            color: #64748b;
                            margin-top: 6px;
                        }
                        .doc-tag {
                            padding: 10px 14px;
                            border-radius: 10px;
                            background: #eef4ff;
                            border: 1px solid #d9e6ff;
                            color: #0a2463;
                            font-size: 11px;
                            font-weight: 800;
                            letter-spacing: 0.12em;
                            text-transform: uppercase;
                            white-space: nowrap;
                        }
                        .section-title {
                            margin: 24px 0 14px;
                            color: #0a2463;
                            font-size: 22px;
                            text-transform: uppercase;
                            letter-spacing: 0.08em;
                        }
                        .meta {
                            display: grid;
                            grid-template-columns: repeat(2, minmax(0, 1fr));
                            gap: 14px 18px;
                            margin-top: 8px;
                        }
                        .row {
                            border: 1px solid #e2e8f0;
                            border-radius: 12px;
                            padding: 12px 14px;
                            background: #ffffff;
                        }
                        .label {
                            display: block;
                            font-size: 10px;
                            color: #64748b;
                            font-weight: 800;
                            letter-spacing: 0.12em;
                            text-transform: uppercase;
                            margin-bottom: 8px;
                        }
                        .value {
                            font-size: 16px;
                            font-weight: 700;
                            color: #0f172a;
                            line-height: 1.5;
                        }
                        .full-row {
                            grid-column: 1 / -1;
                        }
                        .declaration {
                            margin-top: 24px;
                            padding: 16px 18px;
                            background: #f8fafc;
                            border-left: 4px solid #0a2463;
                            border-radius: 10px;
                            color: #334155;
                            line-height: 1.7;
                            font-size: 14px;
                        }
                        .signature-row {
                            display: grid;
                            grid-template-columns: repeat(3, minmax(0, 1fr));
                            gap: 18px;
                            margin-top: 28px;
                        }
                        .sign-box {
                            border-top: 2px solid #0a2463;
                            padding-top: 12px;
                            color: #475569;
                            font-size: 12px;
                            font-weight: 700;
                            letter-spacing: 0.1em;
                            text-transform: uppercase;
                        }
                        .sign-line {
                            margin-top: 26px;
                            border-top: 1px dashed #a8bacd;
                            height: 10px;
                        }
                        .footer {
                            margin-top: 28px;
                            border-top: 2px solid #e2e8f0;
                            padding-top: 14px;
                            color: #475569;
                            font-size: 12px;
                            display: flex;
                            justify-content: space-between;
                            gap: 20px;
                        }
                    </style>
                </head>
                <body>
                    <div class="document">
                        <div class="header">
                            <div class="brand">
                                <div class="seal">TUA</div>
                                <div>
                                    <div class="title">Transport Union Authority</div>
                                    <div class="subtitle">Republic of Liberia</div>
                                </div>
                            </div>
                            <div class="doc-tag">Official Record</div>
                        </div>

                        <div class="section-title">Application Record</div>

                        <div class="meta">
                            <div class="row">
                                <span class="label">Application ID</span>
                                <div class="value">${app.number}</div>
                            </div>
                            <div class="row">
                                <span class="label">Status</span>
                                <div class="value">${app.status}</div>
                            </div>
                            <div class="row">
                                <span class="label">County</span>
                                <div class="value">${app.county}</div>
                            </div>
                            <div class="row">
                                <span class="label">Application Date</span>
                                <div class="value">${app.application_date}</div>
                            </div>
                            <div class="row full-row">
                                <span class="label">Applicant Name</span>
                                <div class="value">${app.applicant_name}</div>
                            </div>
                            <div class="row">
                                <span class="label">Applicant Contact</span>
                                <div class="value">${app.applicant_contact}</div>
                            </div>
                            <div class="row">
                                <span class="label">License Plate Number</span>
                                <div class="value">${app.license_plate_number}</div>
                            </div>
                        </div>

                        <div class="declaration">
                            This document confirms that the above application has been submitted and recorded by the Transport Union Authority.
                            All records are presented in accordance with the official administration process and are intended for verification and processing purposes.
                        </div>

                        <div class="signature-row">
                            <div class="sign-box">
                                Applicant Signature
                                <div class="sign-line"></div>
                            </div>
                            <div class="sign-box">
                                Officer Review
                                <div class="sign-line"></div>
                            </div>
                            <div class="sign-box">
                                Authority Stamp
                                <div class="sign-line"></div>
                            </div>
                        </div>

                        <div class="footer">
                            <span>Generated by TUA Official Portal</span>
                            <span>${new Date().toLocaleDateString()}</span>
                        </div>
                    </div>
                </body>
                </html>
            `;

            const blob = new Blob([html], { type: 'text/html;charset=utf-8' });
            const url = URL.createObjectURL(blob);
            const downloadLink = document.createElement('a');
            downloadLink.href = url;
            downloadLink.download = 'application_' + app.number.replace(/[^a-zA-Z0-9_-]/g, '_') + '.html';
            document.body.appendChild(downloadLink);
            downloadLink.click();
            document.body.removeChild(downloadLink);
            URL.revokeObjectURL(url);
        });
    </script>
</body>
</html>