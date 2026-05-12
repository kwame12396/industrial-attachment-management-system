<?php
// modules/university_supervisor/dashboard.php - with notifications
require_once '../../config/database.php';

if (!isLoggedIn() || !hasRole('university_supervisor')) {
    redirect('index.php');
}

$supervisor = $conn->query("SELECT * FROM university_supervisors WHERE user_id = {$_SESSION['user_id']}")->fetch_assoc();
$supervisor_id = $supervisor['id'];

$students = $conn->query("
    SELECT DISTINCT s.*, a.organization_id, o.name as org_name 
    FROM allocations a 
    JOIN students s ON a.student_id = s.id 
    JOIN organizations o ON a.organization_id = o.id 
    WHERE a.status = 'confirmed'
");

// Get notifications
$notifications = $conn->query("SELECT * FROM notifications WHERE user_id = {$_SESSION['user_id']} ORDER BY created_at DESC LIMIT 10");
$unread_count = $conn->query("SELECT COUNT(*) as count FROM notifications WHERE user_id = {$_SESSION['user_id']} AND is_read = 0")->fetch_assoc()['count'];

// Mark read
if (isset($_GET['mark_read'])) {
    $notif_id = (int)$_GET['mark_read'];
    $conn->query("UPDATE notifications SET is_read = 1 WHERE id = $notif_id AND user_id = {$_SESSION['user_id']}");
    redirect('modules/university_supervisor/dashboard.php');
}
if (isset($_GET['mark_all_read'])) {
    $conn->query("UPDATE notifications SET is_read = 1 WHERE user_id = {$_SESSION['user_id']}");
    redirect('modules/university_supervisor/dashboard.php');
}

// Handle assessment submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_assessment'])) {
    $student_id = (int)$_POST['student_id'];
    $visit_number = (int)$_POST['visit_number'];
    $visit_date = $conn->real_escape_string($_POST['visit_date']);
    $presentation = (int)$_POST['presentation_score'];
    $project_knowledge = (int)$_POST['project_knowledge'];
    $attitude = (int)$_POST['attitude_score'];
    $comments = $conn->real_escape_string($_POST['comments']);
    
    $check = $conn->query("SELECT id FROM university_assessments WHERE student_id = $student_id AND visit_number = $visit_number");
    if ($check->num_rows > 0) {
        $conn->query("UPDATE university_assessments SET visit_date='$visit_date', presentation_score=$presentation, project_knowledge_score=$project_knowledge, attitude_score=$attitude, comments='$comments' WHERE student_id=$student_id AND visit_number=$visit_number");
    } else {
        $conn->query("INSERT INTO university_assessments (student_id, supervisor_id, visit_number, visit_date, presentation_score, project_knowledge_score, attitude_score, comments) VALUES ($student_id, $supervisor_id, $visit_number, '$visit_date', $presentation, $project_knowledge, $attitude, '$comments')");
    }
    $_SESSION['success'] = "Assessment for Visit $visit_number saved!";
    redirect('modules/university_supervisor/dashboard.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>University Supervisor - IAMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f0f2f5; }
        .sidebar { position: fixed; top: 0; left: 0; height: 100vh; width: 260px; background: linear-gradient(135deg, #1a1a2e, #16213e); color: white; }
        .sidebar-header { padding: 20px; text-align: center; }
        .sidebar-menu a { display: block; padding: 12px 25px; color: rgba(255,255,255,0.8); text-decoration: none; }
        .sidebar-menu a:hover, .sidebar-menu a.active { background: rgba(255,255,255,0.1); color: white; padding-left: 30px; }
        .content { margin-left: 260px; padding: 20px; }
        .btn-submit { background: linear-gradient(135deg, #4361ee, #3f37c9); color: white; border: none; }
        .score-input { width: 80px; display: inline-block; }
        .notification-item { border-left: 4px solid #4361ee; margin-bottom: 10px; padding: 10px; background: #f8f9fa; border-radius: 8px; }
        .notification-unread { background: #e3f2fd; border-left-color: #f44336; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header"><svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M7 13.5a6.5 6.5 0 1 0 0-13a6.5 6.5 0 0 0 0 13M5.5 10h3"/><path d="M7 10V6.5H6m1-2.25a.25.25 0 0 1 0-.5m0 .5a.25.25 0 0 0 0-.5"/></g></svg><h4>University Supervisor</h4><small><?php echo $supervisor['full_name']; ?></small></div>
        <div class="sidebar-menu">
            <a href="dashboard.php" class="active"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M7 13.5a6.5 6.5 0 1 0 0-13a6.5 6.5 0 0 0 0 13M5.5 10h3"/><path d="M7 10V6.5H6m1-2.25a.25.25 0 0 1 0-.5m0 .5a.25.25 0 0 0 0-.5"/></g></svg> Assessments</a>
            <a href="view_reports.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 9l-3 .54L5 6.5L10.73.79a1 1 0 0 1 1.42 0l1.06 1.06a1 1 0 0 1 0 1.42Z"/><path d="M12 9.5v3a1 1 0 0 1-1 1H1.5a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1h3"/></g></svg> Student Reports</a>
            <a href="../../logout.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="M9.5 10.5v2a1 1 0 0 1-1 1h-7a1 1 0 0 1-1-1v-11a1 1 0 0 1 1-1h7a1 1 0 0 1 1 1v2M6.5 7h7m-2-2l2 2l-2 2"/></svg> Logout</a>
        </div>
    </div>

    <div class="content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:0.5rem;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M7 13.5a6.5 6.5 0 1 0 0-13a6.5 6.5 0 0 0 0 13M5.5 10h3"/><path d="M7 10V6.5H6m1-2.25a.25.25 0 0 1 0-.5m0 .5a.25.25 0 0 0 0-.5"/></g></svg>Site Visit Assessments</h3>
            <div>
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:0.5rem;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="M6 13.25h2m3-7.5a4 4 0 0 0-8 0v3.5a1.5 1.5 0 0 1-1.5 1.5h11a1.5 1.5 0 0 1-1.5-1.5ZM.5 5.62A6 6 0 0 1 3 .75m10.5 4.87A6 6 0 0 0 11 .75"/></svg>
                <a href="?mark_all_read=1" class="text-decoration-none">Mark all read</a>
                (<?php echo $unread_count; ?> unread)
            </div>
        </div>
        
        <!-- Notifications Section -->
        <div class="card mb-4">
            <div class="card-header bg-white"><h5><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="M6 13.25h2m3-7.5a4 4 0 0 0-8 0v3.5a1.5 1.5 0 0 1-1.5 1.5h11a1.5 1.5 0 0 1-1.5-1.5ZM.5 5.62A6 6 0 0 1 3 .75m10.5 4.87A6 6 0 0 0 11 .75"/></svg> Notifications</h5></div>
            <div class="card-body">
                <?php if($notifications->num_rows > 0): ?>
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
                                <div class="mt-1"><a href="?mark_read=<?php echo $notif['id']; ?>" class="btn btn-sm btn-outline-secondary">Mark as read</a></div>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="text-muted">No notifications.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <?php displayMessage(); ?>
        
        <?php while($student = $students->fetch_assoc()): ?>
            <div class="card mb-4">
                <div class="card-header bg-white"><h5><?php echo $student['full_name']; ?> - <?php echo $student['org_name']; ?></h5></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <form method="POST">
                                <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                <input type="hidden" name="visit_number" value="1">
                                <h6>Visit 1 Assessment</h6>
                                <?php $v1 = $conn->query("SELECT * FROM university_assessments WHERE student_id = {$student['id']} AND visit_number = 1")->fetch_assoc(); ?>
                                <div class="mb-2"><label>Visit Date</label><input type="date" name="visit_date" class="form-control" value="<?php echo $v1['visit_date'] ?? ''; ?>" required></div>
                                <div class="row">
                                    <div class="col-4"><label>Presentation (0-20)</label><input type="number" name="presentation_score" class="form-control score-input" min="0" max="20" value="<?php echo $v1['presentation_score'] ?? ''; ?>"></div>
                                    <div class="col-4"><label>Project Knowledge (0-20)</label><input type="number" name="project_knowledge" class="form-control score-input" min="0" max="20" value="<?php echo $v1['project_knowledge_score'] ?? ''; ?>"></div>
                                    <div class="col-4"><label>Attitude (0-10)</label><input type="number" name="attitude_score" class="form-control score-input" min="0" max="10" value="<?php echo $v1['attitude_score'] ?? ''; ?>"></div>
                                </div>
                                <div class="mb-2"><label>Comments</label><textarea name="comments" class="form-control" rows="2"><?php echo $v1['comments'] ?? ''; ?></textarea></div>
                                <button type="submit" name="submit_assessment" class="btn btn-submit">Save Visit 1</button>
                            </form>
                        </div>
                        <div class="col-md-6">
                            <form method="POST">
                                <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                <input type="hidden" name="visit_number" value="2">
                                <h6>Visit 2 Assessment</h6>
                                <?php $v2 = $conn->query("SELECT * FROM university_assessments WHERE student_id = {$student['id']} AND visit_number = 2")->fetch_assoc(); ?>
                                <div class="mb-2"><label>Visit Date</label><input type="date" name="visit_date" class="form-control" value="<?php echo $v2['visit_date'] ?? ''; ?>" required></div>
                                <div class="row">
                                    <div class="col-4"><label>Presentation (0-20)</label><input type="number" name="presentation_score" class="form-control score-input" min="0" max="20" value="<?php echo $v2['presentation_score'] ?? ''; ?>"></div>
                                    <div class="col-4"><label>Project Knowledge (0-20)</label><input type="number" name="project_knowledge" class="form-control score-input" min="0" max="20" value="<?php echo $v2['project_knowledge_score'] ?? ''; ?>"></div>
                                    <div class="col-4"><label>Attitude (0-10)</label><input type="number" name="attitude_score" class="form-control score-input" min="0" max="10" value="<?php echo $v2['attitude_score'] ?? ''; ?>"></div>
                                </div>
                                <div class="mb-2"><label>Comments</label><textarea name="comments" class="form-control" rows="2"><?php echo $v2['comments'] ?? ''; ?></textarea></div>
                                <button type="submit" name="submit_assessment" class="btn btn-submit">Save Visit 2</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
</body>
</html>