<?php
session_start();
include "staff_auth.php";
include "config.php";
include "partials/admin-toast.php";

if (isset($_GET['id'])) {
    $user_id = intval($_GET['id']);

    if ($user_id != $_SESSION['id']) {
        mysqli_begin_transaction($conn);

        try {
            $delete_payments = mysqli_query($conn, "DELETE p FROM payments p INNER JOIN applications a ON a.id = p.application_id WHERE a.user_id = '$user_id' OR a.processed_by = '$user_id' OR p.received_by = '$user_id'");
            if (!$delete_payments) {
                throw new Exception('Unable to remove linked payment records.');
            }

            $delete_applications = mysqli_query($conn, "DELETE FROM applications WHERE user_id = '$user_id' OR processed_by = '$user_id'");
            if (!$delete_applications) {
                throw new Exception('Unable to remove linked applications.');
            }

            $delete_vehicles = mysqli_query($conn, "DELETE FROM vehicles WHERE created_by = '$user_id'");
            if (!$delete_vehicles) {
                throw new Exception('Unable to remove linked vehicles.');
            }

            $delete_query = mysqli_query($conn, "DELETE FROM users WHERE id = $user_id");
            if (!$delete_query) {
                throw new Exception('Unable to delete user.');
            }

            mysqli_commit($conn);
            admin_toast_add("User deleted successfully.", 'success');
        } catch (Exception $e) {
            mysqli_rollback($conn);
            admin_toast_add("Unable to delete user right now.", 'danger');
        }
    } else {
        admin_toast_add("You cannot delete your own account.", 'warning');
    }
} else {
    admin_toast_add("No user selected for deletion.", 'warning');
}

header("Location: users.php");
exit();
?>
