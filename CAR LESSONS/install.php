<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = "127.0.0.1";
$user = "root";
$password = "";
$port = 3307;

echo "<h1>Installing Database...</h1>";
echo "<ol>";

// Connect to MySQL server
echo "<li>Connecting to MySQL server... ";
$conn = new mysqli($host, $user, $password, '', $port);
if ($conn->connect_error) {
    die("<span style='color:red;'>FAILED: " . $conn->connect_error . "</span></li>");
}
echo "<span style='color:green;'>OK!</span></li>";

// Create database
echo "<li>Creating database 'car_license_registration'... ";
$sql = "CREATE DATABASE IF NOT EXISTS car_license_registration";
if ($conn->query($sql) === TRUE) {
    echo "<span style='color:green;'>OK!</span></li>";
} else {
    die("<span style='color:red;'>FAILED: " . $conn->error . "</span></li>");
}

// Select the database
echo "<li>Selecting database... ";
$conn->select_db("car_license_registration");
echo "<span style='color:green;'>OK!</span></li>";

// Create users table
echo "<li>Creating 'users' table... ";
$sql = "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fullname VARCHAR(100) NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100),
    contact_number VARCHAR(20),
    county VARCHAR(50),
    address TEXT,
    password VARCHAR(255) NOT NULL,
    role ENUM('Admin','Officer','Clerk','Applicant') DEFAULT 'Applicant',
    status ENUM('Active','Inactive') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
if ($conn->query($sql) === TRUE) {
    echo "<span style='color:green;'>OK!</span></li>";
} else {
    die("<span style='color:red;'>FAILED: " . $conn->error . "</span></li>");
}

// Create applicants table
echo "<li>Creating 'applicants' table... ";
$sql = "CREATE TABLE IF NOT EXISTS applicants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fullname VARCHAR(100) NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100),
    contact_number VARCHAR(20),
    county VARCHAR(50),
    address TEXT,
    password VARCHAR(255) NOT NULL,
    status ENUM('Active','Inactive') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
if ($conn->query($sql) === TRUE) {
    echo "<span style='color:green;'>OK!</span></li>";
} else {
    die("<span style='color:red;'>FAILED: " . $conn->error . "</span></li>");
}

// Create vehicles table
echo "<li>Creating 'vehicles' table... ";
$sql = "CREATE TABLE IF NOT EXISTS vehicles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plate_number VARCHAR(20) UNIQUE NOT NULL,
    vehicle_type VARCHAR(30) NULL,
    vehicle_use_class ENUM('Private Vehicle','Commercial Vehicle') NOT NULL DEFAULT 'Private Vehicle',
    official_vehicle_category VARCHAR(50) NOT NULL DEFAULT 'Private Vehicle',
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
if ($conn->query($sql) === TRUE) {
    echo "<span style='color:green;'>OK!</span></li>";
} else {
    die("<span style='color:red;'>FAILED: " . $conn->error . "</span></li>");
}

// Create applications table
echo "<li>Creating 'applications' table... ";
$sql = "CREATE TABLE IF NOT EXISTS applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    application_number VARCHAR(50) UNIQUE NOT NULL,
    license_plate_number VARCHAR(20) UNIQUE NULL,
    vehicle_use_class ENUM('Private Vehicle','Commercial Vehicle') NOT NULL DEFAULT 'Private Vehicle',
    official_vehicle_category VARCHAR(50) NOT NULL DEFAULT 'Private Vehicle',
    county VARCHAR(50) NOT NULL,
    applicant_name VARCHAR(100) NOT NULL,
    applicant_contact VARCHAR(20),
    applicant_address TEXT,
    vehicle_id INT,
    status ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
    application_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    issued_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    processed_by INT,
    processed_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id),
    FOREIGN KEY (processed_by) REFERENCES users(id)
)";
if ($conn->query($sql) === TRUE) {
    echo "<span style='color:green;'>OK!</span></li>";
} else {
    die("<span style='color:red;'>FAILED: " . $conn->error . "</span></li>");
}

