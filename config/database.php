<?php
// config/database.php - Database configuration
session_start();

define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'iams_db');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, 3307);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

define('BASE_URL', 'http://localhost/iams/');

// ─── Auth helpers ────────────────────────────────────────────────────────────
function isLoggedIn() { return isset($_SESSION['user_id']); }
function hasRole($role) { return isset($_SESSION['user_role']) && $_SESSION['user_role'] == $role; }

function redirect($url) {
    header("Location: " . BASE_URL . $url);
    exit();
}

function displayMessage() {
    if (isset($_SESSION['success'])) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">' .
             '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:0.5rem;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="m.5 8.55l2.73 3.51a1 1 0 0 0 1.56.03L13.5 1.55"/></svg>' .
             htmlspecialchars($_SESSION['success']) .
             '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        unset($_SESSION['success']);
    }
    if (isset($_SESSION['error'])) {
        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">' .
             '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:0.5rem;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><circle cx="7" cy="7" r="6.5"/><path d="M7 3.5v3"/><circle cx="7" cy="9.5" r=".5"/></g></svg>' .
             htmlspecialchars($_SESSION['error']) .
             '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        unset($_SESSION['error']);
    }
}

// ─── Password security ───────────────────────────────────────────────────────
$COMMON_PASSWORDS = [
    'password','password1','password123','123456','12345678','qwerty','abc123',
    'letmein','welcome','monkey','dragon','master','hello','iloveyou','sunshine',
    'princess','football','admin','login','passw0rd','pass','test','iams','student'
];

function validatePassword($password) {
    global $COMMON_PASSWORDS;
    $errors = [];
    if (strlen($password) < 8)
        $errors[] = "Password must be at least 8 characters long.";
    if (!preg_match('/[A-Z]/', $password))
        $errors[] = "Password must contain at least one uppercase letter.";
    if (!preg_match('/[a-z]/', $password))
        $errors[] = "Password must contain at least one lowercase letter.";
    if (!preg_match('/[0-9]/', $password))
        $errors[] = "Password must contain at least one number.";
    if (!preg_match('/[^a-zA-Z0-9]/', $password))
        $errors[] = "Password must contain at least one special character (e.g. @, #, !).";
    if (in_array(strtolower($password), $COMMON_PASSWORDS))
        $errors[] = "Password is too common. Please choose a stronger password.";
    return $errors;
}

// ─── Login attempt / brute-force protection ──────────────────────────────────
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_MINUTES', 15);

function getRecentLoginAttempts($email) {
    global $conn;
    $email = $conn->real_escape_string($email);
    $cutoff = date('Y-m-d H:i:s', strtotime('-' . LOCKOUT_MINUTES . ' minutes'));
    $r = $conn->query("SELECT COUNT(*) as cnt FROM login_attempts
                        WHERE email='$email' AND attempted_at > '$cutoff'");
    return $r ? (int)$r->fetch_assoc()['cnt'] : 0;
}

function recordFailedLogin($email) {
    global $conn;
    $email  = $conn->real_escape_string($email);
    $ip     = $conn->real_escape_string($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    $conn->query("INSERT INTO login_attempts (email, ip_address) VALUES ('$email', '$ip')");
}

function clearLoginAttempts($email) {
    global $conn;
    $email = $conn->real_escape_string($email);
    $conn->query("DELETE FROM login_attempts WHERE email='$email'");
}

// ─── Student ID validation ───────────────────────────────────────────────────
function validateStudentId($sid) {
    // Format: 2-4 uppercase letters + 4-digit year + 3-4 digit sequence  e.g. CS20210001
    if (!preg_match('/^[A-Z]{2,4}[0-9]{4}[0-9]{3,4}$/', $sid))
        return "Student ID must follow the format: 2-4 uppercase letters + 4-digit year + 3-4 digit number (e.g. CS20210001).";
    $year = (int)substr($sid, strspn($sid, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 4);
    if ($year < 2000 || $year > (int)date('Y') + 1)
        return "Student ID contains an invalid year.";
    return '';
}

// ─── Notifications ───────────────────────────────────────────────────────────
function createNotification($user_id, $title, $message, $type = 'info', $due_date = null) {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, due_date) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $user_id, $title, $message, $type, $due_date);
    return $stmt->execute();
}

function getUnreadNotificationsCount($user_id) {
    global $conn;
    $r = $conn->query("SELECT COUNT(*) as count FROM notifications WHERE user_id=$user_id AND is_read=0");
    return $r ? (int)$r->fetch_assoc()['count'] : 0;
}

function getUserNotifications($user_id, $limit = 10) {
    global $conn;
    return $conn->query("SELECT * FROM notifications WHERE user_id=$user_id ORDER BY created_at DESC LIMIT $limit");
}

// ─── Activity log ────────────────────────────────────────────────────────────
function logActivity($action, $details = '', $user_id = null) {
    global $conn;
    $uid  = $user_id ?? ($_SESSION['user_id'] ?? null);
    $ip   = $conn->real_escape_string($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    $act  = $conn->real_escape_string($action);
    $det  = $conn->real_escape_string($details);
    $uid_sql = $uid ? $uid : 'NULL';
    $conn->query("INSERT INTO activity_log (user_id, action, details, ip_address)
                  VALUES ($uid_sql, '$act', '$det', '$ip')");
}
