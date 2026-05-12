<?php
// forgot_password.php - Security-question based password reset
require_once 'config/database.php';

if (isLoggedIn()) { redirect('index.php'); }

$step   = $_SESSION['fp_step']   ?? 1;   // 1=enter email, 2=answer question, 3=new password
$fpUser = $_SESSION['fp_user_id'] ?? null;
$message = $error = '';

// ── Step 1: find account by email ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step1'])) {
    $email = trim($_POST['email'] ?? '');
    $stmt  = $conn->prepare("SELECT id, security_question FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $r = $stmt->get_result();
    if ($r->num_rows === 1) {
        $u = $r->fetch_assoc();
        if (empty($u['security_question'])) {
            $error = "No security question set for this account. Please contact the administrator.";
        } else {
            $_SESSION['fp_step']    = 2;
            $_SESSION['fp_user_id'] = $u['id'];
            $_SESSION['fp_email']   = $email;
            $_SESSION['fp_q']       = $u['security_question'];
            redirect('forgot_password.php');
        }
    } else {
        $error = "No account found with that email address.";
    }
}

// ── Step 2: verify security answer ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step2']) && $step == 2) {
    $answer = strtolower(trim($_POST['answer'] ?? ''));
    $stmt   = $conn->prepare("SELECT security_answer FROM users WHERE id = ?");
    $stmt->bind_param("i", $fpUser);
    $stmt->execute();
    $stored = $stmt->get_result()->fetch_assoc()['security_answer'] ?? '';
    if (strtolower($stored) === $answer) {
        $_SESSION['fp_step']     = 3;
        redirect('forgot_password.php');
    } else {
        $error = "Incorrect answer. Please try again.";
    }
}

// ── Step 3: set new password ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step3']) && $step == 3) {
    $pw1 = $_POST['password']  ?? '';
    $pw2 = $_POST['password2'] ?? '';
    $pwErrors = validatePassword($pw1);
    if (!empty($pwErrors)) {
        $error = implode(' ', $pwErrors);
    } elseif ($pw1 !== $pw2) {
        $error = "Passwords do not match.";
    } else {
        $hashed = password_hash($pw1, PASSWORD_DEFAULT);
        $stmt   = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed, $fpUser);
        $stmt->execute();
        // Clear any login lockout so the user can log in immediately
        clearLoginAttempts($_SESSION['fp_email'] ?? '');
        // Clean up session
        unset($_SESSION['fp_step'], $_SESSION['fp_user_id'], $_SESSION['fp_email'], $_SESSION['fp_q']);
        $_SESSION['success'] = "Password reset successful! Please log in with your new password.";
        logActivity('PASSWORD_RESET', "User reset password", $fpUser);
        redirect('index.php');
    }
}

// Cancel / back
if (isset($_GET['cancel'])) {
    unset($_SESSION['fp_step'], $_SESSION['fp_user_id'], $_SESSION['fp_email'], $_SESSION['fp_q']);
    redirect('index.php');
}

