<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = "127.0.0.1";
$user = "root";
$password = "";
$database = "car_license_registration";
$port = 3306;

echo "<h1>Fixing Database...</h1>";
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

// Step 1: Check if license_plate_number column exists
echo "<li>Checking license_plate_number column... ";
$colCheck = $conn->query("SHOW COLUMNS FROM applications LIKE 'license_plate_number'");
$oldColCheck = $conn->query("SHOW COLUMNS FROM applications LIKE 'lesson_plate_number'");
if ($colCheck->num_rows == 0 && $oldColCheck->num_rows > 0) {
    echo "<br>Renaming lesson_plate_number to license_plate_number... ";
    $conn->query("ALTER TABLE applications CHANGE COLUMN lesson_plate_number license_plate_number VARCHAR(20) NULL");
    $colCheck = $conn->query("SHOW COLUMNS FROM applications LIKE 'license_plate_number'");
}
if ($colCheck->num_rows > 0) {
    // First, DROP the UNIQUE constraint temporarily
    echo "<br>Dropping UNIQUE constraint (if exists)... ";
    $conn->query("ALTER TABLE applications DROP INDEX license_plate_number");
    
    // Update all empty license_plate_number values
    echo "<br>Fixing empty license_plate_number values... ";
    $result = $conn->query("SELECT id FROM applications WHERE license_plate_number = '' OR license_plate_number IS NULL ORDER BY id");
    $count = 1;
    while ($row = $result->fetch_assoc()) {
        $id = $row['id'];
        $defaultPlate = "LP-MS-".str_pad($count, 4, '0', STR_PAD_LEFT);
        $conn->query("UPDATE applications SET license_plate_number = '$defaultPlate' WHERE id = $id");
        $count++;
    }
    
    // Now add back the UNIQUE constraint
    echo "<br>Adding UNIQUE constraint back... ";
    $conn->query("ALTER TABLE applications ADD UNIQUE KEY (license_plate_number)");
    echo "<span style='color:green;'>Fixed!</span></li>";
} else {
    echo "<span style='color:blue;'>Column not there, add it!</span></li>";
}

// Step 2: Fix county column
echo "<li>Checking county column... ";
$colCheck2 = $conn->query("SHOW COLUMNS FROM applications LIKE 'county'");
if ($colCheck2->num_rows > 0) {
    // Update all empty county values
    echo "<br>Fixing empty county values... ";
    $conn->query("UPDATE applications SET county = 'Montserrado' WHERE county = '' OR county IS NULL");
    echo "<span style='color:green;'>Fixed!</span></li>";
} else {
    echo "<span style='color:blue;'>Column not there, add it!</span></li>";
}

// Step 3: Check vehicles and payments tables
echo "<li>Checking vehicles table... ";
$tableCheck = $conn->query("SHOW TABLES LIKE 'vehicles'");
if ($tableCheck->num_rows == 0) {
    echo "<br>Creating vehicles table... ";
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
    echo "<span style='color:green;'>Created!</span></li>";
} else {
    echo "<span style='color:blue;'>Exists!</span></li>";
}

echo "<li>Checking payments table... ";
$tableCheck2 = $conn->query("SHOW TABLES LIKE 'payments'");
if ($tableCheck2->num_rows == 0) {
    echo "<br>Creating payments table... ";
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
    echo "<span style='color:green;'>Created!</span></li>";
} else {
    echo "<span style='color:blue;'>Exists!</span></li>";
}

echo "</ol>";
echo "<h2 style='color:green; text-align:center;'>🎉 Database Fixed! 🎉</h2>";
echo "<p style='text-align:center; font-size:18px;'><a href='index.php' style='background: #0a2463; color: white; padding: 10px 25px; text-decoration: none; border-radius: 5px;'>Go to Homepage</a></p>";
$conn->close();
?>
