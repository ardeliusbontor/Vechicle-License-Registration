<?php
session_start();
include "staff_auth.php";
include "config.php";
include "partials/admin-toast.php";

$static_reply_email = 'ardeliusnet@outlook.com';

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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$reply_to_col = mysqli_query($conn, "SHOW COLUMNS FROM contact_messages LIKE 'reply_to_email'");
if (mysqli_num_rows($reply_to_col) == 0) {
    mysqli_query($conn, "ALTER TABLE contact_messages ADD COLUMN reply_to_email VARCHAR(100) DEFAULT NULL");
}

$reply_from_col = mysqli_query($conn, "SHOW COLUMNS FROM contact_messages LIKE 'reply_from_email'");
if (mysqli_num_rows($reply_from_col) == 0) {
    mysqli_query($conn, "ALTER TABLE contact_messages ADD COLUMN reply_from_email VARCHAR(100) DEFAULT 'ardeliusnet@outlook.com'");
}

$is_read_col = mysqli_query($conn, "SHOW COLUMNS FROM contact_messages LIKE 'is_read'");
if (mysqli_num_rows($is_read_col) == 0) {
    mysqli_query($conn, "ALTER TABLE contact_messages ADD COLUMN is_read TINYINT(1) NOT NULL DEFAULT 0");
}

$message = '';
$message_type = '';

$view = strtolower(trim($_GET['view'] ?? 'unread'));
if (!in_array($view, ['unread', 'read', 'all'], true)) {
    $view = 'unread';
}
$selected_id = (int)($_GET['selected'] ?? 0);

function messages_redirect_with_state($view, $selected_id = 0) {
    $query = 'messages.php?view=' . urlencode($view);
    if ($selected_id > 0) {
        $query .= '&selected=' . (int)$selected_id;
    }
    header('Location: ' . $query);
    exit();
}

function send_smtp_command($socket, $command) {
    fwrite($socket, $command . "\r\n");
    return fgets($socket);
}

