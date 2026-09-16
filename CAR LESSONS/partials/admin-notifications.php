<?php
if (!function_exists('admin_get_unread_notification_count')) {
    function admin_get_unread_notification_count($conn) {
        static $cachedCount = null;

        if ($cachedCount !== null) {
            return $cachedCount;
        }

        mysqli_query($conn, "CREATE TABLE IF NOT EXISTS admin_notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_type VARCHAR(50) NOT NULL,
            message VARCHAR(255) NOT NULL,
            reference_id INT DEFAULT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $cachedCount = 0;
        $count_result = mysqli_query($conn, "SELECT COUNT(*) AS count FROM admin_notifications WHERE is_read = 0");
        if ($count_result) {
            $row = mysqli_fetch_assoc($count_result);
            $cachedCount = (int) ($row['count'] ?? 0);
        }

        return $cachedCount;
    }
}

if (!function_exists('admin_collect_unread_notification_toasts')) {
    function admin_collect_unread_notification_toasts($conn, $limit = 6) {
        $toasts = [];
        $safe_limit = max(1, (int) $limit);
        $notifications_result = mysqli_query($conn, "SELECT id, message, event_type FROM admin_notifications WHERE is_read = 0 ORDER BY created_at ASC LIMIT $safe_limit");

        if ($notifications_result) {
            while ($notification = mysqli_fetch_assoc($notifications_result)) {
                $notification_type = ($notification['event_type'] === 'new_application' || $notification['event_type'] === 'renewal_application') ? 'info' : 'success';
                $toasts[] = [
                    'id' => (int) $notification['id'],
                    'message' => (string) $notification['message'],
                    'type' => $notification_type
                ];
            }
        }

        return $toasts;
    }
}

if (!function_exists('admin_mark_notifications_read')) {
    function admin_mark_notifications_read($conn, $notification_ids) {
        if (!is_array($notification_ids) || empty($notification_ids)) {
            return;
        }

        $safe_ids = [];
        foreach ($notification_ids as $id) {
            $safe_ids[] = (int) $id;
        }

        $safe_ids = array_values(array_unique(array_filter($safe_ids, function ($id) {
            return $id > 0;
        })));

        if (empty($safe_ids)) {
            return;
        }

        $ids_sql = implode(',', $safe_ids);
        mysqli_query($conn, "UPDATE admin_notifications SET is_read = 1 WHERE id IN ($ids_sql)");
    }
}

if (!function_exists('admin_get_unread_contact_message_count')) {
    function admin_get_unread_contact_message_count($conn) {
        static $cachedCount = null;

        if ($cachedCount !== null) {
            return $cachedCount;
        }

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
            reply_to_email VARCHAR(100) DEFAULT NULL,
            reply_from_email VARCHAR(100) DEFAULT 'ardeliusnet@outlook.com',
            replied_at DATETIME DEFAULT NULL,
            replied_by INT DEFAULT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $is_read_col = mysqli_query($conn, "SHOW COLUMNS FROM contact_messages LIKE 'is_read'");
        if ($is_read_col && mysqli_num_rows($is_read_col) == 0) {
            mysqli_query($conn, "ALTER TABLE contact_messages ADD COLUMN is_read TINYINT(1) NOT NULL DEFAULT 0");
        }

        $cachedCount = 0;
        $count_result = mysqli_query($conn, "SELECT COUNT(*) AS count FROM contact_messages WHERE is_read = 0");
        if ($count_result) {
            $row = mysqli_fetch_assoc($count_result);
            $cachedCount = (int) ($row['count'] ?? 0);
        }

        return $cachedCount;
    }
}
?>