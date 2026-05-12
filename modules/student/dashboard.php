<?php
// modules/student/dashboard.php - Updated with notifications
require_once '../../config/database.php';

if (!isLoggedIn() || !hasRole('student')) {
    redirect('index.php');
}

$student = $conn->query("SELECT * FROM students WHERE user_id = {$_SESSION['user_id']}")->fetch_assoc();
$student_id = $student['id'];

$allocation = $conn->query("
    SELECT a.*, o.name as org_name, o.location, o.contact_person 
    FROM allocations a 
    JOIN organizations o ON a.organization_id = o.id 
    WHERE a.student_id = $student_id
")->fetch_assoc();

$logbook_count = $conn->query("SELECT COUNT(*) as count FROM logbooks WHERE student_id = $student_id AND status='submitted'")->fetch_assoc()['count'];
$final_report = $conn->query("SELECT id FROM final_reports WHERE student_id = $student_id")->num_rows;

// Get unread notifications
$unread_count = getUnreadNotificationsCount($_SESSION['user_id']);
$notifications = getUserNotifications($_SESSION['user_id'], 5);

// Handle marking notification as read
if (isset($_GET['mark_read'])) {
    $notif_id = (int)$_GET['mark_read'];
    $conn->query("UPDATE notifications SET is_read = 1 WHERE id = $notif_id AND user_id = {$_SESSION['user_id']}");
    redirect('modules/student/dashboard.php');
}

// Handle logbook submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_logbook'])) {
    $week_number = (int)$_POST['week_number'];
    $activities = $conn->real_escape_string($_POST['activities']);
    $challenges = $conn->real_escape_string($_POST['challenges']);
    $plans = $conn->real_escape_string($_POST['plans']);
    
    $check = $conn->query("SELECT id FROM logbooks WHERE student_id = $student_id AND week_number = $week_number");
    if ($check->num_rows > 0) {
        $conn->query("UPDATE logbooks SET activities='$activities', challenges='$challenges', plans='$plans', status='submitted', submitted_at=NOW() 
                      WHERE student_id=$student_id AND week_number=$week_number");
    } else {
        $conn->query("INSERT INTO logbooks (student_id, week_number, activities, challenges, plans, status, submitted_at) 
                      VALUES ($student_id, $week_number, '$activities', '$challenges', '$plans', 'submitted', NOW())");
    }
    $_SESSION['success'] = "Logbook for Week $week_number submitted!";
    redirect('modules/student/dashboard.php');
}

// Handle final report upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['final_report'])) {
    $upload_dir = '../../assets/uploads/reports/';
    if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
    $filename = time() . '_' . basename($_FILES['final_report']['name']);
    $target_path = $upload_dir . $filename;
    if (move_uploaded_file($_FILES['final_report']['tmp_name'], $target_path)) {
        $existing = $conn->query("SELECT id FROM final_reports WHERE student_id = $student_id");
        if ($existing->num_rows > 0) {
            $conn->query("UPDATE final_reports SET file_path='$filename', original_filename='{$_FILES['final_report']['name']}', submission_date=NOW() WHERE student_id=$student_id");
        } else {
            $conn->query("INSERT INTO final_reports (student_id, file_path, original_filename) VALUES ($student_id, '$filename', '{$_FILES['final_report']['name']}')");
        }
        $_SESSION['success'] = "Final report uploaded!";
    } else {
        $_SESSION['error'] = "Upload failed.";
    }
    redirect('modules/student/dashboard.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - IAMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f0f2f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .sidebar { position: fixed; top: 0; left: 0; height: 100vh; width: 260px; background: linear-gradient(135deg, #1a1a2e, #16213e); color: white; }
        .sidebar-header { padding: 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-menu a { display: block; padding: 12px 25px; color: rgba(255,255,255,0.8); text-decoration: none; transition: all 0.3s; }
        .sidebar-menu a:hover, .sidebar-menu a.active { background: rgba(255,255,255,0.1); color: white; padding-left: 30px; }
        .sidebar-menu a i { margin-right: 10px; width: 25px; }
        .content { margin-left: 260px; padding: 20px; }
        .info-card { background: white; border-radius: 15px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .notification-item { border-left: 4px solid #4361ee; background: #f8f9fa; margin-bottom: 10px; padding: 12px; border-radius: 8px; }
        .notification-unread { background: #e3f2fd; border-left-color: #0d6efd; }
        .btn-submit { background: linear-gradient(135deg, #4361ee, #3f37c9); color: white; border: none; padding: 10px 20px; border-radius: 10px; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header"><svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="m7 1.367l6.5 2.817L7 7L.5 4.184z"/><path d="m3.45 5.469l.006 3.064S4.529 9.953 7 9.953s3.55-1.42 3.55-1.42l-.001-3.064m-8.854 5.132v-5.89m.001 8.282a1.196 1.196 0 1 0 0-2.392a1.196 1.196 0 0 0 0 2.392"/></g></svg><h4>Student Portal</h4></div>
        <div class="sidebar-menu">
            <a href="dashboard.php" class="active"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="M13.5 6.94a1 1 0 0 0-.32-.74L7 .5L.82 6.2a1 1 0 0 0-.32.74v5.56a1 1 0 0 0 1 1h11a1 1 0 0 0 1-1zM7 13.5v-4"/></svg> Dashboard</a>
            <a href="my_allocation.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="M8.461 4.75V1.594c0-.56-.454-1.015-1.015-1.015h-4.79c-.562 0-1.016.454-1.016 1.015v11.827m-1.121 0h4.968M1.64 3.187H4.1M1.64 5.75h3.847m4.993 4.282a1.75 1.75 0 1 0 0-3.5a1.75 1.75 0 0 0 0 3.5m-3.001 3.389a3.04 3.04 0 0 1 .39-1.46a3.03 3.03 0 0 1 2.611-1.537a3.03 3.03 0 0 1 2.612 1.538c.25.445.385.947.39 1.459"/></svg> My Allocation</a>
            <a href="logbooks.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 9l-3 .54L5 6.5L10.73.79a1 1 0 0 1 1.42 0l1.06 1.06a1 1 0 0 1 0 1.42Z"/><path d="M12 9.5v3a1 1 0 0 1-1 1H1.5a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1h3"/></g></svg> Logbooks</a>
            <a href="final_report.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 9l-3 .54L5 6.5L10.73.79a1 1 0 0 1 1.42 0l1.06 1.06a1 1 0 0 1 0 1.42Z"/><path d="M12 9.5v3a1 1 0 0 1-1 1H1.5a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1h3"/></g></svg> Final Report</a>
            <a href="notifications.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="M6 13.25h2m3-7.5a4 4 0 0 0-8 0v3.5a1.5 1.5 0 0 1-1.5 1.5h11a1.5 1.5 0 0 1-1.5-1.5ZM.5 5.62A6 6 0 0 1 3 .75m10.5 4.87A6 6 0 0 0 11 .75"/></svg> Notifications <?php if($unread_count > 0): ?><span class="badge bg-danger"><?php echo $unread_count; ?></span><?php endif; ?></a>
            <a href="rate_company.php"><i class="fas fa-star me-2"></i>Rate My Company</a>
            <a href="../../logout.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="M9.5 10.5v2a1 1 0 0 1-1 1h-7a1 1 0 0 1-1-1v-11a1 1 0 0 1 1-1h7a1 1 0 0 1 1 1v2M6.5 7h7m-2-2l2 2l-2 2"/></svg> Logout</a>
        </div>
    </div>

    <div class="content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3>Welcome, <?php echo htmlspecialchars($student['full_name']); ?>!</h3>
            <div><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:0.5rem;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="M6 13.25h2m3-7.5a4 4 0 0 0-8 0v3.5a1.5 1.5 0 0 1-1.5 1.5h11a1.5 1.5 0 0 1-1.5-1.5ZM.5 5.62A6 6 0 0 1 3 .75m10.5 4.87A6 6 0 0 0 11 .75"/></svg> <?php echo $unread_count; ?> unread notifications</div>
        </div>
        
        <?php displayMessage(); ?>
        
        <div class="row">
            <div class="col-md-4">
                <div class="info-card text-center">
                    <h2><?php echo $logbook_count; ?></h2>
                    <p>Logbooks Submitted</p>
                    <hr>
                    <h2><?php echo $final_report ? '&#10003;' : '&#8212;'; ?></h2>
                    <p>Final Report</p>
                </div>
            </div>
            <div class="col-md-8">
                <div class="info-card">
                    <h5><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:0.5rem;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M7 13.5a6.5 6.5 0 1 0 0-13a6.5 6.5 0 0 0 0 13M5.5 10h3"/><path d="M7 10V6.5H6m1-2.25a.25.25 0 0 1 0-.5m0 .5a.25.25 0 0 0 0-.5"/></g></svg>Your Attachment Status</h5>
                    <?php if($allocation): ?>
                        <p><strong>Organization:</strong> <?php echo $allocation['org_name']; ?></p>
                        <p><strong>Location:</strong> <?php echo $allocation['location']; ?></p>
                        <p><strong>Supervisor:</strong> <?php echo $allocation['contact_person']; ?></p>
                        <span class="badge bg-success">Allocated</span>
                    <?php else: ?>
                        <div class="alert alert-warning">You have not been allocated yet. The coordinator will assign you soon.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Notifications Section -->
        <div class="info-card">
            <h5><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:0.5rem;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="M6 13.25h2m3-7.5a4 4 0 0 0-8 0v3.5a1.5 1.5 0 0 1-1.5 1.5h11a1.5 1.5 0 0 1-1.5-1.5ZM.5 5.62A6 6 0 0 1 3 .75m10.5 4.87A6 6 0 0 0 11 .75"/></svg>Recent Notifications</h5>
            <?php if($notifications && $notifications->num_rows > 0): ?>
                <?php while($notif = $notifications->fetch_assoc()): ?>
                    <div class="notification-item <?php echo $notif['is_read'] ? '' : 'notification-unread'; ?>">
                        <div class="d-flex justify-content-between">
                            <strong><?php echo htmlspecialchars($notif['title']); ?></strong>
                            <small class="text-muted"><?php echo date('d M Y H:i', strtotime($notif['created_at'])); ?></small>
                        </div>
                        <p class="mb-1"><?php echo nl2br(htmlspecialchars($notif['message'])); ?></p>
                        <?php if($notif['due_date']): ?>
                            <small class="text-danger"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M7 13.5a6.5 6.5 0 1 0 0-13a6.5 6.5 0 0 0 0 13M5.5 10h3"/><path d="M7 10V6.5H6m1-2.25a.25.25 0 0 1 0-.5m0 .5a.25.25 0 0 0 0-.5"/></g></svg> Due: <?php echo date('d M Y', strtotime($notif['due_date'])); ?></small>
                        <?php endif; ?>
                        <?php if(!$notif['is_read']): ?>
                            <a href="?mark_read=<?php echo $notif['id']; ?>" class="btn btn-sm btn-link p-0 mt-1">Mark as read</a>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
                <a href="notifications.php" class="btn btn-sm btn-outline-primary mt-2">View all notifications</a>
            <?php else: ?>
                <p class="text-muted">No notifications yet.</p>
            <?php endif; ?>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="info-card">
                    <h5><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:0.5rem;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 9l-3 .54L5 6.5L10.73.79a1 1 0 0 1 1.42 0l1.06 1.06a1 1 0 0 1 0 1.42Z"/><path d="M12 9.5v3a1 1 0 0 1-1 1H1.5a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1h3"/></g></svg>Submit Weekly Logbook</h5>
                    <form method="POST">
                        <div class="mb-2"><input type="number" name="week_number" class="form-control" placeholder="Week Number" required></div>
                        <div class="mb-2"><textarea name="activities" class="form-control" rows="3" placeholder="Activities done this week..." required></textarea></div>
                        <div class="mb-2"><textarea name="challenges" class="form-control" rows="2" placeholder="Challenges faced"></textarea></div>
                        <div class="mb-2"><textarea name="plans" class="form-control" rows="2" placeholder="Plans for next week"></textarea></div>
                        <button type="submit" name="submit_logbook" class="btn btn-submit w-100">Submit Logbook</button>
                    </form>
                </div>
            </div>
            <div class="col-md-6">
                <div class="info-card">
                    <h5><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:0.5rem;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 9l-3 .54L5 6.5L10.73.79a1 1 0 0 1 1.42 0l1.06 1.06a1 1 0 0 1 0 1.42Z"/><path d="M12 9.5v3a1 1 0 0 1-1 1H1.5a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1h3"/></g></svg>Final Report</h5>
                    <?php if($final_report): ?>
                        <div class="alert alert-success">Report submitted. Thank you!</div>
                    <?php else: ?>
                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-2"><input type="file" name="final_report" class="form-control" accept=".pdf,.doc,.docx" required></div>
                            <button type="submit" class="btn btn-submit w-100">Upload Report</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>