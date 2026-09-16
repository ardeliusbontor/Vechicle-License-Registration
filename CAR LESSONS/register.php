<?php
session_start();
include "config.php";
include "partials/admin-toast.php";

if (isset($_SESSION['id'])) {
    if (($_SESSION['role'] ?? '') === 'Applicant') {
        admin_toast_add("You are already logged in.", 'info');
        header("Location: user-dashboard.php");
    } else {
        admin_toast_add("You are already signed in as staff.", 'info');
        header("Location: dashboard.php");
    }
    exit();
}

$counties = [
    'Bomi',
    'Bong',
    'Gbarpolu',
    'Grand Bassa',
    'Grand Cape Mount',
    'Grand Gedeh',
    'Grand Kru',
    'Lofa',
    'Margibi',
    'Maryland',
    'Montserrado',
    'Nimba',
    'Rivercess',
    'River Gee',
    'Sinoe'
];

$message = '';
$message_type = '';
$form_data = [
    'fullname' => '',
    'username' => '',
    'email' => '',
    'contact_number' => '',
    'county' => '',
    'address' => ''
];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    foreach ($form_data as $field => $value) {
        $form_data[$field] = trim($_POST[$field] ?? '');
    }

    $fullname = mysqli_real_escape_string($conn, $form_data['fullname']);
    $username = mysqli_real_escape_string($conn, $form_data['username']);
    $email = mysqli_real_escape_string($conn, $form_data['email']);
    $contact_number = mysqli_real_escape_string($conn, $form_data['contact_number']);
    $county = mysqli_real_escape_string($conn, $form_data['county']);
    $address = mysqli_real_escape_string($conn, $form_data['address']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);
    $confirm_password = mysqli_real_escape_string($conn, $_POST['confirm_password']);

    if (!in_array($form_data['county'], $counties, true)) {
        $message = "Please select a valid county.";
        $message_type = "danger";
    } elseif ($password !== $confirm_password) {
        $message = "Passwords do not match!";
        $message_type = "danger";
    } else {
        $check = mysqli_query($conn, "SELECT id FROM applicants WHERE username='$username'");
        if (mysqli_num_rows($check) > 0) {
            $message = "Username already exists!";
            $message_type = "danger";
        } else {
            $hashedPassword = appPasswordHash($password);
            $sql = "INSERT INTO applicants (fullname, username, email, password, status, contact_number, county, address) 
                    VALUES ('$fullname', '$username', '$email', '" . mysqli_real_escape_string($conn, $hashedPassword) . "', 'Active', '$contact_number', '$county', '$address')";
            if (mysqli_query($conn, $sql)) {
                session_regenerate_id(true);
                $_SESSION['id'] = mysqli_insert_id($conn);
                $_SESSION['fullname'] = $form_data['fullname'];
                $_SESSION['username'] = $form_data['username'];
                $_SESSION['role'] = 'Applicant';
                $_SESSION['account_type'] = 'applicant';
                admin_toast_add("Account created successfully. Welcome to TUA!", 'success');
                header("Location: user-dashboard.php");
                exit();
            } else {
                $message = "Error: " . mysqli_error($conn);
                $message_type = "danger";
            }
        }
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
    <title>Register - Transport Union Authority</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin-toast.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            min-height: 100vh;
            background: linear-gradient(135deg, rgba(10,36,99,0.95) 0%, rgba(15,52,96,0.9) 100%), 
                        url('./bg.png') center/cover;
            display: flex;
            flex-direction: column;
        }

        /* Navbar */
        .navbar {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 15px 50px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo-section img {
            height: 70px;
        }

        .logo-text h1 {
            color: #0a2463;
            font-size: 16px;
            font-weight: 700;
        }

        .logo-text p {
            color: #c41e3a;
            font-size: 12px;
            font-weight: 600;
        }

        .nav-links a {
            text-decoration: none;
            color: #0a2463;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Register Container */
        .register-wrapper {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 40px;
        }

        .register-container {
            background: white;
            padding: 45px;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 500px;
        }

        .register-logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .register-logo img {
            height: 80px;
            margin-bottom: 15px;
        }

        .register-logo h2 {
            color: #0a2463;
            font-size: 22px;
            margin-bottom: 5px;
        }

        .register-logo p {
            color: #666;
            font-size: 14px;
        }

        .alert {
            padding: 14px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 14px;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
        }

        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #0a2463;
            font-weight: 600;
            font-size: 14px;
        }

        .form-group input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 15px;
            outline: none;
            transition: border-color 0.3s;
        }

        .form-group input:focus {
            border-color: #0a2463;
        }

        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 15px;
            outline: none;
            transition: border-color 0.3s;
            resize: vertical;
        }

        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #0a2463;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .register-btn {
            width: 100%;
            background: linear-gradient(135deg, #c41e3a 0%, #a01830 100%);
            color: white;
            padding: 15px;
            border: none;
            border-radius: 10px;
            font-size: 17px;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .register-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(196, 30, 58, 0.4);
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #666;
            text-decoration: none;
            font-size: 14px;
        }

        .back-link i {
            margin-right: 5px;
        }

        .login-link {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }

        .login-link p {
            color: #666;
            font-size: 14px;
        }

        .login-link a {
            color: #0a2463;
            text-decoration: none;
            font-weight: 700;
        }

        .compliance-box {
            margin-top: 18px;
            border: 1px solid #e2e8f0;
            border-left: 4px solid #0a2463;
            border-radius: 10px;
            padding: 14px;
            background: #f8fafc;
        }

        .compliance-box h4 {
            color: #0a2463;
            font-size: 15px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .compliance-box ul {
            margin-left: 18px;
            color: #334155;
            font-size: 13px;
            line-height: 1.7;
        }

        .compliance-box p {
            margin-top: 10px;
            color: #475569;
            font-size: 13px;
            line-height: 1.7;
        }

        .compliance-box a {
            color: #0a2463;
            font-weight: 700;
            text-decoration: none;
        }

        .compliance-box a:hover {
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="logo-section">
            <img src="assets/images/logo.png" alt="TUA Logo">
            <div class="logo-text">
                <h1>TRANSPORT UNION AUTHORITY</h1>
                <p>REPUBLIC OF LIBERIA</p>
            </div>
        </div>
        <div class="nav-links">
            <a href="index.php"><i class="fa-solid fa-arrow-left"></i> BACK TO HOMEPAGE</a>
        </div>
    </nav>

    <!-- Register Section -->
    <div class="register-wrapper">
        <div class="register-container">
            <div class="register-logo">
                <img src="assets/images/logo.png" alt="TUA Logo">
                <h2>CREATE YOUR ACCOUNT</h2>
                <p>Register your applicant profile to apply for a license plate</p>
            </div>

            <form method="POST" action="">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="fullname" value="<?php echo htmlspecialchars($form_data['fullname']); ?>" placeholder="Enter your full name" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" value="<?php echo htmlspecialchars($form_data['username']); ?>" placeholder="Choose a username" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($form_data['email']); ?>" placeholder="Enter your email address" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Contact Number</label>
                        <input type="text" name="contact_number" value="<?php echo htmlspecialchars($form_data['contact_number']); ?>" placeholder="Enter your phone number" required>
                    </div>
                    <div class="form-group">
                        <label>County</label>
                        <select name="county" required>
                            <option value="">Select your county</option>
                            <?php foreach ($counties as $county): ?>
                                <option value="<?php echo htmlspecialchars($county); ?>" <?php echo $form_data['county'] === $county ? 'selected' : ''; ?>><?php echo htmlspecialchars($county); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Address</label>
                    <textarea name="address" rows="3" placeholder="Enter your full address" required><?php echo htmlspecialchars($form_data['address']); ?></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" placeholder="Choose a password" required>
                    </div>

                    <div class="form-group">
                        <label>Confirm Password</label>
                        <input type="password" name="confirm_password" placeholder="Confirm your password" required>
                    </div>
                </div>

                <button type="submit" class="register-btn">
                    <i class="fa-solid fa-user-plus"></i> CREATE ACCOUNT
                </button>

                <div class="compliance-box">
                    <h4><i class="fa-solid fa-shield-halved"></i> Before You Create Your Account</h4>
                    <ul>
                        <li>Use your legal name and active contact details for verification.</li>
                        <li>Account information is used for application processing and compliance checks.</li>
                        <li>Providing false records can lead to account suspension or rejection.</li>
                    </ul>
                    <p>By registering, you agree to our <a href="terms.php">Terms & Conditions</a> and <a href="privacy.php">Privacy Policy</a>.</p>
                </div>

                <div class="login-link">
                    <p>Already have an account? <a href="login.php">Login here</a></p>
                </div>
            </form>

            <a href="index.php" class="back-link"><i class="fa-solid fa-home"></i> Return to Homepage</a>
        </div>
    </div>
    <?php if (!empty($page_toasts)): ?>
        <script>
            window.adminToastQueue = <?php echo json_encode($page_toasts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        </script>
    <?php endif; ?>
    <script src="assets/js/admin-toast.js"></script>
    <script src="assets/js/form-toast-validation.js"></script>
</body>
</html>