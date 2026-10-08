<?php
/**
 * ClassicCuts Barbershop - Form Submission & Live Availability Check Handler
 */

define('IS_API', true);
header('Content-Type: application/json');

require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'result' => 'error',
        'errors' => ['request' => 'Invalid request method. Only POST is allowed.']
    ]);
    exit;
}

if (!$db_connected) {
    echo json_encode([
        'result' => 'error',
        'errors' => ['database' => $db_error ?: 'Database connection error. Ensure MySQL is running.']
    ]);
    exit;
}

$errors = [];

function sanitize($data) {
    return htmlspecialchars(trim($data ?? ''), ENT_QUOTES, 'UTF-8');
}

$action = sanitize($_POST['action'] ?? '');

// HANDLE REVIEW SUBMISSION
if ($action === 'submit_review' || isset($_POST['rating'])) {
    $reviewerName = sanitize($_POST['customer_name'] ?? $_POST['Name'] ?? '');
    $rating       = (int)($_POST['rating'] ?? 5);
    $comment      = sanitize($_POST['comment'] ?? $_POST['message'] ?? '');

    if (empty($reviewerName)) {
        $errors['customer_name'] = 'Please enter your name.';
    }
    if (empty($comment)) {
        $errors['comment'] = 'Please write your review comment.';
    }

    if (!empty($errors)) {
        echo json_encode(['result' => 'error', 'errors' => $errors]);
        exit;
    }

    try {
        if ($pdo) {
            $stmt = $pdo->prepare("INSERT INTO reviews (customer_name, rating, comment) VALUES (:name, :rating, :comment)");
            $stmt->execute([':name' => $reviewerName, ':rating' => $rating, ':comment' => $comment]);
        } elseif ($mysqli) {
            $stmt = $mysqli->prepare("INSERT INTO reviews (customer_name, rating, comment) VALUES (?, ?, ?)");
            $stmt->bind_param("sis", $reviewerName, $rating, $comment);
            $stmt->execute();
        }
        echo json_encode([
            'result' => 'success',
            'message' => 'Thank you for your feedback! Your review has been submitted.'
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['result' => 'error', 'errors' => ['db' => $e->getMessage()]]);
        exit;
    }
}

// HANDLE APPOINTMENT / CONTACT SUBMISSION
$name    = sanitize($_POST['Name'] ?? $_POST['name'] ?? '');
$email   = sanitize($_POST['Email'] ?? $_POST['email'] ?? '');
$phone   = sanitize($_POST['phone'] ?? '');
$message = sanitize($_POST['message'] ?? '');

if (empty($name)) {
    $errors['Name'] = 'Please enter your name.';
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['Email'] = 'Please enter a valid email address.';
}

if (empty($phone)) {
    $errors['phone'] = 'Please enter your phone number.';
}

$servicesList = [];
foreach ($_POST as $key => $value) {
    if (strpos($key, 'Services') === 0 || $key === 'Services') {
        if (is_array($value)) {
            foreach ($value as $v) {
                $servicesList[] = sanitize($v);
            }
        } else {
            $servicesList[] = sanitize($value);
        }
    }
}

$staff  = sanitize($_POST['Staff'] ?? '');
$date   = sanitize($_POST['date'] ?? '');
$time   = sanitize($_POST['select_time'] ?? '');

$isBooking = !empty($date) || !empty($staff) || !empty($servicesList);

if ($isBooking) {
    if (empty($date)) {
        $errors['date'] = 'Please select an appointment date.';
    }
    if (empty($staff)) {
        $staff = 'Ram Bahadur'; // Default assigned barber
    }

    // LIVE BARBER AVAILABILITY CONFLICT CHECK
    if (!empty($date) && !empty($time) && !empty($staff)) {
        try {
            $alreadyBooked = 0;
            if ($pdo) {
                $stmtCheck = $pdo->prepare("
                    SELECT COUNT(*) FROM appointments 
                    WHERE staff = :staff AND appointment_date = :date AND appointment_time = :time 
                    AND status IN ('Pending', 'Confirmed')
                ");
                $stmtCheck->execute([':staff' => $staff, ':date' => $date, ':time' => $time]);
                $alreadyBooked = (int)$stmtCheck->fetchColumn();
            } elseif ($mysqli) {
                $stmtCheck = $mysqli->prepare("
                    SELECT COUNT(*) as cnt FROM appointments 
                    WHERE staff = ? AND appointment_date = ? AND appointment_time = ? 
                    AND status IN ('Pending', 'Confirmed')
                ");
                $stmtCheck->bind_param("sss", $staff, $date, $time);
                $stmtCheck->execute();
                $resC = $stmtCheck->get_result();
                $rowC = $resC ? $resC->fetch_assoc() : null;
                $alreadyBooked = (int)($rowC['cnt'] ?? 0);
            }

            if ($alreadyBooked > 0) {
                $errors['Staff'] = "Barber '{$staff}' is already booked for {$time} on {$date}. Please select another barber or choose a different time slot.";
            }
        } catch (Exception $e) {
            // Ignore if check fails
        }
    }
} else {
    if (empty($message)) {
        $errors['message'] = 'Please enter your message.';
    }
}

if (!empty($errors)) {
    echo json_encode([
        'result' => 'error',
        'errors' => $errors
    ]);
    exit;
}

try {
    if ($isBooking) {
        $servicesStr = !empty($servicesList) ? implode(', ', $servicesList) : 'General Haircut & Grooming';
        $apptNo = 'APT-' . rand(1000, 9999);

        // Service Catalog Price Table
        $servicePrices = [
            'Regular Haircut'            => 375.00,
            'Scissors Haircut'           => 400.00,
            'Kids Haircut'               => 350.00,
            'Head Shave'                 => 275.00,
            'Royal Shave'                => 330.00,
            'Royal Head Shave'           => 330.00,
            'Beard Trim No Shave'        => 350.00,
            'Beard Trim Shave'           => 350.00,
            'Beard Shave Up'             => 350.00,
            'Deep Pore Cleansing'        => 500.00,
            'Aromatherapy Facial'        => 450.00,
            'Acne Problem Facial'        => 600.00,
            'European Facial'            => 500.00,
            'Glycolic Peel Facial'       => 350.00,
            'Haircut + Shave'            => 500.00,
            'Haircut + Beard Trim'       => 500.00,
            'Haircut + Beard Trim Shave' => 550.00,
            'Haircut + Beard Shape Up'   => 600.00,
        ];

        $calculatedPrice = 0.0;
        foreach ($servicesList as $srv) {
            if (isset($servicePrices[$srv])) {
                $calculatedPrice += $servicePrices[$srv];
            }
        }
        if ($calculatedPrice <= 0) {
            $calculatedPrice = 375.00;
        }
        $price = $calculatedPrice;
        $paymentMethod = sanitize($_POST['payment_method'] ?? 'Cash');
        $txnId         = sanitize($_POST['txn_id'] ?? '');

        // Set initial payment status based on payment method chosen by customer
        if (stripos($paymentMethod, 'esewa') !== false) {
            $paymentStatus = 'Paid via eSewa';
        } elseif (stripos($paymentMethod, 'bank') !== false || stripos($paymentMethod, 'transfer') !== false) {
            $paymentStatus = 'Paid via Bank / Card';
        } else {
            // Cash → will be marked paid when admin/staff confirms
            $paymentStatus = 'Pending (Unpaid)';
        }

        if ($pdo) {
            $stmt = $pdo->prepare("
                INSERT INTO appointments (appointment_no, name, email, phone, services, staff, appointment_date, appointment_time, price, message, payment_method, txn_id, payment_status, status, is_read)
                VALUES (:appno, :name, :email, :phone, :services, :staff, :appointment_date, :appointment_time, :price, :message, :pmethod, :txnid, :pstatus, 'Pending', 0)
            ");
            $stmt->execute([
                ':appno'            => $apptNo,
                ':name'             => $name,
                ':email'            => $email,
                ':phone'            => $phone,
                ':services'         => $servicesStr,
                ':staff'            => $staff,
                ':appointment_date' => $date,
                ':appointment_time' => $time,
                ':price'            => $price,
                ':message'          => $message,
                ':pmethod'          => $paymentMethod,
                ':txnid'            => $txnId,
                ':pstatus'          => $paymentStatus
            ]);
        } elseif ($mysqli) {
            $stmt = $mysqli->prepare("
                INSERT INTO appointments (appointment_no, name, email, phone, services, staff, appointment_date, appointment_time, price, message, payment_method, txn_id, payment_status, status, is_read)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', 0)
            ");
            $stmt->bind_param("ssssssssdssss", $apptNo, $name, $email, $phone, $servicesStr, $staff, $date, $time, $price, $message, $paymentMethod, $txnId, $paymentStatus);
            $stmt->execute();
        }

        echo json_encode([
            'result' => 'success',
            'message' => "Thank you! Your appointment ({$apptNo}) with {$staff} has been booked successfully."
        ]);
    } else {
        if ($pdo) {
            $stmt = $pdo->prepare("
                INSERT INTO contacts (name, email, phone, message)
                VALUES (:name, :email, :phone, :message)
            ");
            $stmt->execute([
                ':name'    => $name,
                ':email'   => $email,
                ':phone'   => $phone,
                ':message' => $message
            ]);
        } elseif ($mysqli) {
            $stmt = $mysqli->prepare("
                INSERT INTO contacts (name, email, phone, message)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->bind_param("ssss", $name, $email, $phone, $message);
            $stmt->execute();
        }

        echo json_encode([
            'result' => 'success',
            'message' => 'Thank you! Your message has been sent successfully.'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'result' => 'error',
        'errors' => ['db' => 'Failed to save to database. Error: ' . $e->getMessage()]
    ]);
}
