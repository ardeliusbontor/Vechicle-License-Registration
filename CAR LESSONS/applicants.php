<?php
session_start();
include "staff_auth.php";
include "config.php";
include "partials/admin-toast.php";
include "partials/admin-notifications.php";

$unread_admin_notifications = admin_get_unread_notification_count($conn);

if (($_SESSION['role'] ?? '') !== 'Admin') {
    admin_toast_add("Only the system administrator can manage applicants.", 'warning');
    header('Location: dashboard.php');
    exit();
}

$applicants_query = mysqli_query($conn, "SELECT * FROM applicants ORDER BY created_at DESC");
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
    <title>Applicants - Transport Union Authority</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin-toast.css">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f1f5f9;
            color: #0f172a;
        }
        a { text-decoration: none; }
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
        .sidebar-header img { height: 76px; margin-bottom: 14px; }
        .sidebar-header h2 {
            font-size: 16px;
            font-weight: 800;
            letter-spacing: 0.08em;
            margin-bottom: 5px;
        }
        .sidebar-header p {
            font-size: 10px;
            opacity: 0.82;
            letter-spacing: 0.16em;
            text-transform: uppercase;
        }
        .sidebar-menu { list-style: none; padding: 0 15px; }
        .sidebar-menu li { margin-bottom: 6px; }
        .sidebar-menu a {
            display: flex; align-items: center; padding: 12px 16px; color: rgba(255,255,255,0.92);
            border-radius: 12px; transition: all 0.25s ease; font-weight: 600; border: 1px solid transparent;
        }
        .sidebar-menu a:hover { background: rgba(255,255,255,0.08); border-color: rgba(255,255,255,0.08); transform: translateX(2px); }
        .sidebar-menu a.active {
            background: linear-gradient(135deg, rgba(255,255,255,0.14) 0%, rgba(59,130,246,0.18) 100%);
            border-color: rgba(255,255,255,0.12); box-shadow: inset 0 1px 0 rgba(255,255,255,0.12);
        }
        .sidebar-menu i { margin-right: 14px; width: 22px; text-align: center; font-size: 16px; }
        .main-content { margin-left: 260px; min-height: 100vh; }
        .top-header {
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            padding: 18px 40px; box-shadow: 0 2px 12px rgba(15, 23, 42, 0.08);
            display: flex; justify-content: space-between; align-items: center; gap: 18px; border-bottom: 1px solid #e2e8f0;
        }
        .page-title h1 {
            color: #0a2463; font-size: 24px; display: flex; align-items: center; gap: 10px; font-weight: 800;
        }
        .user-info { display: flex; align-items: center; justify-content: flex-end; gap: 14px; flex-wrap: wrap; }
        .role {
            background: linear-gradient(135deg, #c41e3a 0%, #a01830 100%);
            color: white; padding: 6px 12px; border-radius: 999px; font-size: 11px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase;
        }
        .name { color: #0a2463; font-weight: 700; font-size: 14px; }
        .user-info a {
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%); color: #c41e3a; padding: 10px 16px; border-radius: 10px;
            display: flex; align-items: center; gap: 8px; font-weight: 700; border: 1px solid #dfe8f4;
        }
        .page-content { padding: 35px 40px; }
        .content-card {
            background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
            padding: 30px; border-radius: 16px; box-shadow: 0 8px 18px rgba(15, 23, 42, 0.06); border: 1px solid #e2e8f0;
        }
        .card-header {
            display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; padding-bottom: 20px; border-bottom: 2px solid #f1f5f9;
        }
        .card-header h2 { color: #0a2463; font-size: 20px; display: flex; align-items: center; gap: 10px; }
        .btn {
            padding: 11px 18px; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #0a2463 0%, #0f3460 100%); color: white; box-shadow: 0 8px 18px rgba(10, 36, 99, 0.18);
        }
        table { width: 100%; border-collapse: collapse; }
        th {
            background: #f8fafc; color: #475569; padding: 14px 16px; text-align: left; font-weight: 700; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;
        }
        td { padding: 16px; border-bottom: 1px solid #f1f5f9; font-size: 14px; color: #334155; }
        tr:hover { background: #f8fafc; }
        .status-active {
            background: #dcfce7; color: #166534; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;
        }
        .status-inactive {
            background: #fee2e2; color: #991b1b; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;
        }
        .action-buttons { display: flex; gap: 8px; }
        .btn-warning {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; padding: 7px 14px; font-size: 13px; box-shadow: 0 8px 18px rgba(245, 158, 11, 0.2);
        }
        @media (max-width: 980px) {
            .top-header { flex-direction: column; align-items: flex-start; gap: 14px; }
            .page-content { padding: 24px 20px; }
        }
        @media (max-width: 720px) {
            .main-content { margin-left: 0; }
            .sidebar { position: static; width: 100%; height: auto; }
            .content-card { padding: 20px; overflow-x: auto; }
            table { min-width: 980px; }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <img src="assets/images/logo.png" alt="TUA">
            <h2>TUA PORTAL</h2>
            <p>REPUBLIC OF LIBERIA</p>
        </div>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php"><i class="fa-solid fa-gauge-high"></i> Dashboard</a></li>
            <li><a href="users.php"><i class="fa-solid fa-users"></i> Users Management</a></li>
            <li><a href="applicants.php" class="active"><i class="fa-solid fa-user-group"></i> Applicants</a></li>
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

    <div class="main-content">
        <div class="top-header">
            <div class="page-title">
                <h1><i class="fa-solid fa-user-group"></i> Applicants</h1>
            </div>
            <div class="user-info">
                <span class="role"><?php echo htmlspecialchars($_SESSION['role']); ?></span>
                <span class="name"><?php echo htmlspecialchars($_SESSION['fullname']); ?></span>
                <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
            </div>
        </div>

        <div class="page-content">
            <div class="content-card">
                <div class="card-header">
                    <h2><i class="fa-solid fa-users"></i> Applicant Accounts</h2>
                    <a href="register.php" class="btn btn-primary"><i class="fa-solid fa-user-plus"></i> Add Applicant</a>
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
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($applicants_query && mysqli_num_rows($applicants_query) > 0): ?>
                            <?php while ($applicant = mysqli_fetch_assoc($applicants_query)): ?>
                                <tr>
                                    <td><?php echo (int) $applicant['id']; ?></td>
                                    <td><?php echo htmlspecialchars($applicant['fullname']); ?></td>
                                    <td><?php echo htmlspecialchars($applicant['username']); ?></td>
                                    <td><?php echo htmlspecialchars($applicant['email'] ?: '—'); ?></td>
                                    <td><?php echo htmlspecialchars($applicant['contact_number'] ?: '—'); ?></td>
                                    <td><?php echo htmlspecialchars($applicant['county'] ?: '—'); ?></td>
                                    <td>
                                        <?php if (($applicant['status'] ?? 'Active') === 'Active'): ?>
                                            <span class="status-active">Active</span>
                                        <?php else: ?>
                                            <span class="status-inactive">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="reset_applicant_password.php?id=<?php echo (int) $applicant['id']; ?>" class="btn btn-warning"><i class="fa-solid fa-key"></i> Reset Password</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align:center; color:#64748b; padding:30px;">No applicant accounts found.</td>
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
