<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
$conn = new mysqli("127.0.0.1", "root", "", null, 3306);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h1 style='color: #0a2463; text-align: center; margin: 40px 0 30px;'>🚀 Complete Database Fix</h1>";
echo "<div style='max-width: 900px; margin: 0 auto; font-family: Arial, sans-serif;'>";

// Step 1: Create or use database
echo "<h3 style='color: #c41e3a;'>Step 1: Setting up database...</h3>";
$conn->query("CREATE DATABASE IF NOT EXISTS car_license_registration");
$conn->select_db("car_license_registration");
echo "<p style='color: #166534;'>✓ Database ready</p><br>";

// Step 2: Create users table if not exists
echo "<h3 style='color: #c41e3a;'>Step 2: Checking users table...</h3>";
$conn->query("CREATE TABLE IF NOT EXISTS users (
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
)");
echo "<p style='color: #166534;'>✓ Users table ready</p><br>";

// Step 3: Create vehicles table if not exists
echo "<h3 style='color: #c41e3a;'>Step 3: Checking vehicles table...</h3>";
$conn->query("CREATE TABLE IF NOT EXISTS vehicles (
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
echo "<p style='color: #166534;'>✓ Vehicles table ready</p><br>";

// Step 4: Create applications table with all necessary columns
echo "<h3 style='color: #c41e3a;'>Step 4: Fixing applications table...</h3>";
$conn->query("DROP TABLE IF EXISTS applications");
$conn->query("CREATE TABLE applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    application_number VARCHAR(50) UNIQUE NOT NULL,
    license_plate_number VARCHAR(20) UNIQUE NOT NULL,
    county VARCHAR(50) NOT NULL,
    applicant_name VARCHAR(100) NOT NULL,
    applicant_contact VARCHAR(20),
    applicant_address TEXT,
    vehicle_id INT,
    status ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
    application_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_by INT,
    processed_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id),
    FOREIGN KEY (processed_by) REFERENCES users(id)
)");
echo "<p style='color: #166534;'>✓ Applications table recreated with all columns!</p><br>";

// Step 5: Create payments table if not exists
echo "<h3 style='color: #c41e3a;'>Step 5: Checking payments table...</h3>";
$conn->query("CREATE TABLE IF NOT EXISTS payments (
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
echo "<p style='color: #166534;'>✓ Payments table ready</p><br>";

// Step 6: Insert default admin user if not exists
echo "<h3 style='color: #c41e3a;'>Step 6: Setting up default users...</h3>";
$check_admin = $conn->query("SELECT * FROM users WHERE username='admin'");
if ($check_admin->num_rows == 0) {
    $conn->query("INSERT INTO users (fullname, username, email, password, role, status) VALUES ('System Administrator', 'admin', 'admin@tua.gov.lr', 'admin123', 'Admin', 'Active')");
    echo "<p style='color: #166534;'>✓ Admin user created</p>";
} else {
    echo "<p style='color: #166534;'>✓ Admin user already exists</p>";
}

$check_officer = $conn->query("SELECT * FROM users WHERE username='officer'");
if ($check_officer->num_rows == 0) {
    $conn->query("INSERT INTO users (fullname, username, email, password, role, status) VALUES ('John Officer', 'officer', 'officer@tua.gov.lr', 'officer123', 'Officer', 'Active')");
    echo "<p style='color: #166534;'>✓ Officer user created</p>";
}

$check_clerk = $conn->query("SELECT * FROM users WHERE username='clerk'");
if ($check_clerk->num_rows == 0) {
    $conn->query("INSERT INTO users (fullname, username, email, password, role, status) VALUES ('Jane Clerk', 'clerk', 'clerk@tua.gov.lr', 'clerk123', 'Clerk', 'Active')");
    echo "<p style='color: #166534;'>✓ Clerk user created</p>";
}

echo "<br><div style='background: #dcfce7; padding: 25px; border-radius: 15px; text-align: center; margin-top: 40px;'>";
echo "<h2 style='color: #166534; margin-bottom: 10px;'>🎉 ALL ISSUES RESOLVED!</h2>";
echo "<p style='color: #065f46; font-size: 17px; margin-bottom: 20px;'>Your database is now fully set up and ready to use!</p>";
echo "<a href='index.php' style='display: inline-block; background: #0a2463; color: white; padding: 15px 40px; border-radius: 10px; text-decoration: none; font-weight: bold; font-size: 16px;'>🏠 Go to Homepage</a>";
echo "</div>";

echo "</div>";

$conn->close();
?>
