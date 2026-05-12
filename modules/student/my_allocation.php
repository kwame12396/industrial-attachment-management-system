<?php
// modules/student/my_allocation.php
require_once '../../config/database.php';

if (!isLoggedIn() || !hasRole('student')) {
    redirect('index.php');
}

$student = $conn->query("SELECT * FROM students WHERE user_id = {$_SESSION['user_id']}")->fetch_assoc();
$student_id = $student['id'];

$allocation = $conn->query("
    SELECT a.*, o.name as org_name, o.location, o.contact_person, o.contact_email, o.contact_phone, o.description
    FROM allocations a
    JOIN organizations o ON a.organization_id = o.id
    WHERE a.student_id = $student_id
")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Allocation - IAMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f0f2f5; }
        .sidebar { position: fixed; top: 0; left: 0; height: 100vh; width: 260px; background: linear-gradient(135deg, #1a1a2e, #16213e); color: white; }
        .sidebar-header { padding: 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-menu a { display: block; padding: 12px 25px; color: rgba(255,255,255,0.8); text-decoration: none; transition: all 0.3s; }
        .sidebar-menu a:hover, .sidebar-menu a.active { background: rgba(255,255,255,0.1); color: white; padding-left: 30px; }
        .sidebar-menu a i { margin-right: 10px; width: 25px; }
        .content { margin-left: 260px; padding: 20px; }
        .info-card { background: white; border-radius: 15px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header"><svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="m7 1.367l6.5 2.817L7 7L.5 4.184z"/><path d="m3.45 5.469l.006 3.064S4.529 9.953 7 9.953s3.55-1.42 3.55-1.42l-.001-3.064m-8.854 5.132v-5.89m.001 8.282a1.196 1.196 0 1 0 0-2.392a1.196 1.196 0 0 0 0 2.392"/></g></svg><h4>Student Portal</h4></div>
        <div class="sidebar-menu">
            <a href="dashboard.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="M13.5 6.94a1 1 0 0 0-.32-.74L7 .5L.82 6.2a1 1 0 0 0-.32.74v5.56a1 1 0 0 0 1 1h11a1 1 0 0 0 1-1zM7 13.5v-4"/></svg> Dashboard</a>
            <a href="my_allocation.php" class="active"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="M8.461 4.75V1.594c0-.56-.454-1.015-1.015-1.015h-4.79c-.562 0-1.016.454-1.016 1.015v11.827m-1.121 0h4.968M1.64 3.187H4.1M1.64 5.75h3.847m4.993 4.282a1.75 1.75 0 1 0 0-3.5a1.75 1.75 0 0 0 0 3.5m-3.001 3.389a3.04 3.04 0 0 1 .39-1.46a3.03 3.03 0 0 1 2.611-1.537a3.03 3.03 0 0 1 2.612 1.538c.25.445.385.947.39 1.459"/></svg> My Allocation</a>
            <a href="logbooks.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 9l-3 .54L5 6.5L10.73.79a1 1 0 0 1 1.42 0l1.06 1.06a1 1 0 0 1 0 1.42Z"/><path d="M12 9.5v3a1 1 0 0 1-1 1H1.5a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1h3"/></g></svg> Logbooks</a>
            <a href="final_report.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 9l-3 .54L5 6.5L10.73.79a1 1 0 0 1 1.42 0l1.06 1.06a1 1 0 0 1 0 1.42Z"/><path d="M12 9.5v3a1 1 0 0 1-1 1H1.5a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1h3"/></g></svg> Final Report</a>
            <a href="../../logout.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="M9.5 10.5v2a1 1 0 0 1-1 1h-7a1 1 0 0 1-1-1v-11a1 1 0 0 1 1-1h7a1 1 0 0 1 1 1v2M6.5 7h7m-2-2l2 2l-2 2"/></svg> Logout</a>
        </div>
    </div>
    
    <div class="content">
        <h3><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:0.5rem;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="M8.461 4.75V1.594c0-.56-.454-1.015-1.015-1.015h-4.79c-.562 0-1.016.454-1.016 1.015v11.827m-1.121 0h4.968M1.64 3.187H4.1M1.64 5.75h3.847m4.993 4.282a1.75 1.75 0 1 0 0-3.5a1.75 1.75 0 0 0 0 3.5m-3.001 3.389a3.04 3.04 0 0 1 .39-1.46a3.03 3.03 0 0 1 2.611-1.537a3.03 3.03 0 0 1 2.612 1.538c.25.445.385.947.39 1.459"/></svg>My Attachment Allocation</h3>
        
        <?php if($allocation): ?>
            <div class="info-card">
                <div class="row">
                    <div class="col-md-8">
                        <h4><?php echo htmlspecialchars($allocation['org_name']); ?></h4>
                        <p><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:0.5rem;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M7 13.5a6.5 6.5 0 1 0 0-13a6.5 6.5 0 0 0 0 13M5.5 10h3"/><path d="M7 10V6.5H6m1-2.25a.25.25 0 0 1 0-.5m0 .5a.25.25 0 0 0 0-.5"/></g></svg><?php echo $allocation['location']; ?></p>
                        <hr>
                        <p><strong>Contact Person:</strong> <?php echo $allocation['contact_person']; ?></p>
                        <p><strong>Email:</strong> <?php echo $allocation['contact_email']; ?></p>
                        <p><strong>Phone:</strong> <?php echo $allocation['contact_phone']; ?></p>
                        <p><strong>Description:</strong> <?php echo nl2br($allocation['description']); ?></p>
                    </div>
                    <div class="col-md-4 text-center">
                        <div class="alert alert-success mt-4">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="m.5 8.55l2.73 3.51a1 1 0 0 0 1.56.03L13.5 1.55"/></svg>
                            <p class="mt-2"><strong>Status: <?php echo ucfirst($allocation['status']); ?></strong></p>
                            <small>Allocated on <?php echo date('d M Y', strtotime($allocation['allocated_at'])); ?></small>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-warning">You have not been allocated to any organization yet. The coordinator will assign you soon.</div>
        <?php endif; ?>
    </div>
</body>
</html>