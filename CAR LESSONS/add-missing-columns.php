<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1 style='color: #0a2463; text-align: center; margin: 40px 0 30px;'>🔧 Adding Missing Columns</h1>";
echo "<div style='max-width: 900px; margin: 0 auto; font-family: Arial, sans-serif;'>";

// Connect
$conn = new mysqli("127.0.0.1", "root", "", "car_license_registration", 3306);
if ($conn->connect_error) {
    die("<p style='color: red;'>Connection failed: " . $conn->connect_error . "</p>");
}

// Function to add column if it doesn't exist
function addColumnIfNotExists($conn, $table, $column, $definition) {
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    if ($result->num_rows == 0) {
        $conn->query("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
        echo "<p style='color: #166534;'>✓ Added column `$column` to `$table`</p>";
    } else {
        echo "<p style='color: #64748b;'>ℹ️ Column `$column` already exists in `$table`</p>";
    }
}

// 1. Check users table - add Applicant role
echo "<h3 style='color: #c41e3a;'>Step 1: Checking users table...</h3>";
$conn->query("ALTER TABLE users MODIFY COLUMN role ENUM('Admin','Officer','Clerk','Applicant') DEFAULT 'Applicant'");
echo "<p style='color: #166534;'>✓ Updated user roles</p><br>";

// 2. Check applications table - add missing columns
echo "<h3 style='color: #c41e3a;'>Step 2: Checking applications table...</h3>";
addColumnIfNotExists($conn, 'applications', 'user_id', 'INT AFTER id');
if ($conn->query("SHOW COLUMNS FROM `applications` LIKE 'license_plate_number'")->num_rows == 0 && $conn->query("SHOW COLUMNS FROM `applications` LIKE 'lesson_plate_number'")->num_rows > 0) {
    $conn->query("ALTER TABLE `applications` CHANGE COLUMN `lesson_plate_number` `license_plate_number` VARCHAR(20) UNIQUE NOT NULL");
    echo "<p style='color: #166534;'>✓ Renamed column `lesson_plate_number` to `license_plate_number` in `applications`</p>";
}
addColumnIfNotExists($conn, 'applications', 'license_plate_number', 'VARCHAR(20) UNIQUE NOT NULL AFTER application_number');
addColumnIfNotExists($conn, 'applications', 'county', 'VARCHAR(50) NOT NULL AFTER license_plate_number');

// Add index and foreign key for user_id
$index_check = $conn->query("SHOW INDEX FROM applications WHERE Key_name = 'idx_user_id'");
if ($index_check->num_rows == 0) {
    $conn->query("ALTER TABLE applications ADD INDEX idx_user_id (user_id)");
    echo "<p style='color: #166534;'>✓ Added index for user_id</p>";
}

$fk_check = $conn->query("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_NAME='applications' AND CONSTRAINT_TYPE='FOREIGN KEY' AND CONSTRAINT_NAME='applications_ibfk_3'");
if ($fk_check->num_rows == 0) {
    $conn->query("ALTER TABLE applications ADD FOREIGN KEY (user_id) REFERENCES users(id)");
    echo "<p style='color: #166534;'>✓ Added foreign key for user_id</p>";
}

echo "<br>";

// 3. Check vehicles table - add created_by
echo "<h3 style='color: #c41e3a;'>Step 3: Checking vehicles table...</h3>";
addColumnIfNotExists($conn, 'vehicles', 'created_by', 'INT AFTER created_at');

echo "<br><div style='background: #dcfce7; padding: 25px; border-radius: 15px; text-align: center; margin-top: 40px;'>";
echo "<h2 style='color: #166534; margin-bottom: 10px;'>🎉 ALL COLUMNS ADDED!</h2>";
echo "<p style='color: #065f46; font-size: 17px; margin-bottom: 20px;'>You can now submit applications with no errors!</p>";
echo "<a href='index.php' style='display: inline-block; background: #0a2463; color: white; padding: 15px 40px; border-radius: 10px; text-decoration: none; font-weight: bold; font-size: 16px;'>🏠 Go to Homepage</a>";
echo "</div>";

echo "</div>";
$conn->close();
?>