function send_client_reply($to, $from, $subject, $body) {
    $smtp_host = appEnv('SMTP_HOST');
    $smtp_port = (int) appEnv('SMTP_PORT', 587);
    $smtp_user = appEnv('SMTP_USERNAME');
    $smtp_pass = appEnv('SMTP_PASSWORD');

    if (!$smtp_host || !$smtp_user || !$smtp_pass) {
        return false;
    }

    $safe_subject = 'Re: ' . preg_replace('/[\r\n]+/', '', $subject);
    $message_body = "Hello,\r\n\r\n" . $body . "\r\n\r\n---\r\nThis message was sent from the Transport Union Authority contact system.\r\n";
    $message = "Subject: $safe_subject\r\n";
    $message .= "From: $from\r\n";
    $message .= "To: $to\r\n";
    $message .= "Reply-To: $from\r\n";
    $message .= "MIME-Version: 1.0\r\n";
    $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $message .= "X-Mailer: PHP/" . phpversion() . "\r\n";
    $message .= "\r\n";
    $message .= $message_body;
    $message .= "\r\n.\r\n";

    $socket = fsockopen('tls://' . $smtp_host, $smtp_port, $errno, $errstr, 20);
    if (!$socket) {
        return false;
    }

    $response = fgets($socket);
    if (substr($response, 0, 3) !== '220') {
        fclose($socket);
        return false;
    }

    $ehlo = send_smtp_command($socket, 'EHLO localhost');
    if (substr($ehlo, 0, 3) !== '250') {
        fclose($socket);
        return false;
    }

    $starttls = send_smtp_command($socket, 'STARTTLS');
    if (substr($starttls, 0, 3) !== '220') {
        fclose($socket);
        return false;
    }

    stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
    send_smtp_command($socket, 'EHLO localhost');

    $auth = send_smtp_command($socket, 'AUTH LOGIN');
    if (substr($auth, 0, 3) !== '334') {
        fclose($socket);
        return false;
    }

    $user = send_smtp_command($socket, base64_encode($smtp_user));
    if (substr($user, 0, 3) !== '334') {
        fclose($socket);
        return false;
    }

    $pass = send_smtp_command($socket, base64_encode($smtp_pass));
    if (substr($pass, 0, 3) !== '235') {
        fclose($socket);
        return false;
    }

    $mail_from = send_smtp_command($socket, 'MAIL FROM:<'.$from.'>');
    if (substr($mail_from, 0, 3) !== '250') {
        fclose($socket);
        return false;
    }

    $rcpt_to = send_smtp_command($socket, 'RCPT TO:<'.$to.'>');
    if (substr($rcpt_to, 0, 3) !== '250') {
        fclose($socket);
        return false;
    }

    $data = send_smtp_command($socket, 'DATA');
    if (substr($data, 0, 3) !== '354') {
        fclose($socket);
        return false;
    }

    fwrite($socket, $message);
    $result = fgets($socket);
    send_smtp_command($socket, 'QUIT');
    fclose($socket);

    return substr($result, 0, 3) === '250';
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = trim($_POST['action'] ?? '');
    $post_view = strtolower(trim($_POST['view'] ?? $view));
    if (!in_array($post_view, ['unread', 'read', 'all'], true)) {
        $post_view = 'unread';
    }
    $message_id = (int)($_POST['message_id'] ?? 0);

    if ($action === 'mark_all_read') {
        mysqli_query($conn, "UPDATE contact_messages SET is_read = 1 WHERE is_read = 0");
        admin_toast_add('All messages have been marked as read.', 'success');
        messages_redirect_with_state($post_view, 0);
    }

    if ($action === 'mark_read_single' && $message_id > 0) {
        mysqli_query($conn, "UPDATE contact_messages SET is_read = 1 WHERE id = $message_id");
        admin_toast_add('Message marked as read.', 'success');
        messages_redirect_with_state($post_view, $message_id);
    }

    if ($action === 'mark_unread_single' && $message_id > 0) {
        mysqli_query($conn, "UPDATE contact_messages SET is_read = 0 WHERE id = $message_id");
        admin_toast_add('Message marked as unread.', 'info');
        messages_redirect_with_state($post_view, $message_id);
    }

    if ($action === 'send_reply') {
        $reply = mysqli_real_escape_string($conn, trim($_POST['reply'] ?? ''));
        $reply_method = mysqli_real_escape_string($conn, trim($_POST['reply_method'] ?? 'Email'));
        $reply_to = mysqli_real_escape_string($conn, trim($_POST['reply_to'] ?? ''));
        $reply_from = mysqli_real_escape_string($conn, trim($_POST['reply_from'] ?? $static_reply_email));

        if ($message_id > 0 && $reply !== '') {
            $subject_result = mysqli_query($conn, "SELECT subject FROM contact_messages WHERE id=$message_id");
            $subject_row = mysqli_fetch_assoc($subject_result);
            $subject = $subject_row['subject'] ?? 'Your inquiry';

            $update_query = mysqli_query($conn, "UPDATE contact_messages SET status='Replied', is_read=1, admin_reply='$reply', reply_method='$reply_method', reply_to_email='$reply_to', reply_from_email='$reply_from', replied_at=NOW(), replied_by='{$_SESSION['id']}' WHERE id=$message_id");
            if ($update_query) {
                $mail_sent = send_client_reply($reply_to, $reply_from, $subject, $reply);
                if ($mail_sent) {
                    admin_toast_add('Reply saved and sent to the client successfully.', 'success');
                } else {
                    admin_toast_add('Reply saved, but the email could not be delivered from this server. Please verify the local mail setup.', 'danger');
                }
            } else {
                admin_toast_add('Unable to save reply. Please try again.', 'danger');
            }
        } else {
            admin_toast_add('Please enter a reply before saving.', 'danger');
        }

        messages_redirect_with_state($post_view, $message_id);
    }
}

$page_toasts = admin_toast_pull();
if ($message !== '') {
    $page_toasts[] = [
        'message' => trim(strip_tags((string) $message)),
        'type' => admin_toast_normalize_type($message_type)
    ];
}

$selected_message = null;
if ($selected_id > 0) {
    $selected_result = mysqli_query($conn, "SELECT * FROM contact_messages WHERE id = $selected_id LIMIT 1");
    if ($selected_result && mysqli_num_rows($selected_result) > 0) {
        $selected_message = mysqli_fetch_assoc($selected_result);
    }
}

