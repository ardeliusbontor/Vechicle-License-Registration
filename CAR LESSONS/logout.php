<?php

session_start();
include "partials/admin-toast.php";

$_SESSION = [];
session_destroy();

session_start();
admin_toast_add("You have been logged out successfully.", 'success');

header("Location: login.php");

?>