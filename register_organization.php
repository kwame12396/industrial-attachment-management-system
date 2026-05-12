<?php
// register_organization.php
require_once 'config/database.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name                = trim($_POST['name']             ?? '');
    $registration_number = trim($_POST['registration_number'] ?? '');
    $location_raw        = trim($_POST['location']         ?? '');
    $location            = $location_raw === 'Other' ? trim($_POST['location_other'] ?? '') : $location_raw;
    $website             = trim($_POST['website']          ?? '');
    $contact_person      = trim($_POST['contact_person']   ?? '');
    $contact_email       = trim($_POST['contact_email']    ?? '');
    $contact_phone       = trim($_POST['contact_phone']    ?? '');
    $required_skills     = trim($_POST['required_skills']  ?? '');
    $capacity            = max(1, (int)($_POST['capacity'] ?? 3));
    $description         = trim($_POST['description']      ?? '');
    $industry_type       = trim($_POST['industry_type']    ?? 'Other');
    $supervisor_name     = trim($_POST['supervisor_name']  ?? '');
    $supervisor_position = trim($_POST['supervisor_position'] ?? '');
    $supervisor_email    = trim($_POST['supervisor_email'] ?? '');
    $supervisor_password = $_POST['supervisor_password']   ?? '';
    $supervisor_password2= $_POST['supervisor_password2']  ?? '';
    $sec_q               = trim($_POST['security_question'] ?? '');
    $sec_a               = strtolower(trim($_POST['security_answer'] ?? ''));

    // Validate
    if (empty($name))   $errors[] = "Organisation name is required.";
    if (empty($registration_number)) $errors[] = "Registration number is required.";
    if (empty($location)) $errors[] = "Location is required.";
    if (empty($contact_person)) $errors[] = "Contact person is required.";
    if (empty($contact_email) || !filter_var($contact_email, FILTER_VALIDATE_EMAIL))
        $errors[] = "A valid contact email is required.";
    if (empty($contact_phone)) $errors[] = "Contact phone is required.";
    if (empty($required_skills)) $errors[] = "Required skills are required.";
    if (empty($supervisor_name))  $errors[] = "Supervisor name is required.";
    if (empty($supervisor_email) || !filter_var($supervisor_email, FILTER_VALIDATE_EMAIL))
        $errors[] = "A valid supervisor email is required.";
    if (empty($sec_q) || empty($sec_a))
        $errors[] = "Please select and answer a security question.";

    $pwErrors = validatePassword($supervisor_password);
    $errors = array_merge($errors, $pwErrors);
    if ($supervisor_password !== $supervisor_password2) $errors[] = "Supervisor passwords do not match.";

    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT id FROM organizations WHERE registration_number = ?");
        $stmt->bind_param("s", $registration_number);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) $errors[] = "An organisation with this registration number already exists.";

        $stmt2 = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt2->bind_param("s", $supervisor_email);
        $stmt2->execute();
        if ($stmt2->get_result()->num_rows > 0) $errors[] = "Supervisor email is already in use.";
    }

    if (empty($errors)) {
        $hashedPw = password_hash($supervisor_password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("INSERT INTO organizations (name, registration_number, location, website, contact_person, contact_email, contact_phone, required_skills, capacity, description, industry_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssssisss", $name, $registration_number, $location, $website, $contact_person, $contact_email, $contact_phone, $required_skills, $capacity, $description, $industry_type);
        $stmt->execute();
        $org_id = $conn->insert_id;

        $stmt2 = $conn->prepare("INSERT INTO users (email, password, role, related_id, security_question, security_answer) VALUES (?, ?, 'industrial_supervisor', ?, ?, ?)");
        $stmt2->bind_param("ssiss", $supervisor_email, $hashedPw, $org_id, $sec_q, $sec_a);
        $stmt2->execute();
        $sup_user_id = $conn->insert_id;

        $stmt3 = $conn->prepare("INSERT INTO industrial_supervisors (user_id, organization_id, full_name, position, phone) VALUES (?, ?, ?, ?, ?)");
        $stmt3->bind_param("iisss", $sup_user_id, $org_id, $supervisor_name, $supervisor_position, $contact_phone);
        $stmt3->execute();

        logActivity('REGISTER', "New organisation registered: $name", $sup_user_id);
        $_SESSION['success'] = "Organisation registered successfully! Supervisor credentials created. Please log in.";
        redirect('index.php');
    }
}

