<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

function appLoadEnvFile($path) {
    if (!is_file($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            continue;
        }

        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }

        $key = trim($parts[0]);
        $value = trim($parts[1]);
        $value = preg_replace('/^(["\'])|(["\'])$/', '', $value);

        if ($key === '') {
            continue;
        }

        $_ENV[$key] = $value;
        putenv($key . '=' . $value);
    }
}

appLoadEnvFile(__DIR__ . '/.env');

function appEnv($key, $default = null) {
    $value = getenv($key);
    if ($value === false || $value === null || $value === '') {
        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }
        return $default;
    }
    return $value;
}

function appIsProduction() {
    return strtolower((string) appEnv('APP_ENV', 'local')) === 'production';
}

$rawHost = trim((string) appEnv('DB_HOST', '127.0.0.1'));
$hostCandidates = array_values(array_unique(array_filter([
    $rawHost !== '' ? $rawHost : '127.0.0.1',
    '127.0.0.1',
    'localhost',
    '::1',
], function ($value) {
    return trim((string) $value) !== '';
})));

$user = trim((string) appEnv('DB_USER', 'root'));
if ($user === '') {
    $user = 'root';
}

$password = (string) appEnv('DB_PASSWORD', '');
$database = trim((string) appEnv('DB_NAME', 'car_license_registration'));
if ($database === '') {
    $database = 'car_license_registration';
}

$portCandidates = array_values(array_unique(array_filter([
    (int) appEnv('DB_PORT', 3306),
    3306,
    3307,
    3308,
], function ($value) {
    return $value > 0;
})));

$conn = false;
$host = $hostCandidates[0] ?? '127.0.0.1';
$port = $portCandidates[0] ?? 3306;

foreach ($hostCandidates as $candidateHost) {
    foreach ($portCandidates as $candidatePort) {
        $conn = mysqli_connect($candidateHost, $user, $password, $database, $candidatePort);
        if ($conn) {
            $host = $candidateHost;
            $port = $candidatePort;
            break 2;
        }
    }
}

if (!$conn) {
    $databaseError = mysqli_connect_error();
    die("Database Connection Failed. Please start MySQL in XAMPP and confirm the database is available on port 3306/3307. Error: " . ($databaseError ?: 'Connection refused'));
}

if (function_exists('session_status') && session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_secure', appIsProduction() ? '1' : '0');
    ini_set('session.gc_maxlifetime', '1800');
}

function appEnsureSchemaCompatibility($conn) {
    $roleColumnCheck = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'role'");
    if ($roleColumnCheck && mysqli_num_rows($roleColumnCheck) > 0) {
        $roleColumn = mysqli_fetch_assoc($roleColumnCheck);
        $columnType = strtolower((string) ($roleColumn['Type'] ?? ''));
        if ($columnType !== '' && stripos($columnType, 'applicant') === false) {
            mysqli_query($conn, "ALTER TABLE users MODIFY COLUMN role ENUM('Admin','Officer','Clerk','Applicant') DEFAULT 'Applicant'");
        }
    }

    $statusColumnCheck = mysqli_query($conn, "SHOW COLUMNS FROM applicants LIKE 'status'");
    if ($statusColumnCheck && mysqli_num_rows($statusColumnCheck) === 0) {
        mysqli_query($conn, "ALTER TABLE applicants ADD COLUMN status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active' AFTER password");
    }

    $applicantPasswordCheck = mysqli_query($conn, "SHOW COLUMNS FROM applicants LIKE 'password'");
    if ($applicantPasswordCheck && mysqli_num_rows($applicantPasswordCheck) === 0) {
        mysqli_query($conn, "ALTER TABLE applicants ADD COLUMN password VARCHAR(255) NOT NULL AFTER email");
    }
}

appEnsureSchemaCompatibility($conn);
appEnsurePlateStatusCompatibility($conn);

