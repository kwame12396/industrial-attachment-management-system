<?php
// index.php - Landing page and login
require_once 'config/database.php';

if (isLoggedIn()) {
    switch($_SESSION['user_role']) {
        case 'coordinator':           redirect('modules/coordinator/dashboard.php'); break;
        case 'student':               redirect('modules/student/dashboard.php'); break;
        case 'industrial_supervisor': redirect('modules/industrial_supervisor/dashboard.php'); break;
        case 'university_supervisor': redirect('modules/university_supervisor/dashboard.php'); break;
    }
}

$error = '';
$attempts_left = MAX_LOGIN_ATTEMPTS;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Brute-force check
    $recent = getRecentLoginAttempts($email);
    if ($recent >= MAX_LOGIN_ATTEMPTS) {
        $error = "Account temporarily locked after too many failed attempts. Please wait " . LOCKOUT_MINUTES . " minutes or use Forgot Password.";
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            if ($user['is_locked']) {
                $error = "This account has been locked. Please contact the administrator.";
            } elseif (password_verify($password, $user['password'])) {
                clearLoginAttempts($email);
                logActivity('LOGIN', "User logged in as {$user['role']}", $user['id']);
                $_SESSION['user_id']         = $user['id'];
                $_SESSION['user_email']      = $user['email'];
                $_SESSION['user_role']       = $user['role'];
                $_SESSION['user_related_id'] = $user['related_id'];
                switch($user['role']) {
                    case 'coordinator':           redirect('modules/coordinator/dashboard.php'); break;
                    case 'student':               redirect('modules/student/dashboard.php'); break;
                    case 'industrial_supervisor': redirect('modules/industrial_supervisor/dashboard.php'); break;
                    case 'university_supervisor': redirect('modules/university_supervisor/dashboard.php'); break;
                }
            } else {
                recordFailedLogin($email);
                $remaining = MAX_LOGIN_ATTEMPTS - ($recent + 1);
                if ($remaining <= 0) {
                    $error = "Too many failed attempts. Account locked for " . LOCKOUT_MINUTES . " minutes.";
                } else {
                    $error = "Invalid password! You have $remaining attempt(s) remaining.";
                }
            }
        } else {
            recordFailedLogin($email);
            $error = "No account found with that email address.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IAMS - Industrial Attachment Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            background: url('assets/images/tech.jpg') no-repeat center center fixed;
            background-size: cover;
            position: relative;
        }
        body::before {
            content: '';
            position: fixed; top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.58);
            z-index: -1;
        }
        .login-wrapper {
            min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
            padding: 2rem;
        }
        .glass-card {
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(15px);
            border-radius: 32px;
            padding: 2.5rem;
            box-shadow: 0 25px 45px rgba(0,0,0,0.2);
            border: 1px solid rgba(255,255,255,0.2);
            max-width: 480px; width: 100%;
            transition: transform 0.3s ease;
        }
        .glass-card:hover { transform: translateY(-4px); }
        .logo-area { text-align: center; margin-bottom: 2rem; }
        .logo-icon { font-size: 4rem; color: white; text-shadow: 0 2px 10px rgba(0,0,0,0.2); }
        .logo-area h2 { color: white; font-weight: 700; margin-top: 0.5rem; letter-spacing: -0.5px; }
        .logo-area p  { color: rgba(255,255,255,0.8); font-size: 0.9rem; }
        .form-group { margin-bottom: 1.25rem; }
        .input-group-custom {
            background: rgba(255,255,255,0.15);
            border-radius: 16px; padding: 0.5rem 1rem;
            display: flex; align-items: center;
            border: 1px solid rgba(255,255,255,0.2);
            transition: all 0.3s;
        }
        .input-group-custom:focus-within {
            background: rgba(255,255,255,0.25);
            border-color: rgba(255,255,255,0.5);
            box-shadow: 0 0 0 3px rgba(255,255,255,0.1);
        }
        .input-group-custom i { color: rgba(255,255,255,0.7); font-size: 1.1rem; margin-right: 12px; flex-shrink: 0; }
        .input-group-custom input {
            background: transparent; border: none;
            color: white; font-size: 1rem; width: 100%; outline: none;
        }
        .input-group-custom input::placeholder { color: rgba(255,255,255,0.6); }
        .toggle-pw {
            background: none; border: none;
            color: rgba(255,255,255,0.6); cursor: pointer; padding: 0 0 0 8px;
            transition: color 0.2s;
        }
        .toggle-pw:hover { color: white; }
        .btn-login {
            background: linear-gradient(135deg, #4361ee, #3a0ca3);
            border: none; padding: 13px; border-radius: 40px;
            font-weight: 600; font-size: 1rem; width: 100%; color: white;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(0,0,0,0.25);
        }
        .btn-login:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.35); background: linear-gradient(135deg, #3a0ca3, #4361ee); }
        .register-links { text-align: center; margin-top: 1.25rem; }
        .register-links a { color: rgba(255,255,255,0.9); text-decoration: none; margin: 0 10px; font-size: 0.88rem; transition: color 0.2s; }
        .register-links a:hover { color: white; text-decoration: underline; }
        .forgot-link { text-align: center; margin-top: 0.75rem; }
        .forgot-link a { color: rgba(255,255,255,0.7); font-size: 0.85rem; text-decoration: none; }
        .forgot-link a:hover { color: white; text-decoration: underline; }
        .divider { display: flex; align-items: center; color: rgba(255,255,255,0.5); font-size: 0.8rem; margin: 1.25rem 0; }
        .divider-line { flex: 1; height: 1px; background: rgba(255,255,255,0.3); }
        .divider-text { padding: 0 10px; }
        .security-badge { display: flex; align-items: center; justify-content: center; gap: 6px; color: rgba(255,255,255,0.5); font-size: 0.75rem; margin-top: 1rem; }
        @media (max-width: 576px) { .glass-card { padding: 1.5rem; } }
    </style>
</head>
<body>
<div class="login-wrapper">
    <div class="glass-card">
        <div class="logo-area">
            <div class="logo-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="M13.5 6.94a1 1 0 0 0-.32-.74L7 .5L.82 6.2a1 1 0 0 0-.32.74v5.56a1 1 0 0 0 1 1h11a1 1 0 0 0 1-1zM7 13.5v-4"/></svg></div>
            <h2>Industrial Attachment<br>Management System</h2>
            <p>Department of Computer Science</p>
        </div>

        <?php if($error): ?>
            <div class="alert alert-danger text-center py-2 mb-3">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:0.5rem;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><circle cx="7" cy="7" r="6.5"/><path d="M7 3.5v3"/><circle cx="7" cy="9.5" r=".5"/></g></svg><?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success text-center py-2 mb-3">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:0.5rem;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="m.5 8.55l2.73 3.51a1 1 0 0 0 1.56.03L13.5 1.55"/></svg><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="loginForm">
            <div class="form-group">
                <div class="input-group-custom">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 9l-3 .54L5 6.5L10.73.79a1 1 0 0 1 1.42 0l1.06 1.06a1 1 0 0 1 0 1.42Z"/><path d="M12 9.5v3a1 1 0 0 1-1 1H1.5a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1h3"/></g></svg>
                    <input type="email" name="email" id="email" placeholder="Email Address" required
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
            </div>
            <div class="form-group">
                <div class="input-group-custom">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><rect width="13" height="7" x=".5" y="3.5" rx="1"/><circle cx="3.5" cy="7" r=".5"/><circle cx="6.5" cy="7" r=".5"/><path d="M9.5 8H11"/></g></svg>
                    <input type="password" name="password" id="password" placeholder="Password" required>
                    <button type="button" class="toggle-pw" onclick="togglePassword()" title="Show/hide password">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3.625c-4.187 0-5.945 3.766-5.945 3.844S2.813 11.312 7 11.312s5.945-3.765 5.945-3.843S11.187 3.625 7 3.625M2.169 5.813L.61 4.252m4.525-.354L4.5 1.843m7.331 3.97l1.559-1.56m-4.525-.355L9.5 1.843"/><path d="M5.306 7.081a1.738 1.738 0 1 0 3.388.776a1.738 1.738 0 1 0-3.388-.776"/></g></svg>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-login">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:0.5rem;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="M3.85.5L10 6.65a.48.48 0 0 1 0 .7L3.85 13.5"/></svg>Sign In
            </button>
        </form>

        <div class="forgot-link">
            <a href="forgot_password.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:0.25rem;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><circle cx="6.44" cy="11.33" r="2.17"/><path d="m8 9.8l3.86-3.86a.36.36 0 0 1 .51 0l1.13 1.15m-3.05.28l1.02 1.02M2 12.5h-.5a1 1 0 0 1-1-1v-10a1 1 0 0 1 1-1h11a1 1 0 0 1 1 1V4m-13-.5h13"/></g></svg>Forgot your password?</a>
        </div>

        <div class="divider">
            <div class="divider-line"></div>
            <span class="divider-text">New here?</span>
            <div class="divider-line"></div>
        </div>

        <div class="register-links">
            <a href="register_student.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:0.25rem;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="m7 1.367l6.5 2.817L7 7L.5 4.184z"/><path d="m3.45 5.469l.006 3.064S4.529 9.953 7 9.953s3.55-1.42 3.55-1.42l-.001-3.064m-8.854 5.132v-5.89m.001 8.282a1.196 1.196 0 1 0 0-2.392a1.196 1.196 0 0 0 0 2.392"/></g></svg>Student Registration</a>
            <a href="register_organization.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:0.25rem;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="M8.461 4.75V1.594c0-.56-.454-1.015-1.015-1.015h-4.79c-.562 0-1.016.454-1.016 1.015v11.827m-1.121 0h4.968M1.64 3.187H4.1M1.64 5.75h3.847m4.993 4.282a1.75 1.75 0 1 0 0-3.5a1.75 1.75 0 0 0 0 3.5m-3.001 3.389a3.04 3.04 0 0 1 .39-1.46a3.03 3.03 0 0 1 2.611-1.537a3.03 3.03 0 0 1 2.612 1.538c.25.445.385.947.39 1.459"/></svg>Organization Registration</a>
            <a href="register_industrial_supervisor.php"><i class="fas fa-user-tie me-1"></i>Supervisor Registration</a>
        </div>

        <div class="security-badge">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M7.36 13.4a1 1 0 0 1-.72 0v0A9.59 9.59 0 0 1 .5 4.46V1.54a1 1 0 0 1 1-1h11a1 1 0 0 1 1 1v2.92a9.59 9.59 0 0 1-6.14 8.94"/><path d="M9 7V5a2 2 0 1 0-4 0v2a2 2 0 1 0 4 0M3.5 6H5m4 0h1.5M5 5.5h4m-.187-1.312L10 3M8.813 7.813L10 9M5.188 7.813L4 9m1.188-4.812L4 3"/></g></svg>
            Secured · Max <?php echo MAX_LOGIN_ATTEMPTS; ?> login attempts · Auto-lockout
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePassword() {
    var input = document.getElementById('password');
    var icon  = document.getElementById('pwEyeIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}
</script>
</body>
</html>
