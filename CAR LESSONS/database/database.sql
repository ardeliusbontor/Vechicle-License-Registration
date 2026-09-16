CREATE DATABASE car_license_registration;

USE car_license_registration;

CREATE TABLE users (

    id INT AUTO_INCREMENT PRIMARY KEY,

    fullname VARCHAR(100) NOT NULL,

    username VARCHAR(50) UNIQUE NOT NULL,

    email VARCHAR(100),

    contact_number VARCHAR(20),

    county VARCHAR(50),

    address TEXT,

    password VARCHAR(255) NOT NULL,

    role ENUM('Admin','Officer','Clerk') DEFAULT 'Admin',

    status ENUM('Active','Inactive') DEFAULT 'Active',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP

);

CREATE TABLE applicants (

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

);

CREATE TABLE vehicles (

    id INT AUTO_INCREMENT PRIMARY KEY,

    plate_number VARCHAR(20) UNIQUE NOT NULL,

    vehicle_type VARCHAR(30) NULL,

    vehicle_use_class ENUM('Private Vehicle','Commercial Vehicle') NOT NULL DEFAULT 'Private Vehicle',

    official_vehicle_category VARCHAR(50) NOT NULL DEFAULT 'Private Vehicle',

    status ENUM('Pending','Active','Expired','Rejected') NOT NULL DEFAULT 'Pending',

    expiry_date DATE NULL,

    make VARCHAR(50) NOT NULL,

    model VARCHAR(50) NOT NULL,

    year INT,

    color VARCHAR(30),

    owner_name VARCHAR(100) NOT NULL,

    owner_contact VARCHAR(20),

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    created_by INT NULL,

    created_by_applicant INT NULL,

    FOREIGN KEY (created_by) REFERENCES users(id),

    FOREIGN KEY (created_by_applicant) REFERENCES applicants(id)

);

CREATE TABLE applications (

    id INT AUTO_INCREMENT PRIMARY KEY,

    applicant_id INT NULL,

    user_id INT NULL,

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

    FOREIGN KEY (applicant_id) REFERENCES applicants(id),

    FOREIGN KEY (user_id) REFERENCES users(id),

    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id),

    FOREIGN KEY (processed_by) REFERENCES users(id)

);

CREATE TABLE payments (

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

);

CREATE TABLE admin_notifications (

    id INT AUTO_INCREMENT PRIMARY KEY,

    event_type VARCHAR(50) NOT NULL,

    message VARCHAR(255) NOT NULL,

    reference_id INT DEFAULT NULL,

    is_read TINYINT(1) NOT NULL DEFAULT 0,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP

);

CREATE TABLE contact_messages (

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

);

-- Insert test admin user
INSERT INTO users (fullname, username, email, password, role, status)
VALUES ('System Administrator', 'admin', 'admin@example.com', 'admin123', 'Admin', 'Active');

-- Insert test officer user
INSERT INTO users (fullname, username, email, password, role, status)
VALUES ('John Officer', 'officer', 'officer@example.com', 'officer123', 'Officer', 'Active');

-- Insert test clerk user
INSERT INTO users (fullname, username, email, password, role, status)
VALUES ('Jane Clerk', 'clerk', 'clerk@example.com', 'clerk123', 'Clerk', 'Active');
