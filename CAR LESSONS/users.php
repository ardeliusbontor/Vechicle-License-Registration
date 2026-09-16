<?php
session_start();
include "staff_auth.php";
include "config.php";
include "partials/admin-toast.php";
include "partials/admin-notifications.php";

$unread_admin_notifications = admin_get_unread_notification_count($conn);

if (($_SESSION['role'] ?? '') !== 'Admin') {
    admin_toast_add("Only the system administrator can view all users.", 'warning');
    header('Location: dashboard.php');
    exit();
}

if (isset($_GET['export']) && $_GET['export'] === 'users_csv') {
    $csv_result = mysqli_query($conn, "SELECT id, fullname, username, email, contact_number, county, role, status, created_at FROM users ORDER BY created_at DESC");
    $filename = 'users-export-' . date('Ymd-His') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Full Name', 'Username', 'Email', 'Contact', 'County', 'Role', 'Status', 'Created On']);

    if ($csv_result) {
        while ($row = mysqli_fetch_assoc($csv_result)) {
            fputcsv($output, [
                $row['id'],
                $row['fullname'],
                $row['username'],
                $row['email'],
                $row['contact_number'],
                $row['county'],
                $row['role'],
                $row['status'],
                $row['created_at']
            ]);
        }
    }

    fclose($output);
    exit();
}

// Get all users
$users_query = mysqli_query($conn, "SELECT * FROM users ORDER BY created_at DESC");

$users_export_link = 'users.php?export=users_csv';
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
    <title>Users - Transport Union Authority</title>
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
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #0a2463 0%, #0f3460 100%);
            color: white;
            box-shadow: 0 8px 18px rgba(10, 36, 99, 0.18);
        }

        .btn-primary:hover {
            opacity: 0.96;
            transform: translateY(-1px);
        }

        .btn-warning {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            padding: 7px 14px;
            font-size: 13px;
            box-shadow: 0 8px 18px rgba(245, 158, 11, 0.2);
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

        .status-active {
            background: #dcfce7;
            color: #166534;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-inactive {
            background: #fee2e2;
            color: #991b1b;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .role-badge {
            background: #e0e7ff;
            color: #3730a3;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
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
            <li><a href="users.php" class="active"><i class="fa-solid fa-users"></i> Users Management</a></li>
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
                <h1><i class="fa-solid fa-users"></i> System Users</h1>
            </div>
            <div class="user-info">
                <div class="header-actions">
                    <a href="<?php echo htmlspecialchars($users_export_link); ?>" class="action-btn download">
                        <i class="fa-solid fa-file-csv"></i> Download CSV
                    </a>
                    <a href="users.php" class="action-btn refresh">
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
                    <h2><i class="fa-solid fa-user-group"></i> All System Users</h2>
                    <div class="header-actions">
                        <a href="<?php echo htmlspecialchars($users_export_link); ?>" class="action-btn download"><i class="fa-solid fa-download"></i> Export List</a>
                        <a href="add_user.php" class="btn btn-primary"><i class="fa-solid fa-user-plus"></i> Add New User</a>
                    </div>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Full Name</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Contact</th>
                            <th>County</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Created On</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($user = mysqli_fetch_assoc($users_query)): ?>
                        <tr>
                            <td>#<?php echo $user['id']; ?></td>
                            <td><strong><?php echo $user['fullname']; ?></strong></td>
                            <td><?php echo $user['username']; ?></td>
                            <td><?php echo $user['email'] ? $user['email'] : '<span style="color:#94a3b8">—</span>'; ?></td>
                            <td><?php echo !empty($user['contact_number']) ? htmlspecialchars($user['contact_number']) : '<span style="color:#94a3b8">—</span>'; ?></td>
                            <td><?php echo !empty($user['county']) ? htmlspecialchars($user['county']) : '<span style="color:#94a3b8">—</span>'; ?></td>
                            <td><span class="role-badge"><?php echo $user['role']; ?></span></td>
                            <td><span class="status-<?php echo strtolower($user['status']); ?>"><?php echo $user['status']; ?></span></td>
                            <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="btn btn-warning"><i class="fa-solid fa-edit"></i> Edit</a>
                                    <?php if ($user['id'] != $_SESSION['id']): ?>
                                        <a href="delete_user.php?id=<?php echo $user['id']; ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this user?')"><i class="fa-solid fa-trash"></i> Delete</a>
                                    <?php endif; ?>
                                </div>
                            </td>
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
