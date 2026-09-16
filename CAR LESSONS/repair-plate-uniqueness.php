<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = '127.0.0.1';
$user = 'root';
$password = '';
$database = 'car_license_registration';
$port = 3306;

function normalizePlatePrefix($value) {
    $value = strtolower(trim((string) $value));
    $value = preg_replace('/[^a-z0-9]/', '', $value);

    $map = [
        'smallpassengercar' => 'A1',
        'a1' => 'A1',
        'mediumpassengercar' => 'A2',
        'a2' => 'A2',
        'largepassengercarminivan' => 'A3',
        'a3' => 'A3',
        'smallsuvlightpickup' => 'A4',
        'a4' => 'A4',
        'mediumsuvpickup' => 'A5',
        'a5' => 'A5',
        'largeheavysuvpickup' => 'A6',
        'a6' => 'A6',
        'sedan' => 'A2',
        'car' => 'A2',
        'vehicle' => 'A2',
        'suv' => 'A4',
        'jeep' => 'A4',
        'smallpassenger' => 'A1',
        'mediumpassenger' => 'A2',
        'largepassenger' => 'A3',
        'pickup' => 'A5',
        'truck' => 'A6',
        'trailer' => 'A6',
        'bus' => 'A6',
        'motorcycle' => 'A6',
        'bike' => 'A6',
        'commercial' => 'A6',
    ];

    if (isset($map[$value])) {
        return $map[$value];
    }

    if (preg_match('/^a([1-6])$/i', $value)) {
        return 'A' . preg_replace('/[^1-6]/', '', $value);
    }

    if (preg_match('/^([1-6])$/', $value)) {
        return 'A' . $value;
    }

    if (strpos($value, 'small') !== false && (strpos($value, 'car') !== false || strpos($value, 'passenger') !== false)) {
        return 'A1';
    }

    if (strpos($value, 'sedan') !== false || strpos($value, 'corolla') !== false || strpos($value, 'civic') !== false || strpos($value, 'camry') !== false || strpos($value, 'sunny') !== false) {
        return 'A2';
    }

    if (strpos($value, 'minivan') !== false || strpos($value, 'wagon') !== false || strpos($value, 'mercedes') !== false || strpos($value, 'bmw') !== false || strpos($value, 'sienna') !== false) {
        return 'A3';
    }

    if (strpos($value, 'suv') !== false || strpos($value, 'jeep') !== false || strpos($value, 'pickup') !== false) {
        if (strpos($value, 'large') !== false || strpos($value, 'heavy') !== false || strpos($value, 'suburban') !== false || strpos($value, 'patrol') !== false || strpos($value, 'landcruiser') !== false) {
            return 'A6';
        }
        if (strpos($value, 'medium') !== false || strpos($value, 'pathfinder') !== false || strpos($value, '4runner') !== false || strpos($value, 'hilux') !== false || strpos($value, 'ranger') !== false) {
            return 'A5';
        }
        return 'A4';
    }

    if (strpos($value, 'truck') !== false || strpos($value, 'trailer') !== false || strpos($value, 'heavyduty') !== false) {
        return 'A6';
    }

    return 'A2';
}

function generateUniquePlate($prefix, $used) {
    $prefix = strtoupper($prefix);
    for ($n = 1; $n <= 9999; $n++) {
        $digits = str_pad((string) $n, 4, '0', STR_PAD_LEFT);
        $candidate = $prefix . $digits;
        if (!isset($used[$candidate])) {
            $used[$candidate] = true;
            return $candidate;
        }
    }

    return null;
}

$conn = new mysqli($host, $user, $password, $database, $port);
if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

function indexExists($conn, $table, $indexName) {
    $result = $conn->query("SHOW INDEX FROM `" . $table . "` WHERE Key_name = '" . $conn->real_escape_string($indexName) . "'");
    return $result && $result->num_rows > 0;
}

$results = [
    'applications_updated' => 0,
    'vehicles_updated' => 0,
    'applications_skipped' => 0,
    'vehicles_skipped' => 0,
    'duplicate_count' => 0,
];

$usedPlates = [];
$applicationsResult = $conn->query("SELECT id, application_number, vehicle_id, license_plate_number FROM applications WHERE license_plate_number IS NOT NULL AND TRIM(license_plate_number) <> '' ORDER BY id");
while ($row = $applicationsResult->fetch_assoc()) {
    $value = trim($row['license_plate_number']);
    $usedPlates[$value] = true;
}

$vehiclesResult = $conn->query("SELECT id, vehicle_type, plate_number FROM vehicles WHERE plate_number IS NOT NULL AND TRIM(plate_number) <> '' ORDER BY id");
while ($row = $vehiclesResult->fetch_assoc()) {
    $value = trim($row['plate_number']);
    $usedPlates[$value] = true;
}

