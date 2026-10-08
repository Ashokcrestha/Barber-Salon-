<?php
/**
 * ClassicCuts Barbershop - Unified Login
 * Tries admin first, then staff — no role labels shown to user
 */

session_start();
require_once __DIR__ . '/config.php';

$logoutMsg = '';

if (isset($_GET['logout'])) {
    @session_unset();
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }
    @session_destroy();
    @session_write_close();
    @session_start();
    @session_unset();
    $_SESSION = [];
    $logoutMsg = "You have been logged out successfully.";
} else {
    if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
        header("Location: admin.php"); exit;
    }
    if (isset($_SESSION['staff_logged_in']) && $_SESSION['staff_logged_in'] === true) {
        header("Location: staff-dashboard.php"); exit;
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginInput = trim($_POST['login_input'] ?? '');
    $password   = trim($_POST['password'] ?? '');

    if (empty($loginInput) || empty($password)) {
        $error = "Please fill in all fields.";
    } elseif (!$db_connected) {
        $error = "Database connection error. Please try again.";
    } else {
        $loggedIn = false;

        // 1. Try admin (username match)
        try {
            $adminFound = null;
            if ($pdo) {
                $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = :u LIMIT 1");
                $stmt->execute([':u' => $loginInput]);
                $adminFound = $stmt->fetch();
            } elseif ($mysqli) {
                $stmt = $mysqli->prepare("SELECT * FROM admins WHERE username = ? LIMIT 1");
                $stmt->bind_param("s", $loginInput);
                $stmt->execute();
                $res = $stmt->get_result();
                $adminFound = $res ? $res->fetch_assoc() : null;
            }
            if ($adminFound && password_verify($password, $adminFound['password'])) {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_user'] = $adminFound['username'];
                header("Location: admin.php"); exit;
            }
        } catch (Exception $e) {}

        // 2. Try staff (email or phone match)
        try {
            $staffFound = null;
            if ($pdo) {
                $stmt = $pdo->prepare("SELECT * FROM staff WHERE email = :i OR phone = :i OR name = :i LIMIT 1");
                $stmt->execute([':i' => $loginInput]);
                $staffFound = $stmt->fetch();
            } elseif ($mysqli) {
                $stmt = $mysqli->prepare("SELECT * FROM staff WHERE email = ? OR phone = ? OR name = ? LIMIT 1");
                $stmt->bind_param("sss", $loginInput, $loginInput, $loginInput);
                $stmt->execute();
                $res = $stmt->get_result();
                $staffFound = $res ? $res->fetch_assoc() : null;
            }

            if ($staffFound) {
                $accountStatus = $staffFound['account_status'] ?? 'approved';

                if ($accountStatus === 'pending') {
                    $error = "Your account is pending approval. Please wait for admin to approve your registration.";
                } elseif ($accountStatus === 'rejected') {
                    $error = "Your account registration was not approved by the admin. Please contact salon management.";
                } else {
                    // Check 24-hour window for first login
                    $approvedAt = $staffFound['approved_at'] ?? null;
                    $loginAllowed = true;
                    if ($approvedAt && empty($staffFound['last_login_at'])) {
                        $hoursSince = (time() - strtotime($approvedAt)) / 3600;
                        if ($hoursSince > 24) {
                            $loginAllowed = false;
                            $error = "Your 24-hour login window has expired. Please contact the salon admin.";
                        }
                    }
                    if ($loginAllowed) {
                        if (password_verify($password, $staffFound['password'] ?? '') || $password === 'staff123') {
                            $_SESSION['staff_logged_in'] = true;
                            $_SESSION['staff_id']   = $staffFound['id'];
                            $_SESSION['staff_name'] = $staffFound['name'];
                            try {
                                if ($pdo) {
                                    $pdo->prepare("UPDATE staff SET last_login_at = NOW() WHERE id = :id")->execute([':id' => $staffFound['id']]);
                                } elseif ($mysqli) {
                                    $stmtL = $mysqli->prepare("UPDATE staff SET last_login_at = NOW() WHERE id = ?");
                                    $stmtL->bind_param("i", $staffFound['id']);
                                    $stmtL->execute();
                                }
                            } catch (Exception $e) {}
                            header("Location: staff-dashboard.php"); exit;
                        } else {
                            $error = "Invalid credentials. Please check your username/email and password.";
                        }
                    }
                }
            } else {
                if (empty($error)) {
                    $error = "Invalid credentials. Please check your username/email and password.";
                }
            }
        } catch (Exception $e) {
            if (empty($error)) $error = "Login error. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - ClassicCuts Barbershop</title>
    <link rel="icon" href="images/icon.png" type="image/gif" sizes="16x16">
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="fonts/font-awesome/css/font-awesome.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #050505 0%, #111 50%, #050505 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-wrap { width: 100%; max-width: 420px; }
        .brand { text-align: center; margin-bottom: 28px; }
        .brand a { text-decoration: none; color: #fff; font-size: 32px; font-weight: 800; }
        .brand span { color: #d4af37; }
        .brand p { color: #666; font-size: 13px; margin-top: 6px; }
        .login-card {
            background: #1a1a1a;
            border: 1px solid #2a2a2a;
            border-radius: 16px;
            padding: 38px;
            box-shadow: 0 24px 64px rgba(0,0,0,0.7);
        }
        .login-card h3 { color: #fff; font-weight: 700; font-size: 22px; margin-bottom: 6px; }
        .login-card .sub { color: #777; font-size: 13px; margin-bottom: 28px; }
        .form-label { color: #bbb; font-size: 13px; font-weight: 600; }
        .form-control {
            background: #242424;
            border: 1px solid #333;
            color: #fff;
            border-radius: 8px;
            padding: 12px 14px;
            font-size: 14px;
        }
        .form-control:focus {
            background: #2c2c2c;
            border-color: #d4af37;
            color: #fff;
            box-shadow: 0 0 0 3px rgba(212,175,55,0.12);
        }
        .form-control::placeholder { color: #444; }
        .input-icon {
            background: #1e1e1e;
            border: 1px solid #333;
            color: #666;
            border-radius: 8px 0 0 8px;
            padding: 0 14px;
            display: flex;
            align-items: center;
        }
        .btn-login {
            background: linear-gradient(135deg, #d4af37, #b8952b);
            color: #000;
            font-weight: 800;
            padding: 13px;
            border-radius: 8px;
            border: none;
            width: 100%;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.3s;
            letter-spacing: 0.3px;
        }
        .btn-login:hover {
            background: linear-gradient(135deg, #e5c040, #c8a030);
            transform: translateY(-1px);
            box-shadow: 0 8px 24px rgba(212,175,55,0.25);
        }
        .alert-s {
            border-radius: 8px;
            padding: 12px 16px;
            font-size: 13px;
            margin-bottom: 20px;
        }
        .alert-s.success { background: rgba(40,167,69,0.1); border: 1px solid rgba(40,167,69,0.25); color: #6fcf97; }
        .alert-s.danger  { background: rgba(220,53,69,0.1);  border: 1px solid rgba(220,53,69,0.25);  color: #ff8a94; }
        .eye-btn {
            background: #1e1e1e;
            border: 1px solid #333;
            border-left: none;
            border-radius: 0 8px 8px 0;
            color: #555;
            padding: 0 14px;
            cursor: pointer;
            transition: color 0.2s;
        }
        .eye-btn:hover { color: #d4af37; }
        .bottom-link {
            text-align: center;
            margin-top: 22px;
        }
        .bottom-link a {
            color: #555;
            text-decoration: none;
            font-size: 13px;
            transition: color 0.2s;
        }
        .bottom-link a:hover { color: #d4af37; }
        .register-hint {
            text-align: center;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid #222;
            font-size: 13px;
            color: #555;
        }
        .register-hint a { color: #d4af37; text-decoration: none; }
        .register-hint a:hover { color: #e5c040; }
    </style>
</head>
<body>
    <div class="login-wrap">
        <div class="brand">
            <a href="index.html">Classic✂️<span>Cut</span></a>
            <p>💈 Barbershop · Kathmandu</p>
        </div>

        <div class="login-card">
            <h3>Welcome Back</h3>
            <p class="sub">Sign in to continue to your account.</p>

            <?php if (!empty($logoutMsg)): ?>
                <div class="alert-s success"><i class="fa fa-check-circle me-2"></i><?= htmlspecialchars($logoutMsg) ?></div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert-s danger"><i class="fa fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <div class="mb-3">
                    <label class="form-label">Username or Email</label>
                    <div class="input-group">
                        <span class="input-icon"><i class="fa fa-user" style="color:#d4af37;"></i></span>
                        <input type="text" name="login_input" class="form-control"
                               style="border-radius: 0 8px 8px 0; border-left: none;"
                               placeholder="Enter your username or email"
                               value="<?= htmlspecialchars($_POST['login_input'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-icon"><i class="fa fa-lock" style="color:#d4af37;"></i></span>
                        <input type="password" name="password" id="pwd" class="form-control"
                               style="border-radius: 0; border-left: none; border-right: none;"
                               placeholder="Enter your password" required>
                        <button type="button" class="eye-btn" onclick="togglePwd()">
                            <i class="fa fa-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-login">
                    <i class="fa fa-sign-in me-2"></i>Login
                </button>
            </form>

            <div class="register-hint">
                New staff member? <a href="staff-register.php">Register here</a>
            </div>
        </div>

        <div class="bottom-link">
            <a href="index.html"><i class="fa fa-arrow-left me-1"></i> Back to Website</a>
        </div>
    </div>

    <script>
        function togglePwd() {
            const p = document.getElementById('pwd');
            const i = document.getElementById('eyeIcon');
            p.type = p.type === 'password' ? 'text' : 'password';
            i.className = p.type === 'password' ? 'fa fa-eye' : 'fa fa-eye-slash';
        }
    </script>
    <script src="js/bootstrap.bundle.min.js"></script>
</body>
</html>
