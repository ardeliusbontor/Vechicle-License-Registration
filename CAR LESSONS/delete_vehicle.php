<?php
session_start();
include "staff_auth.php";
include "config.php";
include "partials/admin-toast.php";

if (isset($_GET['id'])) {
    $vehicle_id = (int) $_GET['id'];

    mysqli_begin_transaction($conn);

    try {
        $delete_payments = mysqli_query($conn, "DELETE p FROM payments p INNER JOIN applications a ON a.id = p.application_id WHERE a.vehicle_id = '$vehicle_id'");
        if (!$delete_payments) {
            throw new Exception('Unable to remove linked payments.');
        }

        $delete_history = mysqli_query($conn, "DELETE FROM plate_status_history WHERE vehicle_id = '$vehicle_id'");
        if (!$delete_history) {
            throw new Exception('Unable to remove status history.');
        }

        $delete_applications = mysqli_query($conn, "DELETE FROM applications WHERE vehicle_id = '$vehicle_id'");
        if (!$delete_applications) {
            throw new Exception('Unable to remove linked applications.');
        }

        $delete_query = mysqli_query($conn, "DELETE FROM vehicles WHERE id = '$vehicle_id'");
        if (!$delete_query) {
            throw new Exception('Unable to remove the vehicle.');
        }

        mysqli_commit($conn);
        logAuditEvent(
            $conn,
            'vehicle_deleted',
            'Vehicle deleted by admin with all linked records cleaned up.',
            $_SESSION['id'] ?? null,
            $_SESSION['role'] ?? 'Admin',
            'vehicle',
            $vehicle_id,
            ['deleted_related_applications' => true]
        );
        admin_toast_add("Vehicle deleted successfully.", 'success');
    } catch (Exception $e) {
        mysqli_rollback($conn);
        admin_toast_add("Unable to delete vehicle right now.", 'danger');
    }
} else {
    admin_toast_add("No vehicle selected for deletion.", 'warning');
}

header("Location: vehicles.php");
exit();
?>
