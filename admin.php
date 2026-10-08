<?php
/**
 * ClassicCuts Barbershop - Admin Dashboard (BarberBaba Inspired UI & Staff Reassignment)
 */

session_start();

// Direct Logout Handler
if (isset($_GET['logout'])) {
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    @session_destroy();
    header("Location: login.php?logout=1");
    exit;
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php?tab=admin");
    exit;
}

require_once __DIR__ . '/config.php';

$activeTab = $_GET['tab'] ?? $_POST['tab'] ?? 'dashboard';
if (!in_array($activeTab, ['dashboard', 'appointments', 'staff', 'reviews', 'contacts'])) {
    $activeTab = 'dashboard';
}

$actionMsg = '';
$actionError = '';

// Handle Admin Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $db_connected) {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_status') {
        $id = (int)($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? 'Pending';
        if ($id > 0) {
            try {
                if ($status === 'Confirmed' || $status === 'Completed') {
                    $pMethod = 'Cash';
                    if ($pdo) {
                        $stM = $pdo->prepare("SELECT payment_method FROM appointments WHERE id = :id");
                        $stM->execute([':id' => $id]);
                        $pMethod = $stM->fetchColumn() ?: 'Cash';
                    } elseif ($mysqli) {
                        $stM = $mysqli->prepare("SELECT payment_method FROM appointments WHERE id = ?");
                        $stM->bind_param("i", $id);
                        $stM->execute();
                        $resM = $stM->get_result();
                        $rowM = $resM ? $resM->fetch_assoc() : null;
                        $pMethod = $rowM['payment_method'] ?? 'Cash';
                    }

                    $autoPayStatus = 'Paid via Cash';
                    if (stripos($pMethod, 'esewa') !== false) {
                        $autoPayStatus = 'Paid via eSewa';
                    } elseif (stripos($pMethod, 'bank') !== false || stripos($pMethod, 'card') !== false) {
                        $autoPayStatus = 'Paid via Bank / Card';
                    }

                    if ($pdo) {
                        $stmt = $pdo->prepare("UPDATE appointments SET status = :status, payment_status = :pstatus WHERE id = :id");
                        $stmt->execute([':status' => $status, ':pstatus' => $autoPayStatus, ':id' => $id]);
                    } elseif ($mysqli) {
                        $stmt = $mysqli->prepare("UPDATE appointments SET status = ?, payment_status = ? WHERE id = ?");
                        $stmt->bind_param("ssi", $status, $autoPayStatus, $id);
                        $stmt->execute();
                    }
                    $actionMsg = "Appointment #{$id} status updated to '{$status}' and payment status auto-updated to '{$autoPayStatus}'.";
                } else {
                    if ($pdo) {
                        $stmt = $pdo->prepare("UPDATE appointments SET status = :status WHERE id = :id");
                        $stmt->execute([':status' => $status, ':id' => $id]);
                    } elseif ($mysqli) {
                        $stmt = $mysqli->prepare("UPDATE appointments SET status = ? WHERE id = ?");
                        $stmt->bind_param("si", $status, $id);
                        $stmt->execute();
                    }
                    $actionMsg = "Appointment #{$id} status updated to '{$status}'.";
                }
            } catch (Exception $e) {
                $actionError = "Failed to update status: " . $e->getMessage();
            }
        }
    } elseif ($action === 'update_payment_status') {
        $id = (int)($_POST['id'] ?? 0);
        $payStatus = trim($_POST['payment_status'] ?? 'Pending (Unpaid)');
        if ($id > 0) {
            try {
                if ($pdo) {
                    $stmt = $pdo->prepare("UPDATE appointments SET payment_status = :pstatus WHERE id = :id");
                    $stmt->execute([':pstatus' => $payStatus, ':id' => $id]);
                } elseif ($mysqli) {
                    $stmt = $mysqli->prepare("UPDATE appointments SET payment_status = ? WHERE id = ?");
                    $stmt->bind_param("si", $payStatus, $id);
                    $stmt->execute();
                }
                $actionMsg = "Appointment #{$id} payment status updated to '{$payStatus}'.";
            } catch (Exception $e) {
                $actionError = "Failed to update payment status: " . $e->getMessage();
            }
        }
    } elseif ($action === 'reassign_staff') {
        $id = (int)($_POST['id'] ?? 0);
        $newStaff = trim($_POST['staff'] ?? '');
        if ($id > 0 && !empty($newStaff)) {
            try {
                if ($pdo) {
                    $stmt = $pdo->prepare("UPDATE appointments SET staff = :staff, is_read = 0 WHERE id = :id");
                    $stmt->execute([':staff' => $newStaff, ':id' => $id]);
                } elseif ($mysqli) {
                    $stmt = $mysqli->prepare("UPDATE appointments SET staff = ?, is_read = 0 WHERE id = ?");
                    $stmt->bind_param("si", $newStaff, $id);
                    $stmt->execute();
                }
                $actionMsg = "Appointment #{$id} reassigned to staff member '{$newStaff}'.";
            } catch (Exception $e) {
                $actionError = "Failed to reassign staff: " . $e->getMessage();
            }
        }
    } elseif ($action === 'delete_appointment') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                if ($pdo) {
                    $stmt = $pdo->prepare("DELETE FROM appointments WHERE id = :id");
                    $stmt->execute([':id' => $id]);
                } elseif ($mysqli) {
                    $stmt = $mysqli->prepare("DELETE FROM appointments WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                }
                $actionMsg = "Appointment #{$id} deleted successfully.";
            } catch (Exception $e) {
                $actionError = "Failed to delete appointment: " . $e->getMessage();
            }
        }
    } elseif ($action === 'add_staff') {
        $s_name  = trim($_POST['name'] ?? '');
        $s_email = trim($_POST['email'] ?? '');
        $s_phone = trim($_POST['phone'] ?? '');
        $s_role  = trim($_POST['role'] ?? 'Barber');
        $s_exp   = (int)($_POST['experience_years'] ?? 1);
        $s_base  = (float)($_POST['base_salary'] ?? 20000);
        $s_comm  = (float)($_POST['commission_rate'] ?? 15);
        $s_esewa = trim($_POST['esewa_id'] ?? '');
        $s_bank  = trim($_POST['bank_name'] ?? '');
        $s_acc   = trim($_POST['bank_acc_num'] ?? '');
        $s_bio   = trim($_POST['bio'] ?? '');
        if (empty($s_bio)) {
            $s_bio = "Specialist in {$s_role} with {$s_exp} years of precision styling experience.";
        }
        $defaultPass = password_hash('staff123', PASSWORD_DEFAULT);

        // Handle Image Upload or Selection
        $s_photo = 'images/team/1.jpg'; // Default fallback image
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
                    $s_photo = 'images/team/' . $newFileName;
                }
            }
        } elseif (!empty($_POST['photo_select'])) {
            $s_photo = trim($_POST['photo_select']);
        }

        if (!empty($s_name)) {
            try {
                if ($pdo) {
                    $stmt = $pdo->prepare("
                        INSERT INTO staff (name, email, phone, password, role, experience_years, base_salary, commission_rate, esewa_id, bank_name, bank_acc_num, photo, bio)
                        VALUES (:name, :email, :phone, :pass, :role, :exp, :base, :comm, :esewa, :bank, :acc, :photo, :bio)
                    ");
                    $stmt->execute([
                        ':name' => $s_name, ':email' => $s_email, ':phone' => $s_phone, ':pass' => $defaultPass,
                        ':role' => $s_role, ':exp' => $s_exp, ':base' => $s_base, ':comm' => $s_comm,
                        ':esewa' => $s_esewa, ':bank' => $s_bank, ':acc' => $s_acc, ':photo' => $s_photo, ':bio' => $s_bio
                    ]);
                } elseif ($mysqli) {
                    $stmt = $mysqli->prepare("
                        INSERT INTO staff (name, email, phone, password, role, experience_years, base_salary, commission_rate, esewa_id, bank_name, bank_acc_num, photo, bio)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->bind_param("sssssiddsssss", $s_name, $s_email, $s_phone, $defaultPass, $s_role, $s_exp, $s_base, $s_comm, $s_esewa, $s_bank, $s_acc, $s_photo, $s_bio);
                    $stmt->execute();
                }
                $actionMsg = "Staff member '{$s_name}' added successfully.";
            } catch (Exception $e) {
                $actionError = "Failed to add staff member: " . $e->getMessage();
            }
        }
    } elseif ($action === 'edit_staff') {
        $id      = (int)($_POST['id'] ?? 0);
        $s_name  = trim($_POST['name'] ?? '');
        $s_email = trim($_POST['email'] ?? '');
        $s_phone = trim($_POST['phone'] ?? '');
        $s_role  = trim($_POST['role'] ?? 'Barber');
        $s_exp   = (int)($_POST['experience_years'] ?? 1);
        $s_base  = (float)($_POST['base_salary'] ?? 20000);
        $s_comm  = (float)($_POST['commission_rate'] ?? 15);
        $s_esewa = trim($_POST['esewa_id'] ?? '');
        $s_bank  = trim($_POST['bank_name'] ?? '');
        $s_acc   = trim($_POST['bank_acc_num'] ?? '');
        $s_bio   = trim($_POST['bio'] ?? '');

        // Handle Image Upload or Selection
        $s_photo = '';
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
                    $s_photo = 'images/team/' . $newFileName;
                }
            }
        } elseif (!empty($_POST['photo_select'])) {
            $s_photo = trim($_POST['photo_select']);
        }

        if ($id > 0 && !empty($s_name)) {
            try {
                if (!empty($s_photo)) {
                    if ($pdo) {
                        $stmt = $pdo->prepare("
                            UPDATE staff SET name = :name, email = :email, phone = :phone, role = :role, 
                            experience_years = :exp, base_salary = :base, commission_rate = :comm, 
                            esewa_id = :esewa, bank_name = :bank, bank_acc_num = :acc, bio = :bio, photo = :photo WHERE id = :id
                        ");
                        $stmt->execute([
                            ':name' => $s_name, ':email' => $s_email, ':phone' => $s_phone, ':role' => $s_role,
                            ':exp' => $s_exp, ':base' => $s_base, ':comm' => $s_comm,
                            ':esewa' => $s_esewa, ':bank' => $s_bank, ':acc' => $s_acc, ':bio' => $s_bio, ':photo' => $s_photo, ':id' => $id
                        ]);
                    } elseif ($mysqli) {
                        $stmt = $mysqli->prepare("
                            UPDATE staff SET name=?, email=?, phone=?, role=?, experience_years=?, base_salary=?, commission_rate=?, esewa_id=?, bank_name=?, bank_acc_num=?, bio=?, photo=? WHERE id=?
                        ");
                        $stmt->bind_param("ssssiddsssssi", $s_name, $s_email, $s_phone, $s_role, $s_exp, $s_base, $s_comm, $s_esewa, $s_bank, $s_acc, $s_bio, $s_photo, $id);
                        $stmt->execute();
                    }
                } else {
                    if ($pdo) {
                        $stmt = $pdo->prepare("
                            UPDATE staff SET name = :name, email = :email, phone = :phone, role = :role, 
                            experience_years = :exp, base_salary = :base, commission_rate = :comm, 
                            esewa_id = :esewa, bank_name = :bank, bank_acc_num = :acc, bio = :bio WHERE id = :id
                        ");
                        $stmt->execute([
                            ':name' => $s_name, ':email' => $s_email, ':phone' => $s_phone, ':role' => $s_role,
                            ':exp' => $s_exp, ':base' => $s_base, ':comm' => $s_comm,
                            ':esewa' => $s_esewa, ':bank' => $s_bank, ':acc' => $s_acc, ':bio' => $s_bio, ':id' => $id
                        ]);
                    } elseif ($mysqli) {
                        $stmt = $mysqli->prepare("
                            UPDATE staff SET name=?, email=?, phone=?, role=?, experience_years=?, base_salary=?, commission_rate=?, esewa_id=?, bank_name=?, bank_acc_num=?, bio=? WHERE id=?
                        ");
                        $stmt->bind_param("ssssiddssssi", $s_name, $s_email, $s_phone, $s_role, $s_exp, $s_base, $s_comm, $s_esewa, $s_bank, $s_acc, $s_bio, $id);
                        $stmt->execute();
                    }
                }
                $actionMsg = "Staff member '{$s_name}' details updated successfully.";
            } catch (Exception $e) {
                $actionError = "Failed to update staff: " . $e->getMessage();
            }
        }
    } elseif ($action === 'pay_salary') {
        $id     = (int)($_POST['id'] ?? 0);
        $method = trim($_POST['pay_method'] ?? 'Paid via eSewa');
        if ($id > 0) {
            try {
                if ($pdo) {
                    $stmt = $pdo->prepare("UPDATE staff SET salary_status = :status WHERE id = :id");
                    $stmt->execute([':status' => $method, ':id' => $id]);
                } elseif ($mysqli) {
                    $stmt = $mysqli->prepare("UPDATE staff SET salary_status = ? WHERE id = ?");
                    $stmt->bind_param("si", $method, $id);
                    $stmt->execute();
                }
                $actionMsg = "Salary payment status updated to '{$method}'.";
            } catch (Exception $e) {
                $actionError = "Failed to process salary payment: " . $e->getMessage();
            }
        }
    } elseif ($action === 'delete_staff') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                if ($pdo) {
                    $stmt = $pdo->prepare("DELETE FROM staff WHERE id = :id");
                    $stmt->execute([':id' => $id]);
                } elseif ($mysqli) {
                    $stmt = $mysqli->prepare("DELETE FROM staff WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                }
                $actionMsg = "Staff member #{$id} removed.";
            } catch (Exception $e) {
                $actionError = "Failed to delete staff: " . $e->getMessage();
            }
        }
    } elseif ($action === 'approve_staff_registration') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                if ($pdo) {
                    $stmt = $pdo->prepare("UPDATE staff SET account_status = 'approved', approved_at = NOW() WHERE id = :id");
                    $stmt->execute([':id' => $id]);
                } elseif ($mysqli) {
                    $stmt = $mysqli->prepare("UPDATE staff SET account_status = 'approved', approved_at = NOW() WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                }
                $actionMsg = "Staff registration #{$id} approved. They can now login within 24 hours.";
            } catch (Exception $e) {
                $actionError = "Failed to approve staff: " . $e->getMessage();
            }
        }
    } elseif ($action === 'reject_staff_registration') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                if ($pdo) {
                    $stmt = $pdo->prepare("UPDATE staff SET account_status = 'rejected' WHERE id = :id");
                    $stmt->execute([':id' => $id]);
                } elseif ($mysqli) {
                    $stmt = $mysqli->prepare("UPDATE staff SET account_status = 'rejected' WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                }
                $actionMsg = "Staff registration #{$id} has been rejected.";
            } catch (Exception $e) {
                $actionError = "Failed to reject staff: " . $e->getMessage();
            }
        }
    } elseif ($action === 'delete_contact') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                if ($pdo) {
                    $stmt = $pdo->prepare("DELETE FROM contacts WHERE id = :id");
                    $stmt->execute([':id' => $id]);
                } elseif ($mysqli) {
                    $stmt = $mysqli->prepare("DELETE FROM contacts WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                }
                $actionMsg = "Contact message #{$id} deleted.";
            } catch (Exception $e) {
                $actionError = "Failed to delete message: " . $e->getMessage();
            }
        }
    } elseif ($action === 'delete_review') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                if ($pdo) {
                    $stmt = $pdo->prepare("DELETE FROM reviews WHERE id = :id");
                    $stmt->execute([':id' => $id]);
                } elseif ($mysqli) {
                    $stmt = $mysqli->prepare("DELETE FROM reviews WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                }
                $actionMsg = "Review #{$id} deleted.";
            } catch (Exception $e) {
                $actionError = "Failed to delete review: " . $e->getMessage();
            }
        }
    }
}

