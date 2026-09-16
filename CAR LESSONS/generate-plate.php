<?php
session_start();
include "staff_auth.php";
include "config.php";
include "partials/admin-toast.php";
include "partials/admin-notifications.php";

$unread_admin_notifications = admin_get_unread_notification_count($conn);

$message = '';
$message_type = '';
$generated_plate = '';
$qr_data = '';
$has_paid_payment = false;
$payment_status_label = 'Pending Payment';
$application = null;
$show_payment_form = false;

function getVehicleClassificationInfo($conn, $application = []) {
    $default = ['code' => 'A2', 'label' => 'Medium Passenger Car'];
    $vehicle_id = $application['vehicle_id'] ?? null;
    $vehicleTypeMap = [
        'smallpassengercar' => ['code' => 'A1', 'label' => 'Small Passenger Car'],
        'a1' => ['code' => 'A1', 'label' => 'Small Passenger Car'],
        'mediumpassengercar' => ['code' => 'A2', 'label' => 'Medium Passenger Car'],
        'a2' => ['code' => 'A2', 'label' => 'Medium Passenger Car'],
        'largepassengercarminivan' => ['code' => 'A3', 'label' => 'Large Passenger Car / Minivan'],
        'a3' => ['code' => 'A3', 'label' => 'Large Passenger Car / Minivan'],
        'smallsuvlightpickup' => ['code' => 'A4', 'label' => 'Small SUV / Light Pickup'],
        'a4' => ['code' => 'A4', 'label' => 'Small SUV / Light Pickup'],
        'mediumsuvpickup' => ['code' => 'A5', 'label' => 'Medium SUV / Pickup'],
        'a5' => ['code' => 'A5', 'label' => 'Medium SUV / Pickup'],
        'largeheavysuvpickup' => ['code' => 'A6', 'label' => 'Large / Heavy SUV / Pickup'],
        'a6' => ['code' => 'A6', 'label' => 'Large / Heavy SUV / Pickup'],
        'sedan' => ['code' => 'A2', 'label' => 'Medium Passenger Car'],
        'car' => ['code' => 'A2', 'label' => 'Medium Passenger Car'],
        'vehicle' => ['code' => 'A2', 'label' => 'Medium Passenger Car'],
        'suv' => ['code' => 'A4', 'label' => 'Small SUV / Light Pickup'],
        'jeep' => ['code' => 'A4', 'label' => 'Small SUV / Light Pickup'],
        'small_passenger_car' => ['code' => 'A1', 'label' => 'Small Passenger Car'],
        'medium_passenger_car' => ['code' => 'A2', 'label' => 'Medium Passenger Car'],
        'large_passenger_car_minivan' => ['code' => 'A3', 'label' => 'Large Passenger Car / Minivan'],
        'small_suv_light_pickup' => ['code' => 'A4', 'label' => 'Small SUV / Light Pickup'],
        'large_heavy_suv_pickup' => ['code' => 'A6', 'label' => 'Large / Heavy SUV / Pickup'],
        'mini_bus' => ['code' => 'A4', 'label' => 'Small SUV / Light Pickup'],
        'minibus' => ['code' => 'A4', 'label' => 'Small SUV / Light Pickup'],
        'taxi' => ['code' => 'A4', 'label' => 'Small SUV / Light Pickup'],
        'pickup' => ['code' => 'A5', 'label' => 'Medium SUV / Pickup'],
        'pickuptruck' => ['code' => 'A5', 'label' => 'Medium SUV / Pickup'],
        'truck' => ['code' => 'A6', 'label' => 'Large / Heavy SUV / Pickup'],
        'trailer' => ['code' => 'A6', 'label' => 'Large / Heavy SUV / Pickup'],
        'motorcycle' => ['code' => 'A6', 'label' => 'Large / Heavy SUV / Pickup'],
        'bike' => ['code' => 'A6', 'label' => 'Large / Heavy SUV / Pickup'],
        'bus' => ['code' => 'A6', 'label' => 'Large / Heavy SUV / Pickup'],
        'commercial' => ['code' => 'A6', 'label' => 'Large / Heavy SUV / Pickup'],
    ];

    $normalizeCandidate = function ($value) {
        $candidate = strtolower(trim((string) $value));
        $candidate = preg_replace('/[^a-z0-9]/', '', $candidate);
        return $candidate;
    };

    $parseClassification = function ($candidate) use ($vehicleTypeMap) {
        if ($candidate === '') {
            return null;
        }

        if (isset($vehicleTypeMap[$candidate])) {
            return $vehicleTypeMap[$candidate];
        }

        if (preg_match('/^a([1-6])$/i', $candidate)) {
            $level = preg_replace('/[^1-6]/', '', $candidate);
            $mapping = [
                '1' => ['code' => 'A1', 'label' => 'Small Passenger Car'],
                '2' => ['code' => 'A2', 'label' => 'Medium Passenger Car'],
                '3' => ['code' => 'A3', 'label' => 'Large Passenger Car / Minivan'],
                '4' => ['code' => 'A4', 'label' => 'Small SUV / Light Pickup'],
                '5' => ['code' => 'A5', 'label' => 'Medium SUV / Pickup'],
                '6' => ['code' => 'A6', 'label' => 'Large / Heavy SUV / Pickup'],
            ];
            return $mapping[$level] ?? ['code' => 'A2', 'label' => 'Medium Passenger Car'];
        }

        if (preg_match('/^([1-6])$/', $candidate)) {
            $level = $candidate;
            $mapping = [
                '1' => ['code' => 'A1', 'label' => 'Small Passenger Car'],
                '2' => ['code' => 'A2', 'label' => 'Medium Passenger Car'],
                '3' => ['code' => 'A3', 'label' => 'Large Passenger Car / Minivan'],
                '4' => ['code' => 'A4', 'label' => 'Small SUV / Light Pickup'],
                '5' => ['code' => 'A5', 'label' => 'Medium SUV / Pickup'],
                '6' => ['code' => 'A6', 'label' => 'Large / Heavy SUV / Pickup'],
            ];
            return $mapping[$level] ?? ['code' => 'A2', 'label' => 'Medium Passenger Car'];
        }

        if (strpos($candidate, 'smallpassengercar') !== false || strpos($candidate, 'compact') !== false || strpos($candidate, 'micra') !== false || strpos($candidate, 'starlet') !== false || strpos($candidate, 'alto') !== false || strpos($candidate, 'picanto') !== false) {
            return ['code' => 'A1', 'label' => 'Small Passenger Car'];
        }

        if (strpos($candidate, 'sedan') !== false || strpos($candidate, 'corolla') !== false || strpos($candidate, 'civic') !== false || strpos($candidate, 'camry') !== false || strpos($candidate, 'sunny') !== false) {
            return ['code' => 'A2', 'label' => 'Medium Passenger Car'];
        }

        if (strpos($candidate, 'minivan') !== false || strpos($candidate, 'wagon') !== false || strpos($candidate, 'avalan') !== false || strpos($candidate, 'mercedes') !== false || strpos($candidate, 'bmw') !== false || strpos($candidate, 'sienna') !== false || strpos($candidate, 'quest') !== false) {
            return ['code' => 'A3', 'label' => 'Large Passenger Car / Minivan'];
        }

        if (strpos($candidate, 'suv') !== false || strpos($candidate, 'jeep') !== false || strpos($candidate, 'rav4') !== false || strpos($candidate, 'crv') !== false || strpos($candidate, 'cx5') !== false || strpos($candidate, 'pickup') !== false) {
            if (strpos($candidate, 'large') !== false || strpos($candidate, 'heavy') !== false || strpos($candidate, 'suburban') !== false || strpos($candidate, 'f150') !== false || strpos($candidate, 'fseries') !== false || strpos($candidate, 'patrol') !== false || strpos($candidate, 'landcruiser') !== false) {
                return ['code' => 'A6', 'label' => 'Large / Heavy SUV / Pickup'];
            }
            if (strpos($candidate, 'medium') !== false || strpos($candidate, 'pathfinder') !== false || strpos($candidate, '4runner') !== false || strpos($candidate, 'hilux') !== false || strpos($candidate, 'ranger') !== false) {
                return ['code' => 'A5', 'label' => 'Medium SUV / Pickup'];
            }
            return ['code' => 'A4', 'label' => 'Small SUV / Light Pickup'];
        }

        if (strpos($candidate, 'truck') !== false || strpos($candidate, 'trailer') !== false || strpos($candidate, 'heavy') !== false || strpos($candidate, 'suburban') !== false || strpos($candidate, 'f150') !== false || strpos($candidate, 'fseries') !== false || strpos($candidate, 'patrol') !== false || strpos($candidate, 'landcruiser') !== false) {
            return ['code' => 'A6', 'label' => 'Large / Heavy SUV / Pickup'];
        }

        return null;
    };

    if (!empty($vehicle_id)) {
        $vehicle_query = mysqli_query($conn, "SELECT * FROM vehicles WHERE id = '" . (int)$vehicle_id . "' LIMIT 1");
        if ($vehicle_query && mysqli_num_rows($vehicle_query) > 0) {
            $vehicle = mysqli_fetch_assoc($vehicle_query);

            foreach (['vehicle_type', 'classification', 'vehicle_classification', 'classification_code', 'class_code', 'plate_class'] as $field) {
                if (!empty($vehicle[$field])) {
                    $candidate = $normalizeCandidate($vehicle[$field]);
                    $parsed = $parseClassification($candidate);
                    if ($parsed !== null) {
                        return $parsed;
                    }
                }
            }
        }
    }

    if (!empty($application['vehicle_type'])) {
        $candidate = $normalizeCandidate($application['vehicle_type']);
        $parsed = $parseClassification($candidate);
        if ($parsed !== null) {
            return $parsed;
        }
    }

    if (!empty($application['classification'])) {
        $candidate = $normalizeCandidate($application['classification']);
        $parsed = $parseClassification($candidate);
        if ($parsed !== null) {
            return $parsed;
        }
    }

    return $default;
}