function appBaseUrl() {
    $configuredUrl = trim((string) appEnv('APP_URL', ''));
    if ($configuredUrl !== '') {
        return rtrim($configuredUrl, '/');
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
    $basePath = str_replace('\\', '/', dirname($scriptName));
    $basePath = rtrim($basePath, '/');

    if ($basePath === '.' || $basePath === '/' || $basePath === '') {
        $basePath = '';
    }

    $encodedPath = preg_replace('/(?<!%20)\s+/', '%20', $basePath);
    return $scheme . '://' . $host . ($encodedPath ? '/' . ltrim($encodedPath, '/') : '');
}

function appPasswordHash($password) {
    return password_hash((string) $password, PASSWORD_DEFAULT);
}

function appPasswordVerify($password, $storedHash) {
    $plainPassword = (string) $password;
    $storedHash = (string) $storedHash;

    if ($storedHash === '') {
        return false;
    }

    if (password_verify($plainPassword, $storedHash)) {
        return true;
    }

    return $storedHash === $plainPassword;
}

function appSendSmtpEmail($to, $subject, $body, $fromEmail = null, $fromName = 'Transport Union Authority') {
    $smtpHost = appEnv('SMTP_HOST');
    $smtpPort = (int) appEnv('SMTP_PORT', 587);
    $smtpUser = appEnv('SMTP_USERNAME');
    $smtpPassword = appEnv('SMTP_PASSWORD');
    $fromEmail = $fromEmail ?: appEnv('SMTP_FROM', $smtpUser ?: 'no-reply@localhost');

    if (!$smtpHost || !$smtpUser || !$smtpPassword) {
        $headers = "From: " . $fromEmail . "\r\n";
        $headers .= "Reply-To: " . $fromEmail . "\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        return mail($to, $subject, $body, $headers);
    }

    $safeSubject = 'Re: ' . preg_replace('/[\r\n]+/', '', $subject);
    $messageBody = "Hello,\r\n\r\n" . $body . "\r\n\r\n---\r\nThis message was sent from the Transport Union Authority system.\r\n";
    $message = "Subject: $safeSubject\r\n";
    $message .= "From: " . $fromName . " <" . $fromEmail . ">\r\n";
    $message .= "To: " . $to . "\r\n";
    $message .= "Reply-To: " . $fromEmail . "\r\n";
    $message .= "MIME-Version: 1.0\r\n";
    $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $message .= "X-Mailer: PHP/" . phpversion() . "\r\n";
    $message .= "\r\n";
    $message .= $messageBody;
    $message .= "\r\n.\r\n";

    $socket = fsockopen('tls://' . $smtpHost, $smtpPort, $errno, $errstr, 20);
    if (!$socket) {
        return false;
    }

    $response = fgets($socket);
    if (substr($response, 0, 3) !== '220') {
        fclose($socket);
        return false;
    }

    $commands = [
        'EHLO localhost',
        'AUTH LOGIN',
        base64_encode($smtpUser),
        base64_encode($smtpPassword),
        'MAIL FROM:<' . $fromEmail . '>',
        'RCPT TO:<' . $to . '>',
        'DATA'
    ];

    foreach ($commands as $command) {
        fwrite($socket, $command . "\r\n");
        $result = fgets($socket);
        if ($command === 'EHLO localhost' && substr($result, 0, 3) !== '250') {
            fclose($socket);
            return false;
        }
        if ($command === 'AUTH LOGIN' && substr($result, 0, 3) !== '334') {
            fclose($socket);
            return false;
        }
        if ($command === base64_encode($smtpUser) && substr($result, 0, 3) !== '334') {
            fclose($socket);
            return false;
        }
        if ($command === base64_encode($smtpPassword) && substr($result, 0, 3) !== '235') {
            fclose($socket);
            return false;
        }
        if (($command === 'MAIL FROM:<' . $fromEmail . '>' || $command === 'RCPT TO:<' . $to . '>' || $command === 'DATA') && substr($result, 0, 3) !== '250' && substr($result, 0, 3) !== '354') {
            fclose($socket);
            return false;
        }
    }

    fwrite($socket, $message);
    $finalResult = fgets($socket);
    fwrite($socket, "QUIT\r\n");
    fclose($socket);

    return substr($finalResult, 0, 3) === '250';
}

function appVerificationUrl($searchValue) {
    $baseUrl = appBaseUrl();
    return $baseUrl . '/verify.php?search=' . rawurlencode((string) $searchValue);
}

function appNormalizePaymentCurrency($currency = null) {
    $normalized = strtoupper(trim((string) ($currency ?? 'USD')));
    if ($normalized === 'LRD') {
        return 'LRD';
    }
    return 'USD';
}

function appNormalizePaymentStatus($value = null) {
    $normalized = strtoupper(trim((string) ($value ?? 'Pending')));

    if ($normalized === 'PAID') {
        return 'Paid';
    }

    if ($normalized === 'PENDING' || $normalized === 'PENDING PAYMENT') {
        return 'Pending';
    }

    return 'Pending';
}

function appFormatPaymentAmount($amount, $currency = 'USD') {
    $currencyCode = appNormalizePaymentCurrency($currency);
    $numericAmount = (float) $amount;

    if ($currencyCode === 'LRD') {
        return 'LRD ' . number_format($numericAmount, 2, '.', ',');
    }

    return 'USD ' . number_format($numericAmount, 2, '.', ',');
}

function appPlateExpiryDeadlineTimestamp($expiryDate = null) {
    if ($expiryDate === null || $expiryDate === '') {
        return null;
    }

    $expiryTimestamp = strtotime((string) $expiryDate);
    if ($expiryTimestamp === false) {
        return null;
    }

    return strtotime(date('Y-m-d 23:59:59', $expiryTimestamp));
}

function appIsPlateExpired($expiryDate = null) {
    $expiryTimestamp = appPlateExpiryDeadlineTimestamp($expiryDate);
    if ($expiryTimestamp === null) {
        return false;
    }

    return time() > $expiryTimestamp;
}

function appIsPlateExpiringSoon($expiryDate = null, $daysThreshold = 30) {
    if ($expiryDate === null || $expiryDate === '') {
        return false;
    }

    $expiryTimestamp = appPlateExpiryDeadlineTimestamp($expiryDate);
    if ($expiryTimestamp === null) {
        return false;
    }

    $now = time();
    if ($now > $expiryTimestamp) {
        return false;
    }

    $windowEnd = $now + ((int) $daysThreshold * 24 * 60 * 60);
    return $expiryTimestamp >= $now && $expiryTimestamp <= $windowEnd;
}

function appEffectivePlateStatus($status, $expiryDate = null) {
    $normalizedStatus = strtoupper(trim((string) $status));

    if ($normalizedStatus === 'BLOCKED') {
        return 'Blocked';
    }

    if ($normalizedStatus === 'REJECTED') {
        return 'Rejected';
    }

    if (appIsPlateExpired($expiryDate)) {
        return 'Expired';
    }

    if (appIsPlateExpiringSoon($expiryDate, 30)) {
        return 'Expiring Soon';
    }

    if ($normalizedStatus === 'PENDING') {
        return 'Pending';
    }

    if ($normalizedStatus === 'EXPIRED') {
        return 'Expired';
    }

    if ($normalizedStatus === 'APPROVED') {
        return 'Approved';
    }

    if ($normalizedStatus === 'ACTIVE') {
        return 'Active';
    }

    return $normalizedStatus !== '' ? ucfirst(strtolower($normalizedStatus)) : 'Pending';
}

function appResolvePlateStatus($vehicleStatus = null, $vehicleExpiryDate = null, $applicationStatus = null, $applicationExpiryDate = null) {
    $vehicleStatusValue = appEffectivePlateStatus($vehicleStatus ?? 'Pending', $vehicleExpiryDate ?? null);
    $applicationStatusValue = appEffectivePlateStatus($applicationStatus ?? 'Pending', $applicationExpiryDate ?? null);

    $statusPriority = ['Blocked', 'Rejected', 'Expired', 'Expiring Soon', 'Active', 'Approved', 'Pending'];
    $allStatuses = [$applicationStatusValue, $vehicleStatusValue];

    foreach ($statusPriority as $priorityStatus) {
        if (in_array($priorityStatus, $allStatuses, true)) {
            return $priorityStatus;
        }
    }

    return 'Pending';
}

function appEnsurePlateStatusCompatibility($conn) {
    $statusTables = ['vehicles', 'applications'];
    foreach ($statusTables as $tableName) {
        $columnCheck = mysqli_query($conn, "SHOW COLUMNS FROM `{$tableName}` LIKE 'status'");
        if ($columnCheck && mysqli_num_rows($columnCheck) > 0) {
            $column = mysqli_fetch_assoc($columnCheck);
            $columnType = strtolower((string) ($column['Type'] ?? ''));
            if (stripos($columnType, 'enum') !== false) {
                mysqli_query($conn, "ALTER TABLE `{$tableName}` MODIFY COLUMN status ENUM('Pending','Approved','Rejected','Blocked','Active','Expired') NOT NULL DEFAULT 'Pending'");
            }
        }
    }
}

function appNormalizeVehicleUseClass($value) {
    $normalized = strtolower(trim((string) $value));
    if ($normalized === 'commercial' || $normalized === 'commercial vehicle' || $normalized === 'commercial_vehicle' || $normalized === 'commercial-vehicle') {
        return 'Commercial Vehicle';
    }
    if ($normalized === 'private' || $normalized === 'private vehicle' || $normalized === 'private_vehicle' || $normalized === 'private-vehicle') {
        return 'Private Vehicle';
    }
    return 'Private Vehicle';
}

function appNormalizeOfficialVehicleCategory($value) {
    $normalized = strtolower(trim((string) $value));
    if ($normalized === '' || $normalized === 'private vehicle') {
        return 'Private Vehicle';
    }
    if ($normalized === 'commercial vehicle' || $normalized === 'commercial') {
        return 'Commercial Vehicle';
    }
    if (str_contains($normalized, 'motorcycle')) {
        return 'Motorcycle';
    }
    return ucfirst(trim((string) $value));
}

function appIsMotorcycleCategory($officialVehicleCategory = null, $vehicleUseClass = null) {
    $category = strtolower(trim((string) ($officialVehicleCategory ?? '')));
    if (str_contains($category, 'motorcycle')) {
        return true;
    }
    $classValue = strtolower(trim((string) ($vehicleUseClass ?? '')));
    return str_contains($classValue, 'motorcycle');
}

function appGetVehiclePlateColor($vehicleUseClass = null, $officialVehicleCategory = null) {
    if (appIsMotorcycleCategory($officialVehicleCategory, $vehicleUseClass)) {
        return 'blue';
    }

    if (appNormalizeVehicleUseClass($vehicleUseClass) === 'Commercial Vehicle') {
        return 'yellow';
    }

    return 'orange';
}

function appVehiclePlateColorStyle($vehicleUseClass = null, $officialVehicleCategory = null) {
    $useClass = strtolower(trim((string) ($vehicleUseClass ?? '')));
    $category = strtolower(trim((string) ($officialVehicleCategory ?? '')));

    if (str_contains($category, 'motorcycle') || str_contains($useClass, 'motorcycle')) {
        return ['background' => 'linear-gradient(180deg, #2563eb 0%, #1d4ed8 100%)', 'border' => '#1d4ed8', 'text' => '#ffffff'];
    }

    if (str_contains($useClass, 'commercial') || str_contains($category, 'commercial')) {
        return ['background' => 'linear-gradient(180deg, #facc15 0%, #eab308 100%)', 'border' => '#eab308', 'text' => '#1f2937'];
    }

    return ['background' => 'linear-gradient(180deg, #ef8d2f 0%, #ea7f1c 100%)', 'border' => '#d96f12', 'text' => '#ffffff'];
}

function appGenerateMotorcyclePlateNumber($conn) {
    $attempts = 0;
    $maxAttempts = 50000;

    while ($attempts < $maxAttempts) {
        $digits = str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);
        if (!preg_match('/^\d{5}$/', $digits)) {
            $attempts++;
            continue;
        }

        $safePlate = mysqli_real_escape_string($conn, $digits);
        $vehicleCheck = mysqli_query($conn, "SELECT id FROM vehicles WHERE plate_number = '$safePlate' LIMIT 1");
        if ($vehicleCheck && mysqli_num_rows($vehicleCheck) > 0) {
            $attempts++;
            continue;
        }

        $applicationCheck = mysqli_query($conn, "SELECT id FROM applications WHERE license_plate_number = '$safePlate' LIMIT 1");
        if ($applicationCheck && mysqli_num_rows($applicationCheck) > 0) {
            $attempts++;
            continue;
        }

        return $digits;
    }

    return null;
}

