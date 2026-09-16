<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
$conn = new mysqli("localhost", "root", "", "car_license_registration");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h1>Fixing Database...</h1>";
echo "<style>body{font-family:Arial,sans-serif;padding:40px;} li{margin:15px 0;} .success{color:green;} .error{color:red;}</style>";
echo "<ol>";

// Step 1: Fix license_plate_number column
echo "<li>Fixing 'license_plate_number' column... ";
$colCheck = $conn->query("SHOW COLUMNS FROM applications LIKE 'license_plate_number'");
$oldColCheck = $conn->query("SHOW COLUMNS FROM applications LIKE 'lesson_plate_number'");
if ($colCheck->num_rows == 0 && $oldColCheck->num_rows > 0) {
    $conn->query("ALTER TABLE applications CHANGE COLUMN lesson_plate_number license_plate_number VARCHAR(20) NOT NULL DEFAULT 'LP-MS-0000'");
    $conn->query("ALTER TABLE applications ADD UNIQUE KEY license_plate_unique (license_plate_number)");
    echo "<span class='success'>✅ Renamed existing column successfully!</span></li>";
} elseif ($colCheck->num_rows == 0) {
    // Add the column without UNIQUE constraint
    $conn->query("ALTER TABLE applications ADD COLUMN license_plate_number VARCHAR(20) NOT NULL DEFAULT 'LP-MS-0000' AFTER application_number");
    
    // Update all existing rows with unique values
    $result = $conn->query("SELECT id FROM applications ORDER BY id");
    $count = 1;
    while ($row = $result->fetch_assoc()) {
        $id = $row['id'];
        $newPlate = "LP-MS-" . str_pad($count, 4, '0', STR_PAD_LEFT);
        $conn->query("UPDATE applications SET license_plate_number = '$newPlate' WHERE id = $id");
        $count++;
    }
    
    // Now add UNIQUE constraint
    $conn->query("ALTER TABLE applications ADD UNIQUE KEY license_plate_unique (license_plate_number)");
    echo "<span class='success'>✅ Added and populated successfully!</span></li>";
} else {
    echo "<span class='success'>✅ Already exists!</span></li>";
}

// Step 2: Fix county column
echo "<li>Fixing 'county' column... ";
$colCheck2 = $conn->query("SHOW COLUMNS FROM applications LIKE 'county'");
if ($colCheck2->num_rows == 0) {
    $conn->query("ALTER TABLE applications ADD COLUMN county VARCHAR(50) NOT NULL DEFAULT 'Montserrado' AFTER license_plate_number");
    echo "<span class='success'>✅ Added successfully!</span></li>";
} else {
    // Update empty county values
    $conn->query("UPDATE applications SET county = 'Montserrado' WHERE county = '' OR county IS NULL");
    echo "<span class='success'>✅ Already exists and fixed empty values!</span></li>";
}

// Step 3: Fix vehicles table
echo "<li>Checking 'vehicles' table... ";
$tableCheck = $conn->query("SHOW TABLES LIKE 'vehicles'");
if ($tableCheck->num_rows == 0) {
    $sql = "CREATE TABLE vehicles (
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
    )";
    $conn->query($sql);
    echo "<span class='success'>✅ Created successfully!</span></li>";
} else {
    echo "<span class='success'>✅ Already exists!</span></li>";
}

// Step 4: Fix payments table
echo "<li>Checking 'payments' table... ";
$tableCheck2 = $conn->query("SHOW TABLES LIKE 'payments'");
if ($tableCheck2->num_rows == 0) {
    $sql = "CREATE TABLE payments (
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
    )";
    $conn->query($sql);
    echo "<span class='success'>✅ Created successfully!</span></li>";
} else {
    echo "<span class='success'>✅ Already exists!</span></li>";
}

echo "</ol>";
echo "<h2 class='success' style='text-align:center;'>🎉 DATABASE FULLY FIXED! 🎉</h2>";
echo "<p style='text-align:center;'><a href='index.php' style='background:#0a2463;color:white;padding:12px 24px;text-decoration:none;border-radius:8px;font-weight:bold;'>GO TO HOME PAGE</a></p>";

$conn->close();
?>
