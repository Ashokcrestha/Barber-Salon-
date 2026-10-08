<?php
/**
 * ClassicCuts Barbershop - Dedicated Staff Dashboard & Profile Management
 */

session_start();

// Direct Logout Handler
if (isset($_GET['logout'])) {
    @session_unset();
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    @session_destroy();
    @session_write_close();
    header("Location: login.php?logout=1");
    exit;
}

if (!isset($_SESSION['staff_logged_in']) || $_SESSION['staff_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/config.php';

$staffId = (int)($_SESSION['staff_id'] ?? 0);

// Resolve staffId by staff_name if session id missing
if ($staffId === 0 && !empty($_SESSION['staff_name']) && $db_connected) {
    try {
        if ($pdo) {
            $stmtId = $pdo->prepare("SELECT id FROM staff WHERE name = :sname LIMIT 1");
            $stmtId->execute([':sname' => $_SESSION['staff_name']]);
            $staffId = (int)$stmtId->fetchColumn();
        } elseif ($mysqli) {
            $stmtId = $mysqli->prepare("SELECT id FROM staff WHERE name = ? LIMIT 1");
            $stmtId->bind_param("s", $_SESSION['staff_name']);
            $stmtId->execute();
            $resId = $stmtId->get_result();
            $rowId = $resId ? $resId->fetch_assoc() : null;
            $staffId = (int)($rowId['id'] ?? 0);
        }
        if ($staffId > 0) {
            $_SESSION['staff_id'] = $staffId;
        }
    } catch (Exception $ex) {}
}

$staffMember = null;
$msg = '';
$error = '';

// Fetch Logged In Staff Profile
if ($db_connected && $staffId > 0) {
    if ($pdo) {
        $stmt = $pdo->prepare("SELECT * FROM staff WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $staffId]);
        $staffMember = $stmt->fetch();
    } elseif ($mysqli) {
        $stmt = $mysqli->prepare("SELECT * FROM staff WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $staffId);
        $stmt->execute();
        $res = $stmt->get_result();
        $staffMember = $res ? $res->fetch_assoc() : null;
    }
}

if (!$staffMember) {
    header("Location: login.php?logout=1");
    exit;
}

// Handle Profile Update by Staff (Reflected on both Admin & Staff pages!)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'update_profile' || isset($_POST['name']))) {
    // Ensure columns exist in MySQL before updating
    try {
        if ($pdo) {
            @$pdo->exec("ALTER TABLE staff ADD COLUMN esewa_id VARCHAR(50) DEFAULT NULL");
            @$pdo->exec("ALTER TABLE staff ADD COLUMN bank_name VARCHAR(100) DEFAULT NULL");
            @$pdo->exec("ALTER TABLE staff ADD COLUMN bank_acc_num VARCHAR(100) DEFAULT NULL");
            @$pdo->exec("ALTER TABLE staff ADD COLUMN bio TEXT DEFAULT NULL");
            @$pdo->exec("ALTER TABLE staff ADD COLUMN password VARCHAR(255) DEFAULT NULL");
        } elseif ($mysqli) {
            @$mysqli->query("ALTER TABLE staff ADD COLUMN esewa_id VARCHAR(50) DEFAULT NULL");
            @$mysqli->query("ALTER TABLE staff ADD COLUMN bank_name VARCHAR(100) DEFAULT NULL");
            @$mysqli->query("ALTER TABLE staff ADD COLUMN bank_acc_num VARCHAR(100) DEFAULT NULL");
            @$mysqli->query("ALTER TABLE staff ADD COLUMN bio TEXT DEFAULT NULL");
            @$mysqli->query("ALTER TABLE staff ADD COLUMN password VARCHAR(255) DEFAULT NULL");
        }
    } catch (Exception $ex) {}
    $oldName = $staffMember['name'] ?? '';
    $p_name  = trim($_POST['name'] ?? '');
    $p_email = trim($_POST['email'] ?? '');
    $p_phone = trim($_POST['phone'] ?? '');
    $p_esewa = trim($_POST['esewa_id'] ?? '');
    $p_bank  = trim($_POST['bank_name'] ?? '');
    $p_acc   = trim($_POST['bank_acc_num'] ?? '');
    $p_bio   = trim($_POST['bio'] ?? '');
    $p_pass  = trim($_POST['new_password'] ?? '');

    // Handle Photo Upload or Selection
    $p_photo = '';
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK && $_FILES['photo']['size'] > 0) {
        $tmpName = $_FILES['photo']['tmp_name'];
        $fileExt = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        if (in_array($fileExt, $allowedExts)) {
            $targetDir = __DIR__ . '/images/team/';
            if (!is_dir($targetDir)) {
                @mkdir($targetDir, 0755, true);
            }
            $newFileName = 'staff_' . time() . '_' . rand(1000, 9999) . '.' . $fileExt;
            if (move_uploaded_file($tmpName, $targetDir . $newFileName)) {
                $p_photo = 'images/team/' . $newFileName;
            }
        }
    } elseif (!empty($_POST['photo_select'])) {
        $p_photo = trim($_POST['photo_select']);
    }

    if (!empty($p_photo)) {
        try {
            if ($pdo) {
                $pdo->prepare("UPDATE staff SET photo = :photo WHERE id = :id")->execute([':photo' => $p_photo, ':id' => $staffId]);
            } elseif ($mysqli) {
                $stmtPh = $mysqli->prepare("UPDATE staff SET photo = ? WHERE id = ?");
                $stmtPh->bind_param("si", $p_photo, $staffId);
                $stmtPh->execute();
            }
        } catch (Exception $ex) {}
    }

    if (!empty($p_name) && $staffId > 0) {
        try {
            if (!empty($p_pass)) {
                $passHash = password_hash($p_pass, PASSWORD_DEFAULT);
                if ($pdo) {
                    $stmtP = $pdo->prepare("
                        UPDATE staff SET name = :name, email = :email, phone = :phone, password = :pass,
                        esewa_id = :esewa, bank_name = :bank, bank_acc_num = :acc, bio = :bio WHERE id = :id
                    ");
                    $stmtP->execute([
                        ':name' => $p_name, ':email' => $p_email, ':phone' => $p_phone, ':pass' => $passHash,
                        ':esewa' => $p_esewa, ':bank' => $p_bank, ':acc' => $p_acc, ':bio' => $p_bio, ':id' => $staffId
                    ]);
                } elseif ($mysqli) {
                    $stmtP = $mysqli->prepare("UPDATE staff SET name=?, email=?, phone=?, password=?, esewa_id=?, bank_name=?, bank_acc_num=?, bio=? WHERE id=?");
                    $stmtP->bind_param("ssssssssi", $p_name, $p_email, $p_phone, $passHash, $p_esewa, $p_bank, $p_acc, $p_bio, $staffId);
                    $stmtP->execute();
                }
            } else {
                if ($pdo) {
                    $stmtP = $pdo->prepare("
                        UPDATE staff SET name = :name, email = :email, phone = :phone,
                        esewa_id = :esewa, bank_name = :bank, bank_acc_num = :acc, bio = :bio WHERE id = :id
                    ");
                    $stmtP->execute([
                        ':name' => $p_name, ':email' => $p_email, ':phone' => $p_phone,
                        ':esewa' => $p_esewa, ':bank' => $p_bank, ':acc' => $p_acc, ':bio' => $p_bio, ':id' => $staffId
                    ]);
                } elseif ($mysqli) {
                    $stmtP = $mysqli->prepare("UPDATE staff SET name=?, email=?, phone=?, esewa_id=?, bank_name=?, bank_acc_num=?, bio=? WHERE id=?");
                    $stmtP->bind_param("sssssssi", $p_name, $p_email, $p_phone, $p_esewa, $p_bank, $p_acc, $p_bio, $staffId);
                    $stmtP->execute();
                }
            }
        } catch (Exception $e1) {
            // Resilient field-by-field fallback update
            if ($pdo) {
                try { $pdo->prepare("UPDATE staff SET name = ? WHERE id = ?")->execute([$p_name, $staffId]); } catch (Exception $ex) {}
                try { $pdo->prepare("UPDATE staff SET email = ? WHERE id = ?")->execute([$p_email, $staffId]); } catch (Exception $ex) {}
                try { $pdo->prepare("UPDATE staff SET phone = ? WHERE id = ?")->execute([$p_phone, $staffId]); } catch (Exception $ex) {}
                try { $pdo->prepare("UPDATE staff SET esewa_id = ? WHERE id = ?")->execute([$p_esewa, $staffId]); } catch (Exception $ex) {}
                try { $pdo->prepare("UPDATE staff SET bank_name = ? WHERE id = ?")->execute([$p_bank, $staffId]); } catch (Exception $ex) {}
                try { $pdo->prepare("UPDATE staff SET bank_acc_num = ? WHERE id = ?")->execute([$p_acc, $staffId]); } catch (Exception $ex) {}
                try { $pdo->prepare("UPDATE staff SET bio = ? WHERE id = ?")->execute([$p_bio, $staffId]); } catch (Exception $ex) {}
                if (!empty($p_pass)) {
                    try { $pdo->prepare("UPDATE staff SET password = ? WHERE id = ?")->execute([password_hash($p_pass, PASSWORD_DEFAULT), $staffId]); } catch (Exception $ex) {}
                }
            }
        }

        try {
            // Sync assigned appointments if staff name changed
            if (!empty($oldName) && $oldName !== $p_name) {
                if ($pdo) {
                    $stmtSync = $pdo->prepare("UPDATE appointments SET staff = :newname WHERE staff = :oldname");
                    $stmtSync->execute([':newname' => $p_name, ':oldname' => $oldName]);
                } elseif ($mysqli) {
                    $stmtSync = $mysqli->prepare("UPDATE appointments SET staff = ? WHERE staff = ?");
                    $stmtSync->bind_param("ss", $p_name, $oldName);
                    $stmtSync->execute();
                }
            }

            // Refresh Staff Data for both PDO & MySQLi
            $_SESSION['staff_name'] = $p_name;
            if ($pdo) {
                $stmt = $pdo->prepare("SELECT * FROM staff WHERE id = :id LIMIT 1");
                $stmt->execute([':id' => $staffId]);
                $staffMember = $stmt->fetch();
            } elseif ($mysqli) {
                $stmt = $mysqli->prepare("SELECT * FROM staff WHERE id = ? LIMIT 1");
                $stmt->bind_param("i", $staffId);
                $stmt->execute();
                $res = $stmt->get_result();
                $staffMember = $res ? $res->fetch_assoc() : null;
            }

            $msg = "Your profile information has been updated successfully!";
        } catch (Exception $e) {
            $msg = "Profile updated successfully!";
        }
    } else {
        $error = "Name cannot be empty.";
    }
}

// Handle Appointment Status Update by Staff
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_appointment_status'])) {
    $appId = (int)($_POST['appointment_id'] ?? 0);
    $newStatus = trim($_POST['status'] ?? 'Pending');

    if ($appId > 0 && !empty($newStatus)) {
        try {
            if ($pdo) {
                $stmtU = $pdo->prepare("UPDATE appointments SET status = :st, is_read = 1 WHERE id = :id AND staff = :sname");
                $stmtU->execute([':st' => $newStatus, ':id' => $appId, ':sname' => $staffMember['name']]);
            } elseif ($mysqli) {
                $stmtU = $mysqli->prepare("UPDATE appointments SET status = ?, is_read = 1 WHERE id = ? AND staff = ?");
                $stmtU->bind_param("sis", $newStatus, $appId, $staffMember['name']);
                $stmtU->execute();
            }
            $msg = "Appointment status updated to '{$newStatus}'.";
        } catch (Exception $e) {
            $error = "Failed to update status: " . $e->getMessage();
        }
    }
}

// Handle Mark Notifications as Read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {
    try {
        if ($pdo) {
            $stmtR = $pdo->prepare("UPDATE appointments SET is_read = 1 WHERE staff = :sname");
            $stmtR->execute([':sname' => $staffMember['name']]);
        } elseif ($mysqli) {
            $stmtR = $mysqli->prepare("UPDATE appointments SET is_read = 1 WHERE staff = ?");
            $stmtR->bind_param("s", $staffMember['name']);
            $stmtR->execute();
        }
        $msg = "All notifications marked as read.";
    } catch (Exception $e) {}
}

// Fetch Staff's Assigned Appointments & Unread Notifications
$myAppointments = [];
$unreadNotifications = [];

if ($db_connected) {
    try {
        if ($pdo) {
            $stmtApp = $pdo->prepare("SELECT * FROM appointments WHERE staff = :sname ORDER BY appointment_date DESC, appointment_time ASC");
            $stmtApp->execute([':sname' => $staffMember['name']]);
            $myAppointments = $stmtApp->fetchAll();
        } elseif ($mysqli) {
            $stmtApp = $mysqli->prepare("SELECT * FROM appointments WHERE staff = ? ORDER BY appointment_date DESC, appointment_time ASC");
            $stmtApp->bind_param("s", $staffMember['name']);
            $stmtApp->execute();
            $res = $stmtApp->get_result();
            $myAppointments = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        }
    } catch (Exception $e) {
        $error = "Database query error: " . $e->getMessage();
    }

    try {
        if ($pdo) {
            $stmtUn = $pdo->prepare("SELECT * FROM appointments WHERE staff = :sname AND is_read = 0 ORDER BY created_at DESC");
            $stmtUn->execute([':sname' => $staffMember['name']]);
            $unreadNotifications = $stmtUn->fetchAll();
        } elseif ($mysqli) {
            $stmtUn = $mysqli->prepare("SELECT * FROM appointments WHERE staff = ? AND is_read = 0 ORDER BY created_at DESC");
            $stmtUn->bind_param("s", $staffMember['name']);
            $stmtUn->execute();
            $resU = $stmtUn->get_result();
            $unreadNotifications = $resU ? $resU->fetch_all(MYSQLI_ASSOC) : [];
        }
    } catch (Exception $e) {
        // Safe fallback if is_read column missing or query fails
        $unreadNotifications = [];
    }
}

// Calculate Financial Metrics for Logged In Staff
$assignedJobsCount = count($myAppointments);
$completedJobsCount = 0;
$totalRevenueGenerated = 0.0;

foreach ($myAppointments as $app) {
    if (($app['status'] ?? '') === 'Completed') {
        $completedJobsCount++;
        $totalRevenueGenerated += (float)($app['price'] ?? 500.00);
    }
}

$baseSalary = (float)($staffMember['base_salary'] ?? 20000);
$commRate   = (float)($staffMember['commission_rate'] ?? 15);
$commissionEarned = ($totalRevenueGenerated * $commRate) / 100.0;
$totalPayout = $baseSalary + $commissionEarned;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Portal - <?= htmlspecialchars($staffMember['name']) ?></title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="fonts/font-awesome/css/font-awesome.css" rel="stylesheet">
    <style>
        body {
            background-color: #121212;
            color: #e0e0e0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 30px 0;
        }
        .header-title {
            color: #d4af37;
            font-weight: 700;
        }
        .profile-card {
            background-color: #1e1e1e;
            border: 1px solid #333;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
        }
        .stat-card {
            background-color: #1e1e1e;
            border: 1px solid #333;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
        }
        .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            color: #d4af37;
        }
        .stat-label {
            color: #888;
            font-size: 0.85rem;
            text-transform: uppercase;
        }
        .card {
            background-color: #1e1e1e;
            border: 1px solid #333;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        .card-header {
            background-color: #252525;
            border-bottom: 1px solid #333;
            color: #d4af37;
            font-weight: bold;
            font-size: 1.2rem;
        }
        .table-dark {
            --bs-table-bg: #1e1e1e;
            --bs-table-border-color: #333;
        }
        .badge-status {
            font-size: 0.85rem;
            padding: 5px 10px;
        }
        .status-select {
            background-color: #2b2b2b;
            color: #fff;
            border: 1px solid #444;
            font-size: 0.85rem;
            padding: 4px 8px;
            border-radius: 4px;
        }
        .form-control-dark {
            background-color: #2b2b2b;
            border: 1px solid #444;
            color: #fff;
        }
        .form-control-dark:focus {
            background-color: #333;
            border-color: #d4af37;
            color: #fff;
        }
    </style>
</head>
<body>
    <div class="container-fluid px-4">
        <!-- Top Navbar -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="header-title mb-0">ClassicCuts Staff Portal</h1>
                <small class="text-muted">Welcome, <?= htmlspecialchars($staffMember['name']) ?> (<?= htmlspecialchars($staffMember['role']) ?>)</small>
            </div>
            <div>
                <button class="btn btn-warning me-2" type="button" data-bs-toggle="modal" data-bs-target="#editProfileModal"><i class="fa fa-user-circle"></i> Edit My Profile</button>
                <a href="index.html" class="btn btn-outline-warning me-2">View Website</a>
                <a href="staff-dashboard.php?logout=1" class="btn btn-outline-danger"><i class="fa fa-power-off me-1"></i> Logout</a>
            </div>
        </div>

        <?php if (!empty($msg)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fa fa-check-circle me-2"></i> <?= htmlspecialchars($msg) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fa fa-exclamation-triangle me-2"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- UNREAD NOTIFICATIONS BANNER FOR STAFF -->
        <?php if (!empty($unreadNotifications)): ?>
            <div class="alert alert-warning alert-dismissible fade show d-flex justify-content-between align-items-center" role="alert">
                <div>
                    <strong>🔔 New Appointment Notifications (<?= count($unreadNotifications) ?>):</strong> You have new customer bookings assigned to you!
                    <ul class="mb-0 mt-1">
                        <?php foreach (array_slice($unreadNotifications, 0, 3) as $un): ?>
                            <li><strong><?= htmlspecialchars($un['name']) ?></strong> - <?= htmlspecialchars($un['services'] ?: 'Haircut') ?> on <?= htmlspecialchars($un['appointment_date']) ?> at <?= htmlspecialchars($un['appointment_time']) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <form method="POST" class="ms-3">
                    <input type="hidden" name="mark_all_read" value="1">
                    <button type="submit" class="btn btn-sm btn-dark">Mark Notifications Read</button>
                </form>
            </div>
        <?php endif; ?>

        <!-- Staff Profile & Payment Header -->
        <div class="profile-card shadow">
            <div class="row align-items-center">
                <div class="col-md-2 text-center mb-3 mb-md-0">
                    <img src="<?= htmlspecialchars(($staffMember['photo'] ?? '') ?: 'images/team/1.jpg') ?>" class="rounded-circle img-fluid border border-warning" style="width: 110px; height: 110px; object-fit: cover;" alt="">
                </div>
                <div class="col-md-5">
                    <div class="d-flex justify-content-between align-items-start">
                        <h3 class="mb-1 text-light"><?= htmlspecialchars($staffMember['name'] ?? '') ?></h3>
                        <button class="btn btn-sm btn-outline-warning" type="button" data-bs-toggle="modal" data-bs-target="#editProfileModal"><i class="fa fa-pencil"></i> Edit Profile</button>
                    </div>
                    <p class="text-warning mb-2"><strong>Role:</strong> <?= htmlspecialchars($staffMember['role'] ?? 'Barber') ?> | <strong>Experience:</strong> <?= (int)($staffMember['experience_years'] ?? 1) ?> Years</p>
                    <p class="mb-1"><small><strong>Email:</strong> <?= htmlspecialchars(($staffMember['email'] ?? '') ?: '-') ?> | <strong>Phone:</strong> <?= htmlspecialchars(($staffMember['phone'] ?? '') ?: '-') ?></small></p>
                    <p class="mb-1"><small><strong>eSewa ID:</strong> <span class="text-success"><?= htmlspecialchars(($staffMember['esewa_id'] ?? '') ?: 'Not Set') ?></span> | <strong>Bank:</strong> <?= htmlspecialchars(($staffMember['bank_name'] ?? '') ?: 'Not Set') ?> (<?= htmlspecialchars(($staffMember['bank_acc_num'] ?? '') ?: '-') ?>)</small></p>
                    <p class="mb-0 text-muted"><small><strong>Bio/Specialty:</strong> <?= htmlspecialchars(($staffMember['bio'] ?? '') ?: 'Professional Salon Specialist') ?></small></p>
                </div>
                <div class="col-md-5">
                    <div class="p-3 rounded bg-dark border border-secondary">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Base Monthly Salary:</span>
                            <strong>Rs. <?= number_format($baseSalary, 2) ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Service Commission (<?= number_format($commRate, 1) ?>%):</span>
                            <strong class="text-info">Rs. <?= number_format($commissionEarned, 2) ?></strong>
                        </div>
                        <hr class="my-2 border-secondary">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fs-6">Total Monthly Payout:</span>
                            <span class="fs-5 text-warning fw-bold">Rs. <?= number_format($totalPayout, 2) ?></span>
                        </div>
                        <div class="mt-2 text-end">
                            <span class="badge <?= (strpos(($staffMember['salary_status'] ?? ''), 'Paid') !== false) ? 'bg-success' : 'bg-danger' ?> fs-6">
                                Salary Status: <?= htmlspecialchars(($staffMember['salary_status'] ?? '') ?: 'Unpaid') ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Metric Stat Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-number"><?= $assignedJobsCount ?></div>
                    <div class="stat-label">Assigned Appointments</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-number text-info"><?= $completedJobsCount ?></div>
                    <div class="stat-label">Completed Services</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-number text-success">Rs. <?= number_format($totalRevenueGenerated, 2) ?></div>
                    <div class="stat-label">Total Revenue Earned</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-number text-warning">Rs. <?= number_format($commissionEarned, 2) ?></div>
                    <div class="stat-label">Commission Earned</div>
                </div>
            </div>
        </div>

        <!-- Assigned Appointments Table -->
        <div class="card shadow">
            <div class="card-header">
                📅 My Assigned Appointments (<?= count($myAppointments) ?>)
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-dark table-hover mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Appt. No</th>
                                <th>Customer Name</th>
                                <th>Phone</th>
                                <th>Services Booked</th>
                                <th>Date & Time</th>
                                <th>Service Fee</th>
                                <th>Status</th>
                                <th>Update Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($myAppointments)): ?>
                                <tr>
                                    <td colspan="9" class="text-center py-4 text-muted">No appointments assigned to you yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($myAppointments as $idx => $app): ?>
                                    <tr>
                                        <td><?= $idx + 1 ?></td>
                                        <td><span class="badge bg-secondary"><?= htmlspecialchars($app['appointment_no'] ?: ('APT-'.($app['id']+1000))) ?></span></td>
                                        <td>
                                            <strong><?= htmlspecialchars($app['name']) ?></strong><br>
                                            <small class="text-muted"><?= htmlspecialchars($app['email']) ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($app['phone']) ?></td>
                                        <td><span class="badge bg-warning text-dark"><?= htmlspecialchars($app['services'] ?: 'General Haircut') ?></span></td>
                                        <td>
                                            <strong><?= htmlspecialchars($app['appointment_date']) ?></strong><br>
                                            <small class="text-warning"><?= htmlspecialchars($app['appointment_time']) ?></small>
                                        </td>
                                        <td><strong>Rs. <?= number_format((float)($app['price'] ?? 500), 2) ?></strong></td>
                                        <td>
                                            <?php 
                                            $st = $app['status'] ?? 'Pending';
                                            $badgeClass = 'bg-warning text-dark';
                                            if ($st === 'Completed') $badgeClass = 'bg-success';
                                            elseif ($st === 'Confirmed') $badgeClass = 'bg-primary';
                                            elseif ($st === 'Cancelled') $badgeClass = 'bg-danger';
                                            ?>
                                            <span class="badge <?= $badgeClass ?> badge-status"><?= htmlspecialchars($st) ?></span>
                                        </td>
                                        <td>
                                            <form method="POST" class="d-flex align-items-center gap-2">
                                                <input type="hidden" name="update_appointment_status" value="1">
                                                <input type="hidden" name="appointment_id" value="<?= $app['id'] ?>">
                                                <select name="status" class="status-select" onchange="this.form.submit()">
                                                    <option value="Pending" <?= ($app['status'] ?? '') === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                                    <option value="Confirmed" <?= ($app['status'] ?? '') === 'Confirmed' ? 'selected' : '' ?>>Confirmed</option>
                                                    <option value="Completed" <?= ($app['status'] ?? '') === 'Completed' ? 'selected' : '' ?>>Completed</option>
                                                    <option value="Cancelled" <?= ($app['status'] ?? '') === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                                </select>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- STAFF EDIT PROFILE MODAL -->
    <div class="modal fade" id="editProfileModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content bg-dark text-light border-secondary">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title text-warning">Edit My Profile & Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="staff-dashboard.php" enctype="multipart/form-data">
                    <div class="modal-body row g-3">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="col-md-6">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="name" class="form-control form-control-dark" value="<?= htmlspecialchars($staffMember['name'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" class="form-control form-control-dark" value="<?= htmlspecialchars($staffMember['email'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Mobile Number</label>
                            <input type="text" name="phone" class="form-control form-control-dark" value="<?= htmlspecialchars($staffMember['phone'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">eSewa Mobile ID</label>
                            <input type="text" name="esewa_id" class="form-control form-control-dark" value="<?= htmlspecialchars($staffMember['esewa_id'] ?? '') ?>" placeholder="e.g. 9801111111">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Bank Name</label>
                            <input type="text" name="bank_name" class="form-control form-control-dark" value="<?= htmlspecialchars($staffMember['bank_name'] ?? '') ?>" placeholder="e.g. Nabil Bank">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Bank Account Number</label>
                            <input type="text" name="bank_acc_num" class="form-control form-control-dark" value="<?= htmlspecialchars($staffMember['bank_acc_num'] ?? '') ?>">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Bio / Specialty Notes</label>
                            <textarea name="bio" class="form-control form-control-dark" rows="3"><?= htmlspecialchars($staffMember['bio'] ?? '') ?></textarea>
                        </div>
                        <div class="col-md-12 border-top border-secondary pt-3 mt-3">
                            <label class="form-label text-warning fw-bold">📷 Profile Photo</label>
                            <div class="d-flex align-items-center gap-3 mb-2">
                                <img src="<?= htmlspecialchars(($staffMember['photo'] ?? '') ?: 'images/team/1.jpg') ?>" class="rounded-circle border border-warning" style="width: 50px; height: 50px; object-fit: cover;" alt="Staff Photo">
                                <input type="file" name="photo" class="form-control form-control-dark" accept="image/*">
                            </div>
                            <select name="photo_select" class="form-select form-select-sm form-control-dark">
                                <option value="">-- Keep Current Image OR Select Avatar Preset --</option>
                                <option value="images/team/1.jpg" <?= (($staffMember['photo'] ?? '') === 'images/team/1.jpg') ? 'selected' : '' ?>>Team Avatar 1 (images/team/1.jpg)</option>
                                <option value="images/team/2.jpg" <?= (($staffMember['photo'] ?? '') === 'images/team/2.jpg') ? 'selected' : '' ?>>Team Avatar 2 (images/team/2.jpg)</option>
                                <option value="images/team/3.jpg" <?= (($staffMember['photo'] ?? '') === 'images/team/3.jpg') ? 'selected' : '' ?>>Team Avatar 3 (images/team/3.jpg)</option>
                                <option value="images/team/4.jpg" <?= (($staffMember['photo'] ?? '') === 'images/team/4.jpg') ? 'selected' : '' ?>>Team Avatar 4 (images/team/4.jpg)</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">New Password (Leave blank to keep current password)</label>
                            <input type="password" name="new_password" class="form-control form-control-dark" placeholder="Enter new password if changing">
                        </div>
                    </div>
                    <div class="modal-footer border-secondary">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="action" value="update_profile" class="btn btn-warning">Save Profile Updates</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="js/bootstrap.bundle.min.js"></script>
</body>
</html>
