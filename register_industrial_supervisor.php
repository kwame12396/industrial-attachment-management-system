<?php
// register_industrial_supervisor.php
// Dedicated registration portal for Industrial Supervisors.
// Supervisors register individually and choose which organisation they belong to.
require_once 'config/database.php';

$errors = [];

// Fetch existing organisations for the dropdown
$orgsResult = $conn->query("SELECT id, name, location FROM organizations ORDER BY name ASC");
$organizations = [];
while ($row = $orgsResult->fetch_assoc()) {
    $organizations[] = $row;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $org_id          = (int)($_POST['organization_id']    ?? 0);
    $full_name       = trim($_POST['full_name']            ?? '');
    $position        = trim($_POST['position']             ?? '');
    $phone           = trim($_POST['phone']                ?? '');
    $email           = trim($_POST['email']                ?? '');
    $password        = $_POST['password']                  ?? '';
    $password2       = $_POST['password2']                 ?? '';
    $sec_q           = trim($_POST['security_question']    ?? '');
    $sec_a           = strtolower(trim($_POST['security_answer'] ?? ''));

    // Validate
    if ($org_id < 1)
        $errors[] = "Please select an organisation.";
    if (empty($full_name))
        $errors[] = "Full name is required.";
    if (empty($position))
        $errors[] = "Your position/job title is required.";
    if (empty($phone))
        $errors[] = "Phone number is required.";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors[] = "A valid email address is required.";
    if (empty($sec_q) || empty($sec_a))
        $errors[] = "Please select and answer a security question.";

    $pwErrors = validatePassword($password);
    $errors   = array_merge($errors, $pwErrors);
    if ($password !== $password2)
        $errors[] = "Passwords do not match.";

    if (empty($errors)) {
        // Check email uniqueness
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0)
            $errors[] = "An account with this email already exists.";
    }

    if (empty($errors)) {
        // Verify the chosen org actually exists
        $orgCheck = $conn->query("SELECT id FROM organizations WHERE id=$org_id");
        if ($orgCheck->num_rows == 0)
            $errors[] = "The selected organisation does not exist.";
    }

    if (empty($errors)) {
        $hashedPw = password_hash($password, PASSWORD_DEFAULT);

        // Insert user account
        $stmt = $conn->prepare("INSERT INTO users (email, password, role, related_id, security_question, security_answer) VALUES (?, ?, 'industrial_supervisor', ?, ?, ?)");
        $stmt->bind_param("ssiss", $email, $hashedPw, $org_id, $sec_q, $sec_a);
        $stmt->execute();
        $new_user_id = $conn->insert_id;

        // Insert supervisor profile
        $stmt2 = $conn->prepare("INSERT INTO industrial_supervisors (user_id, organization_id, full_name, position, phone) VALUES (?, ?, ?, ?, ?)");
        $stmt2->bind_param("iisss", $new_user_id, $org_id, $full_name, $position, $phone);
        $stmt2->execute();

        logActivity('REGISTER', "New industrial supervisor registered: $full_name", $new_user_id);
        $_SESSION['success'] = "Registration successful! You can now log in with your credentials.";
        redirect('index.php');
    }
}