$securityQuestions = [
    "What is your mother's maiden name?",
    "What was the name of your first pet?",
    "What city was the company founded in?",
    "What is the name of your primary school?",
    "What was your childhood nickname?",
    "What is your favourite book?",
];

$industries = [
    'Information Technology','Banking & Finance','Telecommunications',
    'Healthcare','Education','Government / Public Sector',
    'Mining & Resources','Retail','Manufacturing','Media & Entertainment',
    'Data & Analytics','Engineering','Other'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Organisation Registration - IAMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Inter',sans-serif; background:url('assets/images/tech.jpg') no-repeat center center fixed; background-size:cover; min-height:100vh; padding:2rem 0; }
        body::before { content:''; position:fixed; inset:0; background:rgba(0,0,0,0.65); z-index:-1; }
        .glass-card { background:rgba(255,255,255,0.1); backdrop-filter:blur(15px); border-radius:28px; padding:2rem; border:1px solid rgba(255,255,255,0.2); max-width:1020px; margin:0 auto; }
        .form-header { text-align:center; margin-bottom:1.75rem; }
        .form-header h2 { color:white; font-weight:700; }
        .form-header p { color:rgba(255,255,255,0.8); }
        .form-label { color:rgba(255,255,255,0.9); font-weight:500; font-size:0.9rem; }
        .form-control, .form-select { background:rgba(255,255,255,0.15); border:1px solid rgba(255,255,255,0.25); border-radius:14px; padding:0.7rem 1rem; color:white; }
        .form-control:focus, .form-select:focus { background:rgba(255,255,255,0.25); border-color:rgba(255,255,255,0.5); box-shadow:none; color:white; }
        .form-control::placeholder { color:rgba(255,255,255,0.55); }
        option { background:#1a1a2e; }
        .btn-register { background:linear-gradient(135deg,#4361ee,#3a0ca3); border:none; padding:13px; border-radius:40px; font-weight:600; width:100%; color:white; transition:0.3s; }
        .btn-register:hover { transform:translateY(-2px); }
        .section-title { color:white; font-size:1rem; font-weight:600; margin:1.5rem 0 1rem; border-left:4px solid #4361ee; padding-left:12px; }
        a { color:rgba(255,255,255,0.9); }
        a:hover { color:white; text-decoration:underline; }
        .pw-req li { font-size:0.78rem; color:rgba(255,255,255,0.88); list-style:none; margin-bottom:3px; }
        .pw-req li.ok  { color:#28a745; }
        .pw-req li.bad { color:#ff6b6b; }
        .match-msg { font-size:0.76rem; margin-top:4px; }
    </style>
</head>
<body>
<div class="container">
<div class="glass-card">
    <div class="form-header">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="M8.461 4.75V1.594c0-.56-.454-1.015-1.015-1.015h-4.79c-.562 0-1.016.454-1.016 1.015v11.827m-1.121 0h4.968M1.64 3.187H4.1M1.64 5.75h3.847m4.993 4.282a1.75 1.75 0 1 0 0-3.5a1.75 1.75 0 0 0 0 3.5m-3.001 3.389a3.04 3.04 0 0 1 .39-1.46a3.03 3.03 0 0 1 2.611-1.537a3.03 3.03 0 0 1 2.612 1.538c.25.445.385.947.39 1.459"/></svg>
        <h2>Organisation Registration</h2>
        <p>Register as a Host Organisation for Industrial Attachment</p>
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
        <div class="section-title"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:0.5rem;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M7 13.5a6.5 6.5 0 1 0 0-13a6.5 6.5 0 0 0 0 13M5.5 10h3"/><path d="M7 10V6.5H6m1-2.25a.25.25 0 0 1 0-.5m0 .5a.25.25 0 0 0 0-.5"/></g></svg>Organisation Details</div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Organisation Name *</label>
                <input type="text" name="name" class="form-control" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Registration Number *</label>
                <input type="text" name="registration_number" class="form-control" required value="<?php echo htmlspecialchars($_POST['registration_number'] ?? ''); ?>">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Industry Type</label>
                <select name="industry_type" class="form-select">
                    <?php foreach($industries as $ind): ?>
                        <option value="<?php echo $ind; ?>" <?php echo ($_POST['industry_type'] ?? '') == $ind ? 'selected':''; ?>><?php echo $ind; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Location *</label>
                <select name="location" id="locationSelect" class="form-select" required onchange="toggleOther('locationSelect','locationOther')">
                    <option value="">-- Select --</option>
                    <?php foreach(['Gaborone','Francistown','Lobatse','Selebi-Phikwe','Maun','Kasane','Palapye','Jwaneng','Orapa','Other'] as $loc): ?>
                        <option value="<?php echo $loc; ?>" <?php echo ($_POST['location'] ?? '') == $loc ? 'selected':''; ?>><?php echo $loc; ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="location_other" id="locationOther" class="form-control mt-2" placeholder="Specify location" style="display:none;">
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Contact Person *</label>
                <input type="text" name="contact_person" class="form-control" required value="<?php echo htmlspecialchars($_POST['contact_person'] ?? ''); ?>">
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Contact Email *</label>
                <input type="email" name="contact_email" class="form-control" required value="<?php echo htmlspecialchars($_POST['contact_email'] ?? ''); ?>">
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Contact Phone *</label>
                <input type="text" name="contact_phone" class="form-control" required value="<?php echo htmlspecialchars($_POST['contact_phone'] ?? ''); ?>">
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Website</label>
                <input type="url" name="website" class="form-control" placeholder="https://" value="<?php echo htmlspecialchars($_POST['website'] ?? ''); ?>">
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Student Capacity *</label>
                <input type="number" name="capacity" class="form-control" value="<?php echo htmlspecialchars($_POST['capacity'] ?? '3'); ?>" min="1" max="50" required>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Required Skills *</label>
                <input type="text" name="required_skills" class="form-control" required placeholder="e.g., Web Dev, Python" value="<?php echo htmlspecialchars($_POST['required_skills'] ?? ''); ?>">
            </div>
            <div class="col-12 mb-3">
                <label class="form-label">Organisation Description</label>
                <textarea name="description" class="form-control" rows="2" placeholder="Brief description of what the organisation does..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
            </div>
        </div>

        <div class="section-title"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:0.5rem;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M7 13.5a6.5 6.5 0 1 0 0-13a6.5 6.5 0 0 0 0 13M5.5 10h3"/><path d="M7 10V6.5H6m1-2.25a.25.25 0 0 1 0-.5m0 .5a.25.25 0 0 0 0-.5"/></g></svg>Industrial Supervisor Account</div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Supervisor Full Name *</label>
                <input type="text" name="supervisor_name" class="form-control" required value="<?php echo htmlspecialchars($_POST['supervisor_name'] ?? ''); ?>">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Supervisor Position *</label>
                <input type="text" name="supervisor_position" class="form-control" required value="<?php echo htmlspecialchars($_POST['supervisor_position'] ?? ''); ?>">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Supervisor Login Email *</label>
                <input type="email" name="supervisor_email" class="form-control" required value="<?php echo htmlspecialchars($_POST['supervisor_email'] ?? ''); ?>">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Security Question * <small style="color:rgba(255,255,255,0.5)">(for password reset)</small></label>
                <select name="security_question" class="form-select" required>
                    <option value="">-- Select --</option>
                    <?php foreach($securityQuestions as $q): ?>
                        <option value="<?php echo htmlspecialchars($q); ?>" <?php echo ($_POST['security_question'] ?? '') === $q ? 'selected':''; ?>><?php echo htmlspecialchars($q); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Supervisor Password *</label>
                <div style="position:relative;">
                    <input type="password" name="supervisor_password" id="supPw" class="form-control" required oninput="updateStrength(this.value); checkMatch();" placeholder="Min 8 chars, upper, number, symbol">
                    <button type="button" onclick="togglePw('supPw','eye1')" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:rgba(255,255,255,0.6);cursor:pointer;"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3.625c-4.187 0-5.945 3.766-5.945 3.844S2.813 11.312 7 11.312s5.945-3.765 5.945-3.843S11.187 3.625 7 3.625M2.169 5.813L.61 4.252m4.525-.354L4.5 1.843m7.331 3.97l1.559-1.56m-4.525-.355L9.5 1.843"/><path d="M5.306 7.081a1.738 1.738 0 1 0 3.388.776a1.738 1.738 0 1 0-3.388-.776"/></g></svg></button>
                </div>
                <div class="progress mt-2" style="height:6px;background:rgba(255,255,255,0.1);">
                    <div class="progress-bar" id="pwBar" style="width:0%;transition:0.4s;border-radius:3px;"></div>
                </div>
                <div id="pwLabel" style="font-size:0.76rem;margin-top:3px;color:rgba(255,255,255,0.9);"></div>
                <ul class="pw-req" id="pwReqs" style="padding-left:0;list-style:none;">
                    <li id="req-len">At least 8 characters</li>
                    <li id="req-upper">Uppercase letter</li>
                    <li id="req-num">Number</li>
                    <li id="req-sym">Special character</li>
                </ul>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Confirm Password *</label>
                <div style="position:relative;">
                    <input type="password" name="supervisor_password2" id="supPw2" class="form-control" required oninput="checkMatch()">
                    <button type="button" onclick="togglePw('supPw2','eye2')" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:rgba(255,255,255,0.6);cursor:pointer;"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3.625c-4.187 0-5.945 3.766-5.945 3.844S2.813 11.312 7 11.312s5.945-3.765 5.945-3.843S11.187 3.625 7 3.625M2.169 5.813L.61 4.252m4.525-.354L4.5 1.843m7.331 3.97l1.559-1.56m-4.525-.355L9.5 1.843"/><path d="M5.306 7.081a1.738 1.738 0 1 0 3.388.776a1.738 1.738 0 1 0-3.388-.776"/></g></svg></button>
                </div>
                <div id="matchMsg" class="match-msg"></div>
                <div class="mt-2">
                    <label class="form-label">Security Answer *</label>
                    <input type="text" name="security_answer" class="form-control" required placeholder="Your answer" value="<?php echo htmlspecialchars($_POST['security_answer'] ?? ''); ?>">
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-register"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:0.5rem;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="m.5 8.55l2.73 3.51a1 1 0 0 0 1.56.03L13.5 1.55"/></svg>Register Organisation</button>
        <div class="text-center mt-3"><a href="index.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:0.25rem;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="M3.85.5L10 6.65a.48.48 0 0 1 0 .7L3.85 13.5"/></svg>Back to Login</a></div>
    </form>
</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const COMMON = ['password','password1','password123','123456','12345678','qwerty','abc123','letmein','welcome'];
function togglePw(id, iconId) { var el=document.getElementById(id); var ic=document.getElementById(iconId); el.type=el.type==='password'?'text':'password'; ic.classList.toggle('fa-eye'); ic.classList.toggle('fa-eye-slash'); }
function toggleOther(selId, inpId) { var v=document.getElementById(selId).value; var el=document.getElementById(inpId); el.style.display=v==='Other'?'block':'none'; el.required=v==='Other'; }
function setReq(id,ok){ var el=document.getElementById(id); if(!el)return; el.classList.toggle('ok',ok); el.classList.toggle('bad',!ok); el.textContent=(ok?'\u2713 ':'\u2717 ')+el.textContent.replace(/^[\u2713\u2717]\s*/,''); }
function updateStrength(pw){
    var checks={len:pw.length>=8,upper:/[A-Z]/.test(pw),num:/[0-9]/.test(pw),sym:/[^a-zA-Z0-9]/.test(pw)};
    setReq('req-len',checks.len); setReq('req-upper',checks.upper); setReq('req-num',checks.num); setReq('req-sym',checks.sym);
    var score=Object.values(checks).filter(Boolean).length;
    var isCommon=COMMON.includes(pw.toLowerCase()); if(isCommon)score=0;
    var colours=['#dc3545','#fd7e14','#ffc107','#28a745'];
    var labels=['Weak','Fair','Good','Strong'];
    var bar=document.getElementById('pwBar');
    bar.style.width=(score*25)+'%'; bar.style.background=colours[score-1]||'#dc3545';
    document.getElementById('pwLabel').textContent=score>0?(isCommon?'Too common':labels[score-1]):'';
    document.getElementById('pwLabel').style.color=colours[score-1]||'#dc3545';
}
function checkMatch(){ var p1=document.getElementById('supPw').value; var p2=document.getElementById('supPw2').value; var msg=document.getElementById('matchMsg'); if(!p2){msg.textContent='';return;} msg.textContent=p1===p2?'Passwords match':'Passwords do not match'; msg.style.color=p1===p2?'#22c55e':'#f87171'; }
function validateForm(){ var p1=document.getElementById('supPw').value; var p2=document.getElementById('supPw2').value; if(p1!==p2){alert('Passwords do not match.');return false;} return true; }
</script>
</body>
</html>
