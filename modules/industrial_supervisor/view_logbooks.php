<?php
require_once '../../config/database.php';
if (!isLoggedIn() || !hasRole('industrial_supervisor')) { redirect('index.php'); }
$user_id = $_SESSION['user_id'];
$sup = $conn->query("SELECT is2.*, o.name AS org_name FROM industrial_supervisors is2 JOIN organizations o ON is2.organization_id=o.id WHERE is2.user_id=$user_id")->fetch_assoc();
$org_id = $sup['organization_id'];
$sup_id = $sup['id'];

// Handle comment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['logbook_id'])) {
    $lid     = (int)$_POST['logbook_id'];
    $comment = trim($_POST['comment'] ?? '');
    $sid_ret = (int)($_POST['student_id'] ?? 0);
    if ($lid && !empty($comment)) {
        $name = $sup['full_name'];
        $now  = date('Y-m-d H:i:s');
        $stmt = $conn->prepare("UPDATE logbooks SET supervisor_comments=?, commented_by=?, commented_at=?, status='reviewed' WHERE id=?");
        $stmt->bind_param("sssi", $comment, $name, $now, $lid);
        $stmt->execute();
        logActivity('ISUP_COMMENT', "Industrial supervisor commented on logbook #$lid");
        $_SESSION['success'] = "Comment posted on Week logbook.";
    }
    redirect('modules/industrial_supervisor/view_logbooks.php' . ($sid_ret ? "?student_id=$sid_ret" : ''));
}