$applicationsResult = $conn->query("SELECT id, application_number, vehicle_id, license_plate_number FROM applications WHERE license_plate_number IS NOT NULL AND TRIM(license_plate_number) <> '' ORDER BY id");
while ($row = $applicationsResult->fetch_assoc()) {
    $plate = trim($row['license_plate_number']);
    $plateCount = $conn->query("SELECT COUNT(*) AS total FROM applications WHERE license_plate_number = '" . $conn->real_escape_string($plate) . "'")
        ->fetch_assoc()['total'];

    $vehiclePlateCount = $conn->query("SELECT COUNT(*) AS total FROM vehicles WHERE plate_number = '" . $conn->real_escape_string($plate) . "'")
        ->fetch_assoc()['total'];

    if ($plateCount === 1 && $vehiclePlateCount === 0) {
        $results['applications_skipped']++;
        continue;
    }

    $results['duplicate_count']++;
    $prefix = 'A2';
    if (!empty($row['vehicle_id'])) {
        $vehicleRow = $conn->query("SELECT vehicle_type FROM vehicles WHERE id = '" . (int)$row['vehicle_id'] . "' LIMIT 1")->fetch_assoc();
        if ($vehicleRow && !empty($vehicleRow['vehicle_type'])) {
            $prefix = normalizePlatePrefix($vehicleRow['vehicle_type']);
        }
    }

    $newPlate = generateUniquePlate($prefix, $usedPlates);
    if ($newPlate === null) {
        $newPlate = generateUniquePlate('A2', $usedPlates);
    }

    if ($newPlate !== null) {
        $conn->query("UPDATE applications SET license_plate_number = '" . $conn->real_escape_string($newPlate) . "' WHERE id = '" . (int)$row['id'] . "'");
        $usedPlates[$newPlate] = true;
        $results['applications_updated']++;
    }
}

$vehiclesResult = $conn->query("SELECT id, vehicle_type, plate_number FROM vehicles WHERE plate_number IS NOT NULL AND TRIM(plate_number) <> '' ORDER BY id");
while ($row = $vehiclesResult->fetch_assoc()) {
    $plate = trim($row['plate_number']);
    $appCount = $conn->query("SELECT COUNT(*) AS total FROM applications WHERE license_plate_number = '" . $conn->real_escape_string($plate) . "'")
        ->fetch_assoc()['total'];

    $vehicleCount = $conn->query("SELECT COUNT(*) AS total FROM vehicles WHERE plate_number = '" . $conn->real_escape_string($plate) . "'")
        ->fetch_assoc()['total'];

    if ($vehicleCount === 1 && $appCount === 0) {
        $results['vehicles_skipped']++;
        continue;
    }

    $prefix = normalizePlatePrefix($row['vehicle_type'] ?? 'A2');
    $newPlate = generateUniquePlate($prefix, $usedPlates);
    if ($newPlate === null) {
        $newPlate = generateUniquePlate('A2', $usedPlates);
    }

    if ($newPlate !== null) {
        $conn->query("UPDATE vehicles SET plate_number = '" . $conn->real_escape_string($newPlate) . "' WHERE id = '" . (int)$row['id'] . "'");
        $usedPlates[$newPlate] = true;
        $results['vehicles_updated']++;
    }
}

if (indexExists($conn, 'applications', 'license_plate_unique')) {
    $conn->query("ALTER TABLE applications DROP INDEX license_plate_unique");
}
if (!indexExists($conn, 'applications', 'license_plate_unique')) {
    $conn->query("ALTER TABLE applications ADD UNIQUE KEY license_plate_unique (license_plate_number)");
}

if (indexExists($conn, 'vehicles', 'plate_number')) {
    $conn->query("ALTER TABLE vehicles DROP INDEX plate_number");
}
if (indexExists($conn, 'vehicles', 'plate_number_unique')) {
    $conn->query("ALTER TABLE vehicles DROP INDEX plate_number_unique");
}
if (!indexExists($conn, 'vehicles', 'plate_number_unique')) {
    $conn->query("ALTER TABLE vehicles ADD UNIQUE KEY plate_number_unique (plate_number)");
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plate Uniqueness Repair</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 40px; background: #f8fafc; color: #0f172a; }
        .box { max-width: 700px; margin: 0 auto; background: white; border-radius: 14px; padding: 24px; box-shadow: 0 10px 30px rgba(15,23,42,0.08); }
        h1 { color: #0a2463; margin-bottom: 16px; }
        ul { line-height: 2; }
        .ok { color: #166534; font-weight: 700; }
    </style>
</head>
<body>
    <div class="box">
        <h1>Plate Uniqueness Repair Complete</h1>
        <ul>
            <li><span class="ok">Applications updated:</span> <?php echo $results['applications_updated']; ?></li>
            <li><span class="ok">Vehicles updated:</span> <?php echo $results['vehicles_updated']; ?></li>
            <li><span class="ok">Duplicate plate entries found:</span> <?php echo $results['duplicate_count']; ?></li>
            <li><span class="ok">Applications already valid:</span> <?php echo $results['applications_skipped']; ?></li>
            <li><span class="ok">Vehicles already valid:</span> <?php echo $results['vehicles_skipped']; ?></li>
        </ul>
        <p><strong>Note:</strong> This maintenance script ensures every stored plate number is unique across the system and reassigns duplicates with a valid A1–A6 prefix format.</p>
    </div>
</body>
</html>
