<?php
// modules/student/logbooks.php - View all logbooks
require_once '../../config/database.php';

if (!isLoggedIn() || !hasRole('student')) {
    redirect('index.php');
}

$student = $conn->query("SELECT * FROM students WHERE user_id = {$_SESSION['user_id']}")->fetch_assoc();
$student_id = $student['id'];

// Handle new logbook submission
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
    redirect('modules/student/logbooks.php');
}

$logbooks = $conn->query("SELECT * FROM logbooks WHERE student_id = $student_id ORDER BY week_number DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Logbooks - IAMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f0f2f5; }
        .sidebar { position: fixed; top: 0; left: 0; height: 100vh; width: 260px; background: linear-gradient(135deg, #1a1a2e, #16213e); color: white; }
        .sidebar-header { padding: 20px; text-align: center; }
        .sidebar-menu a { display: block; padding: 12px 25px; color: rgba(255,255,255,0.8); text-decoration: none; }
        .sidebar-menu a:hover, .sidebar-menu a.active { background: rgba(255,255,255,0.1); color: white; padding-left: 30px; }
        .sidebar-menu a i { margin-right: 10px; width: 25px; }
        .content { margin-left: 260px; padding: 20px; }
        .btn-submit { background: linear-gradient(135deg, #4361ee, #3f37c9); color: white; border: none; padding: 10px 20px; border-radius: 10px; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header"><svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="m7 1.367l6.5 2.817L7 7L.5 4.184z"/><path d="m3.45 5.469l.006 3.064S4.529 9.953 7 9.953s3.55-1.42 3.55-1.42l-.001-3.064m-8.854 5.132v-5.89m.001 8.282a1.196 1.196 0 1 0 0-2.392a1.196 1.196 0 0 0 0 2.392"/></g></svg><h4>Student Portal</h4></div>
        <div class="sidebar-menu">
            <a href="dashboard.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="M13.5 6.94a1 1 0 0 0-.32-.74L7 .5L.82 6.2a1 1 0 0 0-.32.74v5.56a1 1 0 0 0 1 1h11a1 1 0 0 0 1-1zM7 13.5v-4"/></svg> Dashboard</a>
            <a href="my_allocation.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="M8.461 4.75V1.594c0-.56-.454-1.015-1.015-1.015h-4.79c-.562 0-1.016.454-1.016 1.015v11.827m-1.121 0h4.968M1.64 3.187H4.1M1.64 5.75h3.847m4.993 4.282a1.75 1.75 0 1 0 0-3.5a1.75 1.75 0 0 0 0 3.5m-3.001 3.389a3.04 3.04 0 0 1 .39-1.46a3.03 3.03 0 0 1 2.611-1.537a3.03 3.03 0 0 1 2.612 1.538c.25.445.385.947.39 1.459"/></svg> My Allocation</a>
            <a href="logbooks.php" class="active"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 9l-3 .54L5 6.5L10.73.79a1 1 0 0 1 1.42 0l1.06 1.06a1 1 0 0 1 0 1.42Z"/><path d="M12 9.5v3a1 1 0 0 1-1 1H1.5a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1h3"/></g></svg> Logbooks</a>
            <a href="final_report.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 9l-3 .54L5 6.5L10.73.79a1 1 0 0 1 1.42 0l1.06 1.06a1 1 0 0 1 0 1.42Z"/><path d="M12 9.5v3a1 1 0 0 1-1 1H1.5a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1h3"/></g></svg> Final Report</a>
            <a href="../../logout.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="M9.5 10.5v2a1 1 0 0 1-1 1h-7a1 1 0 0 1-1-1v-11a1 1 0 0 1 1-1h7a1 1 0 0 1 1 1v2M6.5 7h7m-2-2l2 2l-2 2"/></svg> Logout</a>
        </div>
    </div>
    
    <div class="content">
        <h3><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:0.5rem;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 9l-3 .54L5 6.5L10.73.79a1 1 0 0 1 1.42 0l1.06 1.06a1 1 0 0 1 0 1.42Z"/><path d="M12 9.5v3a1 1 0 0 1-1 1H1.5a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1h3"/></g></svg>Weekly Logbooks</h3>
        <?php displayMessage(); ?>
        
        <div class="row">
            <div class="col-md-5">
                <div class="card mb-4">
                    <div class="card-header bg-white"><h5>Submit New Logbook</h5></div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label>Week Number</label>
                                <input type="number" name="week_number" class="form-control" required min="1" max="12">
                            </div>
                            <div class="mb-3">
                                <label>Activities Done</label>
                                <textarea name="activities" class="form-control" rows="4" required></textarea>
                            </div>
                            <div class="mb-3">
                                <label>Challenges Faced</label>
                                <textarea name="challenges" class="form-control" rows="2"></textarea>
                            </div>
                            <div class="mb-3">
                                <label>Plans for Next Week</label>
                                <textarea name="plans" class="form-control" rows="2"></textarea>
                            </div>
                            <button type="submit" name="submit_logbook" class="btn btn-submit">Submit Logbook</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-7">
                <div class="card">
                    <div class="card-header bg-white"><h5>Submitted Logbooks</h5></div>
                    <div class="card-body">
                        <?php if($logbooks->num_rows > 0): ?>
                            <?php while($log = $logbooks->fetch_assoc()): ?>
                                <div class="border-bottom mb-3 pb-3">
                                    <h6>Week <?php echo $log['week_number']; ?> - <small class="text-muted">Submitted: <?php echo date('d M Y', strtotime($log['submitted_at'])); ?></small></h6>
                                    <p><strong>Activities:</strong> <?php echo nl2br(htmlspecialchars($log['activities'])); ?></p>
                                    <?php if($log['challenges']): ?>
                                        <p><strong>Challenges:</strong> <?php echo nl2br(htmlspecialchars($log['challenges'])); ?></p>
                                    <?php endif; ?>
                                    <?php if($log['supervisor_comments']): ?>
                                        <p class="text-primary"><strong>Supervisor Feedback:</strong> <?php echo nl2br(htmlspecialchars($log['supervisor_comments'])); ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p class="text-muted">No logbooks submitted yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>