// Create payments table
echo "<li>Creating 'payments' table... ";
$sql = "CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT,
    amount DECIMAL(10,2) NOT NULL,
    payment_currency ENUM('USD','LRD') NOT NULL DEFAULT 'USD',
    payment_method ENUM('Cash','Card','Mobile Money'),
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    receipt_number VARCHAR(50) UNIQUE NOT NULL,
    status ENUM('Paid','Pending') DEFAULT 'Pending',
    received_by INT,
    FOREIGN KEY (application_id) REFERENCES applications(id),
    FOREIGN KEY (received_by) REFERENCES users(id)
)";
if ($conn->query($sql) === TRUE) {
    echo "<span style='color:green;'>OK!</span></li>";
} else {
    die("<span style='color:red;'>FAILED: " . $conn->error . "</span></li>");
}

// Create admin notifications table
echo "<li>Creating 'admin_notifications' table... ";
$sql = "CREATE TABLE IF NOT EXISTS admin_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(50) NOT NULL,
    message VARCHAR(255) NOT NULL,
    reference_id INT DEFAULT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
if ($conn->query($sql) === TRUE) {
    echo "<span style='color:green;'>OK!</span></li>";
} else {
    die("<span style='color:red;'>FAILED: " . $conn->error . "</span></li>");
}

// Create contact messages table
echo "<li>Creating 'contact_messages' table... ";
$sql = "CREATE TABLE IF NOT EXISTS contact_messages (
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
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
if ($conn->query($sql) === TRUE) {
    echo "<span style='color:green;'>OK!</span></li>";
} else {
    die("<span style='color:red;'>FAILED: " . $conn->error . "</span></li>");
}

// Ensure upgrade columns for older installs
echo "<li>Ensuring contact message columns exist... ";
$conn->query("ALTER TABLE contact_messages ADD COLUMN IF NOT EXISTS reply_to_email VARCHAR(100) DEFAULT NULL");
$conn->query("ALTER TABLE contact_messages ADD COLUMN IF NOT EXISTS reply_from_email VARCHAR(100) DEFAULT 'ardeliusnet@outlook.com'");
$conn->query("ALTER TABLE contact_messages ADD COLUMN IF NOT EXISTS is_read TINYINT(1) NOT NULL DEFAULT 0");
echo "<span style='color:green;'>OK!</span></li>";

$applicationApplicantIdCheck = $conn->query("SHOW COLUMNS FROM applications LIKE 'applicant_id'");
if ($applicationApplicantIdCheck && $applicationApplicantIdCheck->num_rows == 0) {
    $conn->query("ALTER TABLE applications ADD COLUMN applicant_id INT NULL AFTER user_id");
    $conn->query("ALTER TABLE applications ADD INDEX idx_applicant_id (applicant_id)");
    $conn->query("ALTER TABLE applications ADD CONSTRAINT fk_applications_applicant FOREIGN KEY (applicant_id) REFERENCES applicants(id)");
}

// Insert test users
echo "<li>Adding test users... ";
$check_admin = $conn->query("SELECT * FROM users WHERE username='admin'");
if ($check_admin->num_rows == 0) {
    $conn->query("INSERT INTO users (fullname, username, email, password, role, status) VALUES ('System Administrator', 'admin', 'admin@example.com', 'admin123', 'Admin', 'Active')");
}

$check_officer = $conn->query("SELECT * FROM users WHERE username='officer'");
if ($check_officer->num_rows == 0) {
    $conn->query("INSERT INTO users (fullname, username, email, password, role, status) VALUES ('John Officer', 'officer', 'officer@example.com', 'officer123', 'Officer', 'Active')");
}

$check_clerk = $conn->query("SELECT * FROM users WHERE username='clerk'");
if ($check_clerk->num_rows == 0) {
    $conn->query("INSERT INTO users (fullname, username, email, password, role, status) VALUES ('Jane Clerk', 'clerk', 'clerk@example.com', 'clerk123', 'Clerk', 'Active')");
}
echo "<span style='color:green;'>OK!</span></li>";

echo "</ol>";
echo "<h2 style='color:green; text-align:center;'>🎉 Installation Complete! 🎉</h2>";
echo "<p style='text-align:center; font-size:18px;'><a href='index.php' style='background: #007bff; color: white; padding: 10px 25px; text-decoration: none; border-radius: 5px;'>Go to Homepage</a></p>";
$conn->close();
?>