function appEnsureVehicleClassificationColumns($conn) {
    $checks = [
        ['table' => 'vehicles', 'column' => 'vehicle_use_class', 'sql' => "ALTER TABLE vehicles ADD COLUMN vehicle_use_class ENUM('Private Vehicle','Commercial Vehicle') NOT NULL DEFAULT 'Private Vehicle' AFTER vehicle_type"],
        ['table' => 'vehicles', 'column' => 'official_vehicle_category', 'sql' => "ALTER TABLE vehicles ADD COLUMN official_vehicle_category VARCHAR(50) NOT NULL DEFAULT 'Private Vehicle' AFTER vehicle_use_class"],
        ['table' => 'applications', 'column' => 'vehicle_use_class', 'sql' => "ALTER TABLE applications ADD COLUMN vehicle_use_class ENUM('Private Vehicle','Commercial Vehicle') NOT NULL DEFAULT 'Private Vehicle' AFTER vehicle_id"],
        ['table' => 'applications', 'column' => 'official_vehicle_category', 'sql' => "ALTER TABLE applications ADD COLUMN official_vehicle_category VARCHAR(50) NOT NULL DEFAULT 'Private Vehicle' AFTER vehicle_use_class"],
    ];

    foreach ($checks as $check) {
        $exists = mysqli_query($conn, "SHOW COLUMNS FROM `{$check['table']}` LIKE '{$check['column']}'");
        if ($exists && mysqli_num_rows($exists) === 0) {
            mysqli_query($conn, $check['sql']);
        }
    }

    $tables = ['vehicles', 'applications'];
    foreach ($tables as $tableName) {
        $columnCheck = mysqli_query($conn, "SHOW COLUMNS FROM `{$tableName}` LIKE 'official_vehicle_category'");
        if ($columnCheck && mysqli_num_rows($columnCheck) > 0) {
            mysqli_query($conn, "ALTER TABLE `{$tableName}` MODIFY official_vehicle_category VARCHAR(50) NOT NULL DEFAULT 'Private Vehicle'");
        }
    }
}

