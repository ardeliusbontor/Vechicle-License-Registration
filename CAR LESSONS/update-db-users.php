<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = "localhost";
$user = "root";
$password = "";
$database = "car_license_registration";
$port = 3306;

echo "<h1>Updating Database for Public Users...</h1>";
echo "<ol>";

// Connect to MySQL server
echo "<li>Connecting to MySQL server... ";
$conn = new mysqli($host, $user, $password, $database, $port);
if ($conn->connect_error) {
    die("<span style='color:red;'>FAILED: " . $conn->connect_error . "</span></li>");
}
echo "<span style='color:green;'>OK!</span></li>";

// Select the database
echo "<li>Selecting database... ";
if (!$conn->select_db($database)) {
    die("<span style='color:red;'>FAILED: " . $conn->error . "</span></li>");
}
echo "<span style='color:green;'>OK!</span></li>";

// Update users table to include Applicant role
echo "<li>Updating users table roles... ";
$conn->query("ALTER TABLE users MODIFY COLUMN role ENUM('Admin','Officer','Clerk','Applicant') DEFAULT 'Applicant'");
echo "<span style='color:green;'>OK!</span></li>";

echo "<li>Adding applicant profile fields to users table... ";
$profile_columns = [
    'contact_number' => "ALTER TABLE users ADD COLUMN contact_number VARCHAR(20) AFTER email",
    'county' => "ALTER TABLE users ADD COLUMN county VARCHAR(50) AFTER contact_number",
    'address' => "ALTER TABLE users ADD COLUMN address TEXT AFTER county"
];

foreach ($profile_columns as $column => $sql) {
    $column_check = $conn->query("SHOW COLUMNS FROM users LIKE '$column'");
    if ($column_check && $column_check->num_rows == 0) {
        $conn->query($sql);
    }
}

$conn->query("UPDATE users SET role = 'Applicant' WHERE role = '' OR role IS NULL");
echo "<span style='color:green;'>OK!</span></li>";

// Add user_id to applications table to link to user
echo "<li>Adding user_id to applications table... ";
$col_check = $conn->query("SHOW COLUMNS FROM applications LIKE 'user_id'");
if ($col_check->num_rows == 0) {
    $conn->query("ALTER TABLE applications ADD COLUMN user_id INT AFTER id, ADD INDEX idx_user_id (user_id), ADD FOREIGN KEY (user_id) REFERENCES users(id)");
    echo "<span style='color:green;'>ADDED!</span></li>";
} else {
    echo "<span style='color:blue;'>ALREADY EXISTS!</span></li>";
}

echo "</ol>";
echo "<h2 style='color:green; text-align:center;'>🎉 Database Updated Successfully! 🎉</h2>";
echo "<p style='text-align:center; font-size:18px;'><a href='index.php' style='background: #0a2463; color: white; padding: 10px 25px; text-decoration: none; border-radius: 5px;'>Go to Homepage</a></p>";
$conn->close();
?>