$step  = $_SESSION['fp_step'] ?? 1;
$fpQ   = $_SESSION['fp_q']    ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - IAMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family:'Inter',sans-serif; min-height:100vh;
            background:url('assets/images/tech.jpg') no-repeat center center fixed;
            background-size:cover; position:relative;
        }
        body::before { content:''; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:-1; }
        .wrapper { min-height:100vh; display:flex; align-items:center; justify-content:center; padding:2rem; }
        .glass-card {
            background:rgba(255,255,255,0.1); backdrop-filter:blur(15px);
            border-radius:28px; padding:2.5rem;
            border:1px solid rgba(255,255,255,0.2);
            max-width:460px; width:100%;
        }
        .card-title { color:white; font-weight:700; font-size:1.5rem; text-align:center; }
        .card-sub   { color:rgba(255,255,255,0.75); font-size:0.88rem; text-align:center; margin-bottom:1.5rem; }
        .step-bar { display:flex; justify-content:center; gap:8px; margin-bottom:1.75rem; }
        .step-dot {
            width:32px; height:32px; border-radius:50%;
            background:rgba(255,255,255,0.2); color:rgba(255,255,255,0.6);
            display:flex; align-items:center; justify-content:center;
            font-size:0.8rem; font-weight:600;
            border:2px solid rgba(255,255,255,0.2);
        }
        .step-dot.active  { background:#4361ee; color:white; border-color:#4361ee; }
        .step-dot.done    { background:#28a745; color:white; border-color:#28a745; }
        .step-line { width:40px; height:2px; background:rgba(255,255,255,0.2); margin-top:15px; }
        label { color:rgba(255,255,255,0.9); font-size:0.9rem; font-weight:500; margin-bottom:6px; display:block; }
        .form-control {
            background:rgba(255,255,255,0.15); border:1px solid rgba(255,255,255,0.25);
            border-radius:14px; padding:0.75rem 1rem; color:white; width:100%;
        }
        .form-control:focus { background:rgba(255,255,255,0.25); border-color:rgba(255,255,255,0.5); outline:none; color:white; box-shadow:none; }
        .form-control::placeholder { color:rgba(255,255,255,0.5); }
        .btn-primary-custom {
            background:linear-gradient(135deg,#4361ee,#3a0ca3); border:none;
            padding:12px; border-radius:40px; font-weight:600; width:100%; color:white;
            cursor:pointer; transition:0.3s; margin-top:1rem;
        }
        .btn-primary-custom:hover { transform:translateY(-2px); box-shadow:0 6px 20px rgba(0,0,0,0.3); }
        .back-link { display:block; text-align:center; color:rgba(255,255,255,0.7); font-size:0.85rem; margin-top:1rem; text-decoration:none; }
        .back-link:hover { color:white; text-decoration:underline; }
        .pw-strength { height:6px; border-radius:3px; margin-top:6px; transition:width 0.3s; }
        .strength-label { font-size:0.75rem; margin-top:4px; }
        .q-box { background:rgba(255,255,255,0.1); border-radius:12px; padding:12px 16px; color:rgba(255,255,255,0.9); margin-bottom:1rem; font-style:italic; }
    </style>
</head>
<body>
<div class="wrapper">
<div class="glass-card">
    <div class="card-title"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:0.5rem;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><circle cx="6.44" cy="11.33" r="2.17"/><path d="m8 9.8l3.86-3.86a.36.36 0 0 1 .51 0l1.13 1.15m-3.05.28l1.02 1.02M2 12.5h-.5a1 1 0 0 1-1-1v-10a1 1 0 0 1 1-1h11a1 1 0 0 1 1 1V4m-13-.5h13"/></g></svg>Reset Password</div>
    <div class="card-sub">Regain access to your IAMS account</div>

    <!-- Progress steps -->
    <div class="step-bar">
        <div class="step-dot <?php echo $step >= 1 ? ($step > 1 ? 'done' : 'active') : ''; ?>">
            <?php echo $step > 1 ? '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="m.5 8.55l2.73 3.51a1 1 0 0 0 1.56.03L13.5 1.55"/></svg>' : '1'; ?>
        </div>
        <div class="step-line"></div>
        <div class="step-dot <?php echo $step >= 2 ? ($step > 2 ? 'done' : 'active') : ''; ?>">
            <?php echo $step > 2 ? '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="m.5 8.55l2.73 3.51a1 1 0 0 0 1.56.03L13.5 1.55"/></svg>' : '2'; ?>
        </div>
        <div class="step-line"></div>
        <div class="step-dot <?php echo $step >= 3 ? 'active' : ''; ?>">3</div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger py-2 mb-3"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:0.5rem;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><circle cx="7" cy="7" r="6.5"/><path d="M7 3.5v3"/><circle cx="7" cy="9.5" r=".5"/></g></svg><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($step == 1): ?>
        <p style="color:rgba(255,255,255,0.8);font-size:0.88rem;margin-bottom:1rem;">Enter your registered email address and we will ask your security question.</p>
        <form method="POST">
            <div class="mb-3">
                <label>Email Address</label>
                <input type="email" name="email" class="form-control" placeholder="you@example.com" required>
            </div>
            <button type="submit" name="step1" class="btn-primary-custom"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:0.5rem;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="M3.85.5L10 6.65a.48.48 0 0 1 0 .7L3.85 13.5"/></svg>Continue</button>
        </form>

    <?php elseif ($step == 2): ?>
        <div class="q-box"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:0.5rem;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M7 13.5a6.5 6.5 0 1 0 0-13a6.5 6.5 0 0 0 0 13M5.5 10h3"/><path d="M7 10V6.5H6m1-2.25a.25.25 0 0 1 0-.5m0 .5a.25.25 0 0 0 0-.5"/></g></svg><?php echo htmlspecialchars($fpQ); ?></div>
        <form method="POST">
            <div class="mb-3">
                <label>Your Answer</label>
                <input type="text" name="answer" class="form-control" placeholder="Type your answer..." required>
            </div>
            <button type="submit" name="step2" class="btn-primary-custom"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:0.5rem;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="m.5 8.55l2.73 3.51a1 1 0 0 0 1.56.03L13.5 1.55"/></svg>Verify</button>
        </form>

    <?php elseif ($step == 3): ?>
        <p style="color:rgba(255,255,255,0.8);font-size:0.88rem;margin-bottom:1rem;">
            Identity verified! Choose a strong new password.
        </p>
        <form method="POST" onsubmit="return checkPwMatch()">
            <div class="mb-2">
                <label>New Password</label>
                <input type="password" name="password" id="newPw" class="form-control" placeholder="Min 8 chars, upper, number, symbol" required oninput="updateStrength(this.value)">
                <div class="progress mt-2" style="height:6px;">
                    <div class="progress-bar pw-strength" id="pwBar" style="width:0%"></div>
                </div>
                <div class="strength-label" id="pwLabel" style="color:rgba(255,255,255,0.88)"></div>
            </div>
            <div class="mb-3">
                <label>Confirm New Password</label>
                <input type="password" name="password2" id="newPw2" class="form-control" placeholder="Repeat new password" required>
                <div id="matchMsg" style="font-size:0.78rem;margin-top:4px;"></div>
            </div>
            <ul style="color:rgba(255,255,255,0.9);font-size:0.78rem;margin-bottom:0.5rem;padding-left:1.2rem;">
                <li>At least 8 characters</li>
                <li>Uppercase &amp; lowercase letters</li>
                <li>At least one number</li>
                <li>At least one special character (e.g. @, #, !)</li>
                <li>Not a common password</li>
            </ul>
            <button type="submit" name="step3" class="btn-primary-custom"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:0.5rem;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="m.5 8.55l2.73 3.51a1 1 0 0 0 1.56.03L13.5 1.55"/></svg>Save New Password</button>
        </form>
    <?php endif; ?>

    <a href="forgot_password.php?cancel=1" class="back-link"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:0.25rem;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="M3.85.5L10 6.65a.48.48 0 0 1 0 .7L3.85 13.5"/></svg>Back to Login</a>
</div>
</div>
<script>
function updateStrength(pw) {
    var score = 0;
    if (pw.length >= 8) score++;
    if (/[A-Z]/.test(pw)) score++;
    if (/[0-9]/.test(pw)) score++;
    if (/[^a-zA-Z0-9]/.test(pw)) score++;
    if (pw.length >= 12) score++;
    var bar = document.getElementById('pwBar');
    var lbl = document.getElementById('pwLabel');
    var colours = ['#dc3545','#fd7e14','#ffc107','#28a745','#20c997'];
    var labels  = ['Very Weak','Weak','Fair','Strong','Very Strong'];
    bar.style.width = (score * 20) + '%';
    bar.style.background = colours[score - 1] || '#dc3545';
    lbl.textContent = score > 0 ? labels[score - 1] : '';
    lbl.style.color = colours[score - 1] || '#dc3545';
}
function checkPwMatch() {
    var p1 = document.getElementById('newPw').value;
    var p2 = document.getElementById('newPw2').value;
    var msg = document.getElementById('matchMsg');
    if (p1 !== p2) {
        msg.textContent = 'Passwords do not match';
        msg.style.color = '#ff6b6b';
        return false;
    }
    return true;
}
document.getElementById('newPw2') && document.getElementById('newPw2').addEventListener('input', function() {
    var p1 = document.getElementById('newPw').value;
    var msg = document.getElementById('matchMsg');
    if (this.value === p1) {
        msg.textContent = 'Passwords match';
        msg.style.color = '#28a745';
    } else {
        msg.textContent = 'Passwords do not match';
        msg.style.color = '#ff6b6b';
    }
});
</script>
</body>
</html>
