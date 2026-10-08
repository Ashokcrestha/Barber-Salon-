<?php
/**
 * ClassicCuts Barbershop - Staff Registration Portal
 * New staff registers here → Admin approves → Staff can login within 24 hours
 */

session_start();
require_once __DIR__ . '/config.php';

// Auto-add registration columns to staff table
if ($db_connected) {
    if ($pdo) {
        try { $pdo->exec("ALTER TABLE `staff` ADD COLUMN `account_status` VARCHAR(20) DEFAULT 'pending'"); } catch (Exception $ex) {}
        try { $pdo->exec("ALTER TABLE `staff` ADD COLUMN `approved_at` DATETIME DEFAULT NULL"); } catch (Exception $ex) {}
        try { $pdo->exec("ALTER TABLE `staff` ADD COLUMN `registration_token` VARCHAR(64) DEFAULT NULL"); } catch (Exception $ex) {}
    } elseif ($mysqli) {
        @$mysqli->query("ALTER TABLE `staff` ADD COLUMN `account_status` VARCHAR(20) DEFAULT 'pending'");
        @$mysqli->query("ALTER TABLE `staff` ADD COLUMN `approved_at` DATETIME DEFAULT NULL");
        @$mysqli->query("ALTER TABLE `staff` ADD COLUMN `registration_token` VARCHAR(64) DEFAULT NULL");
    }
}

