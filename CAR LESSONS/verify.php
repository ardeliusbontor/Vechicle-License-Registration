<?php
include "config.php";

$result = null;
$found = false;
$search = '';

function buildCountyPlateCode($county) {
    $normalized = strtoupper(trim((string) $county));
    if ($normalized === '') {
        return 'LIB';
    }

    $parts = preg_split('/\s+/', preg_replace('/[^A-Z\s]/', '', $normalized));
    $parts = array_values(array_filter($parts, function ($part) {
        return $part !== '' && $part !== 'COUNTY';
    }));

    if (count($parts) >= 2) {
        return substr($parts[0], 0, 2) . substr($parts[1], 0, 2);
    }

    return substr($parts[0], 0, 4);
}

function buildVerificationUrl($searchValue) {
    return appVerificationUrl($searchValue);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $search = mysqli_real_escape_string($conn, $_POST['search']);
} elseif (isset($_GET['search']) && $_GET['search'] !== '') {
    $search = mysqli_real_escape_string($conn, $_GET['search']);
}

if ($search !== '') {
    $search_sql = mysqli_real_escape_string($conn, $search);
    $county_column_exists = false;
    $county_check = mysqli_query($conn, "SHOW COLUMNS FROM vehicles LIKE 'county'");
    if ($county_check && mysqli_num_rows($county_check) > 0) {
        $county_column_exists = true;
    }

    $search_match = "(
        a.id = '$search_sql'
        OR a.license_plate_number = '$search_sql'
        OR a.license_plate_number LIKE '%$search_sql%'
        OR a.application_number = '$search_sql'
        OR a.application_number LIKE '%$search_sql%'
        OR v.plate_number = '$search_sql'
        OR v.plate_number LIKE '%$search_sql%'
    )";

    $application_query = "SELECT a.*, v.plate_number, v.make, v.model, v.owner_name, v.owner_contact, v.expiry_date, v.status AS vehicle_status,
                            COALESCE(a.license_plate_number, v.plate_number) AS display_plate,
                            a.status AS record_status,
                            COALESCE(a.expires_at, v.expiry_date) AS effective_expiry
                         FROM applications a
                         LEFT JOIN vehicles v ON a.vehicle_id = v.id
                         WHERE " . $search_match . "
                         AND TRIM(COALESCE(a.license_plate_number, v.plate_number, '')) <> ''
                         AND a.status IN ('Pending', 'Approved', 'Active', 'Rejected', 'Blocked', 'Expired')";

    $result = mysqli_query($conn, $application_query);

    if (!$result || mysqli_num_rows($result) === 0) {
        $vehicle_only_query = "SELECT v.*, NULL AS id, v.status AS record_status, v.expiry_date AS effective_expiry,
                                v.plate_number AS display_plate, v.plate_number AS plate_number
                             FROM vehicles v
                             WHERE v.plate_number = '$search_sql'
                               OR v.plate_number LIKE '%$search_sql%'
                               OR v.id = '$search_sql'";

        $vehicle_result = mysqli_query($conn, $vehicle_only_query);
        if ($vehicle_result && mysqli_num_rows($vehicle_result) > 0) {
            $result = $vehicle_result;
        }
    }

    $found = $result && mysqli_num_rows($result) > 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify License Plate - Transport Union Authority</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/public-footer.css">
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
            padding: 60px 50px;
        }

        .container {
            max-width: 800px;
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

        .search-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.08);
            padding: 40px;
            margin-bottom: 30px;
        }

        .search-form {
            display: flex;
            gap: 15px;
        }

        .search-form input {
            flex: 1;
            padding: 16px 24px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 16px;
            outline: none;
            transition: all 0.3s;
        }

        .search-form input:focus {
            border-color: #0a2463;
            box-shadow: 0 0 0 4px rgba(10, 36, 99, 0.05);
        }

        .btn-search {
            background: linear-gradient(135deg, #0a2463 0%, #0f3460 100%);
            color: white;
            padding: 0 30px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-search:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(10, 36, 99, 0.4);
        }

        /* Results */
        .results-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.08);
            padding: 40px;
        }

        .result-item {
            border: 1px solid #e2e8f0;
            border-radius: 22px;
            padding: 25px;
            margin-bottom: 20px;
            background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
        }

        .result-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .plate-display {
            position: relative;
            display: grid;
            grid-template-columns: 76px 1fr;
            width: min(100%, 520px);
            min-height: 220px;
            border-radius: 20px;
            overflow: hidden;
            background:
                linear-gradient(180deg, rgba(255,255,255,0.16), transparent 18%),
                linear-gradient(180deg, #ef8d2f 0%, #ea7f1c 100%);
            border: 5px solid #d96f12;
            box-shadow: 0 16px 35px rgba(15, 23, 42, 0.16);
            padding: 0;
        }

        .plate-display::before,
        .plate-display::after,
        .plate-display .plate-bolt {
            content: "";
            position: absolute;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: radial-gradient(circle at 30% 30%, #ffffff 0%, #d9d9d9 38%, #9ca3af 100%);
            border: 2px solid rgba(15, 23, 42, 0.28);
            box-shadow: inset 0 1px 2px rgba(255,255,255,0.85);
        }

        .plate-display::before {
            top: 14px;
            left: 22px;
        }

        .plate-display::after {
            bottom: 14px;
            right: 22px;
        }

        .plate-display .plate-bolt {
            bottom: 14px;
            left: 22px;
        }

        .plate-side {
            background: linear-gradient(180deg, #f8fafc 0%, #e5eef7 100%);
            border-right: 2px solid rgba(15, 23, 42, 0.18);
            padding: 14px 8px 12px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
        }

        .plate-side img {
            width: 38px;
            height: 28px;
            object-fit: cover;
            border-radius: 3px;
            border: 1px solid rgba(15, 23, 42, 0.12);
        }

        .plate-display .l-badge {
            width: 46px;
            height: 46px;
            border-radius: 50%;
            border: 2px solid #64748b;
            color: #0f172a;
            font-weight: 800;
            font-size: 20px;
            background: rgba(255,255,255,0.88);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .plate-qr {
            width: 52px;
            min-height: 52px;
            padding: 4px;
            border-radius: 10px;
            background: rgba(255,255,255,0.94);
            border: 1px solid rgba(15, 23, 42, 0.12);
            box-shadow: 0 6px 14px rgba(15, 23, 42, 0.08);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 8px 0;
        }

        .plate-qr img {
            display: block;
            width: 100% !important;
            height: auto !important;
        }

        .plate-main {
            display: flex;
            flex-direction: column;
            padding: 14px 18px 16px;
            background-image: linear-gradient(90deg, rgba(255,255,255,0.06), transparent 15%, transparent 85%, rgba(0,0,0,0.05));
        }

        .plate-topline {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .plate-country {
            color: #22170f;
            font-size: 28px;
            font-weight: 900;
            letter-spacing: 0.16em;
            text-transform: uppercase;
        }

        .plate-month,
        .plate-year {
            min-width: 62px;
            padding: 7px 10px;
            background: linear-gradient(180deg, #2d6ee7 0%, #1f55c8 100%);

            .plate-qr {
                width: 42px;
                min-height: 42px;
            }
            color: white;
            border-radius: 8px;
            font-weight: 900;
            text-align: center;
            box-shadow: inset 0 -2px 0 rgba(0,0,0,0.12), 0 3px 6px rgba(15, 23, 42, 0.15);
            border: 1px solid rgba(255,255,255,0.18);
        }

        .plate-month {
            font-size: 18px;
            line-height: 1;
        }

        .plate-month small,
        .plate-year small {
            display: block;
            font-size: 8px;
            font-weight: 700;
            letter-spacing: 0.08em;
            margin-top: 3px;
        }

        .plate-number-wrap {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .plate-number {
            font-family: Impact, Haettenschweiler, 'Arial Narrow Bold', sans-serif;
            font-size: 96px;
            line-height: 0.88;
            font-weight: 900;
            color: #111827;
            letter-spacing: 0.025em;
            text-transform: uppercase;
            text-shadow: 1px 2px 0 rgba(255,255,255,0.14), -1px -1px 0 rgba(0,0,0,0.06);
        }

        .plate-bottomline {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 14px;
        }

        .plate-owner {
            color: rgba(17, 24, 39, 0.78);
            font-size: 10px;
            line-height: 1.55;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .plate-county-flag {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 70px;
            padding: 8px 12px;
            border-radius: 14px;
            background: rgba(255,255,255,0.16);
            border: 1px solid rgba(255,255,255,0.24);
            color: #fff7ed;
            font-size: 22px;
            font-weight: 900;
            letter-spacing: 0.12em;
            text-shadow: 0 1px 0 rgba(0,0,0,0.16);
        }

        .status-badge {
            padding: 8px 20px;
            border-radius: 25px;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.06em;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 120px;
            transition: all 0.3s ease;
        }

        .status-alert {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px 16px;
            border-radius: 14px;
            font-weight: 800;
            margin-bottom: 18px;
            border: 1px solid transparent;
            animation: pulseAlert 1.8s ease-in-out infinite;
        }

        @keyframes pulseAlert {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.01); opacity: 0.88; }
        }

        .status-alert.active {
            background: linear-gradient(135deg, #dcfce7 0%, #f0fdf4 100%);
            border-color: #86efac;
            color: #166534;
            box-shadow: 0 12px 28px rgba(34, 197, 94, 0.12);
        }

        .status-alert.expired,
        .status-alert.rejected,
        .status-alert.blocked {
            background: linear-gradient(135deg, #450a0a 0%, #7f1d1d 28%, #fecaca 100%);
            border: 3px solid #fca5a5;
            color: #fff7f7;
            box-shadow: 0 18px 36px rgba(127, 29, 29, 0.34), inset 0 0 0 1px rgba(255,255,255,0.18);
            font-size: 16px;
            padding: 18px 20px;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.15);
        }

        .alert-emphasis {
            display: inline-block;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.45);
            border-radius: 10px;
            padding: 4px 10px;
            margin-right: 8px;
            font-size: 0.72em;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            font-weight: 900;
            vertical-align: middle;
        }

        .status-badge.pending {
            background: #fef3c7;
            color: #b45309;
        }

        .status-badge.approved,
        .status-badge.active {
            background: linear-gradient(135deg, #dcfce7 0%, #f0fdf4 100%);
            color: #166534;
            box-shadow: 0 8px 18px rgba(34, 197, 94, 0.14);
        }

        .status-badge.rejected,
        .status-badge.expired,
        .status-badge.blocked {
            background: linear-gradient(135deg, #fee2e2 0%, #fff1f2 100%);
            color: #b91c1c;
            box-shadow: 0 8px 18px rgba(239, 68, 68, 0.12);
        }

        .result-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .detail-item h4 {
            color: #64748b;
            font-size: 13px;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .detail-item p {
            color: #0f172a;
            font-size: 15px;
            font-weight: 600;
        }

        .no-results {
            text-align: center;
            padding: 40px;
            color: #64748b;
        }

        .no-results i {
            font-size: 64px;
            margin-bottom: 20px;
            color: #cbd5e1;
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
                padding: 40px 20px;
            }

            .search-card,
            .results-card {
                padding: 24px;
            }

            .search-form {
                flex-direction: column;
            }

            .result-details {
                grid-template-columns: 1fr;
            }

            .result-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }

            .plate-display {
                grid-template-columns: 60px 1fr;
                min-height: 180px;
            }

            .plate-country {
                font-size: 20px;
                letter-spacing: 0.08em;
            }

            .plate-number {
                font-size: 60px;
            }

            .plate-month,
            .plate-year {
                min-width: 50px;
                font-size: 14px;
            }

            .page-header h1 {
                font-size: 28px;
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
            <a href="index.php"><i class="fa-solid fa-arrow-left"></i> Back to Homepage</a>
        </div>
    </nav>

    <!-- Content -->
    <div class="content-wrapper">
        <div class="container">
            <div class="page-header">
                <h1><i class="fa-solid fa-search"></i> Verify License Plate</h1>
                <p>Search by application number, license plate number or vehicle plate number</p>
            </div>

            <div class="search-card">
                <form method="POST" action="" class="search-form">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Enter application number or plate number" required>
                    <button type="submit" class="btn-search">
                        <i class="fa-solid fa-search"></i> Verify
                    </button>
                </form>
            </div>

            <?php if ($_SERVER['REQUEST_METHOD'] == 'POST' || $search !== ''): ?>
                <?php $public_verify_url = $search !== '' ? appBaseUrl() . '/verify.php?search=' . rawurlencode($search) : ''; ?>
                <div class="results-card">
                    <?php if ($found): ?>
                        <?php while ($row = mysqli_fetch_assoc($result)): ?>
                            <?php
                                $effective_status = appResolvePlateStatus(
                                    $row['vehicle_status'] ?? ($row['record_status'] ?? 'Pending'),
                                    $row['expiry_date'] ?? ($row['effective_expiry'] ?? null),
                                    $row['record_status'] ?? ($row['vehicle_status'] ?? 'Pending'),
                                    $row['effective_expiry'] ?? ($row['expiry_date'] ?? null)
                                );
                                $county_code = buildCountyPlateCode($row['county'] ?? '');
                                $verification_url = buildVerificationUrl($row['license_plate_number'] ?: ($row['plate_number'] ?? $row['application_number']));
                                $display_plate = $row['license_plate_number'] ?: ($row['plate_number'] ?? 'Not available');
                                $display_status = in_array($effective_status, ['Pending', 'Approved', 'Active', 'Rejected', 'Blocked', 'Expired'], true) ? $effective_status : ucfirst(strtolower((string) $effective_status));
                                $plate_style = appVehiclePlateColorStyle($row['vehicle_use_class'] ?? null, $row['official_vehicle_category'] ?? null);
                                $is_valid_plate = in_array($effective_status, ['Approved', 'Active'], true);
                                $is_rejected_plate = in_array($effective_status, ['Rejected', 'Blocked'], true);
                                $is_expired_plate = $effective_status === 'Expired';
                            ?>
                            <div class="result-item">
                                <div class="status-alert <?php echo $is_valid_plate ? 'active' : ($is_expired_plate ? 'expired' : ($is_rejected_plate ? 'rejected' : 'active')); ?>">
                                    <i class="fa-solid <?php echo $is_valid_plate ? 'fa-circle-check' : ($is_expired_plate ? 'fa-triangle-exclamation' : 'fa-ban'); ?>"></i>
                                    <span>
                                        <?php if ($is_valid_plate): ?>
                                            This plate is active and valid.
                                        <?php elseif ($is_expired_plate): ?>
                                            <span class="alert-emphasis">Expired</span> CAUTION: THIS PLATE HAS EXPIRED AND IS NO LONGER VALID. PLEASE RENEW IT OR VISIT THE NEAREST OFFICE FOR ASSISTANCE.
                                        <?php elseif ($effective_status === 'Blocked'): ?>
                                            <span class="alert-emphasis">Blocked</span> CAUTION: THIS PLATE HAS BEEN BLOCKED AND IS NOT VALID FOR USE. DO NOT USE THIS PLATE. PLEASE VISIT THE NEAREST OFFICE IMMEDIATELY FOR VERIFICATION AND CORRECTION.
                                        <?php elseif ($effective_status === 'Rejected'): ?>
                                            <span class="alert-emphasis">Rejected</span> This plate has been rejected and is no longer valid. Please visit the nearest office for verification and correction.
                                        <?php else: ?>
                                            This plate is currently under review.
                                        <?php endif; ?>
                                    </span>
                                </div>

                                <div class="result-header">
                                    <div class="plate-display" style="background: <?php echo htmlspecialchars($plate_style['background']); ?>; border-color: <?php echo htmlspecialchars($plate_style['border']); ?>; color: <?php echo htmlspecialchars($plate_style['text']); ?>;">
                                        <span class="plate-bolt"></span>
                                        <div class="plate-side">
                                            <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/b/b8/Flag_of_Liberia.svg/1280px-Flag_of_Liberia.svg.png" alt="Liberian Flag">
                                            <div class="plate-qr" data-license-qr="<?php echo htmlspecialchars($verification_url, ENT_QUOTES); ?>"></div>
                                            <div class="l-badge">LB</div>
                                        </div>
                                        <div class="plate-main">
                                            <div class="plate-topline">
                                                <div class="plate-country">LIBERIA</div>
                                                <div class="plate-month"><?php echo !empty($row['issued_at']) ? strtoupper(date('M', strtotime($row['issued_at']))) : strtoupper(date('M')); ?><small><?php echo !empty($row['issued_at']) ? date('Y', strtotime($row['issued_at'])) : date('Y'); ?></small></div>
                                            </div>
                                            <div class="plate-number-wrap">
                                                <span class="plate-number"><?php echo htmlspecialchars($display_plate); ?></span>
                                            </div>
                                            <div class="plate-bottomline">
                                                <div class="plate-owner"><?php echo htmlspecialchars($row['county'] ?? 'County'); ?> county</div>
                                                <div class="plate-county-flag"><?php echo htmlspecialchars($county_code); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                    <span class="status-badge <?php echo strtolower($display_status); ?>">
                                        <?php echo strtoupper($display_status); ?>
                                    </span>
                                </div>

                                <?php if ($public_verify_url !== ''): ?>
                                    <div style="margin-bottom: 20px; background: linear-gradient(135deg, #ecfdf5 0%, #f0fdf4 100%); border: 1px solid #bbf7d0; border-radius: 12px; padding: 14px 16px;">
                                        <div style="display: flex; flex-wrap: wrap; gap: 12px; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                            <div>
                                                <div style="font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; color: #166534; margin-bottom: 5px;">Phone access</div>
                                                <div style="color: #14532d; font-weight: 700;">Open this verification link on your phone:</div>
                                            </div>
                                            <a href="<?php echo htmlspecialchars($public_verify_url, ENT_QUOTES); ?>" target="_blank" rel="noopener" style="display:inline-flex; align-items:center; gap:8px; background:linear-gradient(135deg, #16a34a 0%, #15803d 100%); color:white; padding:12px 18px; border-radius:12px; text-decoration:none; font-weight:700;">
                                                <i class="fa-solid fa-mobile-screen-button"></i> Open on phone
                                            </a>
                                        </div>
                                        <div style="background: rgba(255,255,255,0.7); border: 1px solid rgba(22,101,52,0.15); border-radius: 10px; padding: 12px 14px; word-break: break-all; color: #14532d; font-size: 14px; font-weight: 600;">
                                            <?php echo htmlspecialchars($public_verify_url); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="result-details">
                                    <div class="detail-item">
                                        <h4>License Plate Number</h4>
                                        <p><?php echo htmlspecialchars($display_plate); ?></p>
                                    </div>
                                    <div class="detail-item">
                                        <h4>Vehicle Plate Number</h4>
                                        <p><?php echo htmlspecialchars($row['plate_number'] ?? 'Not available'); ?></p>
                                    </div>
                                    <div class="detail-item">
                                        <h4>County</h4>
                                        <p><?php echo htmlspecialchars($row['county'] ?? 'Not specified'); ?></p>
                                    </div>
                                    <div class="detail-item">
                                        <h4>Application Number</h4>
                                        <p><?php echo htmlspecialchars($row['application_number'] ?? 'Not available'); ?></p>
                                    </div>
                                    <div class="detail-item">
                                        <h4>Applicant Name</h4>
                                        <p><?php echo htmlspecialchars($row['applicant_name'] ?? ($row['owner_name'] ?? 'Not available')); ?></p>
                                    </div>
                                    <div class="detail-item">
                                        <h4>Vehicle</h4>
                                        <p><?php echo htmlspecialchars(($row['make'] ?? '') . ' ' . ($row['model'] ?? '')); ?></p>
                                    </div>
                                    <div class="detail-item">
                                        <h4>Owner</h4>
                                        <p><?php echo htmlspecialchars($row['owner_name'] ?? 'Not available'); ?></p>
                                    </div>
                                    <div class="detail-item">
                                        <h4>Status</h4>
                                        <p><?php echo htmlspecialchars($display_status); ?></p>
                                    </div>
                                    <div class="detail-item">
                                        <h4>Application Date</h4>
                                        <p><?php echo !empty($row['application_date']) ? date('F j, Y', strtotime($row['application_date'])) : 'Not available'; ?></p>
                                    </div>
                                    <div class="detail-item">
                                        <h4>Issue Date</h4>
                                        <p><?php echo !empty($row['issued_at']) ? date('F j, Y', strtotime($row['issued_at'])) : 'Not issued yet'; ?></p>
                                    </div>
                                    <div class="detail-item">
                                        <h4>Expiry Date</h4>
                                        <p><?php echo !empty($row['effective_expiry']) ? date('F j, Y', strtotime($row['effective_expiry'])) : (!empty($row['expires_at']) ? date('F j, Y', strtotime($row['expires_at'])) : 'Not available'); ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="no-results">
                            <i class="fa-solid fa-circle-xmark"></i>
                            <h3>No records found</h3>
                            <p>Please check your application or plate number and try again.</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php include "partials/public-footer.php"; ?>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
        document.querySelectorAll('[data-license-qr]').forEach(function (node) {
            var payload = node.getAttribute('data-license-qr');
            if (!payload) {
                return;
            }

            new QRCode(node, {
                text: payload,
                width: 44,
                height: 44,
                colorDark: '#0a2463',
                colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.M
            });
        });
    </script>
</body>
</html>