$securityQuestions = [
    "What is your mother's maiden name?",
    "What was the name of your first pet?",
    "What city were you born in?",
    "What is the name of your primary school?",
    "What was your childhood nickname?",
    "What is your favourite book?",
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Industrial Supervisor Registration - IAMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family:'Inter',sans-serif;
            background:url('assets/images/tech.jpg') no-repeat center center fixed;
            background-size:cover; min-height:100vh; padding:2rem 0;
        }
        body::before { content:''; position:fixed; inset:0; background:rgba(0,0,0,0.65); z-index:-1; }
        .glass-card {
            background:rgba(255,255,255,0.1); backdrop-filter:blur(15px);
            border-radius:28px; padding:2rem;
            border:1px solid rgba(255,255,255,0.2);
            max-width:680px; margin:0 auto;
        }
        .form-header { text-align:center; margin-bottom:1.75rem; }
        .form-header h2 { color:white; font-weight:700; }
        .form-header p  { color:rgba(255,255,255,0.8); }
        .form-label  { color:rgba(255,255,255,0.9); font-weight:500; font-size:0.9rem; }
        .form-control, .form-select {
            background:rgba(255,255,255,0.15); border:1px solid rgba(255,255,255,0.25);
            border-radius:14px; padding:0.7rem 1rem; color:white;
        }
        .form-control:focus, .form-select:focus {
            background:rgba(255,255,255,0.25); border-color:rgba(255,255,255,0.5);
            box-shadow:none; color:white;
        }
        .form-control::placeholder { color:rgba(255,255,255,0.55); }
        option { background:#1a1a2e; }
        .btn-register {
            background:linear-gradient(135deg,#064e3b,#065f46); border:none;
            padding:13px; border-radius:40px; font-weight:600;
            width:100%; color:white; transition:0.3s;
        }
        .btn-register:hover { transform:translateY(-2px); box-shadow:0 6px 20px rgba(0,0,0,0.3); }
        .section-title {
            color:white; font-size:1rem; font-weight:600;
            margin:1.5rem 0 1rem; border-left:4px solid #10b981; padding-left:12px;
        }
        a { color:rgba(255,255,255,0.9); }
        a:hover { color:white; text-decoration:underline; }
        .pw-strength { height:6px; border-radius:3px; margin-top:6px; transition:width 0.3s; }
        .strength-label { font-size:0.75rem; margin-top:4px; }
        .org-badge {
            display:inline-block; background:rgba(16,185,129,0.2);
            color:#6ee7b7; border-radius:8px; font-size:0.78rem;
            padding:2px 8px; margin-top:4px;
        }
    </style>
</head>
<body>
<div class="container py-4">
<div class="glass-card">
    <div class="form-header">
        <i class="fas fa-user-tie fa-3x" style="color:#10b981;"></i>
        <h2 class="mt-2">Industrial Supervisor Registration</h2>
        <p>Register your supervisor account and link it to your organisation</p>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <strong><i class="fas fa-exclamation-circle me-1"></i>Please fix the following:</strong>
            <ul class="mb-0 mt-1">
                <?php foreach ($errors as $e): ?>
                    <li><?php echo htmlspecialchars($e); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" novalidate>

        <!-- Organisation Selection -->
        <div class="section-title"><i class="fas fa-building me-2"></i>Your Organisation</div>
        <div class="mb-3">
            <label class="form-label">Select Organisation *</label>
            <?php if (empty($organizations)): ?>
                <div class="alert alert-warning">
                    No organisations are registered yet. Please ask your organisation to
                    <a href="register_organization.php">register first</a>.
                </div>
            <?php else: ?>
                <select name="organization_id" class="form-select" required>
                    <option value="">-- Choose your organisation --</option>
                    <?php foreach ($organizations as $org): ?>
                        <option value="<?php echo $org['id']; ?>"
                            <?php echo (($_POST['organization_id'] ?? '') == $org['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($org['name']); ?>
                            (<?php echo htmlspecialchars($org['location']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="org-badge mt-2"><i class="fas fa-info-circle me-1"></i>
                    Your organisation must already be registered. If not,
                    <a href="register_organization.php" style="color:#6ee7b7;">register it here</a> first.
                </div>
            <?php endif; ?>
        </div>

        <!-- Personal Info -->
        <div class="section-title"><i class="fas fa-id-card me-2"></i>Personal Information</div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Full Name *</label>
                <input type="text" name="full_name" class="form-control"
                    placeholder="e.g. John Mensah" required
                    value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Position / Job Title *</label>
                <input type="text" name="position" class="form-control"
                    placeholder="e.g. Senior Software Engineer" required
                    value="<?php echo htmlspecialchars($_POST['position'] ?? ''); ?>">
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label">Phone Number *</label>
            <input type="tel" name="phone" class="form-control"
                placeholder="e.g. +233 20 000 0000" required
                value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
        </div>

        <!-- Account Credentials -->
        <div class="section-title"><i class="fas fa-lock me-2"></i>Account Credentials</div>
        <div class="mb-3">
            <label class="form-label">Email Address *</label>
            <input type="email" name="email" class="form-control"
                placeholder="you@company.com" required
                value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Password *</label>
                <input type="password" name="password" id="pw" class="form-control"
                    placeholder="Min 8 chars, upper, number, symbol" required
                    oninput="updateStrength(this.value)">
                <div class="progress mt-2" style="height:6px;">
                    <div class="progress-bar pw-strength" id="pwBar" style="width:0%"></div>
                </div>
                <div class="strength-label" id="pwLabel" style="color:rgba(255,255,255,0.88)"></div>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Confirm Password *</label>
                <input type="password" name="password2" id="pw2" class="form-control"
                    placeholder="Repeat password" required>
                <div id="matchMsg" style="font-size:0.78rem;margin-top:4px;"></div>
            </div>
        </div>

        <!-- Security Question -->
        <div class="section-title"><i class="fas fa-shield-alt me-2"></i>Security Question</div>
        <div class="mb-3">
            <label class="form-label">Security Question *</label>
            <select name="security_question" class="form-select" required>
                <option value="">-- Select a question --</option>
                <?php foreach ($securityQuestions as $q): ?>
                    <option value="<?php echo htmlspecialchars($q); ?>"
                        <?php echo (($_POST['security_question'] ?? '') === $q) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($q); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-4">
            <label class="form-label">Your Answer *</label>
            <input type="text" name="security_answer" class="form-control"
                placeholder="Your answer (case-insensitive)" required
                value="<?php echo htmlspecialchars($_POST['security_answer'] ?? ''); ?>">
        </div>

        <button type="submit" class="btn-register">
            <i class="fas fa-user-plus me-2"></i>Register as Industrial Supervisor
        </button>
    </form>

    <p style="color:rgba(255,255,255,0.75);font-size:0.85rem;text-align:center;margin-top:1.25rem;">
        Already have an account? <a href="index.php">Log in here</a><br>
        Registering an organisation? <a href="register_organization.php">Organisation registration</a>
    </p>
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
    var bar    = document.getElementById('pwBar');
    var lbl    = document.getElementById('pwLabel');
    var colors = ['#dc3545','#fd7e14','#ffc107','#28a745','#20c997'];
    var labels = ['Very Weak','Weak','Fair','Strong','Very Strong'];
    bar.style.width      = (score * 20) + '%';
    bar.style.background = colors[score - 1] || '#dc3545';
    lbl.textContent      = score > 0 ? labels[score - 1] : '';
    lbl.style.color      = colors[score - 1] || '#dc3545';
}
document.getElementById('pw2').addEventListener('input', function() {
    var msg = document.getElementById('matchMsg');
    if (this.value === document.getElementById('pw').value) {
        msg.textContent = '✓ Passwords match';  msg.style.color = '#28a745';
    } else {
        msg.textContent = '✗ Passwords do not match'; msg.style.color = '#ff6b6b';
    }
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