// Fetch Data
$appointments = [];
$contacts = [];
$staffList = [];
$reviewsList = [];

if ($db_connected) {
    try {
        if ($pdo) {
            $appointments = $pdo->query("SELECT * FROM appointments ORDER BY created_at DESC")->fetchAll();
            $contacts = $pdo->query("SELECT * FROM contacts ORDER BY created_at DESC")->fetchAll();
            $staffList = $pdo->query("SELECT * FROM staff WHERE account_status = 'approved' OR account_status IS NULL ORDER BY experience_years DESC")->fetchAll();
            $reviewsList = $pdo->query("SELECT * FROM reviews ORDER BY created_at DESC")->fetchAll();
        } elseif ($mysqli) {
            $resA = $mysqli->query("SELECT * FROM appointments ORDER BY created_at DESC");
            $appointments = $resA ? $resA->fetch_all(MYSQLI_ASSOC) : [];

            $resC = $mysqli->query("SELECT * FROM contacts ORDER BY created_at DESC");
            $contacts = $resC ? $resC->fetch_all(MYSQLI_ASSOC) : [];

            $resS = $mysqli->query("SELECT * FROM staff WHERE account_status = 'approved' OR account_status IS NULL ORDER BY experience_years DESC");
            $staffList = $resS ? $resS->fetch_all(MYSQLI_ASSOC) : [];

            $resR = $mysqli->query("SELECT * FROM reviews ORDER BY created_at DESC");
            $reviewsList = $resR ? $resR->fetch_all(MYSQLI_ASSOC) : [];
        }
    } catch (Exception $e) {
        $actionError = "Database fetch error: " . $e->getMessage();
    }
}