function getVehicleClassificationPrefix($conn, $application = []) {
    $info = getVehicleClassificationInfo($conn, $application);
    return strtoupper($info['code'] ?? 'A2');
}

function normalizePlateNumber($plate) {
    $normalized = strtoupper(trim((string) $plate));
    $normalized = preg_replace('/\s+/', '', $normalized);
    $normalized = preg_replace('/[^A-Z0-9]/', '', $normalized);

    return $normalized;
}

function plateExistsInSystem($conn, $plate) {
    $normalizedPlate = normalizePlateNumber($plate);
    $safePlate = mysqli_real_escape_string($conn, $normalizedPlate);

    $appCheck = mysqli_query($conn, "SELECT 1 FROM applications WHERE UPPER(TRIM(license_plate_number)) = '$safePlate' LIMIT 1");
    if ($appCheck && mysqli_num_rows($appCheck) > 0) {
        return true;
    }

    $vehicleCheck = mysqli_query($conn, "SELECT 1 FROM vehicles WHERE UPPER(TRIM(plate_number)) = '$safePlate' LIMIT 1");
    if ($vehicleCheck && mysqli_num_rows($vehicleCheck) > 0) {
        return true;
    }

    return false;
}

function generateUniqueLicensePlateNumber($conn, $application = []) {
    $officialCategory = $application['official_vehicle_category'] ?? ($application['vehicle_category'] ?? '');
    $vehicleUseClass = $application['vehicle_use_class'] ?? ($application['use_class'] ?? '');
    if (appIsMotorcycleCategory($officialCategory, $vehicleUseClass)) {
        return generateMotorcyclePlateNumber($conn);
    }

    $classificationCode = getVehicleClassificationPrefix($conn, $application);
    $platePrefix = normalizePlateNumber($classificationCode);
    $attempts = 0;
    $maxAttempts = 20000;

    while ($attempts < $maxAttempts) {
        $digits = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $plate = normalizePlateNumber($platePrefix . $digits);

        if (!plateExistsInSystem($conn, $plate)) {
            return $plate;
        }

        $attempts++;
    }

    return null;
}

if (!function_exists('generateMotorcyclePlateNumber')) {
    function generateMotorcyclePlateNumber($conn) {
        if (function_exists('appGenerateMotorcyclePlateNumber')) {
            return appGenerateMotorcyclePlateNumber($conn);
        }

        $attempts = 0;
        $maxAttempts = 50000;

        while ($attempts < $maxAttempts) {
            $digits = str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);
            if (!preg_match('/^\d{5}$/', $digits)) {
                $attempts++;
                continue;
            }

            if (!plateExistsInSystem($conn, $digits)) {
                return $digits;
            }

            $attempts++;
        }

        return null;
    }
}

if (!function_exists('appVehiclePlateColorStyle')) {
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
}

