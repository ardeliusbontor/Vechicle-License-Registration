<?php
if (!function_exists('admin_toast_normalize_type')) {
    function admin_toast_normalize_type($type) {
        $raw = strtolower(trim((string) $type));
        if ($raw === 'danger') {
            return 'error';
        }
        if ($raw === 'success' || $raw === 'warning' || $raw === 'error' || $raw === 'info') {
            return $raw;
        }

        return 'info';
    }
}

if (!function_exists('admin_toast_add')) {
    function admin_toast_add($message, $type = 'info') {
        $safe_message = trim(strip_tags((string) $message));
        if ($safe_message === '') {
            return;
        }

        if (!isset($_SESSION['admin_toasts']) || !is_array($_SESSION['admin_toasts'])) {
            $_SESSION['admin_toasts'] = [];
        }

        $_SESSION['admin_toasts'][] = [
            'message' => $safe_message,
            'type' => admin_toast_normalize_type($type)
        ];
    }
}

if (!function_exists('admin_toast_pull')) {
    function admin_toast_pull() {
        $toasts = [];

        if (isset($_SESSION['admin_toasts']) && is_array($_SESSION['admin_toasts'])) {
            foreach ($_SESSION['admin_toasts'] as $toast) {
                $message = trim((string) ($toast['message'] ?? ''));
                if ($message === '') {
                    continue;
                }

                $toasts[] = [
                    'message' => $message,
                    'type' => admin_toast_normalize_type($toast['type'] ?? 'info')
                ];
            }
        }

        unset($_SESSION['admin_toasts']);
        return $toasts;
    }
}
?>