$msg = '';
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name       = trim($_POST['name'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $role       = trim($_POST['role'] ?? 'Barber');
    $exp        = (int)($_POST['experience_years'] ?? 1);
    $bio        = trim($_POST['bio'] ?? '');
    $password   = trim($_POST['password'] ?? '');
    $confirm_pw = trim($_POST['confirm_password'] ?? '');

    // Validate
    if (empty($name) || empty($email) || empty($phone) || empty($password)) {
        $error = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif ($password !== $confirm_pw) {
        $error = "Passwords do not match.";
    } elseif (!$db_connected) {
        $error = "Database connection error. Please try again later.";
    } else {
        // Check for duplicate email
        $emailExists = false;
        try {
            if ($pdo) {
                $stmtE = $pdo->prepare("SELECT id FROM staff WHERE email = :email LIMIT 1");
                $stmtE->execute([':email' => $email]);
                $emailExists = $stmtE->fetchColumn() > 0;
            } elseif ($mysqli) {
                $stmtE = $mysqli->prepare("SELECT id FROM staff WHERE email = ? LIMIT 1");
                $stmtE->bind_param("s", $email);
                $stmtE->execute();
                $stmtE->store_result();
                $emailExists = $stmtE->num_rows > 0;
            }
        } catch (Exception $e) {}

        if ($emailExists) {
            $error = "An account with this email already exists. Please use a different email or contact the admin.";
        } else {
            // Handle photo upload
            $photoPath = 'images/team/1.jpg';
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK && $_FILES['photo']['size'] > 0) {
                $tmpName = $_FILES['photo']['tmp_name'];
                $fileExt = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
                $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                if (in_array($fileExt, $allowedExts)) {
                    $targetDir = __DIR__ . '/images/team/';
                    if (!is_dir($targetDir)) @mkdir($targetDir, 0755, true);
                    $newFileName = 'staff_reg_' . time() . '_' . rand(1000, 9999) . '.' . $fileExt;
                    if (move_uploaded_file($tmpName, $targetDir . $newFileName)) {
                        $photoPath = 'images/team/' . $newFileName;
                    }
                }
            }

            $passHash = password_hash($password, PASSWORD_DEFAULT);
            $token    = bin2hex(random_bytes(32));

            try {
                if ($pdo) {
                    $stmtI = $pdo->prepare("
                        INSERT INTO staff (name, email, phone, password, role, experience_years, bio, photo, account_status, registration_token, created_at)
                        VALUES (:name, :email, :phone, :pass, :role, :exp, :bio, :photo, 'pending', :token, NOW())
                    ");
                    $stmtI->execute([
                        ':name'  => $name,
                        ':email' => $email,
                        ':phone' => $phone,
                        ':pass'  => $passHash,
                        ':role'  => $role,
                        ':exp'   => $exp,
                        ':bio'   => $bio,
                        ':photo' => $photoPath,
                        ':token' => $token,
                    ]);
                } elseif ($mysqli) {
                    $stmtI = $mysqli->prepare("INSERT INTO staff (name, email, phone, password, role, experience_years, bio, photo, account_status, registration_token, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW())");
                    $stmtI->bind_param("sssssisss", $name, $email, $phone, $passHash, $role, $exp, $bio, $photoPath, $token);
                    $stmtI->execute();
                }
                $success = true;
                $msg = "Registration submitted successfully! Your account is <strong>pending admin approval</strong>. Once approved, you will have <strong>24 hours</strong> to log in to your dashboard.";
            } catch (Exception $e) {
                $error = "Registration failed: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Registration - ClassicCuts Barbershop</title>
    <link rel="icon" href="images/icon.png" type="image/gif" sizes="16x16">
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="fonts/font-awesome/css/font-awesome.css" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body {
            background: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 50%, #0a0a0a 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #e0e0e0;
            padding: 40px 20px;
        }
        .reg-wrapper {
            max-width: 700px;
            margin: 0 auto;
        }
        .reg-brand {
            text-align: center;
            margin-bottom: 30px;
        }
        .reg-brand a {
            text-decoration: none;
            color: #fff;
            font-size: 32px;
            font-weight: 800;
            font-family: sans-serif;
        }
        .reg-brand span { color: #d4af37; }
        .reg-brand p {
            color: #888;
            font-size: 14px;
            margin-top: 6px;
        }
        .reg-card {
            background: #1e1e1e;
            border: 1px solid #2d2d2d;
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.6);
        }
        .reg-card h2 {
            color: #d4af37;
            font-weight: 700;
            margin-bottom: 6px;
            font-size: 22px;
        }
        .reg-card .subtitle {
            color: #888;
            font-size: 13px;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #2d2d2d;
        }
        .form-label {
            color: #ccc;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 6px;
        }
        .form-control, .form-select {
            background: #2b2b2b;
            border: 1px solid #3d3d3d;
            color: #fff;
            border-radius: 8px;
            padding: 11px 14px;
            font-size: 14px;
            transition: all 0.2s;
        }
        .form-control:focus, .form-select:focus {
            background: #333;
            border-color: #d4af37;
            color: #fff;
            box-shadow: 0 0 0 3px rgba(212,175,55,0.15);
        }
        .form-control::placeholder { color: #555; }
        .form-select option { background: #2b2b2b; }
        .section-divider {
            color: #d4af37;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 25px 0 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .section-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #2d2d2d;
        }
        .btn-register {
            background: linear-gradient(135deg, #d4af37, #b8952b);
            color: #000;
            font-weight: 800;
            padding: 14px;
            border-radius: 8px;
            border: none;
            width: 100%;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            letter-spacing: 0.5px;
        }
        .btn-register:hover {
            background: linear-gradient(135deg, #e5c040, #c8a030);
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(212,175,55,0.3);
        }
        .info-box {
            background: rgba(212,175,55,0.08);
            border: 1px solid rgba(212,175,55,0.25);
            border-radius: 10px;
            padding: 16px 20px;
            margin-bottom: 25px;
        }
        .info-box ul {
            margin: 0;
            padding-left: 20px;
            color: #ccc;
            font-size: 13px;
            line-height: 1.8;
        }
        .info-box h6 {
            color: #d4af37;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .success-box {
            background: rgba(40,167,69,0.1);
            border: 1px solid rgba(40,167,69,0.3);
            border-radius: 12px;
            padding: 30px;
            text-align: center;
        }
        .success-box .icon {
            font-size: 60px;
            margin-bottom: 15px;
        }
        .success-box h4 {
            color: #28a745;
            font-weight: 700;
            margin-bottom: 10px;
        }
        .success-box p {
            color: #ccc;
            font-size: 14px;
            margin-bottom: 0;
        }
        .back-links {
            text-align: center;
            margin-top: 25px;
        }
        .back-links a {
            color: #d4af37;
            text-decoration: none;
            font-size: 13px;
            margin: 0 10px;
        }
        .back-links a:hover { color: #e5c040; }
        .password-strength {
            height: 4px;
            border-radius: 2px;
            margin-top: 6px;
            transition: all 0.3s;
            background: #333;
        }
        .alert-danger {
            background: rgba(220,53,69,0.12);
            border: 1px solid rgba(220,53,69,0.3);
            color: #ff8a94;
            border-radius: 8px;
            padding: 12px 16px;
        }
        .photo-preview {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #d4af37;
            display: none;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="reg-wrapper">
        <div class="reg-brand">
            <a href="index.html">Classic✂️<span>Cut</span></a>
            <p>💈 Barbershop Staff Registration Portal</p>
        </div>

        <div class="reg-card">
            <?php if ($success): ?>
                <div class="success-box">
                    <div class="icon">🎉</div>
                    <h4>Registration Submitted!</h4>
                    <p><?= $msg ?></p>
                    <div class="mt-4">
                        <a href="login.php" class="btn btn-outline-warning me-2">Go to Staff Login</a>
                        <a href="index.html" class="btn btn-outline-secondary">Back to Website</a>
                    </div>
                </div>
            <?php else: ?>
                <h2>✂️ Staff Registration</h2>
                <p class="subtitle">Create your staff account. Admin approval required before you can login.</p>

                <div class="info-box">
                    <h6>📋 Registration Process</h6>
                    <ul>
                        <li>Fill in your details and submit the form</li>
                        <li>Admin will review and approve your account</li>
                        <li>Once approved, you have <strong style="color:#d4af37">24 hours</strong> to login to your dashboard</li>
                        <li>Contact salon admin if you haven't received approval within 24 hours</li>
                    </ul>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert-danger mb-4">
                        <i class="fa fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="staff-register.php" enctype="multipart/form-data" id="regForm">
                    <div class="section-divider">Personal Information</div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" placeholder="e.g. Ram Bahadur Shrestha"
                                   value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email Address <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" placeholder="e.g. ram@classiccuts.com"
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                            <input type="text" name="phone" class="form-control" placeholder="e.g. 9801234567"
                                   value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Job Role</label>
                            <select name="role" class="form-select">
                                <option value="Barber" <?= (($_POST['role'] ?? '') === 'Barber') ? 'selected' : '' ?>>Barber</option>
                                <option value="Master Barber" <?= (($_POST['role'] ?? '') === 'Master Barber') ? 'selected' : '' ?>>Master Barber</option>
                                <option value="Senior Stylist" <?= (($_POST['role'] ?? '') === 'Senior Stylist') ? 'selected' : '' ?>>Senior Stylist</option>
                                <option value="Barber & Stylist" <?= (($_POST['role'] ?? '') === 'Barber & Stylist') ? 'selected' : '' ?>>Barber & Stylist</option>
                                <option value="Junior Barber" <?= (($_POST['role'] ?? '') === 'Junior Barber') ? 'selected' : '' ?>>Junior Barber</option>
                                <option value="Receptionist" <?= (($_POST['role'] ?? '') === 'Receptionist') ? 'selected' : '' ?>>Receptionist</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Years of Experience</label>
                            <input type="number" name="experience_years" class="form-control" min="0" max="50"
                                   value="<?= htmlspecialchars($_POST['experience_years'] ?? '1') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Profile Photo</label>
                            <input type="file" name="photo" class="form-control" accept="image/*" id="photoInput">
                            <img id="photoPreview" class="photo-preview" src="#" alt="Preview">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Bio / Specialty Description</label>
                            <textarea name="bio" class="form-control" rows="3" placeholder="Describe your skills and specialties (e.g. Expert in skin fades & beard styling)"><?= htmlspecialchars($_POST['bio'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <div class="section-divider">Account Security</div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Password <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control" placeholder="Minimum 6 characters"
                                   id="pwdField" required>
                            <div class="password-strength" id="pwdStrength"></div>
                            <small class="text-muted">Use a mix of letters, numbers and symbols</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                            <input type="password" name="confirm_password" class="form-control" placeholder="Re-enter your password"
                                   id="confirmPwd" required>
                            <small id="pwdMatch" class="mt-1 d-block" style="font-size:12px;"></small>
                        </div>
                    </div>

                    <div class="mt-4">
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="agreeTerms" required>
                            <label class="form-check-label text-muted" for="agreeTerms" style="font-size:13px;">
                                I confirm that the information provided is accurate and I agree to the salon's staff policies.
                            </label>
                        </div>
                        <button type="submit" class="btn-register">
                            <i class="fa fa-user-plus me-2"></i>Submit Registration for Admin Approval
                        </button>
                    </div>
                </form>
            <?php endif; ?>

            <div class="back-links">
                <a href="login.php"><i class="fa fa-sign-in me-1"></i>Already have an account? Login</a>
                &nbsp;|&nbsp;
                <a href="index.html"><i class="fa fa-home me-1"></i>Back to Website</a>
            </div>
        </div>
    </div>

    <script>
        // Photo preview
        document.getElementById('photoInput')?.addEventListener('change', function() {
            const preview = document.getElementById('photoPreview');
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = e => {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(this.files[0]);
            }
        });

        // Password strength indicator
        document.getElementById('pwdField')?.addEventListener('input', function() {
            const bar = document.getElementById('pwdStrength');
            const val = this.value;
            let strength = 0;
            if (val.length >= 6) strength++;
            if (val.length >= 10) strength++;
            if (/[A-Z]/.test(val)) strength++;
            if (/[0-9]/.test(val)) strength++;
            if (/[^A-Za-z0-9]/.test(val)) strength++;
            const colors = ['#dc3545','#fd7e14','#ffc107','#20c997','#28a745'];
            const widths = ['20%','40%','60%','80%','100%'];
            bar.style.background = colors[Math.min(strength-1, 4)] || '#333';
            bar.style.width = widths[Math.min(strength-1, 4)] || '0%';
        });

        // Password match
        document.getElementById('confirmPwd')?.addEventListener('input', function() {
            const pwd = document.getElementById('pwdField').value;
            const match = document.getElementById('pwdMatch');
            if (this.value === '') {
                match.textContent = '';
            } else if (this.value === pwd) {
                match.textContent = '✓ Passwords match';
                match.style.color = '#28a745';
            } else {
                match.textContent = '✗ Passwords do not match';
                match.style.color = '#dc3545';
            }
        });
    </script>
</body>
</html>
