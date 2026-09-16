<?php
session_start();
include "staff_auth.php";
include "config.php";
include "partials/admin-toast.php";
include "partials/admin-notifications.php";

$unread_admin_notifications = admin_get_unread_notification_count($conn);

$search = isset($_GET['search']) ? trim((string) $_GET['search']) : '';
$search_sql = '';
if ($search !== '') {
    $safe_search = mysqli_real_escape_string($conn, $search);
    $search_sql = " WHERE event_name LIKE '%$safe_search%' OR event_message LIKE '%$safe_search%' OR actor_role LIKE '%$safe_search%' OR entity_type LIKE '%$safe_search%' ";
}

$audit_logs_result = mysqli_query($conn, "SELECT * FROM admin_audit_logs $search_sql ORDER BY created_at DESC LIMIT 200");
$log_rows = [];
if ($audit_logs_result) {
    while ($row = mysqli_fetch_assoc($audit_logs_result)) {
        $log_rows[] = $row;
    }
}

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
    <title>Audit Logs - Transport Union Authority</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin-toast.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background: #f1f5f9; }
        .sidebar {
            width: 260px; background: linear-gradient(180deg, #081a3d 0%, #0e2d5f 35%, #133d7a 100%);
            color: white; padding: 25px 0 18px; position: fixed; height: 100vh; overflow-y: auto;
            box-shadow: 8px 0 24px rgba(8,16,35,0.18); border-right: 1px solid rgba(255,255,255,0.12);
        }
        .sidebar-header { padding: 0 25px 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.15); margin-bottom: 18px; }
        .sidebar-header img { height: 76px; margin-bottom: 14px; filter: drop-shadow(0 8px 18px rgba(0,0,0,0.18)); }
        .sidebar-header h2 { font-size: 16px; font-weight: 800; margin-bottom: 5px; letter-spacing: 0.08em; }
        .sidebar-header p { font-size: 10px; opacity: 0.82; letter-spacing: 0.16em; text-transform: uppercase; }
        .sidebar-menu { list-style: none; padding: 0 15px; }
        .sidebar-menu li { margin-bottom: 6px; }
        .sidebar-menu a {
            display: flex; align-items: center; padding: 12px 16px; color: rgba(255,255,255,0.92);
            text-decoration: none; border-radius: 12px; transition: all 0.25s ease; font-weight: 600; border: 1px solid transparent;
        }
        .sidebar-menu a:hover { background: rgba(255,255,255,0.08); border-color: rgba(255,255,255,0.08); transform: translateX(2px); }
        .sidebar-menu a.active { background: linear-gradient(135deg, rgba(255,255,255,0.14) 0%, rgba(59,130,246,0.18) 100%); border-color: rgba(255,255,255,0.12); }
        .sidebar-menu i { margin-right: 14px; width: 22px; text-align: center; font-size: 16px; }
        .main-content { margin-left: 260px; min-height: 100vh; }
        .top-header {
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%); padding: 18px 40px;
            display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(15,23,42,0.04);
        }
        .page-title h1 { color: #0a2463; font-size: 24px; }
        .user-info a {
            background: #f1f5f9; color: #c41e3a; padding: 10px 18px; border-radius: 8px; text-decoration: none; display: flex; align-items: center; gap: 8px; font-weight: 600;
        }
        .page-content { padding: 35px 40px; }
        .content-card {
            background: white; padding: 30px; border-radius: 18px; box-shadow: 0 10px 30px rgba(15,23,42,0.06);
            max-width: 1200px; margin: 0 auto;
        }
        .page-tools { display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 24px; flex-wrap: wrap; }
        .page-tools h2 { color: #0a2463; font-size: 26px; }
        .search-box { display: flex; align-items: center; gap: 10px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 10px 14px; }
        .search-box input { border: none; background: transparent; outline: none; min-width: 260px; font-size: 14px; }
        .search-box button {
            border: none; background: #0a2463; color: white; border-radius: 10px; padding: 10px 14px; font-weight: 600; cursor: pointer;
        }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 900px; }
        th, td { padding: 14px 12px; text-align: left; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        th { background: #f8fafc; color: #0f172a; font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase; }
        td { color: #334155; font-size: 14px; }
        .badge {
            display: inline-block; padding: 6px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase;
        }
        .badge-system { background: #dbeafe; color: #1d4ed8; }
        .badge-admin { background: #dcfce7; color: #166534; }
        .badge-user { background: #fef3c7; color: #92400e; }
        .meta { color: #64748b; font-size: 12px; }
        @media (max-width: 900px) {
            .sidebar { position: static; width: 100%; height: auto; }
            .main-content { margin-left: 0; }
            .page-content { padding: 20px 16px; }
            .content-card { padding: 18px; }
            .top-header { padding: 16px 20px; }
            .page-tools { flex-direction: column; align-items: stretch; }
            .search-box { width: 100%; }
            .search-box input { min-width: 0; width: 100%; }
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
            <li><a href="vehicles.php"><i class="fa-solid fa-car"></i> Vehicles</a></li>
            <li><a href="applications.php"><i class="fa-solid fa-file-lines"></i> Applications<?php if ($unread_admin_notifications > 0): ?><span style="margin-left:8px;background:#c41e3a;color:#fff;border-radius:999px;padding:2px 8px;font-size:11px;font-weight:700;"><?php echo $unread_admin_notifications; ?></span><?php endif; ?></a></li>
            <li><a href="messages.php"><i class="fa-solid fa-envelope-open-text"></i> Messages<?php if (admin_get_unread_contact_message_count($conn) > 0): ?><span style="margin-left:8px;background:#c41e3a;color:#fff;border-radius:999px;padding:2px 8px;font-size:11px;font-weight:700;"><?php echo admin_get_unread_contact_message_count($conn); ?></span><?php endif; ?></a></li>
            <li><a href="payments.php"><i class="fa-solid fa-credit-card"></i> Payments</a></li>
            <li><a href="reports.php"><i class="fa-solid fa-chart-line"></i> Reports</a></li>
            <li><a href="generate-plate.php"><i class="fa-solid fa-qrcode"></i> Generate Plate</a></li>
            <li><a href="audit_logs.php" class="active"><i class="fa-solid fa-clock-rotate-left"></i> Audit Logs</a></li>
            <li><a href="index.php" target="_blank"><i class="fa-solid fa-globe"></i> View Website</a></li>
        </ul>
    </div>

    <div class="main-content">
        <div class="top-header">
            <div class="page-title">
                <h1><i class="fa-solid fa-clock-rotate-left"></i> Audit Log</h1>
            </div>
            <div class="user-info">
                <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
            </div>
        </div>

        <div class="page-content">
            <div class="content-card">
                <div class="page-tools">
                    <h2>System Events</h2>
                    <form method="GET" action="audit_logs.php" class="search-box">
                        <i class="fa-solid fa-magnifying-glass" style="color:#64748b"></i>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search event name, actor, or entity">
                        <button type="submit">Search</button>
                    </form>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Actor</th>
                                <th>Event</th>
                                <th>Entity</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($log_rows)): ?>
                                <tr>
                                    <td colspan="5" style="padding: 22px; color: #64748b; text-align: center;">No audit events found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($log_rows as $log): ?>
                                    <?php
                                        $actor_role = strtoupper((string) ($log['actor_role'] ?? 'System'));
                                        $badge_class = 'badge-system';
                                        if (stripos($actor_role, 'ADMIN') !== false || stripos($actor_role, 'STAFF') !== false) { $badge_class = 'badge-admin'; }
                                        if (stripos($actor_role, 'USER') !== false || stripos($actor_role, 'APPLICANT') !== false) { $badge_class = 'badge-user'; }
                                        $metadata = json_decode((string) ($log['metadata'] ?? '[]'), true);
                                        $details = is_array($metadata) ? json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : ''; 
                                    ?>
                                    <tr>
                                        <td class="meta"><?php echo htmlspecialchars(date('d M Y, H:i:s', strtotime($log['created_at']))); ?></td>
                                        <td>
                                            <span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($actor_role); ?></span>
                                            <div class="meta" style="margin-top:6px;">ID: <?php echo htmlspecialchars((string) ($log['actor_id'] ?? 'System')); ?></div>
                                        </td>
                                        <td><strong><?php echo htmlspecialchars($log['event_name'] ?? 'System Event'); ?></strong></td>
                                        <td><?php echo htmlspecialchars(($log['entity_type'] ?? 'system') . ' #' . ($log['entity_id'] ?? 'n/a')); ?></td>
                                        <td>
                                            <div><?php echo htmlspecialchars($log['event_message'] ?? ''); ?></div>
                                            <?php if ($details !== ''): ?>
                                                <pre style="margin-top:8px; background:#f8fafc; padding:8px; border-radius:8px; white-space:pre-wrap; word-break:break-word; font-size:11px; color:#334155; border:1px solid #e2e8f0;"><?php echo htmlspecialchars($details); ?></pre>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
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
