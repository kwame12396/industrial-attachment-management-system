<?php
// register_student.php
require_once 'config/database.php';

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email          = trim($_POST['email'] ?? '');
    $password       = $_POST['password']  ?? '';
    $password2      = $_POST['password2'] ?? '';
    $full_name      = trim($_POST['full_name']    ?? '');
    $student_id     = strtoupper(trim($_POST['student_id'] ?? ''));
    $program        = trim($_POST['program']       ?? '');
    $year_of_study  = (int)($_POST['year_of_study'] ?? 0);
    $phone          = trim($_POST['phone']          ?? '');
    $pref_location  = trim($_POST['preferred_location'] ?? '');
    $pref_type      = trim($_POST['preferred_project_type'] ?? '');
    $skills         = trim($_POST['skills']         ?? '');
    $sec_q          = trim($_POST['security_question'] ?? '');
    $sec_a          = strtolower(trim($_POST['security_answer'] ?? ''));

    // ── Validation ────────────────────────────────────────────────────────
    if (empty($full_name))   $errors[] = "Full name is required.";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors[] = "A valid email address is required.";
    if (empty($phone) || !preg_match('/^[0-9+\s\-]{7,20}$/', $phone))
        $errors[] = "A valid phone number is required.";

    // Student ID
    $sidErr = validateStudentId($student_id);
    if ($sidErr) $errors[] = $sidErr;

    // Password
    $pwErrors = validatePassword($password);
    $errors = array_merge($errors, $pwErrors);
    if ($password !== $password2) $errors[] = "Passwords do not match.";

    // Security question
    if (empty($sec_q) || empty($sec_a))
        $errors[] = "Please select and answer a security question (used for password reset).";

    // DB uniqueness
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) $errors[] = "Email is already registered.";

        $stmt2 = $conn->prepare("SELECT id FROM students WHERE student_id = ?");
        $stmt2->bind_param("s", $student_id);
        $stmt2->execute();
        if ($stmt2->get_result()->num_rows > 0) $errors[] = "Student ID is already registered.";
    }

    if (empty($errors)) {
        $hashedPw = password_hash($password, PASSWORD_DEFAULT);
        $loc = ($pref_location === 'Other') ? trim($_POST['preferred_location_other'] ?? '') : $pref_location;
        $loc = $conn->real_escape_string($loc);
        $pref_type = ($pref_type === 'Other') ? trim($_POST['preferred_project_type_other'] ?? '') : $pref_type;

        $stmt = $conn->prepare("INSERT INTO users (email, password, role, security_question, security_answer) VALUES (?, ?, 'student', ?, ?)");
        $stmt->bind_param("ssss", $email, $hashedPw, $sec_q, $sec_a);
        $stmt->execute();
        $user_id = $conn->insert_id;

        $stmt2 = $conn->prepare("INSERT INTO students (user_id, student_id, full_name, program, year_of_study, phone, preferred_location, preferred_project_type, skills) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt2->bind_param("isssissss", $user_id, $student_id, $full_name, $program, $year_of_study, $phone, $loc, $pref_type, $skills);
        $stmt2->execute();

        logActivity('REGISTER', "New student registered: $full_name ($student_id)", $user_id);
        $_SESSION['success'] = "Registration successful! Please log in.";
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
    <title>Student Registration - IAMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Inter',sans-serif; background:url('assets/images/tech.jpg') no-repeat center center fixed; background-size:cover; min-height:100vh; padding:2rem 0; }
        body::before { content:''; position:fixed; inset:0; background:rgba(0,0,0,0.65); z-index:-1; }
        .glass-card { background:rgba(255,255,255,0.1); backdrop-filter:blur(15px); border-radius:28px; padding:2rem; border:1px solid rgba(255,255,255,0.2); max-width:900px; margin:0 auto; }
        .form-header { text-align:center; margin-bottom:1.75rem; }
        .form-header h2 { color:white; font-weight:700; }
        .form-header p { color:rgba(255,255,255,0.8); }
        .form-label { color:rgba(255,255,255,0.9); font-weight:500; margin-bottom:0.4rem; font-size:0.9rem; }
        .form-control, .form-select { background:rgba(255,255,255,0.15); border:1px solid rgba(255,255,255,0.25); border-radius:14px; padding:0.7rem 1rem; color:white; }
        .form-control:focus, .form-select:focus { background:rgba(255,255,255,0.25); border-color:rgba(255,255,255,0.5); box-shadow:none; color:white; }
        .form-control::placeholder { color:rgba(255,255,255,0.55); }
        .form-control.is-invalid { border-color:#ff6b6b; }
        .form-control.is-valid   { border-color:#28a745; }
        option { background:#1a1a2e; }
        .btn-register { background:linear-gradient(135deg,#4361ee,#3a0ca3); border:none; padding:13px; border-radius:40px; font-weight:600; width:100%; color:white; transition:0.3s; }
        .btn-register:hover { transform:translateY(-2px); background:linear-gradient(135deg,#3a0ca3,#4361ee); }
        .section-title { color:white; font-size:1rem; font-weight:600; margin:1.5rem 0 1rem; border-left:4px solid #4361ee; padding-left:12px; }
        a { color:rgba(255,255,255,0.9); text-decoration:none; }
        a:hover { color:white; text-decoration:underline; }
        .pw-req { background:rgba(0,0,0,0.25); border-radius:12px; padding:12px 16px; margin-top:8px; }
        .pw-req li { font-size:0.78rem; color:rgba(255,255,255,0.88); list-style:none; margin-bottom:3px; }
        .pw-req li.ok  { color:#28a745; }
        .pw-req li.bad { color:#ff6b6b; }
        .pw-strength-bar { height:6px; border-radius:3px; transition:width 0.4s,background 0.4s; }
        .sid-hint { font-size:0.76rem; color:rgba(255,255,255,0.55); margin-top:4px; }
        .match-msg { font-size:0.76rem; margin-top:4px; }
    </style>
</head>
<body>
<div class="container">
<div class="glass-card">
    <div class="form-header">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="m7 1.367l6.5 2.817L7 7L.5 4.184z"/><path d="m3.45 5.469l.006 3.064S4.529 9.953 7 9.953s3.55-1.42 3.55-1.42l-.001-3.064m-8.854 5.132v-5.89m.001 8.282a1.196 1.196 0 1 0 0-2.392a1.196 1.196 0 0 0 0 2.392"/></g></svg>
        <h2>Student Registration</h2>
        <p>Register for Industrial Attachment 2026</p>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <strong><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:0.5rem;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><circle cx="7" cy="7" r="6.5"/><path d="M7 3.5v3"/><circle cx="7" cy="9.5" r=".5"/></g></svg>Please fix the following:</strong>
            <ul class="mb-0 mt-1">
                <?php foreach ($errors as $e): ?>
                    <li><?php echo htmlspecialchars($e); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" onsubmit="return validateForm()">
        <div class="section-title"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:0.5rem;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M13.18 13.5a6.49 6.49 0 0 0-12.36 0z"/><path d="M7 9A4.232 4.232 0 1 0 7 .536A4.232 4.232 0 0 0 7 9"/><path d="M8.382 6.405s-.351.691-1.382.691s-1.382-.69-1.382-.69m5.537-2.444h-.028a4.12 4.12 0 0 1-3.09-1.392a4.12 4.12 0 0 1-3.091 1.392a4.1 4.1 0 0 1-1.973-.5a4.234 4.234 0 0 1 8.182.5"/></g></svg>Personal Information</div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Full Name *</label>
                <input type="text" name="full_name" class="form-control" required value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Student ID * <small style="color:rgba(255,255,255,0.5)">(e.g. CS20210001)</small></label>
                <input type="text" name="student_id" id="studentId" class="form-control" required
                       value="<?php echo htmlspecialchars($_POST['student_id'] ?? ''); ?>"
                       placeholder="e.g. CS20210001" oninput="validateSID(this)">
                <div class="sid-hint">Format: 2–4 uppercase letters + 4-digit year + 3-digit number</div>
                <div id="sidMsg" class="match-msg"></div>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Email Address *</label>
                <input type="email" name="email" class="form-control" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Phone Number *</label>
                <input type="text" name="phone" class="form-control" required value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" placeholder="e.g. +267 71234567">
            </div>
        </div>

        <div class="section-title"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:0.5rem;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="m7 1.367l6.5 2.817L7 7L.5 4.184z"/><path d="m3.45 5.469l.006 3.064S4.529 9.953 7 9.953s3.55-1.42 3.55-1.42l-.001-3.064m-8.854 5.132v-5.89m.001 8.282a1.196 1.196 0 1 0 0-2.392a1.196 1.196 0 0 0 0 2.392"/></g></svg>Academic Details</div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Programme *</label>
                <input type="text" name="program" class="form-control" value="<?php echo htmlspecialchars($_POST['program'] ?? 'Computer Science'); ?>" required>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Year of Study *</label>
                <select name="year_of_study" class="form-select" required>
                    <option value="3" <?php echo ($_POST['year_of_study'] ?? '') == 3 ? 'selected' : ''; ?>>3rd Year</option>
                    <option value="4" <?php echo ($_POST['year_of_study'] ?? '') == 4 ? 'selected' : ''; ?>>4th Year</option>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Preferred Location *</label>
                <select name="preferred_location" id="prefLocSelect" class="form-select" required onchange="toggleOther('prefLocSelect','prefLocOther')">
                    <option value="">-- Select --</option>
                    <?php foreach(['Gaborone','Francistown','Lobatse','Selebi-Phikwe','Maun','Kasane','Palapye','Other'] as $loc): ?>
                        <option value="<?php echo $loc; ?>" <?php echo ($_POST['preferred_location'] ?? '') == $loc ? 'selected':''; ?>><?php echo $loc; ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="preferred_location_other" id="prefLocOther" class="form-control mt-2" placeholder="Specify location" style="display:none;">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Preferred Project Type *</label>
                <select name="preferred_project_type" id="prefTypeSelect" class="form-select" required onchange="toggleOther('prefTypeSelect','prefTypeOther')">
                    <option value="">-- Select --</option>
                    <?php foreach(['Web Development','Mobile App Development','Data Science','AI/Machine Learning','Network Security','Systems Administration','Software Engineering','Database Administration','UI/UX Design','Other'] as $t): ?>
                        <option value="<?php echo $t; ?>" <?php echo ($_POST['preferred_project_type'] ?? '') == $t ? 'selected':''; ?>><?php echo $t; ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="preferred_project_type_other" id="prefTypeOther" class="form-control mt-2" placeholder="Specify project type" style="display:none;">
            </div>
            <div class="col-12 mb-3">
                <label class="form-label">Skills <small style="color:rgba(255,255,255,0.5)">(comma separated)</small></label>
                <textarea name="skills" class="form-control" rows="2" placeholder="e.g., Python, JavaScript, SQL"><?php echo htmlspecialchars($_POST['skills'] ?? ''); ?></textarea>
            </div>
        </div>

        <div class="section-title"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:0.5rem;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><rect width="13" height="7" x=".5" y="3.5" rx="1"/><circle cx="3.5" cy="7" r=".5"/><circle cx="6.5" cy="7" r=".5"/><path d="M9.5 8H11"/></g></svg>Account Security</div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Password *</label>
                <div style="position:relative;">
                    <input type="password" name="password" id="password" class="form-control" required oninput="updateStrength(this.value); checkMatch();" placeholder="Min 8 chars">
                    <button type="button" onclick="togglePw('password','pwEye1')" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:rgba(255,255,255,0.6);cursor:pointer;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3.625c-4.187 0-5.945 3.766-5.945 3.844S2.813 11.312 7 11.312s5.945-3.765 5.945-3.843S11.187 3.625 7 3.625M2.169 5.813L.61 4.252m4.525-.354L4.5 1.843m7.331 3.97l1.559-1.56m-4.525-.355L9.5 1.843"/><path d="M5.306 7.081a1.738 1.738 0 1 0 3.388.776a1.738 1.738 0 1 0-3.388-.776"/></g></svg>
                    </button>
                </div>
                <div class="progress mt-2" style="height:6px;background:rgba(255,255,255,0.1);">
                    <div class="progress-bar pw-strength-bar" id="pwBar" style="width:0%"></div>
                </div>
                <div id="pwLabel" style="font-size:0.76rem;margin-top:3px;color:rgba(255,255,255,0.9);"></div>
                <ul class="pw-req mt-1" id="pwReqs">
                    <li id="req-len">At least 8 characters</li>
                    <li id="req-upper">At least one uppercase letter</li>
                    <li id="req-num">At least one number</li>
                    <li id="req-sym">At least one special character</li>
                </ul>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Confirm Password *</label>
                <div style="position:relative;">
                    <input type="password" name="password2" id="password2" class="form-control" required oninput="checkMatch()">
                    <button type="button" onclick="togglePw('password2','pwEye2')" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:rgba(255,255,255,0.6);cursor:pointer;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3.625c-4.187 0-5.945 3.766-5.945 3.844S2.813 11.312 7 11.312s5.945-3.765 5.945-3.843S11.187 3.625 7 3.625M2.169 5.813L.61 4.252m4.525-.354L4.5 1.843m7.331 3.97l1.559-1.56m-4.525-.355L9.5 1.843"/><path d="M5.306 7.081a1.738 1.738 0 1 0 3.388.776a1.738 1.738 0 1 0-3.388-.776"/></g></svg>
                    </button>
                </div>
                <div id="matchMsg" class="match-msg"></div>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Security Question * <small style="color:rgba(255,255,255,0.5)">(for password reset)</small></label>
                <select name="security_question" class="form-select" required>
                    <option value="">-- Select a question --</option>
                    <?php foreach($securityQuestions as $q): ?>
                        <option value="<?php echo htmlspecialchars($q); ?>" <?php echo ($_POST['security_question'] ?? '') === $q ? 'selected':''; ?>><?php echo htmlspecialchars($q); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Security Answer *</label>
                <input type="text" name="security_answer" class="form-control" required placeholder="Your answer" value="<?php echo htmlspecialchars($_POST['security_answer'] ?? ''); ?>">
                <div style="font-size:0.74rem;color:rgba(255,255,255,0.5);margin-top:3px;">Answer is case-insensitive and stored securely.</div>
            </div>
        </div>

        <button type="submit" class="btn btn-register mt-2"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:0.5rem;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="m.5 8.55l2.73 3.51a1 1 0 0 0 1.56.03L13.5 1.55"/></svg>Create Account</button>
        <div class="text-center mt-3"><a href="index.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:0.25rem;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="M3.85.5L10 6.65a.48.48 0 0 1 0 .7L3.85 13.5"/></svg>Already have an account? Login</a></div>
    </form>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const COMMON = ['password','password1','password123','123456','12345678','qwerty','abc123','letmein','welcome','iams','student'];

function togglePw(id, iconId) {
    var el = document.getElementById(id);
    var ic = document.getElementById(iconId);
    el.type = el.type === 'password' ? 'text' : 'password';
    ic.classList.toggle('fa-eye');
    ic.classList.toggle('fa-eye-slash');
}

function toggleOther(selectId, inputId) {
    var val = document.getElementById(selectId).value;
    document.getElementById(inputId).style.display = val === 'Other' ? 'block' : 'none';
    document.getElementById(inputId).required = val === 'Other';
}

function setReq(id, ok) {
    var el = document.getElementById(id);
    if (!el) return;
    el.classList.toggle('ok', ok);
    el.classList.toggle('bad', !ok);
    el.textContent = (ok ? '\u2713 ' : '\u2717 ') + el.textContent.replace(/^[\u2713\u2717]\s*/,'');
}

function updateStrength(pw) {
    var checks = {
        len:   pw.length >= 8,
        upper: /[A-Z]/.test(pw),
        num:   /[0-9]/.test(pw),
        sym:   /[^a-zA-Z0-9]/.test(pw)
    };
    setReq('req-len',   checks.len);
    setReq('req-upper', checks.upper);
    setReq('req-num',   checks.num);
    setReq('req-sym',   checks.sym);

    var score = Object.values(checks).filter(Boolean).length;
    var isCommon = COMMON.includes(pw.toLowerCase());
    if (isCommon) score = 0;

    var colours = ['#dc3545','#fd7e14','#ffc107','#28a745'];
    var labels  = ['Weak','Fair','Good','Strong'];
    var bar = document.getElementById('pwBar');
    bar.style.width = (score * 25) + '%';
    bar.style.background = colours[score - 1] || '#dc3545';
    document.getElementById('pwLabel').textContent = score > 0 ? (isCommon ? 'Too common — choose a different password' : labels[score - 1]) : '';
    document.getElementById('pwLabel').style.color = colours[score - 1] || '#dc3545';
}

function checkMatch() {
    var p1  = document.getElementById('password').value;
    var p2  = document.getElementById('password2').value;
    var msg = document.getElementById('matchMsg');
    if (!p2) { msg.textContent = ''; return; }
    msg.textContent = p1 === p2 ? 'Passwords match' : 'Passwords do not match';
    msg.style.color = p1 === p2 ? '#28a745' : '#ff6b6b';
}

function validateSID(el) {
    var val = el.value.toUpperCase();
    el.value = val;
    var msg = document.getElementById('sidMsg');
    var ok = /^[A-Z]{2,4}[0-9]{4}[0-9]{3,4}$/.test(val);
    msg.textContent = val.length === 0 ? '' : ok ? 'Valid format' : 'Invalid: use e.g. CS20210001';
    msg.style.color = ok ? '#28a745' : '#ff6b6b';
    el.classList.toggle('is-valid', ok);
    el.classList.toggle('is-invalid', val.length > 0 && !ok);
}

function validateForm() {
    var p1 = document.getElementById('password').value;
    var p2 = document.getElementById('password2').value;
    if (p1 !== p2) { alert('Passwords do not match.'); return false; }
    return true;
}
</script>
</b