appEnsureVehicleClassificationColumns($conn);

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS applicants (
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
)");

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_type ENUM('users','applicants') NOT NULL,
    user_id INT NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    used_at DATETIME NULL,
    INDEX idx_reset_tokens_hash (token_hash),
    INDEX idx_reset_tokens_user (user_type, user_id),
    INDEX idx_reset_tokens_expires (expires_at)
)");

$applicationColumns = mysqli_query($conn, "SHOW COLUMNS FROM applications LIKE 'applicant_id'");
if ($applicationColumns && mysqli_num_rows($applicationColumns) === 0) {
    mysqli_query($conn, "ALTER TABLE applications ADD COLUMN applicant_id INT NULL AFTER user_id");
    mysqli_query($conn, "ALTER TABLE applications ADD INDEX idx_applicant_id (applicant_id)");
}

mysqli_query($conn, "UPDATE applications a
    JOIN applicants p ON TRIM(a.applicant_name) = TRIM(p.fullname)
    SET a.applicant_id = p.id
    WHERE a.applicant_id IS NULL OR a.applicant_id NOT IN (SELECT id FROM applicants)");

mysqli_query($conn, "UPDATE applications
    SET applicant_id = NULL
    WHERE applicant_id IS NOT NULL AND applicant_id NOT IN (SELECT id FROM applicants)");

$vehicleTypeColumn = mysqli_query($conn, "SHOW COLUMNS FROM vehicles LIKE 'vehicle_type'");
if ($vehicleTypeColumn && mysqli_num_rows($vehicleTypeColumn) === 0) {
    mysqli_query($conn, "ALTER TABLE vehicles ADD COLUMN vehicle_type VARCHAR(30) NULL AFTER plate_number");
}

$vehicleCreatedByApplicantColumn = mysqli_query($conn, "SHOW COLUMNS FROM vehicles LIKE 'created_by_applicant'");
if ($vehicleCreatedByApplicantColumn && mysqli_num_rows($vehicleCreatedByApplicantColumn) === 0) {
    mysqli_query($conn, "ALTER TABLE vehicles ADD COLUMN created_by_applicant INT NULL AFTER created_by");
}

$vehicleStatusColumn = mysqli_query($conn, "SHOW COLUMNS FROM vehicles LIKE 'status'");
if ($vehicleStatusColumn && mysqli_num_rows($vehicleStatusColumn) === 0) {
    mysqli_query($conn, "ALTER TABLE vehicles ADD COLUMN status ENUM('Pending','Active','Expired','Rejected') NOT NULL DEFAULT 'Pending' AFTER vehicle_type");
}

$vehicleExpiryDateColumn = mysqli_query($conn, "SHOW COLUMNS FROM vehicles LIKE 'expiry_date'");
if ($vehicleExpiryDateColumn && mysqli_num_rows($vehicleExpiryDateColumn) === 0) {
    mysqli_query($conn, "ALTER TABLE vehicles ADD COLUMN expiry_date DATE NULL AFTER status");
}

$vehiclePaymentStatusColumn = mysqli_query($conn, "SHOW COLUMNS FROM vehicles LIKE 'payment_status'");
if ($vehiclePaymentStatusColumn && mysqli_num_rows($vehiclePaymentStatusColumn) === 0) {
    mysqli_query($conn, "ALTER TABLE vehicles ADD COLUMN payment_status ENUM('Pending','Paid') NOT NULL DEFAULT 'Pending' AFTER expiry_date");
}

$vehicleCountyColumn = mysqli_query($conn, "SHOW COLUMNS FROM vehicles LIKE 'county'");
if ($vehicleCountyColumn && mysqli_num_rows($vehicleCountyColumn) === 0) {
    mysqli_query($conn, "ALTER TABLE vehicles ADD COLUMN county VARCHAR(100) NULL AFTER owner_contact");
}

$applicationsTypeColumn = mysqli_query($conn, "SHOW COLUMNS FROM applications LIKE 'application_type'");
if ($applicationsTypeColumn && mysqli_num_rows($applicationsTypeColumn) === 0) {
    mysqli_query($conn, "ALTER TABLE applications ADD COLUMN application_type ENUM('New','Renewal','Existing Plate') NOT NULL DEFAULT 'New' AFTER status");
}

$renewalPlateColumn = mysqli_query($conn, "SHOW COLUMNS FROM applications LIKE 'renewal_for_plate_number'");
if ($renewalPlateColumn && mysqli_num_rows($renewalPlateColumn) === 0) {
    mysqli_query($conn, "ALTER TABLE applications ADD COLUMN renewal_for_plate_number VARCHAR(20) NULL AFTER application_type");
}

$paymentVehicleColumn = mysqli_query($conn, "SHOW COLUMNS FROM payments LIKE 'vehicle_id'");
if ($paymentVehicleColumn && mysqli_num_rows($paymentVehicleColumn) === 0) {
    mysqli_query($conn, "ALTER TABLE payments ADD COLUMN vehicle_id INT NULL AFTER application_id");
    mysqli_query($conn, "ALTER TABLE payments ADD INDEX idx_payments_vehicle_id (vehicle_id)");
}

$paymentCurrencyColumn = mysqli_query($conn, "SHOW COLUMNS FROM payments LIKE 'payment_currency'");
if ($paymentCurrencyColumn && mysqli_num_rows($paymentCurrencyColumn) === 0) {
    mysqli_query($conn, "ALTER TABLE payments ADD COLUMN payment_currency ENUM('USD','LRD') NOT NULL DEFAULT 'USD' AFTER amount");
}

$existingApplicantVehicleFk = mysqli_query($conn, "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vehicles' AND COLUMN_NAME = 'created_by_applicant' LIMIT 1");
if ($existingApplicantVehicleFk && mysqli_num_rows($existingApplicantVehicleFk) === 0) {
    mysqli_query($conn, "ALTER TABLE vehicles ADD CONSTRAINT fk_vehicles_created_by_applicant FOREIGN KEY (created_by_applicant) REFERENCES applicants(id)");
}

$existingApplicantFk = mysqli_query($conn, "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'applications' AND COLUMN_NAME = 'applicant_id' LIMIT 1");
if ($existingApplicantFk && mysqli_num_rows($existingApplicantFk) === 0) {
    mysqli_query($conn, "ALTER TABLE applications ADD CONSTRAINT fk_applications_applicant FOREIGN KEY (applicant_id) REFERENCES applicants(id)");
}

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS admin_audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    actor_id INT DEFAULT NULL,
    actor_role VARCHAR(50) DEFAULT 'System',
    event_name VARCHAR(100) NOT NULL,
    event_message VARCHAR(255) NOT NULL,
    entity_type VARCHAR(50) DEFAULT NULL,
    entity_id INT DEFAULT NULL,
    metadata JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS plate_status_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT NOT NULL,
    previous_status VARCHAR(30) DEFAULT NULL,
    new_status VARCHAR(30) NOT NULL,
    reason TEXT DEFAULT NULL,
    changed_by INT DEFAULT NULL,
    changed_by_role VARCHAR(50) DEFAULT 'Admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_plate_status_history_vehicle (vehicle_id),
    INDEX idx_plate_status_history_created_at (created_at)
)");

$vehicleRejectionReasonColumn = mysqli_query($conn, "SHOW COLUMNS FROM vehicles LIKE 'rejection_reason'");
if ($vehicleRejectionReasonColumn && mysqli_num_rows($vehicleRejectionReasonColumn) === 0) {
    mysqli_query($conn, "ALTER TABLE vehicles ADD COLUMN rejection_reason TEXT NULL AFTER status");
}

function logAuditEvent($conn, $event_name, $event_message, $actor_id = null, $actor_role = 'System', $entity_type = null, $entity_id = null, $metadata = []) {
    if (!$conn) {
        return false;
    }

    $safe_event = mysqli_real_escape_string($conn, (string) $event_name);
    $safe_message = mysqli_real_escape_string($conn, (string) $event_message);
    $safe_actor_id = $actor_id !== null ? (int) $actor_id : 'NULL';
    $safe_actor_role = mysqli_real_escape_string($conn, (string) $actor_role);
    $safe_entity_type = $entity_type !== null ? mysqli_real_escape_string($conn, (string) $entity_type) : 'NULL';
    $safe_entity_id = $entity_id !== null ? (int) $entity_id : 'NULL';
    $json_metadata = json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $safe_metadata = $json_metadata !== false ? mysqli_real_escape_string($conn, $json_metadata) : 'null';

    $query = "INSERT INTO admin_audit_logs (actor_id, actor_role, event_name, event_message, entity_type, entity_id, metadata)
              VALUES ($safe_actor_id, '$safe_actor_role', '$safe_event', '$safe_message', '$safe_entity_type', $safe_entity_id, '$safe_metadata')";

    return mysqli_query($conn, $query);
}
?>