$students = $conn->query("SELECT s.id, s.full_name, s.student_id, COUNT(l.id) AS lc, SUM(CASE WHEN l.status='submitted' AND (l.supervisor_comments IS NULL OR l.supervisor_comments='') THEN 1 ELSE 0 END) AS pending FROM allocations a JOIN students s ON a.student_id=s.id LEFT JOIN logbooks l ON s.id=l.student_id WHERE a.organization_id=$org_id GROUP BY s.id ORDER BY s.full_name");
$selected_student = null; $logbooks = null;
if (isset($_GET['student_id'])) {
    $student_id = (int)$_GET['student_id'];
    $selected_student = $conn->query("SELECT * FROM students WHERE id=$student_id")->fetch_assoc();
    $logbooks = $conn->query("SELECT * FROM logbooks WHERE student_id=$student_id ORDER BY week_number ASC");
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Student Logbooks - IAMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body{background:#f0f2f5;font-family:'Segoe UI',Tahoma,sans-serif;}
        .sidebar{position:fixed;top:0;left:0;height:100vh;width:260px;background:linear-gradient(135deg,#064e3b,#065f46);color:white;overflow-y:auto;}
        .sidebar-header{padding:20px;text-align:center;border-bottom:1px solid rgba(255,255,255,0.1);}
        .sidebar-menu a{display:block;padding:12px 25px;color:rgba(255,255,255,0.8);text-decoration:none;transition:all 0.3s;}
        .sidebar-menu a:hover,.sidebar-menu a.active{background:rgba(255,255,255,0.1);color:white;padding-left:30px;}
        .sidebar-menu a i{margin-right:10px;width:20px;}
        .content{margin-left:260px;padding:24px;}
        .card{border-radius:16px;border:none;box-shadow:0 2px 12px rgba(0,0,0,0.06);}
        .logbook-entry{background:white;border-radius:14px;padding:18px;margin-bottom:14px;box-shadow:0 1px 6px rgba(0,0,0,0.05);border-left:4px solid #10b981;}
        .logbook-entry.reviewed{border-left-color:#4361ee;}
        .comment-box{background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:12px 14px;margin-top:10px;}
        .comment-header{font-size:0.78rem;color:#059669;font-weight:600;margin-bottom:4px;}
        .student-item{padding:10px 16px;border-bottom:1px solid #f0f0f0;display:flex;justify-content:space-between;align-items:center;cursor:pointer;text-decoration:none;color:inherit;}
        .student-item:hover{background:#f8f9fa;}
        .student-item .student-name{text-decoration:none;color:#1a1a2e;font-weight:500;pointer-events:none;}
    </style>
</head>
<body>
<div class="sidebar">
    <div class="sidebar-header"><svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M7 13.5a6.5 6.5 0 1 0 0-13a6.5 6.5 0 0 0 0 13M5.5 10h3"/><path d="M7 10V6.5H6m1-2.25a.25.25 0 0 1 0-.5m0 .5a.25.25 0 0 0 0-.5"/></g></svg><h6 class="mb-0"><?php echo htmlspecialchars($sup['full_name']); ?></h6><small style="color:rgba(255,255,255,0.7);"><?php echo htmlspecialchars($sup['org_name']); ?></small></div>
    <div class="sidebar-menu">
        <a href="dashboard.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="M13.5 6.94a1 1 0 0 0-.32-.74L7 .5L.82 6.2a1 1 0 0 0-.32.74v5.56a1 1 0 0 0 1 1h11a1 1 0 0 0 1-1zM7 13.5v-4"/></svg> Dashboard</a>
        <a href="view_logbooks.php" class="active"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 9l-3 .54L5 6.5L10.73.79a1 1 0 0 1 1.42 0l1.06 1.06a1 1 0 0 1 0 1.42Z"/><path d="M12 9.5v3a1 1 0 0 1-1 1H1.5a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1h3"/></g></svg> Student Logbooks</a>
        <a href="my_students.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M13.18 13.5a6.49 6.49 0 0 0-12.36 0z"/><path d="M7 9A4.232 4.232 0 1 0 7 .536A4.232 4.232 0 0 0 7 9"/><path d="M8.382 6.405s-.351.691-1.382.691s-1.382-.69-1.382-.69m5.537-2.444h-.028a4.12 4.12 0 0 1-3.09-1.392a4.12 4.12 0 0 1-3.091 1.392a4.1 4.1 0 0 1-1.973-.5a4.234 4.234 0 0 1 8.182.5"/></g></svg> My Students</a>
        <a href="submit_report.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M7 13.5a6.5 6.5 0 1 0 0-13a6.5 6.5 0 0 0 0 13M5.5 10h3"/><path d="M7 10V6.5H6m1-2.25a.25.25 0 0 1 0-.5m0 .5a.25.25 0 0 0 0-.5"/></g></svg> Submit Report</a>
        <a href="my_reports.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="m1.24 6.54l11.5-5.23M10.59.5l2.15.81l-.8 2.15m1.31 10.05h-2.5h0v-7a.5.5 0 0 1 .5-.5h1.5a.5.5 0 0 1 .5.5v7h0Zm-5 0h-2.5h0v-5.5a.5.5 0 0 1 .5-.5h1.5a.5.5 0 0 1 .5.5v5.5h0Zm-5 0H.75h0v-4a.5.5 0 0 1 .5-.5h1.5a.5.5 0 0 1 .5.5v4h0Z"/></svg> My Reports</a>
        <a href="../../logout.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="M9.5 10.5v2a1 1 0 0 1-1 1h-7a1 1 0 0 1-1-1v-11a1 1 0 0 1 1-1h7a1 1 0 0 1 1 1v2M6.5 7h7m-2-2l2 2l-2 2"/></svg> Logout</a>
    </div>
</div>
<div class="content">
    <h4 class="mb-4"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:0.5rem;color:var(--bs-success);"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 9l-3 .54L5 6.5L10.73.79a1 1 0 0 1 1.42 0l1.06 1.06a1 1 0 0 1 0 1.42Z"/><path d="M12 9.5v3a1 1 0 0 1-1 1H1.5a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1h3"/></g></svg>Student Logbooks</h4>
    <?php displayMessage(); ?>
    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-white py-3"><h6 class="mb-0">My Students</h6></div>
                <div class="card-body p-0">
                    <?php while($r=$students->fetch_assoc()): ?>
                    <a href="?student_id=<?php echo $r['id']; ?>" class="student-item <?php echo (isset($student_id)&&$student_id==$r['id'])?'bg-light':''; ?>">
                        <div><span class="student-name"><?php echo htmlspecialchars($r['full_name']); ?></span><div style="font-size:0.75rem;color:#555;"><?php echo htmlspecialchars($r['student_id']); ?></div></div>
                        <div><span class="badge bg-primary"><?php echo $r['lc']; ?></span><?php if($r['pending']>0): ?><br><span class="badge bg-warning text-dark mt-1"><?php echo $r['pending']; ?> pending</span><?php endif; ?></div>
                    </a>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <?php if($selected_student && $logbooks): ?>
            <div class="card">
                <div class="card-header bg-white py-3"><h6 class="mb-0"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:0.5rem;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M13.18 13.5a6.49 6.49 0 0 0-12.36 0z"/><path d="M7 9A4.232 4.232 0 1 0 7 .536A4.232 4.232 0 0 0 7 9"/><path d="M8.382 6.405s-.351.691-1.382.691s-1.382-.69-1.382-.69m5.537-2.444h-.028a4.12 4.12 0 0 1-3.09-1.392a4.12 4.12 0 0 1-3.091 1.392a4.1 4.1 0 0 1-1.973-.5a4.234 4.234 0 0 1 8.182.5"/></g></svg><?php echo htmlspecialchars($selected_student['full_name']); ?> — <?php echo htmlspecialchars($selected_student['student_id']); ?></h6></div>
                <div class="card-body">
                    <?php if($logbooks->num_rows > 0): while($log=$logbooks->fetch_assoc()): ?>
                    <div class="logbook-entry <?php echo $log['status']==='reviewed'?'reviewed':''; ?>">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="badge bg-success">Week <?php echo $log['week_number']; ?></span>
                            <span class="badge bg-<?php echo ['draft'=>'secondary','submitted'=>'warning','reviewed'=>'success'][$log['status']]??'secondary'; ?>"><?php echo ucfirst($log['status']); ?></span>
                        </div>
                        <p class="mb-1"><strong>Activities:</strong> <?php echo nl2br(htmlspecialchars($log['activities'])); ?></p>
                        <?php if($log['challenges']): ?><p class="mb-1"><strong>Challenges:</strong> <?php echo nl2br(htmlspecialchars($log['challenges'])); ?></p><?php endif; ?>
                        <?php if($log['supervisor_comments']): ?>
                        <div class="comment-box">
                            <div class="comment-header"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:0.25rem;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M7 13.5a6.5 6.5 0 1 0 0-13a6.5 6.5 0 0 0 0 13M5.5 10h3"/><path d="M7 10V6.5H6m1-2.25a.25.25 0 0 1 0-.5m0 .5a.25.25 0 0 0 0-.5"/></g></svg>Supervisor Comment<?php if($log['commented_at']): ?> — <?php echo date('d M Y H:i', strtotime($log['commented_at'])); ?><?php endif; ?></div>
                            <div><?php echo nl2br(htmlspecialchars($log['supervisor_comments'])); ?></div>
                        </div>
                        <?php endif; ?>
                        <div class="mt-2">
                            <button class="btn btn-sm btn-outline-success" onclick="toggleCf(<?php echo $log['id']; ?>)">
                                <i class="fas fa-<?php echo $log['supervisor_comments']?'edit':'plus'; ?> me-1"></i><?php echo $log['supervisor_comments']?'Edit Comment':'Add Comment'; ?>
                            </button>
                            <div id="cf-<?php echo $log['id']; ?>" style="display:none;margin-top:10px;">
                                <form method="POST">
                                    <input type="hidden" name="logbook_id" value="<?php echo $log['id']; ?>">
                                    <input type="hidden" name="student_id" value="<?php echo $selected_student['id']; ?>">
                                    <textarea name="comment" class="form-control form-control-sm mb-2" rows="2" required><?php echo htmlspecialchars($log['supervisor_comments']??''); ?></textarea>
                                    <button type="submit" class="btn btn-sm btn-success"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:0.25rem;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M7 13.5a6.5 6.5 0 1 0 0-13a6.5 6.5 0 0 0 0 13M5.5 10h3"/><path d="M7 10V6.5H6m1-2.25a.25.25 0 0 1 0-.5m0 .5a.25.25 0 0 0 0-.5"/></g></svg>Post</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary ms-1" onclick="toggleCf(<?php echo $log['id']; ?>)">Cancel</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; else: ?>
                    <p class="text-muted text-center py-4">No logbooks submitted yet.</p>
                    <?php endif; ?>
                </div>
            </div>
            <?php else: ?>
            <div class="card"><div class="card-body text-center text-muted py-5">Select a student to view their logbooks.</div></div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>function toggleCf(id){var el=document.getElementById('cf-'+id);el.style.display=el.style.display==='none'?'block':'none';}</script>
</body></html>
