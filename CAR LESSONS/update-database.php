<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = "127.0.0.1";
$user = "root";
$password = "";
$database = "car_license_registration";
$port = 3306;

echo "<h1>Updating Database...</h1>";
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

// Check if license_plate_number column exists
echo "<li>Checking license_plate_number column... ";
$colCheck = $conn->query("SHOW COLUMNS FROM applications LIKE 'license_plate_number'");
$oldColCheck = $conn->query("SHOW COLUMNS FROM applications LIKE 'lesson_plate_number'");
if ($colCheck->num_rows == 0 && $oldColCheck->num_rows > 0) {
    $conn->query("ALTER TABLE applications CHANGE COLUMN lesson_plate_number license_plate_number VARCHAR(20) NULL");
    echo "<span style='color:green;'>RENAMED!</span></li>";
} elseif ($colCheck->num_rows == 0) {
    $conn->query("ALTER TABLE applications ADD COLUMN license_plate_number VARCHAR(20) NULL AFTER application_number");
    echo "<span style='color:green;'>ADDED!</span></li>";
} else {
    $conn->query("ALTER TABLE applications MODIFY COLUMN license_plate_number VARCHAR(20) NULL");
    echo "<span style='color:blue;'>Already exists!</span></li>";
}

// Check if user_id column exists
echo "<li>Checking user_id column... ";
$colCheckUser = $conn->query("SHOW COLUMNS FROM applications LIKE 'user_id'");
if ($colCheckUser->num_rows == 0) {
    $conn->query("ALTER TABLE applications ADD COLUMN user_id INT AFTER id");
    $conn->query("ALTER TABLE applications ADD FOREIGN KEY (user_id) REFERENCES users(id)");
    echo "<span style='color:green;'>ADDED!</span></li>";
} else {
    echo "<span style='color:blue;'>Already exists!</span></li>";
}

// Check if issued_at/expires_at columns exist
echo "<li>Checking license expiry columns... ";
$colIssued = $conn->query("SHOW COLUMNS FROM applications LIKE 'issued_at'");
if ($colIssued->num_rows == 0) {
    $conn->query("ALTER TABLE applications ADD COLUMN issued_at TIMESTAMP NULL AFTER application_date");
}
$colExpires = $conn->query("SHOW COLUMNS FROM applications LIKE 'expires_at'");
if ($colExpires->num_rows == 0) {
    $conn->query("ALTER TABLE applications ADD COLUMN expires_at TIMESTAMP NULL AFTER issued_at");
}
echo "<span style='color:green;'>READY!</span></li>";

// Check if county column exists
echo "<li>Checking/Adding county column... ";
$colCheck2 = $conn->query("SHOW COLUMNS FROM applications LIKE 'county'");
if ($colCheck2->num_rows == 0) {
    $conn->query("ALTER TABLE applications ADD COLUMN county VARCHAR(50) NOT NULL AFTER license_plate_number");
    
    // Update existing rows with default county
    $conn->query("UPDATE applications SET county = 'Montserrado' WHERE county = '' OR county IS NULL");
    echo "<span style='color:green;'>ADDED!</span></li>";
} else {
    // Check for empty county values
    $emptyCheck2 = $conn->query("SELECT id FROM applications WHERE county = '' OR county IS NULL");
    if ($emptyCheck2->num_rows > 0) {
        echo "<br>Fixing empty county values... ";
        $conn->query("UPDATE applications SET county = 'Montserrado' WHERE county = '' OR county IS NULL");
        echo "<span style='color:green;'>Fixed!</span></li>";
    } else {
        echo "<span style='color:blue;'>Already exists!</span></li>";
    }
}

// Check if vehicles table exists, create it if not
echo "<li>Checking/Creating vehicles table... ";
$result = $conn->query("SHOW TABLES LIKE 'vehicles'");
if ($result->num_rows == 0) {
    $conn->query("CREATE TABLE vehicles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        plate_number VARCHAR(20) UNIQUE NOT NULL,
        make VARCHAR(50) NOT NULL,
        model VARCHAR(50) NOT NULL,
        year INT,
        color VARCHAR(30),
        owner_name VARCHAR(100) NOT NULL,
        owner_contact VARCHAR(20),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        created_by INT,
        FOREIGN KEY (created_by) REFERENCES users(id)
    )");
    echo "<span style='color:green;'>CREATED!</span></li>";
} else {
    echo "<span style='color:blue;'>Already exists!</span></li>";
}

// Check if payments table exists, create it if not
echo "<li>Checking/Creating payments table... ";
$result = $conn->query("SHOW TABLES LIKE 'payments'");
if ($result->num_rows == 0) {
    $conn->query("CREATE TABLE payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        application_id INT,
        amount DECIMAL(10,2) NOT NULL,
        payment_method ENUM('Cash','Card','Mobile Money'),
        payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        receipt_number VARCHAR(50) UNIQUE NOT NULL,
        status ENUM('Paid','Pending') DEFAULT 'Pending',
        received_by INT,
        FOREIGN KEY (application_id) REFERENCES applications(id),
        FOREIGN KEY (received_by) REFERENCES users(id)
    )");
    echo "<span style='color:green;'>CREATED!</span></li>";
} else {
    echo "<span style='color:blue;'>Already exists!</span></li>";
}

echo "</ol>";
echo "<h2 style='color:green; text-align:center;'>🎉 Database Update Complete! 🎉</h2>";
echo "<p style='text-align:center; font-size:18px;'><a href='index.php' style='background: #0a2463; color: white; padding: 10px 25px; text-decoration: none; border-radius: 5px;'>Go to Homepage</a></p>";
$conn->close();
?>