function buildLicenseQrPayload($application) {
    $baseUrl = appBaseUrl();
    $searchValue = $application['license_plate_number'] ?: $application['application_number'];

    $params = [
        'search' => $searchValue,
        'application_number' => $application['application_number'] ?? '',
        'license_plate_number' => $application['license_plate_number'] ?? '',
        'applicant_name' => $application['applicant_name'] ?? '',
        'applicant_contact' => $application['applicant_contact'] ?? '',
        'applicant_address' => $application['applicant_address'] ?? '',
        'county' => $application['county'] ?? '',
        'status' => $application['status'] ?? '',
        'vehicle_id' => $application['vehicle_id'] ?? '',
    ];

    return $baseUrl . '/verify.php?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}

function buildCountyPlateCode($county) {
    $normalized = strtoupper(trim((string) $county));
    if ($normalized === '') {
        return 'LIB';
    }

    $parts = preg_split('/\s+/', preg_replace('/[^A-Z\s]/', '', $normalized));
    $parts = array_values(array_filter($parts, function ($part) {
        return $part !== '' && $part !== 'COUNTY';
    }));

    if (count($parts) >= 2) {
        return substr($parts[0], 0, 2) . substr($parts[1], 0, 2);
    }

    return substr($parts[0], 0, 4);
}

function syncLinkedVehicleFromApplication($conn, $application) {
    if (empty($application['vehicle_id'])) {
        return false;
    }

    $vehicleId = (int) $application['vehicle_id'];
    $status = strtoupper(trim((string) ($application['status'] ?? 'Pending')));
    $normalizedStatus = 'Pending';

    if ($status === 'APPROVED') {
        $normalizedStatus = 'Active';
    } elseif ($status === 'REJECTED') {
        $normalizedStatus = 'Rejected';
    } elseif ($status === 'EXPIRED') {
        $normalizedStatus = 'Expired';
    } elseif ($status === 'PENDING') {
        $normalizedStatus = 'Pending';
    }

    $plateValue = trim((string) ($application['license_plate_number'] ?? ''));
    $expiryValue = trim((string) ($application['expires_at'] ?? ''));

    $plateSql = $plateValue !== '' ? "plate_number = '" . mysqli_real_escape_string($conn, $plateValue) . "', " : '';
    $expirySql = $expiryValue !== '' ? "expiry_date = '" . mysqli_real_escape_string($conn, $expiryValue) . "', " : "expiry_date = expiry_date, ";
    $paymentSql = $normalizedStatus === 'Active' ? "payment_status = 'Paid', " : "payment_status = payment_status, ";

    $sql = "UPDATE vehicles
        SET {$plateSql}{$expirySql}{$paymentSql}status = '{$normalizedStatus}'
        WHERE id = '{$vehicleId}'";

    return mysqli_query($conn, $sql);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $application_id = mysqli_real_escape_string($conn, $_POST['application_id'] ?? '');
    $action = $_POST['action'] ?? 'lookup';
    $rejection_reason = isset($_POST['rejection_reason']) ? trim((string) $_POST['rejection_reason']) : '';
    $application_query = mysqli_query($conn, "SELECT * FROM applications WHERE id = '$application_id' OR application_number = '$application_id' LIMIT 1");

    if (mysqli_num_rows($application_query) > 0) {
        $application = mysqli_fetch_assoc($application_query);
        $payment_check = mysqli_query($conn, "SELECT COUNT(*) as paid_count FROM payments WHERE (application_id = '{$application['id']}' OR vehicle_id = '{$application['vehicle_id']}') AND status = 'Paid'");
        $payment_summary = mysqli_fetch_assoc($payment_check);
        $has_paid_payment = (int)$payment_summary['paid_count'] > 0;
        $payment_status_label = $has_paid_payment ? 'Paid' : 'Pending Payment';

        if ($action == 'record_payment') {
            $amount = mysqli_real_escape_string($conn, $_POST['amount'] ?? '0');
            $payment_currency = mysqli_real_escape_string($conn, appNormalizePaymentCurrency($_POST['payment_currency'] ?? 'USD'));
            $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method'] ?? 'Cash');
            $receipt_number = 'RCP-' . date('Ymd') . '-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
            $insert_query = mysqli_query($conn, "INSERT INTO payments (application_id, vehicle_id, amount, payment_currency, payment_method, receipt_number, status, received_by) VALUES ('{$application['id']}', '{$application['vehicle_id']}', '$amount', '$payment_currency', '$payment_method', '$receipt_number', 'Paid', '{$_SESSION['id']}')");

            if ($insert_query) {
                mysqli_query($conn, "UPDATE applications SET status = 'Pending', processed_by = '{$_SESSION['id']}', processed_at = NOW() WHERE id = '{$application['id']}'");
                if (!empty($application['vehicle_id'])) {
                    mysqli_query($conn, "UPDATE vehicles SET payment_status = 'Paid' WHERE id = '{$application['vehicle_id']}'");
                }
                $message = "Payment recorded successfully. The admin may still approve or reject the request manually.";
                $message_type = 'success';
                $has_paid_payment = true;
                $payment_status_label = 'Paid';
            } else {
                $message = "Error: " . mysqli_error($conn);
                $message_type = 'danger';
            }
        } elseif ($action == 'approve_plate') {
            $linked_vehicle_plate = '';
            if (!empty($application['vehicle_id'])) {
                $vehicle_lookup = mysqli_query($conn, "SELECT plate_number FROM vehicles WHERE id = '" . (int) $application['vehicle_id'] . "' LIMIT 1");
                if ($vehicle_lookup && mysqli_num_rows($vehicle_lookup) > 0) {
                    $vehicle_record = mysqli_fetch_assoc($vehicle_lookup);
                    $linked_vehicle_plate = normalizePlateNumber($vehicle_record['plate_number'] ?? '');
                }
            }

            $existing_plate_value = normalizePlateNumber($linked_vehicle_plate ?? '');
            if (($application['application_type'] ?? 'New') === 'Existing Plate' && $existing_plate_value === '') {
                $existing_plate_value = normalizePlateNumber((string) ($application['license_plate_number'] ?? ''));
            }

            if (($application['application_type'] ?? 'New') === 'Existing Plate') {
                if ($existing_plate_value !== '') {
                    $generated_plate = $existing_plate_value;
                    $update_query = mysqli_query($conn, "UPDATE applications SET license_plate_number = '$generated_plate', status = 'Approved', processed_by = '{$_SESSION['id']}', processed_at = NOW(), issued_at = NOW(), expires_at = DATE_ADD(NOW(), INTERVAL 3 YEAR) WHERE id = '{$application['id']}'");

                    if ($update_query) {
                        $vehicle_update_query = mysqli_query($conn, "UPDATE vehicles SET plate_number = '$generated_plate', status = 'Active', payment_status = 'Paid', expiry_date = DATE_ADD(NOW(), INTERVAL 3 YEAR) WHERE id = '{$application['vehicle_id']}'");
                        if (!$vehicle_update_query) {
                            $message = "Existing plate approved, but the vehicle record could not be synced: " . mysqli_error($conn);
                            $message_type = 'warning';
                        } else {
                            syncLinkedVehicleFromApplication($conn, array_merge($application, ['status' => 'Approved', 'license_plate_number' => $generated_plate, 'expires_at' => date('Y-m-d H:i:s', strtotime('+3 years'))]));
                            mysqli_query($conn, "INSERT INTO plate_status_history (vehicle_id, previous_status, new_status, reason, changed_by, changed_by_role)
                                VALUES ('{$application['vehicle_id']}', 'Pending', 'Active', 'Admin approved the existing plate without generating a new number', '{$_SESSION['id']}', '{$_SESSION['role']}')");

                            logAuditEvent(
                                $conn,
                                'existing_plate_approved',
                                'Existing plate approved and retained in the system.',
                                $_SESSION['id'] ?? null,
                                $_SESSION['role'] ?? 'Admin',
                                'application',
                                $application['id'],
                                ['application_number' => $application['application_number'], 'plate_number' => $generated_plate]
                            );
                            $application['license_plate_number'] = $generated_plate;
                            $application['issued_at'] = date('Y-m-d H:i:s');
                            $application['expires_at'] = date('Y-m-d H:i:s', strtotime('+3 years'));
                            $qr_data = buildLicenseQrPayload($application);
                            $message = "Existing plate approved successfully for application <strong>{$application['application_number']}</strong>. Plate retained: <strong>$generated_plate</strong>.";
                            $message_type = 'success';
                        }
                    } else {
                        $message = "Error: " . mysqli_error($conn);
                        $message_type = 'danger';
                    }
                } else {
                    $message = "This existing plate application does not contain a valid plate number, so no new random plate will be generated.";
                    $message_type = 'warning';
                }
            } elseif (!empty($application['license_plate_number'])) {
                $generated_plate = normalizePlateNumber($application['license_plate_number']);
                $application['license_plate_number'] = $generated_plate;
                $qr_data = buildLicenseQrPayload($application);
                $message = "This application already has a license plate number: <strong>$generated_plate</strong>";
                $message_type = 'warning';
            } else {
                $generated_plate = generateUniqueLicensePlateNumber($conn, $application);
                $generated_plate = normalizePlateNumber($generated_plate ?? '');

                if ($generated_plate === '' || $generated_plate === null) {
                    $message = "Unable to generate a unique plate number after multiple attempts. Please try again.";
                    $message_type = 'danger';
                } else {
                    $update_query = mysqli_query($conn, "UPDATE applications SET license_plate_number = '$generated_plate', status = 'Approved', processed_by = '{$_SESSION['id']}', processed_at = NOW(), issued_at = NOW(), expires_at = DATE_ADD(NOW(), INTERVAL 3 YEAR) WHERE id = '{$application['id']}'");

                    if ($update_query) {
                        $vehicle_update_query = mysqli_query($conn, "UPDATE vehicles SET plate_number = '$generated_plate', status = 'Active', payment_status = 'Paid', expiry_date = DATE_ADD(NOW(), INTERVAL 3 YEAR) WHERE id = '{$application['vehicle_id']}'");
                        if (!$vehicle_update_query) {
                            $message = "Plate approved, but the vehicle status could not be synced: " . mysqli_error($conn);
                            $message_type = 'warning';
                        } else {
                            syncLinkedVehicleFromApplication($conn, array_merge($application, ['status' => 'Approved', 'license_plate_number' => $generated_plate, 'expires_at' => date('Y-m-d H:i:s', strtotime('+3 years'))]));
                            mysqli_query($conn, "INSERT INTO plate_status_history (vehicle_id, previous_status, new_status, reason, changed_by, changed_by_role)
                                VALUES ('{$application['vehicle_id']}', 'Pending', 'Active', 'Admin approved and generated plate', '{$_SESSION['id']}', '{$_SESSION['role']}')");

                            logAuditEvent(
                                $conn,
                                'plate_generated',
                                'Plate generated and approved for application.',
                                $_SESSION['id'] ?? null,
                                $_SESSION['role'] ?? 'Admin',
                                'application',
                                $application['id'],
                                ['application_number' => $application['application_number'], 'plate_number' => $generated_plate]
                            );
                            $application['license_plate_number'] = $generated_plate;
                            $application['issued_at'] = date('Y-m-d H:i:s');
                            $application['expires_at'] = date('Y-m-d H:i:s', strtotime('+3 years'));
                            $qr_data = buildLicenseQrPayload($application);
                            $message = "Plate approved and generated successfully for application <strong>{$application['application_number']}</strong>.";
                            $message_type = 'success';
                        }
                    } else {
                        $message = "Error: " . mysqli_error($conn);
                        $message_type = 'danger';
                    }
                }
            }
        } elseif ($action == 'reject_plate') {
            $reason_value = $rejection_reason !== '' ? $rejection_reason : 'Rejected by administrator';
            $reason_sql = mysqli_real_escape_string($conn, $reason_value);
            $update_query = mysqli_query($conn, "UPDATE applications SET license_plate_number = NULL, status = 'Rejected', processed_by = '{$_SESSION['id']}', processed_at = NOW(), issued_at = NULL, expires_at = NULL WHERE id = '{$application['id']}'");
            if ($update_query) {
                $vehicle_update_query = mysqli_query($conn, "UPDATE vehicles SET status = 'Rejected', rejection_reason = '$reason_sql', expiry_date = NULL WHERE id = '{$application['vehicle_id']}'");
                if (!$vehicle_update_query) {
                    $message = "Plate request rejected, but the vehicle status could not be synced: " . mysqli_error($conn);
                    $message_type = 'warning';
                } else {
                    mysqli_query($conn, "INSERT INTO plate_status_history (vehicle_id, previous_status, new_status, reason, changed_by, changed_by_role)
                        VALUES ('{$application['vehicle_id']}', 'Pending', 'Rejected', '$reason_sql', '{$_SESSION['id']}', '{$_SESSION['role']}')");

                    logAuditEvent(
                        $conn,
                        'plate_rejected',
                        'Plate application rejected by admin.',
                        $_SESSION['id'] ?? null,
                        $_SESSION['role'] ?? 'Admin',
                        'application',
                        $application['id'],
                        ['application_number' => $application['application_number'], 'reason' => $reason_value]
                    );
                    $message = "Plate request rejected for application <strong>{$application['application_number']}</strong>.";
                    $message_type = 'warning';
                }
            } else {
                $message = "Error: " . mysqli_error($conn);
                $message_type = 'danger';
            }
        } else {
            $show_payment_form = false;
        }
    } else {
        $message = "No application found with that ID.";
        $message_type = 'danger';
    }
}

$page_toasts = admin_toast_pull();
if ($message !== '') {
    $page_toasts[] = [
        'message' => trim(strip_tags((string) $message)),
        'type' => admin_toast_normalize_type($message_type)
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Plate - Transport Union Authority</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin-toast.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        /* Sidebar */
        .sidebar {
            width: 260px;
            background: linear-gradient(180deg, #0a2463 0%, #0f3460 100%);
            color: white;
            padding: 25px 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 0 25px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.15);
            margin-bottom: 20px;
        }

        .sidebar-header img {
            height: 56px;
            width: auto;
            margin-bottom: 12px;
            object-fit: contain;
        }

        .sidebar-header h2 {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .sidebar-header p {
            font-size: 11px;
            opacity: 0.8;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0 15px;
        }

        .sidebar-menu li {
            margin-bottom: 5px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 13px 18px;
            color: rgba(255,255,255,0.9);
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: rgba(255,255,255,0.15);
        }

        .sidebar-menu i {
            margin-right: 14px;
            width: 22px;
            text-align: center;
            font-size: 16px;
        }

        .main-content {
            margin-left: 260px;
            min-height: 100vh;
            background: #f1f5f9;
        }

        .top-header {
            background: white;
            padding: 18px 40px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-title h1 {
            color: #0a2463;
            font-size: 24px;
        }

        .user-info a {
            background: #f1f5f9;
            color: #c41e3a;
            padding: 10px 18px;
            border-radius: 8px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
        }

        .page-content {
            padding: 35px 40px;
        }

        .content-card {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            max-width: 980px;
            margin: 0 auto;
        }

        .card-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .card-header h2 {
            color: #0a2463;
            font-size: 26px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .plate-card {
            border: 1px solid #dbeafe;
            border-radius: 24px;
            padding: 24px;
            background: linear-gradient(135deg, #f8fbff 0%, #ffffff 100%);
            margin-bottom: 20px;
            text-align: center;
            box-shadow: 0 20px 50px rgba(15, 23, 42, 0.08);
            max-width: 900px;
            margin-left: auto;
            margin-right: auto;
        }

        .plate-card-shell {
            margin-top: 18px;
            display: grid;
            gap: 18px;
        }

        .plate-identity {
            position: relative;
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: 18px;
            align-items: stretch;
        }

        .plate-visual {
            position: relative;
            display: grid;
            grid-template-columns: 92px 1fr;
            min-height: 290px;
            border-radius: 22px;
            overflow: hidden;
            background:
                linear-gradient(180deg, rgba(255,255,255,0.16), transparent 18%),
                linear-gradient(180deg, #ef8d2f 0%, #ea7f1c 100%);
            border: 5px solid #d96f12;
            box-shadow: 0 24px 45px rgba(15, 23, 42, 0.16);
        }

        .plate-visual::before,
        .plate-visual::after,
        .plate-visual .plate-bolt {
            content: "";
            position: absolute;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: radial-gradient(circle at 30% 30%, #ffffff 0%, #d9d9d9 38%, #9ca3af 100%);
            border: 2px solid rgba(15, 23, 42, 0.28);
            box-shadow: inset 0 1px 2px rgba(255,255,255,0.85);
        }

        .plate-visual::before {
            top: 14px;
            left: 24px;
        }

        .plate-visual::after {
            bottom: 14px;
            right: 24px;
        }

        .plate-visual .plate-bolt {
            bottom: 14px;
            left: 24px;
        }

        .plate-lane {
            background: linear-gradient(180deg, #f8fafc 0%, #e5eef7 100%);
            border-right: 2px solid rgba(15, 23, 42, 0.18);
            padding: 14px 8px 12px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
        }

        .plate-flag {
            width: 42px;
            height: 30px;
            object-fit: cover;
            border-radius: 3px;
            border: 1px solid rgba(15, 23, 42, 0.12);
        }

        .plate-badge {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            border: 2px solid #64748b;
            color: #0f172a;
            font-weight: 800;
            font-size: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255,255,255,0.9);
        }

        .plate-core {
            position: relative;
            display: flex;
            flex-direction: column;
            padding: 16px 24px 20px;
            background-image: linear-gradient(90deg, rgba(255,255,255,0.06), transparent 15%, transparent 85%, rgba(0,0,0,0.05));
        }

        .plate-identity-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 8px;
        }

        .plate-date-stack {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 10px;
        }
        .plate-date-badge {
            min-width: 104px;
            padding: 10px 14px;
            background: linear-gradient(180deg, #2d6ee7 0%, #1f55c8 100%);
            color: white;
            border-radius: 8px;
            font-weight: 900;
            text-align: center;
            box-shadow: inset 0 -2px 0 rgba(0,0,0,0.12), 0 3px 6px rgba(15, 23, 42, 0.15);
            border: 1px solid rgba(255,255,255,0.18);
            line-height: 1;
            font-size: 22px;
        }

        .plate-date-badge small {
            display: block;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.08em;
            margin-top: 3px;
            text-transform: uppercase;
        }

        .plate-country {
            color: #22170f;
            font-size: 38px;
            font-weight: 900;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            text-shadow: 0 1px 0 rgba(255,255,255,0.18);
        }

        .plate-month,
        .plate-year {
            min-width: 96px;
            padding: 10px 14px;
            background: linear-gradient(180deg, #2d6ee7 0%, #1f55c8 100%);
            color: white;
            border-radius: 8px;
            font-weight: 900;
            text-align: center;
            box-shadow: inset 0 -2px 0 rgba(0,0,0,0.12), 0 3px 6px rgba(15, 23, 42, 0.15);
            border: 1px solid rgba(255,255,255,0.18);
        }

        .plate-month {
            font-size: 24px;
            line-height: 1;
        }

        .plate-year {
            font-size: 22px;
            line-height: 1;
        }

        .plate-month small,
        .plate-year small {
            display: block;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.08em;
            margin-top: 3px;
        }

        .plate-number-wrap {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 8px 8px 2px;
        }

        .plate-identity .plate-number {
            font-family: Impact, Haettenschweiler, 'Arial Narrow Bold', sans-serif;
            font-size: clamp(86px, 11vw, 132px);
            line-height: 0.9;
            margin: 0;
            color: #111827;
            font-weight: 900;
            letter-spacing: 0.015em;
            text-transform: uppercase;
            text-shadow: 1px 2px 0 rgba(255,255,255,0.14), -1px -1px 0 rgba(0,0,0,0.06);
            transform: scaleY(1.02);
        }

        .plate-bottomline {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 14px;
            margin-top: 6px;
        }

        .plate-owner {
            color: rgba(17, 24, 39, 0.78);
            font-size: 11px;
            line-height: 1.55;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            text-align: left;
        }

        .plate-owner strong {
            color: #0f172a;
            display: block;
            font-size: 14px;
            letter-spacing: 0;
            text-transform: none;
            margin-bottom: 2px;
        }

        .user-info a {
            background: #f1f5f9;
            color: #c41e3a;
            padding: 10px 18px;
            border-radius: 8px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
        }

        /* Page Content */
        .page-content {
            padding: 35px 40px;
        }

        .content-card {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            max-width: 1120px;
            margin: 0 auto;
        }

        .card-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .plate-county-flag {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 92px;
            padding: 12px 16px;
            border-radius: 14px;
            background: rgba(255,255,255,0.16);
            border: 1px solid rgba(255,255,255,0.24);
            color: #fff7ed;
            font-size: 28px;
            font-weight: 900;
            letter-spacing: 0.12em;
            text-shadow: 0 1px 0 rgba(0,0,0,0.16);
        }

        .card-header h2 {
            color: #0a2463;
            font-size: 26px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 22px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #0f172a;
            font-weight: 600;
            font-size: 14px;
        }

        .form-group input, .form-group select {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 15px;
            outline: none;
            transition: all 0.3s;
        }

        .form-group input:focus, .form-group select:focus {
            border-color: #0a2463;
            box-shadow: 0 0 0 4px rgba(10,36,99,0.05);
        }

        .btn-primary {
            background: linear-gradient(135deg, #c41e3a 0%, #a01830 100%);
            color: white;
            padding: 15px;
            border: none;
            border-radius: 12px;
            font-size: 17px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(196,30,58,0.4);
        }

        /* Alert */
        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
        }

        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .alert-warning {
            background: #fef3c7;
            color: #92400e;
        }

        .payment-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 999px;
            background: #eff6ff;
            color: #1d4ed8;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .classification-banner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            background: linear-gradient(135deg, #0a2463 0%, #123d8a 100%);
            color: white;
            border-radius: 16px;
            padding: 18px 20px;
            margin-bottom: 20px;
            box-shadow: 0 14px 28px rgba(10, 36, 99, 0.15);
        }

        .classification-banner .classification-label {
            display: block;
            font-size: 11px;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: rgba(255,255,255,0.8);
            margin-bottom: 6px;
            font-weight: 700;
        }

        .classification-banner .classification-code {
            font-size: clamp(2rem, 3vw, 2.8rem);
            font-weight: 900;
            letter-spacing: 0.08em;
        }

        .classification-banner .classification-meta {
            text-align: right;
            font-size: 13px;
            color: rgba(255,255,255,0.88);
            font-weight: 700;
        }

        .plate-card {
            border: 1px solid #dbeafe;
            border-radius: 24px;
            padding: 24px;
            background: linear-gradient(135deg, #f8fbff 0%, #ffffff 100%);
            margin-bottom: 20px;
            text-align: center;
            box-shadow: 0 20px 50px rgba(15, 23, 42, 0.08);
        }

        .plate-card-shell {
            margin-top: 18px;
            display: grid;
            gap: 18px;
        }

        .plate-identity {
            position: relative;
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: 18px;
            align-items: stretch;
        }

        .plate-visual {
            position: relative;
            display: grid;
            width: 100%;
            min-width: 0;
            grid-template-columns: 96px minmax(0, 1fr);
            min-height: 300px;
            border-radius: 22px;
            overflow: hidden;
            background:
                linear-gradient(180deg, rgba(255,255,255,0.16), transparent 18%),
                linear-gradient(180deg, #ef8d2f 0%, #ea7f1c 100%);
            border: 5px solid #d96f12;
            box-shadow: 0 24px 45px rgba(15, 23, 42, 0.16);
        }

        .plate-visual::before,
        .plate-visual::after,
        .plate-visual .plate-bolt {
            content: "";
            position: absolute;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: radial-gradient(circle at 30% 30%, #ffffff 0%, #d9d9d9 38%, #9ca3af 100%);
            border: 2px solid rgba(15, 23, 42, 0.28);
            box-shadow: inset 0 1px 2px rgba(255,255,255,0.85);
        }

        .plate-visual::before {
            top: 14px;
            left: 24px;
        }

        .plate-visual::after {
            bottom: 14px;
            right: 24px;
        }

        .plate-visual .plate-bolt {
            bottom: 14px;
            left: 24px;
        }

        .plate-lane {
            background: linear-gradient(180deg, #f8fafc 0%, #e5eef7 100%);
            border-right: 2px solid rgba(15, 23, 42, 0.18);
            padding: 14px 10px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
        }

        .plate-flag {
            width: 42px;
            height: 30px;
            object-fit: cover;
            border-radius: 3px;
            border: 1px solid rgba(15, 23, 42, 0.12);
        }

        .plate-badge {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            border: 2px solid #64748b;
            color: #0f172a;
            font-weight: 800;
            font-size: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255,255,255,0.9);
        }

        .plate-core {
            position: relative;
            display: flex;
            flex-direction: column;
            padding: 16px 24px 20px;
            background-image: linear-gradient(90deg, rgba(255,255,255,0.06), transparent 15%, transparent 85%, rgba(0,0,0,0.05));
        }

        .plate-identity-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 8px;
        }

        .plate-country {
            color: #22170f;
            font-size: clamp(28px, 3.2vw, 42px);
            font-weight: 900;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            text-shadow: 0 1px 0 rgba(255,255,255,0.18);
        }

        .plate-month,
        .plate-year {
            min-width: 76px;
            padding: 8px 12px;
            background: linear-gradient(180deg, #2d6ee7 0%, #1f55c8 100%);
            color: white;
            border-radius: 8px;
            font-weight: 900;
            text-align: center;
            box-shadow: inset 0 -2px 0 rgba(0,0,0,0.12), 0 3px 6px rgba(15, 23, 42, 0.15);
            border: 1px solid rgba(255,255,255,0.18);
        }

        .plate-month {
            font-size: 22px;
            line-height: 1;
        }

        .plate-month small,
        .plate-year small {
            display: block;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 0.08em;
            margin-top: 3px;
        }

        .plate-number-wrap {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 6px 8px 4px;
        }

        .plate-identity .plate-number {
            font-family: Impact, Haettenschweiler, 'Arial Narrow Bold', sans-serif;
            font-size: clamp(86px, 10vw, 132px);
            line-height: 0.9;
            margin: 0;
            color: #111827;
            font-weight: 900;
            letter-spacing: 0.01em;
            text-transform: uppercase;
            text-shadow: 1px 2px 0 rgba(255,255,255,0.14), -1px -1px 0 rgba(0,0,0,0.06);
            transform: scaleY(1.04);
        }

        .plate-bottomline {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 14px;
            flex-wrap: wrap;
        }

        .plate-owner {
            color: rgba(17, 24, 39, 0.78);
            font-size: 11px;
            line-height: 1.55;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            text-align: left;
            max-width: 100%;
        }

        .plate-owner strong {
            color: #0f172a;
            display: block;
            font-size: 14px;
            letter-spacing: 0;
            text-transform: none;
            margin-bottom: 2px;
        }

        .plate-county-flag {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 92px;
            padding: 10px 14px;
            border-radius: 14px;
            background: rgba(255,255,255,0.16);
            border: 1px solid rgba(255,255,255,0.24);
            color: #fff7ed;
            font-size: 24px;
            font-weight: 900;
            letter-spacing: 0.08em;
            text-shadow: 0 1px 0 rgba(0,0,0,0.16);
        }

        .plate-qr {
            width: 56px;
            min-height: 56px;
            padding: 4px;
            border-radius: 10px;
            background: rgba(255,255,255,0.94);
            border: 1px solid rgba(15, 23, 42, 0.12);
            box-shadow: 0 6px 14px rgba(15, 23, 42, 0.08);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 8px 0;
        }

        .plate-qr::before {
            content: none;
        }

        .plate-qr img {
            display: block;
            width: 100% !important;
            height: auto !important;
        }

        .plate-summary {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            text-align: left;
        }

        .plate-summary-item {
            padding: 14px;
            border-radius: 14px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
        }

        .plate-summary-item span {
            display: block;
            color: #64748b;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 6px;
        }

        .plate-summary-item strong {
            color: #0f172a;
            font-size: 15px;
        }

        .plate-summary-item strong.county-name {
            color: #0a2463;
        }

        /* Plate Display */
        .plate-display {
            width: min(100%, 980px);
            background: linear-gradient(180deg, #f8a65a 0%, #f28a2b 100%);
            border: 4px solid rgba(18, 50, 91, 0.9);
            border-radius: 18px 18px 18px 18px;
            padding: 18px 22px 10px;
            margin: 0 auto 28px;
            box-shadow: 0 12px 28px rgba(10, 36, 99, 0.16);
            position: relative;
            overflow: hidden;
        }

        .plate-display::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(255,255,255,0.08), rgba(0,0,0,0.02));
            pointer-events: none;
        }

        .plate-display-inner {
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: 55px 1fr 92px;
            align-items: center;
            gap: 16px;
        }

        .plate-mini-flag {
            width: 42px;
            height: 28px;
            border-radius: 4px;
            background:
                linear-gradient(90deg, #d81d39 0 25%, #fff 25% 45%, #d81d39 45% 65%, #fff 65% 85%, #d81d39 85% 100%),
                linear-gradient(180deg, #d81d39 0 50%, #fff 50% 100%);
            box-shadow: 0 0 0 1px rgba(12, 18, 28, 0.2);
            position: relative;
            overflow: hidden;
        }

        .plate-mini-flag::before {
            content: "★";
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: rgba(255,255,255,0.9);
            font-size: 14px;
            text-shadow: 0 0 8px rgba(255,255,255,0.6);
        }

        .plate-country-name {
            text-align: left;
            color: #0a1a35;
            font-size: clamp(16px, 2vw, 28px);
            font-weight: 900;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .plate-right-box {
            justify-self: end;
            background: linear-gradient(180deg, #1e63d7 0%, #1a4fb8 100%);
            color: white;
            width: 90px;
            min-height: 70px;
            border-radius: 12px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 8px 10px;
            text-transform: uppercase;
            box-shadow: inset 0 -2px 0 rgba(0,0,0,0.12), 0 4px 10px rgba(11, 46, 106, 0.18);
            font-weight: 800;
            letter-spacing: 0.04em;
        }

        .plate-right-box .month {
            font-size: 16px;
            line-height: 1.2;
        }

        .plate-right-box .year {
            font-size: 12px;
            line-height: 1.2;
            opacity: 0.9;
        }

        .plate-display-number {
            margin: 10px 0 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: Impact, Haettenschweiler, 'Arial Narrow Bold', sans-serif;
            font-size: clamp(72px, 12vw, 160px);
            letter-spacing: 0.05em;
            line-height: 0.9;
            font-weight: 900;
            color: #0c1b2d;
            text-shadow: 0 1px 0 rgba(255,255,255,0.4);
        }

        .plate-display-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            margin-top: 10px;
            color: rgba(12, 27, 45, 0.85);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            font-size: 11px;
        }

        .plate-display-meta .lb-badge {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: rgba(255,255,255,0.18);
            border: 2px solid rgba(12, 27, 45, 0.18);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 900;
        }

        .plate-display-meta .reg-box {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 800;
        }

        .plate-display-meta .reg-box .reg-value {
            background: linear-gradient(180deg, #1d5fe6 0%, #144bb9 100%);
            color: white;
            width: 62px;
            height: 62px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            box-shadow: inset 0 -2px 0 rgba(0,0,0,0.12);
        }

        .plate-display-meta .reg-box .reg-text {
            display: flex;
            flex-direction: column;
            align-items: center;
            font-size: 12px;
            line-height: 1.2;
        }

        .plate-actions {
            margin-top: 16px;
            display: flex;
            justify-content: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn-print {
            width: auto;
            padding: 12px 22px;
            background: linear-gradient(135deg, #0f766e 0%, #115e59 100%);
        }

        .btn-print:hover {
            box-shadow: 0 5px 20px rgba(15, 118, 110, 0.35);
        }

        @media print {
            body {
                background: #ffffff !important;
            }

            .sidebar,
            .top-header,
            .card-header,
            form,
            .payment-pill,
            .plate-actions,
            .page-title,
            .user-info,
            .sidebar-menu,
            .sidebar-header {
                display: none !important;
            }

            .main-content,
            .page-content,
            .content-card {
                margin: 0 !important;
                padding: 0 !important;
                background: #ffffff !important;
                box-shadow: none !important;
                max-width: 100% !important;
            }

            .plate-print-target {
                display: block !important;
                width: 100% !important;
                padding: 0 !important;
                margin: 0 auto !important;
            }

            .plate-card {
                border: none !important;
                box-shadow: none !important;
                margin: 0 !important;
                padding: 0 !important;
                break-inside: avoid;
            }

            .plate-summary {
                margin-top: 16px;
            }
        }

        @media (max-width: 980px) {
            .content-card {
                max-width: 100%;
                padding: 24px;
            }

            .plate-visual {
                grid-template-columns: 84px minmax(0, 1fr);
                min-height: 260px;
            }

            .plate-date-badge {
                min-width: 88px;
                font-size: 18px;
            }

            .plate-country {
                font-size: 28px;
                letter-spacing: 0.08em;
            }

            .plate-identity .plate-number {
                font-size: clamp(72px, 16vw, 110px);
            }

            .plate-summary {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 640px) {
            .page-content {
                padding: 20px 12px;
            }

            .plate-display {
                width: 100%;
                padding: 20px 12px;
                gap: 10px;
            }

            .plate-display .l-badge {
                width: 58px;
                height: 58px;
                font-size: 30px;
            }

            .plate-display .plate-number {
                font-size: clamp(24px, 7vw, 38px);
                letter-spacing: 0.06em;
            }

            .plate-visual {
                grid-template-columns: 72px minmax(0, 1fr);
                min-height: 230px;
            }

            .plate-lane {
                padding: 10px 8px;
            }

            .plate-flag {
                width: 34px;
                height: 24px;
            }

            .plate-badge {
                width: 42px;
                height: 42px;
                font-size: 18px;
            }

            .plate-core {
                padding: 12px 12px 14px;
            }

            .plate-date-badge {
                min-width: 76px;
                font-size: 14px;
                padding: 8px 10px;
            }

            .plate-identity .plate-number {
                font-size: clamp(56px, 18vw, 84px);
            }

            .plate-county-flag {
                min-width: 78px;
                font-size: 18px;
                padding: 8px 10px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <img src="assets/images/logo.png" alt="TUA">
            <h2>TUA PORTAL</h2>
            <p>REPUBLIC OF LIBERIA</p>
        </div>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php"><i class="fa-solid fa-gauge-high"></i> Dashboard</a></li>
            <li><a href="users.php"><i class="fa-solid fa-users"></i> Users Management</a></li>
            <li><a href="vehicles.php"><i class="fa-solid fa-car"></i> Vehicles</a></li>
            <li><a href="applications.php"><i class="fa-solid fa-file-lines"></i> Applications<?php if ($unread_admin_notifications > 0): ?><span style="margin-left:8px;background:#c41e3a;color:#fff;border-radius:999px;padding:2px 8px;font-size:11px;font-weight:700;"><?php echo $unread_admin_notifications; ?></span><?php endif; ?></a></li>
            <li><a href="messages.php"><i class="fa-solid fa-envelope-open-text"></i> Messages<?php if (admin_get_unread_contact_message_count($conn) > 0): ?><span style="margin-left:8px;background:#c41e3a;color:#fff;border-radius:999px;padding:2px 8px;font-size:11px;font-weight:700;"><?php echo admin_get_unread_contact_message_count($conn); ?></span><?php endif; ?></a></li>
            <li><a href="payments.php"><i class="fa-solid fa-credit-card"></i> Payments</a></li>
            <li><a href="reports.php"><i class="fa-solid fa-chart-line"></i> Reports</a></li>
            <li><a href="generate-plate.php" class="active"><i class="fa-solid fa-qrcode"></i> Generate Plate</a></li>
            <li><a href="add_vehicle.php"><i class="fa-solid fa-plus-circle"></i> Register Existing Plate</a></li>
            <li><a href="index.php" target="_blank"><i class="fa-solid fa-globe"></i> View Website</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Header -->
        <div class="top-header">
            <div class="page-title">
                <h1><i class="fa-solid fa-qrcode"></i> Generate License Plate</h1>
            </div>
            <div class="user-info">
                <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
            </div>
        </div>

        <!-- Page Content -->
        <div class="page-content">
            <div class="content-card">
                <div class="card-header">
                    <h2><i class="fa-solid fa-plus-circle"></i> Generate New License Plate</h2>
                </div>

                <?php if ($application): ?>
                    <?php $application_classification = getVehicleClassificationInfo($conn, $application); ?>
                    <div class="payment-pill">
                        <i class="fa-solid fa-circle-check"></i>
                        Payment status: <strong><?php echo htmlspecialchars($payment_status_label); ?></strong>
                    </div>
                    <div class="classification-banner">
                        <div>
                            <span class="classification-label">Vehicle Classification</span>
                            <div class="classification-code"><?php echo htmlspecialchars($application_classification['code'] ?? 'A2'); ?></div>
                        </div>
                        <div class="classification-meta">
                            <div><?php echo htmlspecialchars($application_classification['label'] ?? 'Sedan'); ?></div>
                            <div>Plate prefix: <?php echo htmlspecialchars($application_classification['code'] ?? 'A2'); ?></div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($generated_plate): ?>
                    <?php $plate_style = appVehiclePlateColorStyle($application['vehicle_use_class'] ?? null, $application['official_vehicle_category'] ?? null); ?>
                    <div class="plate-print-target" id="plate-print-target">
                        <div class="plate-card">
                        <?php $county_code = buildCountyPlateCode($application['county'] ?? ''); ?>
                        <h3 style="color:#0a2463; margin-bottom:10px;">License Plate Card Preview</h3>
                        <div class="plate-card-shell">
                            <div class="plate-identity">
                                <div class="plate-visual" style="background: <?php echo htmlspecialchars($plate_style['background']); ?>; border-color: <?php echo htmlspecialchars($plate_style['border']); ?>; color: <?php echo htmlspecialchars($plate_style['text']); ?>;">
                                    <span class="plate-bolt"></span>
                                    <div class="plate-lane">
                                        <img class="plate-flag" src="https://upload.wikimedia.org/wikipedia/commons/b/b8/Flag_of_Liberia.svg" alt="Liberian Flag">
                                        <div class="plate-qr" data-license-qr="<?php echo htmlspecialchars($qr_data, ENT_QUOTES); ?>"></div>
                                        <div class="plate-badge">LB</div>
                                    </div>
                                    <div class="plate-core">
                                        <div class="plate-identity-meta">
                                            <span class="plate-country">Liberia</span>
                                            <span class="plate-date-badge"><?php echo !empty($application['issued_at']) ? date('d/m/Y', strtotime($application['issued_at'])) : date('d/m/Y'); ?><small>Issued</small></span>
                                        </div>
                                        <div class="plate-number-wrap">
                                            <div class="plate-number"><?php echo htmlspecialchars($generated_plate); ?></div>
                                        </div>
                                        <div class="plate-bottomline">
                                            <div class="plate-owner">
                                                <?php echo htmlspecialchars($application['county'] ?? 'County'); ?> County
                                            </div>
                                            <div style="display:flex; align-items:flex-end; gap:10px;">
                                                <div class="plate-county-flag"><?php echo htmlspecialchars($county_code); ?></div>
                                                <span class="plate-date-badge"><?php echo !empty($application['expires_at']) ? date('d/m/Y', strtotime($application['expires_at'])) : date('d/m/Y', strtotime('+3 years')); ?><small>Expires</small></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="plate-summary">
                                <div class="plate-summary-item">
                                    <span>Issue Date</span>
                                    <strong><?php echo !empty($application['issued_at']) ? date('M d, Y', strtotime($application['issued_at'])) : date('M d, Y'); ?></strong>
                                </div>
                                <div class="plate-summary-item">
                                    <span>Expiry Date</span>
                                    <strong><?php echo !empty($application['expires_at']) ? date('M d, Y', strtotime($application['expires_at'])) : date('M d, Y', strtotime('+3 years')); ?></strong>
                                </div>
                                <div class="plate-summary-item">
                                    <span>County Mark</span>
                                    <strong class="county-name"><?php echo htmlspecialchars(($application['county'] ?? 'County') . ' / ' . $county_code); ?></strong>
                                </div>
                            </div>
                        </div>
                        <p style="color:#475569; font-size:14px;">Scan the QR code to open plate verification and display this exact generated plate. You can also open the same public link on a phone.</p>
                        <?php if (!empty($qr_data)): ?>
                            <div style="margin-top:14px; display:flex; flex-wrap:wrap; gap:10px; align-items:center; background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:12px 14px;">
                                <span style="font-weight:700; color:#0f172a;"><i class="fa-solid fa-mobile-screen-button"></i> Phone link:</span>
                                <a href="<?php echo htmlspecialchars($qr_data, ENT_QUOTES); ?>" target="_blank" rel="noopener" style="color:#0a2463; font-weight:700; word-break:break-all;">
                                    <?php echo htmlspecialchars($qr_data); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="plate-actions">
                        <button type="button" class="btn-primary btn-print" onclick="window.print()">
                            <i class="fa-solid fa-print"></i> Print Plate
                        </button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <input type="hidden" name="action" value="lookup">
                    <div class="form-group">
                        <label>Application ID / Application Number</label>
                        <input type="text" name="application_id" required placeholder="Enter the applicant's application ID or application number" value="<?php echo htmlspecialchars($application['application_number'] ?? ''); ?>">
                    </div>
                    <button type="submit" class="btn-primary">
                        <i class="fa-solid fa-magnifying-glass"></i> Check Application
                    </button>
                </form>

                <?php if ($application && !$generated_plate): ?>
                    <?php $precheck_plate = generateUniqueLicensePlateNumber($conn, $application); ?>
                    <?php $precheck_exists = $precheck_plate === null ? true : plateExistsInSystem($conn, $precheck_plate); ?>

                    <?php if ($show_payment_form): ?>
                        <div style="margin-top:24px; border:1px solid #e2e8f0; border-radius:14px; padding:20px; background:#f8fafc;">
                            <h3 style="color:#0a2463; margin-bottom:12px;"><i class="fa-solid fa-credit-card"></i> Optional Payment Recording</h3>
                            <form method="POST" action="">
                                <input type="hidden" name="action" value="record_payment">
                                <input type="hidden" name="application_id" value="<?php echo htmlspecialchars($application['id']); ?>">
                                <div class="form-group">
                                    <label>Amount</label>
                                    <input type="number" step="0.01" min="0" name="amount" value="100" required>
                                </div>
                                <div class="form-group">
                                    <label>Currency</label>
                                    <select name="payment_currency">
                                        <option value="USD">USD</option>
                                        <option value="LRD">LRD</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Payment Method</label>
                                    <select name="payment_method">
                                        <option value="Cash">Cash</option>
                                        <option value="Card">Card</option>
                                        <option value="Mobile Money">Mobile Money</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn-primary" style="width:auto; padding:12px 18px;">
                                    <i class="fa-solid fa-money-bill-wave"></i> Record Payment
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>

                    <?php if ($has_paid_payment || !$show_payment_form): ?>
                        <?php if ($precheck_plate !== null && !$precheck_exists): ?>
                            <div style="margin-top:20px; border:1px solid #bbf7d0; background:#ecfdf5; color:#166534; border-radius:12px; padding:12px 16px; font-weight:700;">
                                <i class="fa-solid fa-shield-check"></i> Unique plate available for this class: <strong><?php echo htmlspecialchars($precheck_plate); ?></strong>
                            </div>
                        <?php else: ?>
                            <div style="margin-top:20px; border:1px solid #fecaca; background:#fef2f2; color:#991b1b; border-radius:12px; padding:12px 16px; font-weight:700;">
                                <i class="fa-solid fa-triangle-exclamation"></i> No unique plate could be generated for this vehicle class. Please review the database and regenerate after resolving duplicates.
                            </div>
                        <?php endif; ?>

                        <div style="margin-top:24px; display:flex; gap:12px; justify-content:center; flex-wrap:wrap;">
                            <form method="POST" action="" style="display:inline;">
                                <input type="hidden" name="action" value="approve_plate">
                                <input type="hidden" name="application_id" value="<?php echo htmlspecialchars($application['id'] ?? ''); ?>">
                                <button type="submit" class="btn-primary" style="width:auto; padding:12px 20px; background:linear-gradient(135deg, #059669 0%, #047857 100%);" <?php echo ($precheck_plate === null || $precheck_exists) ? 'disabled' : ''; ?>>
                                    <i class="fa-solid fa-check"></i> Approve & Generate Plate
                                </button>
                            </form>
                            <form method="POST" action="" style="display:inline;">
                                <input type="hidden" name="action" value="reject_plate">
                                <input type="hidden" name="application_id" value="<?php echo htmlspecialchars($application['id'] ?? ''); ?>">
                                <div style="margin-top:10px; min-width:280px;">
                                    <label for="rejection_reason" style="display:block; margin-bottom:8px; font-weight:700; color:#0f172a;">Rejection Reason</label>
                                    <textarea id="rejection_reason" name="rejection_reason" rows="3" style="width:100%; padding:12px 14px; border:2px solid #e2e8f0; border-radius:12px; resize:vertical;" placeholder="Enter a reason for rejecting this request"></textarea>
                                </div>
                                <div style="margin-top:12px;">
                                    <button type="submit" class="btn-primary" style="width:auto; padding:12px 20px; background:linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);">
                                        <i class="fa-solid fa-xmark"></i> Reject Request
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php if (!empty($page_toasts)): ?>
        <script>
            window.adminToastQueue = <?php echo json_encode($page_toasts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        </script>
    <?php endif; ?>
    <script src="assets/js/admin-toast.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
        document.querySelectorAll('[data-license-qr]').forEach(function (node) {
            var payload = node.getAttribute('data-license-qr');
            if (!payload) {
                return;
            }

            new QRCode(node, {
                text: payload,
                width: 106,
                height: 106,
                colorDark: '#0a2463',
                colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.M
            });
        });
    </script>
</body>
</html>