$total_messages = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS count FROM contact_messages"))['count'] ?? 0);
$unread_messages = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS count FROM contact_messages WHERE is_read = 0"))['count'] ?? 0);
$read_messages = max(0, $total_messages - $unread_messages);

$filter_sql = '';
if ($view === 'unread') {
    $filter_sql = 'WHERE is_read = 0';
} elseif ($view === 'read') {
    $filter_sql = 'WHERE is_read = 1';
}

$messages_query = mysqli_query($conn, "SELECT * FROM contact_messages $filter_sql ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - Transport Union Authority</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin-toast.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background: #edf2f7; color: #0f172a; }
        .container { max-width: 1280px; margin: 0 auto; padding: 24px; }
        .header { background: white; border-radius: 18px; padding: 24px; margin-bottom: 18px; box-shadow: 0 10px 28px rgba(15, 23, 42, 0.06); display: flex; justify-content: space-between; align-items: center; gap: 12px; }
        .header h1 { color: #0a2463; font-size: 24px; display: flex; align-items: center; gap: 10px; }
        .header-meta { color: #64748b; font-size: 13px; margin-top: 6px; }

        .btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 16px; border-radius: 10px; text-decoration: none; font-weight: 700; border: none; cursor: pointer; }
        .btn-secondary { background: #e2e8f0; color: #0f172a; }
        .btn-primary { background: #0a2463; color: white; }
        .btn-success { background: #166534; color: white; }
        .btn-muted { background: #f1f5f9; color: #0f172a; border: 1px solid #cbd5e1; }

        .toolbar { background: white; border-radius: 18px; box-shadow: 0 10px 28px rgba(15, 23, 42, 0.06); padding: 14px; margin-bottom: 18px; display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; }
        .tabs { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .tab { padding: 9px 14px; border-radius: 999px; text-decoration: none; font-weight: 700; font-size: 13px; color: #334155; background: #f8fafc; border: 1px solid #e2e8f0; }
        .tab.active { background: #0a2463; color: white; border-color: #0a2463; }
        .tab .count { margin-left: 6px; font-size: 12px; opacity: 0.9; }

        .inbox { background: white; border-radius: 18px; box-shadow: 0 10px 28px rgba(15, 23, 42, 0.06); min-height: 620px; display: grid; grid-template-columns: 360px 1fr; overflow: hidden; }
        .inbox-list { border-right: 1px solid #e2e8f0; background: #f8fafc; }
        .list-head { padding: 16px; font-weight: 700; color: #0a2463; border-bottom: 1px solid #e2e8f0; background: white; }
        .list-scroll { max-height: 570px; overflow-y: auto; }

        .message-link { display: block; padding: 14px 16px; border-bottom: 1px solid #e2e8f0; text-decoration: none; color: inherit; background: white; }
        .message-link:hover { background: #f1f5f9; }
        .message-link.active { background: #eaf1ff; }
        .message-link.unread { border-left: 4px solid #16a34a; }
        .message-row { display: flex; justify-content: space-between; gap: 8px; align-items: center; }
        .sender { font-weight: 700; color: #0f172a; }
        .time { color: #64748b; font-size: 12px; white-space: nowrap; }
        .subject { margin-top: 3px; color: #1e293b; font-size: 14px; font-weight: 600; }
        .preview { margin-top: 6px; color: #64748b; font-size: 13px; line-height: 1.45; }

        .detail { padding: 22px; }
        .empty-detail { height: 100%; display: flex; align-items: center; justify-content: center; color: #64748b; font-size: 15px; text-align: center; padding: 20px; }
        .detail-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 14px; flex-wrap: wrap; margin-bottom: 12px; }
        .detail-title { color: #0a2463; font-size: 22px; margin-bottom: 6px; }
        .detail-meta { color: #475569; font-size: 13px; line-height: 1.6; }
        .status { display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; }
        .status-new { background: #fee2e2; color: #991b1b; }
        .status-replied { background: #dcfce7; color: #166534; }
        .read-pill { display: inline-block; margin-left: 8px; padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; }
        .read-pill.read { background: #e2e8f0; color: #334155; }
        .read-pill.unread { background: #dcfce7; color: #166534; }

        .message-body { margin-top: 14px; border: 1px solid #e2e8f0; border-radius: 12px; background: #f8fafc; padding: 14px; color: #334155; line-height: 1.65; }
        .action-row { margin-top: 12px; display: flex; gap: 10px; flex-wrap: wrap; }

        .reply-history { margin-top: 14px; padding: 12px; border-left: 4px solid #0a2463; background: #f8fafc; border-radius: 10px; }
        .reply-box { margin-top: 16px; border-top: 1px solid #e2e8f0; padding-top: 14px; }
        .reply-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 10px; }
        .reply-field label { display: block; margin-bottom: 6px; color: #0f172a; font-size: 13px; font-weight: 600; }
        .reply-field input, .reply-field select, textarea { width: 100%; padding: 11px 12px; border: 1px solid #cbd5e1; border-radius: 10px; }
        textarea { min-height: 120px; }

        .empty-list { padding: 28px 16px; color: #64748b; text-align: center; }

        @media (max-width: 1000px) {
            .inbox { grid-template-columns: 1fr; }
            .inbox-list { border-right: none; border-bottom: 1px solid #e2e8f0; }
            .list-scroll { max-height: 360px; }
        }

        @media (max-width: 768px) {
            .container { padding: 14px; }
            .header { padding: 16px; }
            .header h1 { font-size: 20px; }
            .reply-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1><i class="fa-solid fa-envelope-open-text"></i> Client Messages</h1>
                <div class="header-meta">Select a message from the left to open it. You can manage Read or Unread state at any time.</div>
            </div>
            <a href="dashboard.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>
        </div>

        <div class="toolbar">
            <div class="tabs">
                <a href="messages.php?view=unread" class="tab <?php echo $view === 'unread' ? 'active' : ''; ?>">Unread <span class="count"><?php echo $unread_messages; ?></span></a>
                <a href="messages.php?view=read" class="tab <?php echo $view === 'read' ? 'active' : ''; ?>">Read <span class="count"><?php echo $read_messages; ?></span></a>
                <a href="messages.php?view=all" class="tab <?php echo $view === 'all' ? 'active' : ''; ?>">All <span class="count"><?php echo $total_messages; ?></span></a>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="mark_all_read">
                <input type="hidden" name="view" value="<?php echo htmlspecialchars($view); ?>">
                <button type="submit" class="btn btn-success"><i class="fa-solid fa-check-double"></i> Mark All Read</button>
            </form>
        </div>

        <div class="inbox">
            <div class="inbox-list">
                <div class="list-head">Inbox</div>
                <div class="list-scroll">
                    <?php if ($messages_query && mysqli_num_rows($messages_query) > 0): ?>
                        <?php while ($msg = mysqli_fetch_assoc($messages_query)): ?>
                            <?php $is_unread_item = (int)($msg['is_read'] ?? 0) === 0; ?>
                            <a href="messages.php?view=<?php echo urlencode($view); ?>&selected=<?php echo (int)$msg['id']; ?>" class="message-link <?php echo $selected_id === (int)$msg['id'] ? 'active' : ''; ?> <?php echo $is_unread_item ? 'unread' : ''; ?>">
                                <div class="message-row">
                                    <div class="sender"><?php echo htmlspecialchars($msg['full_name']); ?></div>
                                    <div class="time"><?php echo date('M d', strtotime($msg['created_at'])); ?></div>
                                </div>
                                <div class="subject"><?php echo htmlspecialchars($msg['subject']); ?></div>
                                <?php $preview_text = preg_replace('/\s+/', ' ', $msg['message']); ?>
                                <div class="preview"><?php echo htmlspecialchars(strlen($preview_text) > 95 ? substr($preview_text, 0, 95) . '...' : $preview_text); ?></div>
                            </a>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-list">No messages in this view.</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="detail">
                <?php if ($selected_message): ?>
                    <?php $selected_is_unread = (int)($selected_message['is_read'] ?? 0) === 0; ?>
                    <div class="detail-head">
                        <div>
                            <div class="detail-title"><?php echo htmlspecialchars($selected_message['subject']); ?></div>
                            <div class="detail-meta">
                                From: <?php echo htmlspecialchars($selected_message['full_name']); ?> (<?php echo htmlspecialchars($selected_message['email']); ?>)
                                <?php if (!empty($selected_message['phone'])): ?>
                                    • <?php echo htmlspecialchars($selected_message['phone']); ?>
                                <?php endif; ?>
                                <br>
                                Received: <?php echo date('M d, Y h:i A', strtotime($selected_message['created_at'])); ?>
                            </div>
                        </div>
                        <div>
                            <span class="status status-<?php echo strtolower($selected_message['status']); ?>"><?php echo htmlspecialchars($selected_message['status']); ?></span>
                            <span class="read-pill <?php echo $selected_is_unread ? 'unread' : 'read'; ?>"><?php echo $selected_is_unread ? 'Unread' : 'Read'; ?></span>
                        </div>
                    </div>

                    <div class="message-body"><?php echo nl2br(htmlspecialchars($selected_message['message'])); ?></div>

                    <div class="action-row">
                        <?php if ((int)($selected_message['is_read'] ?? 0) === 1): ?>
                            <form method="POST">
                                <input type="hidden" name="action" value="mark_unread_single">
                                <input type="hidden" name="view" value="<?php echo htmlspecialchars($view); ?>">
                                <input type="hidden" name="message_id" value="<?php echo (int)$selected_message['id']; ?>">
                                <button type="submit" class="btn btn-muted"><i class="fa-solid fa-envelope"></i> Mark Unread</button>
                            </form>
                        <?php else: ?>
                            <form method="POST">
                                <input type="hidden" name="action" value="mark_read_single">
                                <input type="hidden" name="view" value="<?php echo htmlspecialchars($view); ?>">
                                <input type="hidden" name="message_id" value="<?php echo (int)$selected_message['id']; ?>">
                                <button type="submit" class="btn btn-success"><i class="fa-solid fa-envelope-open"></i> Mark Read</button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($selected_message['admin_reply'])): ?>
                        <div class="reply-history">
                            <strong>Admin Reply</strong>
                            <div style="margin-top:6px; color:#334155;"><?php echo nl2br(htmlspecialchars($selected_message['admin_reply'])); ?></div>
                            <div style="margin-top:6px; color:#64748b; font-size:13px;">
                                From: <?php echo htmlspecialchars($selected_message['reply_from_email'] ?? $static_reply_email); ?>
                                • To: <?php echo htmlspecialchars($selected_message['reply_to_email'] ?? $selected_message['email']); ?>
                                • Method: <?php echo htmlspecialchars($selected_message['reply_method'] ?? 'Email'); ?>
                                • Replied: <?php echo !empty($selected_message['replied_at']) ? date('M d, Y h:i A', strtotime($selected_message['replied_at'])) : '-'; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($selected_message['status'] !== 'Replied'): ?>
                        <form method="POST" class="reply-box">
                            <input type="hidden" name="action" value="send_reply">
                            <input type="hidden" name="view" value="<?php echo htmlspecialchars($view); ?>">
                            <input type="hidden" name="message_id" value="<?php echo (int)$selected_message['id']; ?>">
                            <div class="reply-grid">
                                <div class="reply-field">
                                    <label>Reply to</label>
                                    <input type="email" name="reply_to" value="<?php echo htmlspecialchars($selected_message['email']); ?>" required>
                                </div>
                                <div class="reply-field">
                                    <label>From</label>
                                    <input type="email" name="reply_from" value="<?php echo htmlspecialchars($static_reply_email); ?>" readonly>
                                </div>
                            </div>
                            <div class="reply-field" style="margin-bottom:10px;">
                                <label>Reply method</label>
                                <select name="reply_method">
                                    <option value="Email">Email</option>
                                    <option value="Phone">Phone</option>
                                    <option value="WhatsApp">WhatsApp</option>
                                    <option value="SMS">SMS</option>
                                </select>
                            </div>
                            <label style="display:block; margin-bottom:6px; font-size:13px; font-weight:600;">Reply to client</label>
                            <textarea name="reply" placeholder="Type your response to the client here..."></textarea>
                            <div class="action-row">
                                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> Save Reply</button>
                            </div>
                        </form>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="empty-detail">
                        <div>
                            <i class="fa-solid fa-inbox" style="font-size:42px; color:#cbd5e1; margin-bottom:12px;"></i><br>
                            Select a message from the inbox list to display details.
                        </div>
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