// Compute Metrics
$totalAppointmentsCount = count($appointments);
$totalCustomersCount    = count(array_unique(array_column($appointments, 'email')));
if ($totalCustomersCount == 0) $totalCustomersCount = $totalAppointmentsCount;

$acceptedAptCount = 0; // Confirmed + Completed
$rejectedAptCount = 0; // Cancelled
$totalSales = 0.0;
$todaySales = 0.0;
$todayDate  = date('Y-m-d');

foreach ($appointments as $app) {
    $st = $app['status'] ?? 'Pending';
    $pr = (float)($app['price'] ?? 500.00);

    if ($st === 'Confirmed' || $st === 'Completed') {
        $acceptedAptCount++;
        $totalSales += $pr;
        if (substr($app['created_at'] ?? '', 0, 10) === $todayDate || ($app['appointment_date'] ?? '') === $todayDate) {
            $todaySales += $pr;
        }
    } elseif ($st === 'Cancelled') {
        $rejectedAptCount++;
    }
}

// Staff Performance Calculations
$staffPerformance = [];
foreach ($staffList as $member) {
    $mName = $member['name'];
    $totalAssigned = 0;
    $completedCount = 0;
    $sRevenue = 0.0;

    foreach ($appointments as $app) {
        if (strcasecmp(trim($app['staff'] ?? ''), trim($mName)) === 0) {
            $totalAssigned++;
            if (($app['status'] ?? '') === 'Completed') {
                $completedCount++;
                $sRevenue += (float)($app['price'] ?? 500.00);
            }
        }
    }

    $commRate = (float)($member['commission_rate'] ?? 15);
    $commissionEarned = ($sRevenue * $commRate) / 100.0;
    $baseSalary = (float)($member['base_salary'] ?? 20000);
    $totalPayout = $baseSalary + $commissionEarned;

    $staffPerformance[$member['id']] = [
        'total_assigned' => $totalAssigned,
        'completed'      => $completedCount,
        'revenue'        => $sRevenue,
        'commission'     => $commissionEarned,
        'total_payout'   => $totalPayout
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BarberBaba Admin Dashboard - Salon Management System</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="fonts/font-awesome/css/font-awesome.css" rel="stylesheet">
    <style>
        :root {
            --sidebar-width: 250px;
        }
        body {
            background-color: #eef2f5;
            color: #333;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow-x: hidden;
        }
        .sidebar {
            width: var(--sidebar-width);
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            background-color: #ffffff;
            border-right: 1px solid #e0e5ec;
            z-index: 1000;
            overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0,0,0,0.03);
        }
        .sidebar-brand {
            padding: 20px 15px;
            font-size: 20px;
            font-weight: 800;
            color: #1e3246;
            border-bottom: 1px solid #eef2f5;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .sidebar-brand span {
            color: #d81b60;
        }
        .sidebar-menu {
            list-style: none;
            padding: 15px 0;
            margin: 0;
        }
        .sidebar-menu li a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 25px;
            color: #55606e;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.2s ease;
        }
        .sidebar-menu li a:hover, .sidebar-menu li a.active {
            background-color: #f0f4f9;
            color: #1e3246;
            border-left: 4px solid #1e3246;
        }
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 25px 30px;
        }
        .top-navbar {
            background-color: #ffffff;
            border-bottom: 1px solid #e0e5ec;
            padding: 12px 30px;
            margin-left: var(--sidebar-width);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .widget-card {
            border-radius: 12px;
            color: #ffffff;
            padding: 22px 25px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            transition: transform 0.2s ease;
            min-height: 110px;
        }
        .widget-card:hover {
            transform: translateY(-3px);
        }
        .widget-icon {
            font-size: 38px;
            opacity: 0.9;
        }
        .widget-info {
            text-align: right;
        }
        .widget-val {
            font-size: 26px;
            font-weight: 800;
            line-height: 1.1;
        }
        .widget-title {
            font-size: 13px;
            font-weight: 600;
            opacity: 0.9;
            margin-top: 4px;
        }
        .bg-card-customers { background-color: #1e3246; }
        .bg-card-appointments { background-color: #e64a58; }
        .bg-card-accepted { background-color: #d96b27; }
        .bg-card-rejected { background-color: #7aa343; }
        .bg-card-services { background-color: #d81b60; }
        .bg-card-today { background-color: #194a6b; }
        .bg-card-yesterday { background-color: #f5b025; }
        .bg-card-sevendays { background-color: #9b9b9b; }
        .bg-card-totalsales { background-color: #2e8b75; }

                .admin-tab-pane {
            display: none;
        }
        .admin-tab-pane.active {
            display: block;
        }

.panel-box {
            background: #ffffff;
            border-radius: 12px;
            border: 1px solid #e0e5ec;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
            margin-bottom: 30px;
            scroll-margin-top: 80px;
        }
        .panel-header {
            padding: 18px 25px;
            border-bottom: 1px solid #eef2f5;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .panel-title {
            font-size: 18px;
            font-weight: 700;
            color: #1e3246;
            margin: 0;
        }
        .dt-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 25px;
            background-color: #fafbfc;
            border-bottom: 1px solid #eef2f5;
        }
        .btn-dt {
            background-color: #5b6777;
            color: #ffffff;
            border: none;
            padding: 6px 14px;
            font-size: 13px;
            font-weight: 600;
            border-radius: 4px;
            margin-right: 4px;
        }
        .btn-dt:hover { background-color: #485260; color: #fff; }

        .table-custom {
            margin-bottom: 0;
        }
        .table-custom th {
            background-color: #f8fafc;
            color: #475569;
            font-weight: 700;
            font-size: 13px;
            text-transform: uppercase;
            border-bottom: 2px solid #e2e8f0;
            padding: 12px 16px;
        }
        .table-custom td {
            padding: 14px 16px;
            vertical-align: middle;
            font-size: 14px;
            border-bottom: 1px solid #e2e8f0;
        }
    </style>
</head>
<body>

    <!-- Sidebar Navigation Menu -->
    <div class="sidebar">
        <div class="sidebar-brand">
            <i class="fa fa-scissors text-warning"></i> Classic✂️<span>Cut</span>
        </div>
        <ul class="sidebar-menu" id="sidebarMenu">
            <li><a href="admin.php?tab=dashboard" data-tab="dashboard" class="nav-tab-link <?= $activeTab === 'dashboard' ? 'active' : '' ?>"><i class="fa fa-dashboard"></i> Dashboard</a></li>
            <li><a href="admin.php?tab=appointments" data-tab="appointments" class="nav-tab-link <?= $activeTab === 'appointments' ? 'active' : '' ?>"><i class="fa fa-calendar"></i> Appointments</a></li>
            <li><a href="admin.php?tab=staff" data-tab="staff" class="nav-tab-link <?= $activeTab === 'staff' ? 'active' : '' ?>"><i class="fa fa-users"></i> Staff & Nepalese Payroll</a></li>
            <li><a href="admin.php?tab=reviews" data-tab="reviews" class="nav-tab-link <?= $activeTab === 'reviews' ? 'active' : '' ?>"><i class="fa fa-star"></i> Reviews & Feedback</a></li>
            <li><a href="admin.php?tab=contacts" data-tab="contacts" class="nav-tab-link <?= $activeTab === 'contacts' ? 'active' : '' ?>"><i class="fa fa-envelope"></i> Contact Messages</a></li>
            <li><a href="login.php?logout=1" class="text-danger"><i class="fa fa-power-off"></i> Logout</a></li>
        </ul>
    </div>

    <!-- Top Bar -->
    <div class="top-navbar">
        <div>
            <h5 class="mb-0 text-dark font-weight-bold">ClassicCuts Salon Management System</h5>
            <small class="text-muted">Logged in as Administrator (<?= htmlspecialchars($_SESSION['admin_user'] ?? 'Admin') ?>)</small>
        </div>
        <div>
            
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="main-content">

        <?php if (!empty($actionMsg)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fa fa-check-circle me-2"></i> <?= htmlspecialchars($actionMsg) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($actionError) || !empty($db_error)): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fa fa-exclamation-triangle me-2"></i> <strong>Error:</strong> <?= htmlspecialchars($actionError ?: $db_error) ?>
            </div>
        <?php endif; ?>

        <div id="tab-dashboard" class="admin-tab-pane <?= $activeTab === 'dashboard' ? 'active' : '' ?>"><!-- SECTION: METRICS CARDS GRID -->
        <div id="dashboard" class="row g-3 mb-4">
            <div class="col-xl-3 col-md-4 col-sm-6">
                <div class="widget-card bg-card-customers">
                    <div class="widget-icon"><i class="fa fa-user"></i></div>
                    <div class="widget-info">
                        <div class="widget-val"><?= $totalCustomersCount ?></div>
                        <div class="widget-title">Total Customer</div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-4 col-sm-6">
                <div class="widget-card bg-card-appointments">
                    <div class="widget-icon"><i class="fa fa-list-alt"></i></div>
                    <div class="widget-info">
                        <div class="widget-val"><?= $totalAppointmentsCount ?></div>
                        <div class="widget-title">Total Appointment</div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-4 col-sm-6">
                <div class="widget-card bg-card-accepted">
                    <div class="widget-icon"><i class="fa fa-check"></i></div>
                    <div class="widget-info">
                        <div class="widget-val"><?= $acceptedAptCount ?></div>
                        <div class="widget-title">Total Accepted Apt</div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-4 col-sm-6">
                <div class="widget-card bg-card-rejected">
                    <div class="widget-icon"><i class="fa fa-file-text-o"></i></div>
                    <div class="widget-info">
                        <div class="widget-val"><?= $rejectedAptCount ?></div>
                        <div class="widget-title">Total Rejected Apt</div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-4 col-sm-6">
                <div class="widget-card bg-card-services">
                    <div class="widget-icon"><i class="fa fa-scissors"></i></div>
                    <div class="widget-info">
                        <div class="widget-val">22</div>
                        <div class="widget-title">Total Services</div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-4 col-sm-6">
                <div class="widget-card bg-card-today">
                    <div class="widget-icon"><i class="fa fa-tag"></i></div>
                    <div class="widget-info">
                        <div class="widget-val">Rs. <?= number_format($todaySales, 0) ?></div>
                        <div class="widget-title">Today Sales</div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-4 col-sm-6">
                <div class="widget-card bg-card-yesterday">
                    <div class="widget-icon"><i class="fa fa-credit-card"></i></div>
                    <div class="widget-info">
                        <div class="widget-val">Rs. 0</div>
                        <div class="widget-title">Yesterday Sales</div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-4 col-sm-6">
                <div class="widget-card bg-card-sevendays">
                    <div class="widget-icon"><i class="fa fa-files-o"></i></div>
                    <div class="widget-info">
                        <div class="widget-val">Rs. <?= number_format($totalSales, 0) ?></div>
                        <div class="widget-title">Last Sevendays Sale</div>
                    </div>
                </div>
            </div>

            <div class="col-xl-12 col-md-12">
                <div class="widget-card bg-card-totalsales">
                    <div class="widget-icon"><i class="fa fa-piggy-bank"></i></div>
                    <div class="widget-info">
                        <div class="widget-val">Rs. <?= number_format($totalSales, 2) ?> NPR</div>
                        <div class="widget-title">Total Sales (Nepalese Rupees)</div>
                    </div>
                </div>
            </div>
        </div>

        </div> <!-- /tab-dashboard -->

<div id="tab-appointments" class="admin-tab-pane <?= $activeTab === 'appointments' ? 'active' : '' ?>"><!-- SECTION: NEW APPOINTMENTS TABLE WITH STAFF REASSIGNMENT -->
        <div id="appointments-section" class="panel-box">
            <div class="panel-header">
                <h3 class="panel-title">New Appointments</h3>
                
            </div>

            <div class="dt-controls">
                <div>
                    <button type="button" class="btn-dt" onclick="copyTableToClipboard()"><i class="fa fa-copy me-1"></i> Copy</button>
                    <button type="button" class="btn-dt" onclick="exportTableToExcel()"><i class="fa fa-file-excel-o me-1"></i> Excel</button>
                    <button type="button" class="btn-dt" onclick="exportTableToPDF()"><i class="fa fa-file-pdf-o me-1"></i> PDF / Print</button>
                    <div class="dropdown d-inline-block">
                        <button type="button" class="btn-dt dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                            Column visibility ▾
                        </button>
                        <ul class="dropdown-menu dropdown-menu-dark p-2" id="colVisDropdown" style="min-width: 180px;">
                            <li><label class="dropdown-item"><input type="checkbox" checked onchange="toggleTableColumn(0)"> #</label></li>
                            <li><label class="dropdown-item"><input type="checkbox" checked onchange="toggleTableColumn(1)"> Appt Number</label></li>
                            <li><label class="dropdown-item"><input type="checkbox" checked onchange="toggleTableColumn(2)"> Name & Email</label></li>
                            <li><label class="dropdown-item"><input type="checkbox" checked onchange="toggleTableColumn(3)"> Mobile</label></li>
                            <li><label class="dropdown-item"><input type="checkbox" checked onchange="toggleTableColumn(4)"> Services & Price</label></li>
                            <li><label class="dropdown-item"><input type="checkbox" checked onchange="toggleTableColumn(5)"> Date</label></li>
                            <li><label class="dropdown-item"><input type="checkbox" checked onchange="toggleTableColumn(6)"> Time</label></li>
                            <li><label class="dropdown-item"><input type="checkbox" checked onchange="toggleTableColumn(7)"> Assigned Staff</label></li>
                            <li><label class="dropdown-item"><input type="checkbox" checked onchange="toggleTableColumn(8)"> Payment Status</label></li>
                            <li><label class="dropdown-item"><input type="checkbox" checked onchange="toggleTableColumn(9)"> Appt Status</label></li>
                            <li><label class="dropdown-item"><input type="checkbox" checked onchange="toggleTableColumn(10)"> Action</label></li>
                        </ul>
                    </div>
                </div>
                <div>
                    <span class="text-muted me-2">Search:</span>
                    <input type="text" id="tableSearch" onkeyup="filterAppointmentsTable()" class="form-control form-control-sm d-inline-block" style="width: 180px;">
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-custom align-middle" id="apptTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Appointment Number</th>
                            <th>Name</th>
                            <th>Mobile Number</th>
                            <th>Booked Services &amp; Price (NPR)</th>
                            <th>Appointment Date</th>
                            <th>Appointment Time</th>
                            <th>Assigned Staff (Reassign)</th>
                            <th>Payment Status</th>
                            <th>Appt Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($appointments)): ?>
                            <tr>
                                <td colspan="11" class="text-center py-4 text-muted">No appointments found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($appointments as $idx => $app): 
                                $pStatus = $app['payment_status'] ?? 'Pending (Unpaid)';
                                $appPrice = (float)($app['price'] ?? 500.00);
                            ?>
                                <tr>
                                    <td><?= $idx + 1 ?></td>
                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($app['appointment_no'] ?: ('APT-'.($app['id']+1000))) ?></span></td>
                                    <td>
                                        <strong><?= htmlspecialchars($app['name']) ?></strong><br>
                                        <small class="text-muted"><?= htmlspecialchars($app['email']) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($app['phone']) ?></td>
                                    <td>
                                        <div class="small fw-semibold"><?= htmlspecialchars($app['services'] ?: 'General Haircut & Grooming') ?></div>
                                        <span class="badge bg-success mt-1 fs-6">Rs. <?= number_format($appPrice, 2) ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($app['appointment_date']) ?></td>
                                    <td><span class="text-dark font-weight-bold"><?= htmlspecialchars($app['appointment_time']) ?></span></td>
                                    <td>
                                        <!-- ADMIN STAFF REASSIGNMENT SELECTOR -->
                                        <form method="POST" action="admin.php#appointments-section" class="d-inline"><input type="hidden" name="action" value="reassign_staff">
                                            <input type="hidden" name="id" value="<?= $app['id'] ?>">
                                            <select name="staff" class="form-select form-select-sm d-inline-block w-auto" onchange="this.form.submit()">
                                                <?php foreach ($staffList as $stItem): ?>
                                                    <option value="<?= htmlspecialchars($stItem['name']) ?>" <?= strcasecmp(trim($app['staff'] ?? ''), trim($stItem['name'])) === 0 ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($stItem['name']) ?> (<?= htmlspecialchars($stItem['role']) ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </form>
                                    </td>
                                    <td>
                                        <!-- INLINE PAYMENT STATUS SELECTOR -->
                                        <form method="POST" action="admin.php#appointments-section" class="d-inline"><input type="hidden" name="action" value="update_payment_status">
                                            <input type="hidden" name="id" value="<?= $app['id'] ?>">
                                            <select name="payment_status" class="form-select form-select-sm d-inline-block w-auto fw-bold <?= (strpos($pStatus, 'Paid') !== false) ? 'text-success border-success' : 'text-danger border-danger' ?>" onchange="this.form.submit()">
                                                <option value="Pending (Unpaid)" <?= ($pStatus === 'Pending (Unpaid)' || empty($pStatus)) ? 'selected' : '' ?>>🔴 Pending (Unpaid)</option>
                                                <option value="Paid via Cash" <?= ($pStatus === 'Paid via Cash') ? 'selected' : '' ?>>🟢 Paid via Cash</option>
                                                <option value="Paid via eSewa" <?= ($pStatus === 'Paid via eSewa') ? 'selected' : '' ?>>🟢 Paid via eSewa</option>
                                                <option value="Paid via Bank / Card" <?= ($pStatus === 'Paid via Bank / Card') ? 'selected' : '' ?>>🟢 Paid via Bank / Card</option>
                                                <option value="Refunded" <?= ($pStatus === 'Refunded') ? 'selected' : '' ?>>🟡 Refunded</option>
                                            </select>
                                        </form>
                                        <?php $payMeth = $app['payment_method'] ?? 'Cash'; $txnRef = $app['txn_id'] ?? ''; ?>
                                        <div class="mt-1">
                                            <span class="badge <?= (stripos($payMeth, 'esewa') !== false) ? 'bg-success' : ((stripos($payMeth, 'bank') !== false || stripos($payMeth, 'transfer') !== false) ? 'bg-info text-dark' : 'bg-secondary') ?>" style="font-size: 11px;">
                                                <?= htmlspecialchars($payMeth) ?>
                                            </span>
                                            <?php if (!empty($txnRef)): ?>
                                                <br><small class="text-muted" title="Transaction ID / Ref Code">🔖 <?= htmlspecialchars($txnRef) ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <form method="POST" action="admin.php#appointments-section" class="d-inline"><input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="id" value="<?= $app['id'] ?>">
                                            <select name="status" class="form-select form-select-sm d-inline-block w-auto" onchange="this.form.submit()">
                                                <option value="Pending" <?= ($app['status'] ?? '') === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                                <option value="Confirmed" <?= ($app['status'] ?? '') === 'Confirmed' ? 'selected' : '' ?>>Confirmed</option>
                                                <option value="Completed" <?= ($app['status'] ?? '') === 'Completed' ? 'selected' : '' ?>>Completed</option>
                                                <option value="Cancelled" <?= ($app['status'] ?? '') === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                            </select>
                                        </form>
                                    </td>
                                    <td>
                                        <form method="POST" action="admin.php#appointments-section" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this appointment?');"><input type="hidden" name="action" value="delete_appointment">
                                            <input type="hidden" name="id" value="<?= $app['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fa fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        </div> <!-- /tab-appointments -->

<div id="tab-staff" class="admin-tab-pane <?= $activeTab === 'staff' ? 'active' : '' ?>"><!-- SECTION: STAFF MANAGEMENT, PAYROLL & EDIT -->
        <div id="staff-section" class="panel-box">
            <div class="panel-header">
                <h3 class="panel-title">💈 Staff Management & Nepalese Payroll (eSewa / Bank)</h3>
                <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#addStaffCollapse">+ Add New Staff</button>
            </div>

            <?php
            // Fetch pending registrations
            $pendingStaff = [];
            if ($db_connected) {
                try {
                    if ($pdo) {
                        $stmtP = $pdo->query("SELECT * FROM staff WHERE account_status = 'pending' ORDER BY created_at DESC");
                        $pendingStaff = $stmtP ? $stmtP->fetchAll() : [];
                    } elseif ($mysqli) {
                        $resP = $mysqli->query("SELECT * FROM staff WHERE account_status = 'pending' ORDER BY created_at DESC");
                        $pendingStaff = $resP ? $resP->fetch_all(MYSQLI_ASSOC) : [];
                    }
                } catch (Exception $e) {}
            }
            if (!empty($pendingStaff)):
            ?>
            <div class="alert alert-warning m-3 border-warning" style="background: #fff8e1; border-left: 5px solid #d4af37;">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 class="mb-0 text-dark">🔔 Pending Staff Registrations (<?= count($pendingStaff) ?>)</h5>
                </div>
                <p class="text-muted mb-3" style="font-size:13px;">The following staff members have submitted registration requests and are awaiting your approval. Once approved, they will have <strong>24 hours</strong> to login to their dashboard.</p>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered bg-white mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>Photo</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Role</th>
                                <th>Exp.</th>
                                <th>Bio</th>
                                <th>Registered</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingStaff as $ps): ?>
                            <tr>
                                <td class="text-center">
                                    <img src="<?= htmlspecialchars($ps['photo'] ?: 'images/team/1.jpg') ?>" style="width:45px; height:45px; object-fit:cover; border-radius:50%; border:2px solid #d4af37;" alt="">
                                </td>
                                <td><strong><?= htmlspecialchars($ps['name']) ?></strong></td>
                                <td><small><?= htmlspecialchars($ps['email'] ?: '-') ?></small></td>
                                <td><?= htmlspecialchars($ps['phone'] ?: '-') ?></td>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars($ps['role'] ?: 'Barber') ?></span></td>
                                <td><?= (int)($ps['experience_years'] ?? 0) ?> yrs</td>
                                <td><small class="text-muted"><?= htmlspecialchars(mb_substr($ps['bio'] ?: '-', 0, 50)) ?><?= strlen($ps['bio'] ?? '') > 50 ? '...' : '' ?></small></td>
                                <td><small><?= date('M d, Y', strtotime($ps['created_at'])) ?></small></td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <form method="POST" action="admin.php#staff-section" class="d-inline"><input type="hidden" name="action" value="approve_staff_registration">
                                            <input type="hidden" name="id" value="<?= $ps['id'] ?>">
                                            <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Approve this staff registration?')">
                                                <i class="fa fa-check"></i> Approve
                                            </button>
                                        </form>
                                        <form method="POST" action="admin.php#staff-section" class="d-inline"><input type="hidden" name="action" value="reject_staff_registration">
                                            <input type="hidden" name="id" value="<?= $ps['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Reject this registration?')">
                                                <i class="fa fa-times"></i> Reject
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <div class="collapse p-4 border-bottom bg-light" id="addStaffCollapse">
                <h5 class="mb-3 text-dark">Add New Barber / Staff Member</h5>
                <form method="POST" action="admin.php#staff-section" enctype="multipart/form-data" class="row g-3">
                    <input type="hidden" name="action" value="add_staff">
                    <div class="col-md-3">
                        <label class="form-label text-dark fw-bold mb-1">Staff Name</label>
                        <input type="text" name="name" class="form-control" placeholder="Nepali Name (e.g. Ram Bahadur)" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-dark fw-bold mb-1">Email</label>
                        <input type="email" name="email" class="form-control" placeholder="Email Address">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label text-dark fw-bold mb-1">Mobile</label>
                        <input type="text" name="phone" class="form-control" placeholder="Mobile Number" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label text-dark fw-bold mb-1">Role</label>
                        <input type="text" name="role" class="form-control" placeholder="Role (e.g. Master Barber)" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label text-dark fw-bold mb-1">Exp (Years)</label>
                        <input type="number" name="experience_years" class="form-control" placeholder="Exp (Years)" value="1" min="0" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-dark fw-bold mb-1">Base Salary (Rs.)</label>
                        <input type="number" step="0.01" name="base_salary" class="form-control" placeholder="Base Salary (Rs.)" value="20000" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-dark fw-bold mb-1">Commission %</label>
                        <input type="number" step="0.01" name="commission_rate" class="form-control" placeholder="Commission %" value="15" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-dark fw-bold mb-1">eSewa Mobile ID</label>
                        <input type="text" name="esewa_id" class="form-control" placeholder="eSewa Mobile ID (e.g. 9801111111)">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-dark fw-bold mb-1">Bank Name</label>
                        <input type="text" name="bank_name" class="form-control" placeholder="Bank Name (e.g. Nabil Bank)">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-dark fw-bold mb-1">Bank Account</label>
                        <input type="text" name="bank_acc_num" class="form-control" placeholder="Bank Account Number">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-dark fw-bold mb-1">📷 Upload Photo / Avatar</label>
                        <input type="file" name="photo" class="form-control" accept="image/*">
                        <select name="photo_select" class="form-select form-select-sm mt-1">
                            <option value="">-- Or Select Avatar Preset --</option>
                            <option value="images/team/1.jpg">Team Avatar 1 (Ram Bahadur)</option>
                            <option value="images/team/2.jpg">Team Avatar 2 (Shyam Kumar)</option>
                            <option value="images/team/3.jpg">Team Avatar 3 (Ramesh Shrestha)</option>
                            <option value="images/team/4.jpg">Team Avatar 4 (Hari Sharma)</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-dark fw-bold mb-1">💈 Specialty Description (Visible on Home & Booking Pages)</label>
                        <input type="text" name="bio" class="form-control" placeholder="e.g. Expert in skin fades & razor beard shaping">
                    </div>
                    <div class="col-md-3 align-self-end ms-auto">
                        <button type="submit" class="btn btn-success w-100">+ Save Staff Member</button>
                    </div>
                </form>
            </div>

            <div class="table-responsive">
                <table class="table table-custom align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Staff Name</th>
                            <th>Role & Experience</th>
                            <th>Base Salary (NPR)</th>
                            <th>Commission %</th>
                            <th>Total Jobs</th>
                            <th>Monthly Payout (Rs.)</th>
                            <th>eSewa & Bank Account</th>
                            <th>Salary Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($staffList)): ?>
                            <tr>
                                <td colspan="10" class="text-center py-4 text-muted">No staff members found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($staffList as $sIdx => $s): 
                                $perf = $staffPerformance[$s['id']] ?? ['completed'=>0, 'total_payout'=>$s['base_salary']];
                            ?>
                                <tr>
                                    <td><?= $sIdx + 1 ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="<?= htmlspecialchars($s['photo'] ?: 'images/team/1.jpg') ?>" class="rounded-circle me-2" style="width: 40px; height: 40px; object-fit: cover;" alt="">
                                            <div>
                                                <strong><?= htmlspecialchars($s['name']) ?></strong><br>
                                                <small class="text-muted"><?= htmlspecialchars($s['phone'] ?: '-') ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?= htmlspecialchars($s['role']) ?></span><br>
                                        <small class="text-dark"><strong><?= (int)$s['experience_years'] ?> Yrs Exp.</strong></small>
                                    </td>
                                    <td><strong>Rs. <?= number_format((float)$s['base_salary'], 2) ?></strong></td>
                                    <td><?= number_format((float)$s['commission_rate'], 1) ?>%</td>
                                    <td><span class="badge bg-info text-dark"><?= $perf['completed'] ?> Completed</span></td>
                                    <td><span class="badge bg-success fs-6">Rs. <?= number_format($perf['total_payout'], 2) ?></span></td>
                                    <td>
                                        <small><strong>eSewa:</strong> <span class="text-success"><?= htmlspecialchars($s['esewa_id'] ?: '-') ?></span></small><br>
                                        <small><strong>Bank:</strong> <?= htmlspecialchars($s['bank_name'] ?: '-') ?> (<?= htmlspecialchars($s['bank_acc_num'] ?: '-') ?>)</small>
                                    </td>
                                    <td>
                                        <!-- INLINE SALARY STATUS SELECTOR -->
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="pay_salary">
                                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                            <select name="pay_method" class="form-select form-select-sm d-inline-block w-auto fw-bold <?= (strpos(($s['salary_status'] ?? ''), 'Paid') !== false) ? 'text-success border-success' : 'text-danger border-danger' ?>" onchange="this.form.submit()">
                                                <option value="Unpaid" <?= (empty($s['salary_status']) || $s['salary_status'] === 'Unpaid') ? 'selected' : '' ?>>Unpaid</option>
                                                <option value="Paid via eSewa" <?= ($s['salary_status'] === 'Paid via eSewa') ? 'selected' : '' ?>>Paid via eSewa</option>
                                                <option value="Paid via Bank" <?= ($s['salary_status'] === 'Paid via Bank') ? 'selected' : '' ?>>Paid via Bank</option>
                                                <option value="Paid via Cash" <?= ($s['salary_status'] === 'Paid via Cash') ? 'selected' : '' ?>>Paid via Cash</option>
                                            </select>
                                        </form>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-success" type="button" data-bs-toggle="modal" data-bs-target="#paySalaryModal<?= $s['id'] ?>">Pay Salary</button>
                                            <button class="btn btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#editStaffModal<?= $s['id'] ?>"><i class="fa fa-pencil"></i></button>
                                            <form method="POST" action="admin.php#staff-section" class="d-inline" onsubmit="return confirm('Are you sure you want to remove this staff member?');"><input type="hidden" name="action" value="delete_staff">
                                                <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                                <button type="submit" class="btn btn-outline-danger"><i class="fa fa-trash"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- STAFF MODALS OUTSIDE TABLE -->
            <?php foreach ($staffList as $s): 
                $perf = $staffPerformance[$s['id']] ?? ['completed'=>0, 'total_payout'=>$s['base_salary']];
            ?>
                <!-- PAY SALARY MODAL -->
                <div class="modal fade" id="paySalaryModal<?= $s['id'] ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Pay Salary - <?= htmlspecialchars($s['name']) ?></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form method="POST">
                                <div class="modal-body">
                                    <input type="hidden" name="action" value="pay_salary">
                                    <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                    <p class="mb-2"><strong>Staff Name:</strong> <?= htmlspecialchars($s['name']) ?></p>
                                    <p class="mb-3"><strong>Total Payout (NPR):</strong> <span class="text-success fw-bold fs-5">Rs. <?= number_format($perf['total_payout'], 2) ?></span></p>
                                    
                                    <div class="p-3 bg-light rounded mb-3 border">
                                        <h6 class="fw-bold mb-2">Payment Transfer Options:</h6>
                                        <p class="mb-1">💚 <strong>eSewa ID:</strong> <span class="text-success fw-bold"><?= htmlspecialchars($s['esewa_id'] ?: 'Not Provided') ?></span></p>
                                        <p class="mb-0">🏦 <strong>Bank:</strong> <?= htmlspecialchars($s['bank_name'] ?: 'Not Provided') ?> (A/C: <?= htmlspecialchars($s['bank_acc_num'] ?: '-') ?>)</p>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Select Payment Method</label>
                                        <select name="pay_method" class="form-select">
                                            <option value="Paid via eSewa" <?= ($s['salary_status'] === 'Paid via eSewa') ? 'selected' : '' ?>>Paid via eSewa Mobile Transfer</option>
                                            <option value="Paid via Bank" <?= ($s['salary_status'] === 'Paid via Bank') ? 'selected' : '' ?>>Paid via Direct Bank Transfer</option>
                                            <option value="Paid via Cash" <?= ($s['salary_status'] === 'Paid via Cash') ? 'selected' : '' ?>>Paid in Cash</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-success">Confirm Salary Payment</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- EDIT STAFF MODAL -->
                <div class="modal fade" id="editStaffModal<?= $s['id'] ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Edit Staff Member - <?= htmlspecialchars($s['name']) ?></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form method="POST" enctype="multipart/form-data">
                                <div class="modal-body row g-3">
                                    <input type="hidden" name="action" value="edit_staff">
                                    <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                    
                                    <div class="col-md-6">
                                        <label class="form-label">Nepali Name</label>
                                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($s['name']) ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Role</label>
                                        <input type="text" name="role" class="form-control" value="<?= htmlspecialchars($s['role']) ?>" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Phone</label>
                                        <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($s['phone']) ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Email</label>
                                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($s['email']) ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Experience (Years)</label>
                                        <input type="number" name="experience_years" class="form-control" value="<?= (int)$s['experience_years'] ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Base Monthly Salary (Rs.)</label>
                                        <input type="number" step="0.01" name="base_salary" class="form-control" value="<?= (float)$s['base_salary'] ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Commission Rate (%)</label>
                                        <input type="number" step="0.01" name="commission_rate" class="form-control" value="<?= (float)$s['commission_rate'] ?>" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">eSewa Mobile ID</label>
                                        <input type="text" name="esewa_id" class="form-control" value="<?= htmlspecialchars($s['esewa_id']) ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Bank Name</label>
                                        <input type="text" name="bank_name" class="form-control" value="<?= htmlspecialchars($s['bank_name']) ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Bank Account Number</label>
                                        <input type="text" name="bank_acc_num" class="form-control" value="<?= htmlspecialchars($s['bank_acc_num']) ?>">
                                    </div>
                                    <div class="col-md-12 border-top pt-3 mt-3">
                                        <label class="form-label fw-bold">💈 Specialty Description / Bio (Visible to Customers)</label>
                                        <textarea name="bio" class="form-control mb-3" rows="2" placeholder="e.g. Expert in skin fades & hair styling"><?= htmlspecialchars($s['bio'] ?? '') ?></textarea>
                                        <label class="form-label fw-bold">📷 Staff Profile Image / Photo</label>
                                        <div class="d-flex align-items-center gap-3 mb-2">
                                            <img src="<?= htmlspecialchars($s['photo'] ?: 'images/team/1.jpg') ?>" class="rounded-circle border" style="width: 50px; height: 50px; object-fit: cover;" alt="Staff Photo">
                                            <input type="file" name="photo" class="form-control" accept="image/*">
                                        </div>
                                        <select name="photo_select" class="form-select form-select-sm">
                                            <option value="">-- Keep Current Image OR Select Avatar Preset --</option>
                                            <option value="images/team/1.jpg" <?= ($s['photo'] === 'images/team/1.jpg') ? 'selected' : '' ?>>Team Avatar 1 (images/team/1.jpg)</option>
                                            <option value="images/team/2.jpg" <?= ($s['photo'] === 'images/team/2.jpg') ? 'selected' : '' ?>>Team Avatar 2 (images/team/2.jpg)</option>
                                            <option value="images/team/3.jpg" <?= ($s['photo'] === 'images/team/3.jpg') ? 'selected' : '' ?>>Team Avatar 3 (images/team/3.jpg)</option>
                                            <option value="images/team/4.jpg" <?= ($s['photo'] === 'images/team/4.jpg') ? 'selected' : '' ?>>Team Avatar 4 (images/team/4.jpg)</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">Save Changes</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        </div> <!-- /tab-staff -->

<div id="tab-reviews" class="admin-tab-pane <?= $activeTab === 'reviews' ? 'active' : '' ?>"><!-- SECTION: REVIEWS MODERATION -->
        <div id="reviews-section" class="panel-box">
            <div class="panel-header">
                <h3 class="panel-title">⭐ Customer Reviews & Feedback</h3>
            </div>
            <div class="table-responsive">
                <table class="table table-custom align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Customer Name</th>
                            <th>Rating</th>
                            <th>Review Comment</th>
                            <th>Submitted On</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reviewsList)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">No reviews submitted yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($reviewsList as $rIdx => $rev): ?>
                                <tr>
                                    <td><?= $rIdx + 1 ?></td>
                                    <td><strong><?= htmlspecialchars($rev['customer_name']) ?></strong></td>
                                    <td><span class="text-warning"><?= str_repeat('★', (int)($rev['rating'] ?: 5)) ?></span></td>
                                    <td>"<?= htmlspecialchars($rev['comment']) ?>"</td>
                                    <td><small class="text-muted"><?= htmlspecialchars($rev['created_at']) ?></small></td>
                                    <td>
                                        <form method="POST" action="admin.php#reviews-section" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this review?');"><input type="hidden" name="action" value="delete_review">
                                            <input type="hidden" name="id" value="<?= $rev['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fa fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        </div> <!-- /tab-reviews -->

<div id="tab-contacts" class="admin-tab-pane <?= $activeTab === 'contacts' ? 'active' : '' ?>"><!-- SECTION: CONTACT MESSAGES -->
        <div id="contacts-section" class="panel-box">
            <div class="panel-header">
                <h3 class="panel-title">✉️ Customer Contact Messages</h3>
            </div>
            <div class="table-responsive">
                <table class="table table-custom align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Mobile</th>
                            <th>Message</th>
                            <th>Received Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($contacts)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">No contact messages received.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($contacts as $cIdx => $con): ?>
                                <tr>
                                    <td><?= $cIdx + 1 ?></td>
                                    <td><strong><?= htmlspecialchars($con['name']) ?></strong></td>
                                    <td><?= htmlspecialchars($con['email']) ?></td>
                                    <td><?= htmlspecialchars($con['phone'] ?: '-') ?></td>
                                    <td><?= htmlspecialchars($con['message']) ?></td>
                                    <td><small class="text-muted"><?= htmlspecialchars($con['created_at']) ?></small></td>
                                    <td>
                                        <form method="POST" action="admin.php#contacts-section" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this message?');"><input type="hidden" name="action" value="delete_contact">
                                            <input type="hidden" name="id" value="<?= $con['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fa fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div> <!-- /tab-contacts -->

    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="js/bootstrap.bundle.min.js"></script>
    <script>
        function filterAppointmentsTable() {
            var input = document.getElementById("tableSearch");
            var filter = input.value.toLowerCase();
            var table = document.getElementById("apptTable");
            var tr = table.getElementsByTagName("tr");

            for (var i = 1; i < tr.length; i++) {
                var text = tr[i].textContent || tr[i].innerText;
                if (text.toLowerCase().indexOf(filter) > -1) {
                    tr[i].style.display = "";
                } else {
                    tr[i].style.display = "none";
                }
            }
        }

        // 1. EXCEL EXPORT FUNCTION
        function exportTableToExcel() {
            var table = document.getElementById("apptTable");
            if (!table) return;

            var html = "<table border='1'><thead><tr>";
            var headers = ["#", "Appointment Number", "Customer Name", "Email", "Mobile", "Booked Services", "Price (NPR)", "Date", "Time", "Assigned Staff", "Payment Status", "Appt Status"];
            headers.forEach(function(h) {
                html += "<th style='background-color: #d4af37; color: #000; font-weight: bold;'>" + h + "</th>";
            });
            html += "</tr></thead><tbody>";

            var trs = table.querySelectorAll("tbody tr");
            trs.forEach(function(tr, idx) {
                if (tr.style.display !== "none") {
                    var tds = tr.querySelectorAll("td");
                    if (tds.length >= 10) {
                        var num = (idx + 1);
                        var apptNo = tds[1].innerText.trim();
                        var nameEl = tds[2].querySelector("strong");
                        var name = nameEl ? nameEl.innerText.trim() : tds[2].innerText.trim();
                        var emailEl = tds[2].querySelector("small");
                        var email = emailEl ? emailEl.innerText.trim() : "";
                        var phone = tds[3].innerText.trim();
                        
                        var srvDiv = tds[4].querySelector("div");
                        var services = srvDiv ? srvDiv.innerText.trim() : tds[4].innerText.trim();
                        var priceSpan = tds[4].querySelector("span");
                        var price = priceSpan ? priceSpan.innerText.trim() : "Rs. 500.00";

                        var date = tds[5].innerText.trim();
                        var time = tds[6].innerText.trim();
                        var staffSel = tds[7].querySelector("select");
                        var staff = staffSel ? staffSel.value : tds[7].innerText.trim();
                        var paySel = tds[8].querySelector("select");
                        var paymentStatus = paySel ? paySel.value : tds[8].innerText.trim();
                        var statusSel = tds[9].querySelector("select");
                        var apptStatus = statusSel ? statusSel.value : tds[9].innerText.trim();

                        html += "<tr>";
                        html += "<td>" + num + "</td>";
                        html += "<td>" + apptNo + "</td>";
                        html += "<td>" + name + "</td>";
                        html += "<td>" + email + "</td>";
                        html += "<td>" + phone + "</td>";
                        html += "<td>" + services + "</td>";
                        html += "<td>" + price + "</td>";
                        html += "<td>" + date + "</td>";
                        html += "<td>" + time + "</td>";
                        html += "<td>" + staff + "</td>";
                        html += "<td>" + paymentStatus + "</td>";
                        html += "<td>" + apptStatus + "</td>";
                        html += "</tr>";
                    }
                }
            });

            html += "</tbody></table>";

            var blob = new Blob(['\ufeff' + html], { type: 'application/vnd.ms-excel' });
            var url = URL.createObjectURL(blob);
            var a = document.createElement("a");
            a.href = url;
            a.download = "ClassicCuts_Appointments_" + new Date().toISOString().slice(0, 10) + ".xls";
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }

        // 2. PDF / PRINT EXPORT FUNCTION
        function exportTableToPDF() {
            var table = document.getElementById("apptTable");
            if (!table) return;

            var printWin = window.open('', '', 'height=700,width=950');
            printWin.document.write('<html><head><title>Appointments Report - ClassicCuts</title>');
            printWin.document.write('<style>');
            printWin.document.write('body { font-family: Arial, sans-serif; padding: 20px; color: #333; }');
            printWin.document.write('h2 { color: #b8860b; text-align: center; margin-bottom: 5px; }');
            printWin.document.write('p { text-align: center; color: #666; margin-bottom: 20px; }');
            printWin.document.write('table { width: 100%; border-collapse: collapse; margin-top: 10px; }');
            printWin.document.write('th, td { border: 1px solid #ddd; padding: 7px; text-align: left; font-size: 12px; }');
            printWin.document.write('th { background-color: #222; color: #fff; }');
            printWin.document.write('tr:nth-child(even) { background-color: #f9f9f9; }');
            printWin.document.write('</style></head><body>');
            
            printWin.document.write('<h2>ClassicCuts Barbershop - Appointments Report</h2>');
            printWin.document.write('<p>Generated on: ' + new Date().toLocaleString() + '</p>');
            printWin.document.write('<table><thead><tr>');
            printWin.document.write('<th>#</th><th>Appt No</th><th>Name</th><th>Email</th><th>Mobile</th><th>Services</th><th>Price</th><th>Date</th><th>Time</th><th>Staff</th><th>Payment</th><th>Status</th>');
            printWin.document.write('</tr></thead><tbody>');

            var trs = table.querySelectorAll("tbody tr");
            trs.forEach(function(tr, idx) {
                if (tr.style.display !== "none") {
                    var tds = tr.querySelectorAll("td");
                    if (tds.length >= 10) {
                        var num = (idx + 1);
                        var apptNo = tds[1].innerText.trim();
                        var nameEl = tds[2].querySelector("strong");
                        var name = nameEl ? nameEl.innerText.trim() : tds[2].innerText.trim();
                        var emailEl = tds[2].querySelector("small");
                        var email = emailEl ? emailEl.innerText.trim() : "";
                        var phone = tds[3].innerText.trim();
                        
                        var srvDiv = tds[4].querySelector("div");
                        var services = srvDiv ? srvDiv.innerText.trim() : tds[4].innerText.trim();
                        var priceSpan = tds[4].querySelector("span");
                        var price = priceSpan ? priceSpan.innerText.trim() : "Rs. 500.00";

                        var date = tds[5].innerText.trim();
                        var time = tds[6].innerText.trim();
                        var staffSel = tds[7].querySelector("select");
                        var staff = staffSel ? staffSel.value : tds[7].innerText.trim();
                        var paySel = tds[8].querySelector("select");
                        var paymentStatus = paySel ? paySel.value : tds[8].innerText.trim();
                        var statusSel = tds[9].querySelector("select");
                        var apptStatus = statusSel ? statusSel.value : tds[9].innerText.trim();

                        printWin.document.write('<tr>');
                        printWin.document.write('<td>' + num + '</td>');
                        printWin.document.write('<td>' + apptNo + '</td>');
                        printWin.document.write('<td>' + name + '</td>');
                        printWin.document.write('<td>' + email + '</td>');
                        printWin.document.write('<td>' + phone + '</td>');
                        printWin.document.write('<td>' + services + '</td>');
                        printWin.document.write('<td>' + price + '</td>');
                        printWin.document.write('<td>' + date + '</td>');
                        printWin.document.write('<td>' + time + '</td>');
                        printWin.document.write('<td>' + staff + '</td>');
                        printWin.document.write('<td>' + paymentStatus + '</td>');
                        printWin.document.write('<td>' + apptStatus + '</td>');
                        printWin.document.write('</tr>');
                    }
                }
            });

            printWin.document.write('</tbody></table></body></html>');
            printWin.document.close();
            printWin.focus();
            setTimeout(function() { printWin.print(); }, 250);
        }

        // 3. COPY TABLE TO CLIPBOARD FUNCTION
        function copyTableToClipboard() {
            var table = document.getElementById("apptTable");
            if (!table) return;

            var textData = "NO\tAPPT_NO\tNAME\tEMAIL\tPHONE\tSERVICES\tPRICE\tDATE\tTIME\tSTAFF\tPAYMENT\tSTATUS\n";
            var trs = table.querySelectorAll("tbody tr");
            trs.forEach(function(tr, idx) {
                if (tr.style.display !== "none") {
                    var tds = tr.querySelectorAll("td");
                    if (tds.length >= 10) {
                        var num = (idx + 1);
                        var apptNo = tds[1].innerText.trim();
                        var nameEl = tds[2].querySelector("strong");
                        var name = nameEl ? nameEl.innerText.trim() : tds[2].innerText.trim();
                        var emailEl = tds[2].querySelector("small");
                        var email = emailEl ? emailEl.innerText.trim() : "";
                        var phone = tds[3].innerText.trim();
                        
                        var srvDiv = tds[4].querySelector("div");
                        var services = srvDiv ? srvDiv.innerText.trim() : tds[4].innerText.trim();
                        var priceSpan = tds[4].querySelector("span");
                        var price = priceSpan ? priceSpan.innerText.trim() : "Rs. 500.00";

                        var date = tds[5].innerText.trim();
                        var time = tds[6].innerText.trim();
                        var staffSel = tds[7].querySelector("select");
                        var staff = staffSel ? staffSel.value : tds[7].innerText.trim();
                        var paySel = tds[8].querySelector("select");
                        var paymentStatus = paySel ? paySel.value : tds[8].innerText.trim();
                        var statusSel = tds[9].querySelector("select");
                        var apptStatus = statusSel ? statusSel.value : tds[9].innerText.trim();

                        textData += num + "\t" + apptNo + "\t" + name + "\t" + email + "\t" + phone + "\t" + services + "\t" + price + "\t" + date + "\t" + time + "\t" + staff + "\t" + paymentStatus + "\t" + apptStatus + "\n";
                    }
                }
            });

            if (navigator.clipboard) {
                navigator.clipboard.writeText(textData).then(function() {
                    alert("Appointments list copied to clipboard!");
                }).catch(function(err) {
                    alert("Copy error: " + err);
                });
            } else {
                alert("Clipboard API not supported in this browser environment.");
            }
        }

        // 4. COLUMN VISIBILITY TOGGLE FUNCTION
        function toggleTableColumn(colIdx) {
            var table = document.getElementById("apptTable");
            if (!table) return;
            
            var trs = table.querySelectorAll("tr");
            trs.forEach(function(tr) {
                var cells = tr.children;
                if (cells[colIdx]) {
                    if (cells[colIdx].style.display === "none") {
                        cells[colIdx].style.display = "";
                    } else {
                        cells[colIdx].style.display = "none";
                    }
                }
            });
        }

        // 5. TAB SWITCHING FUNCTION
        function switchAdminTab(tabName) {
            if (!tabName) tabName = 'dashboard';
            
            var panes = document.querySelectorAll('.admin-tab-pane');
            panes.forEach(function(pane) {
                pane.style.display = 'none';
                pane.classList.remove('active');
            });

            var target = document.getElementById('tab-' + tabName);
            if (target) {
                target.style.display = 'block';
                target.classList.add('active');
            }

            var links = document.querySelectorAll('.nav-tab-link');
            links.forEach(function(link) {
                if (link.getAttribute('data-tab') === tabName) {
                    link.classList.add('active');
                } else {
                    link.classList.remove('active');
                }
            });

            if (window.history && window.history.replaceState) {
                window.history.replaceState(null, null, 'admin.php?tab=' + tabName);
            }
        }

        document.querySelectorAll('.nav-tab-link').forEach(function(link) {
            link.addEventListener('click', function(e) {
                var tab = this.getAttribute('data-tab');
                if (tab) {
                    e.preventDefault();
                    switchAdminTab(tab);
                }
            });
        });

        document.addEventListener('DOMContentLoaded', function() {
            var urlParams = new URLSearchParams(window.location.search);
            var tabParam = urlParams.get('tab');
            if (!tabParam && window.location.hash) {
                tabParam = window.location.hash.replace('#', '').replace('-section', '');
            }
            if (tabParam) {
                switchAdminTab(tabParam);
            }
        });
    </script>
</body>
</html>
