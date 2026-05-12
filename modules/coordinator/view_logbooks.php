<?php
// modules/coordinator/view_logbooks.php
require_once '../../config/database.php';

if (!isLoggedIn() || !hasRole('coordinator')) { redirect('index.php'); }

$students = $conn->query("
    SELECT s.id, s.full_name, s.student_id, COUNT(l.id) AS logbook_count,
           SUM(CASE WHEN l.status='submitted' AND (l.supervisor_comments IS NULL OR l.supervisor_comments='') THEN 1 ELSE 0 END) AS pending_review
    FROM students s
    LEFT JOIN logbooks l ON s.id = l.student_id
    GROUP BY s.id ORDER BY s.full_name
");

$selected_student = null;
$logbooks = null;
if (isset($_GET['student_id'])) {
    $student_id = (int)$_GET['student_id'];
    $selected_student = $conn->query("SELECT * FROM students WHERE id = $student_id")->fetch_assoc();
    $logbooks = $conn->query("SELECT * FROM logbooks WHERE student_id = $student_id ORDER BY week_number ASC");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Logbooks - IAMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background:#f0f2f5; font-family:'Segoe UI',Tahoma,sans-serif; }
        .sidebar { position:fixed; top:0; left:0; height:100vh; width:260px; background:linear-gradient(135deg,#1a1a2e,#16213e); color:white; overflow-y:auto; }
        .sidebar-header { padding:20px; text-align:center; border-bottom:1px solid rgba(255,255,255,0.1); }
        .sidebar-menu a { display:block; padding:12px 25px; color:rgba(255,255,255,0.8); text-decoration:none; transition:all 0.3s; }
        .sidebar-menu a:hover, .sidebar-menu a.active { background:rgba(255,255,255,0.1); color:white; padding-left:30px; }
        .sidebar-menu a i { margin-right:10px; width:20px; }
        .content { margin-left:260px; padding:24px; }
        .card { border-radius:16px; border:none; box-shadow:0 2px 12px rgba(0,0,0,0.06); }
        .student-item { padding:10px 16px; border-bottom:1px solid #f0f0f0; display:flex; justify-content:space-between; align-items:center; transition:background 0.2s; }
        .student-item:hover { background:#f8f9fa; }
        .student-item a { text-decoration:none; color:#1a1a2e; font-weight:500; }
        .logbook-entry { background:white; border-radius:14px; padding:18px; margin-bottom:16px; box-shadow:0 1px 6px rgba(0,0,0,0.05); border-left:4px solid #4361ee; }
        .logbook-entry.reviewed { border-left-color:#10b981; }
        .comment-box { background:#f0fdf4; border:1px solid #bbf7d0; border-radius:12px; padding:14px 16px; margin-top:12px; }
        .comment-box .comment-header { font-size:0.8rem; color:#059669; font-weight:600; margin-bottom:6px; }
        .comment-box .comment-text { color:#065f46; }
        .comment-form-wrap { background:#f8f9fa; border-radius:12px; padding:14px; margin-top:10px; border:1px solid #dee2e6; }
        .week-badge { display:inline-block; background:#4361ee; color:white; border-radius:20px; padding:2px 12px; font-size:0.8rem; font-weight:600; }
        .status-badge { font-size:0.75rem; padding:3px 10px; border-radius:20px; }
    </style>
</head>
<body>
<div class="sidebar">
    <div class="sidebar-header">
        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="M8.461 4.75V1.594c0-.56-.454-1.015-1.015-1.015h-4.79c-.562 0-1.016.454-1.016 1.015v11.827m-1.121 0h4.968M1.64 3.187H4.1M1.64 5.75h3.847m4.993 4.282a1.75 1.75 0 1 0 0-3.5a1.75 1.75 0 0 0 0 3.5m-3.001 3.389a3.04 3.04 0 0 1 .39-1.46a3.03 3.03 0 0 1 2.611-1.537a3.03 3.03 0 0 1 2.612 1.538c.25.445.385.947.39 1.459"/></svg>
        <h5 class="mb-0">IAMS</h5>
        <small style="color:rgba(255,255,255,0.85);">Coordinator Portal</small>
    </div>
    <div class="sidebar-menu">
        <a href="dashboard.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="M13.5 6.94a1 1 0 0 0-.32-.74L7 .5L.82 6.2a1 1 0 0 0-.32.74v5.56a1 1 0 0 0 1 1h11a1 1 0 0 0 1-1zM7 13.5v-4"/></svg> Dashboard</a>
        <a href="students.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="m7 1.367l6.5 2.817L7 7L.5 4.184z"/><path d="m3.45 5.469l.006 3.064S4.529 9.953 7 9.953s3.55-1.42 3.55-1.42l-.001-3.064m-8.854 5.132v-5.89m.001 8.282a1.196 1.196 0 1 0 0-2.392a1.196 1.196 0 0 0 0 2.392"/></g></svg> Students</a>
        <a href="organizations.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="M8.461 4.75V1.594c0-.56-.454-1.015-1.015-1.015h-4.79c-.562 0-1.016.454-1.016 1.015v11.827m-1.121 0h4.968M1.64 3.187H4.1M1.64 5.75h3.847m4.993 4.282a1.75 1.75 0 1 0 0-3.5a1.75 1.75 0 0 0 0 3.5m-3.001 3.389a3.04 3.04 0 0 1 .39-1.46a3.03 3.03 0 0 1 2.611-1.537a3.03 3.03 0 0 1 2.612 1.538c.25.445.385.947.39 1.459"/></svg> Organisations</a>
        <a href="matching.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="m.5 8.55l2.73 3.51a1 1 0 0 0 1.56.03L13.5 1.55"/></svg> Matching</a>
        <a href="allocations.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 9l-3 .54L5 6.5L10.73.79a1 1 0 0 1 1.42 0l1.06 1.06a1 1 0 0 1 0 1.42Z"/><path d="M12 9.5v3a1 1 0 0 1-1 1H1.5a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1h3"/></g></svg> Allocations</a>
        <a href="view_logbooks.php" class="active"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 9l-3 .54L5 6.5L10.73.79a1 1 0 0 1 1.42 0l1.06 1.06a1 1 0 0 1 0 1.42Z"/><path d="M12 9.5v3a1 1 0 0 1-1 1H1.5a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1h3"/></g></svg> View Logbooks</a>
        <a href="view_final_reports.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 9l-3 .54L5 6.5L10.73.79a1 1 0 0 1 1.42 0l1.06 1.06a1 1 0 0 1 0 1.42Z"/><path d="M12 9.5v3a1 1 0 0 1-1 1H1.5a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1h3"/></g></svg> Final Reports</a>
        <a href="reports.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="m1.24 6.54l11.5-5.23M10.59.5l2.15.81l-.8 2.15m1.31 10.05h-2.5h0v-7a.5.5 0 0 1 .5-.5h1.5a.5.5 0 0 1 .5.5v7h0Zm-5 0h-2.5h0v-5.5a.5.5 0 0 1 .5-.5h1.5a.5.5 0 0 1 .5.5v5.5h0Zm-5 0H.75h0v-4a.5.5 0 0 1 .5-.5h1.5a.5.5 0 0 1 .5.5v4h0Z"/></svg> Assessments</a>
        <a href="reminders.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="M6 13.25h2m3-7.5a4 4 0 0 0-8 0v3.5a1.5 1.5 0 0 1-1.5 1.5h11a1.5 1.5 0 0 1-1.5-1.5ZM.5 5.62A6 6 0 0 1 3 .75m10.5 4.87A6 6 0 0 0 11 .75"/></svg> Reminders</a>
        <a href="../../logout.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="M9.5 10.5v2a1 1 0 0 1-1 1h-7a1 1 0 0 1-1-1v-11a1 1 0 0 1 1-1h7a1 1 0 0 1 1 1v2M6.5 7h7m-2-2l2 2l-2 2"/></svg> Logout</a>
    </div>
</div>

<div class="content">
    <h4 class="mb-4"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:0.5rem;color:var(--bs-primary);"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 9l-3 .54L5 6.5L10.73.79a1 1 0 0 1 1.42 0l1.06 1.06a1 1 0 0 1 0 1.42Z"/><path d="M12 9.5v3a1 1 0 0 1-1 1H1.5a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1h3"/></g></svg>Student Logbooks</h4>
    <?php displayMessage(); ?>

    <div class="row">
        <!-- Student list -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:0.5rem;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M13.18 13.5a6.49 6.49 0 0 0-12.36 0z"/><path d="M7 9A4.232 4.232 0 1 0 7 .536A4.232 4.232 0 0 0 7 9"/><path d="M8.382 6.405s-.351.691-1.382.691s-1.382-.69-1.382-.69m5.537-2.444h-.028a4.12 4.12 0 0 1-3.09-1.392a4.12 4.12 0 0 1-3.091 1.392a4.1 4.1 0 0 1-1.973-.5a4.234 4.234 0 0 1 8.182.5"/></g></svg>Students</h6>
                </div>
                <div class="card-body p-0">
                    <?php while($row = $students->fetch_assoc()): ?>
                    <div class="student-item <?php echo (isset($student_id) && $student_id == $row['id']) ? 'bg-light' : ''; ?>">
                        <div>
                            <a href="?student_id=<?php echo $row['id']; ?>"><?php echo htmlspecialchars($row['full_name']); ?></a>
                            <div style="font-size:0.75rem;color:#555;"><?php echo htmlspecialchars($row['student_id']); ?></div>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-primary rounded-pill"><?php echo $row['logbook_count']; ?> logs</span>
                            <?php if($row['pending_review'] > 0): ?>
                                <br><span class="badge bg-warning text-dark mt-1" style="font-size:0.68rem;"><?php echo $row['pending_review']; ?> pending</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>

        <!-- Logbook detail -->
        <div class="col-md-8">
            <?php if($selected_student && $logbooks): ?>
                <div class="card">
                    <div class="card-header bg-white py-3">
                        <h6 class="mb-0">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:0.5rem;color:var(--bs-primary);"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="m7 1.367l6.5 2.817L7 7L.5 4.184z"/><path d="m3.45 5.469l.006 3.064S4.529 9.953 7 9.953s3.55-1.42 3.55-1.42l-.001-3.064m-8.854 5.132v-5.89m.001 8.282a1.196 1.196 0 1 0 0-2.392a1.196 1.196 0 0 0 0 2.392"/></g></svg>
                            <?php echo htmlspecialchars($selected_student['full_name']); ?>
                            <span class="text-muted fw-normal"> — <?php echo htmlspecialchars($selected_student['student_id']); ?></span>
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php if($logbooks->num_rows > 0): ?>
                            <?php while($log = $logbooks->fetch_assoc()): ?>
                            <div class="logbook-entry <?php echo $log['status'] === 'reviewed' ? 'reviewed' : ''; ?>">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <span class="week-badge">Week <?php echo $log['week_number']; ?></span>
                                        <?php if($log['week_start_date']): ?>
                                            <small class="text-muted ms-2"><?php echo date('d M', strtotime($log['week_start_date'])); ?> – <?php echo $log['week_end_date'] ? date('d M Y', strtotime($log['week_end_date'])) : ''; ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <?php
                                            $sc = ['draft'=>'secondary','submitted'=>'warning','reviewed'=>'success'];
                                            $st = $log['status'];
                                        ?>
                                        <span class="badge bg-<?php echo $sc[$st]??'secondary'; ?> status-badge"><?php echo ucfirst($st); ?></span>
                                    </div>
                                </div>

                                <p class="mb-1"><strong>Activities:</strong> <?php echo nl2br(htmlspecialchars($log['activities'])); ?></p>
                                <?php if($log['challenges']): ?><p class="mb-1"><strong>Challenges:</strong> <?php echo nl2br(htmlspecialchars($log['challenges'])); ?></p><?php endif; ?>
                                <?php if($log['plans']): ?><p class="mb-1"><strong>Plans:</strong> <?php echo nl2br(htmlspecialchars($log['plans'])); ?></p><?php endif; ?>

                                <?php if($log['supervisor_comments']): ?>
                                    <div class="comment-box">
                                        <div class="comment-header">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:0.25rem;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M7 13.5a6.5 6.5 0 1 0 0-13a6.5 6.5 0 0 0 0 13M5.5 10h3"/><path d="M7 10V6.5H6m1-2.25a.25.25 0 0 1 0-.5m0 .5a.25.25 0 0 0 0-.5"/></g></svg>
                                            Coordinator Comment
                                            <?php if($log['commented_by']): ?>
                                                &mdash; by <?php echo htmlspecialchars($log['commented_by']); ?>
                                                <?php if($log['commented_at']): ?>
                                                    on <?php echo date('d M Y, H:i', strtotime($log['commented_at'])); ?>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="comment-text"><?php echo nl2br(htmlspecialchars($log['supervisor_comments'])); ?></div>
                                    </div>
                                <?php endif; ?>

                                <!-- Comment form -->
                                <div class="mt-2">
                                    <button class="btn btn-sm btn-outline-primary" onclick="toggleComment(<?php echo $log['id']; ?>)">
                                        <i class="fas fa-<?php echo $log['supervisor_comments'] ? 'edit' : 'plus'; ?> me-1"></i>
                                        <?php echo $log['supervisor_comments'] ? 'Edit Comment' : 'Add Comment'; ?>
                                    </button>
                                    <div id="cf-<?php echo $log['id']; ?>" style="display:none;">
                                        <form method="POST" action="add_comment.php" class="comment-form-wrap mt-2">
                                            <input type="hidden" name="logbook_id" value="<?php echo $log['id']; ?>">
                                            <input type="hidden" name="student_id" value="<?php echo $selected_student['id']; ?>">
                                            <label class="form-label small mb-1"><strong>Your comment:</strong></label>
                                            <textarea name="comment" class="form-control form-control-sm mb-2" rows="2" placeholder="Write your feedback…" required><?php echo htmlspecialchars($log['supervisor_comments'] ?? ''); ?></textarea>
                                            <button type="submit" class="btn btn-sm btn-success"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:0.25rem;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M7 13.5a6.5 6.5 0 1 0 0-13a6.5 6.5 0 0 0 0 13M5.5 10h3"/><path d="M7 10V6.5H6m1-2.25a.25.25 0 0 1 0-.5m0 .5a.25.25 0 0 0 0-.5"/></g></svg>Post Comment</button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary ms-1" onclick="toggleComment(<?php echo $log['id']; ?>)">Cancel</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p class="text-muted text-center py-4"><svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 9l-3 .54L5 6.5L10.73.79a1 1 0 0 1 1.42 0l1.06 1.06a1 1 0 0 1 0 1.42Z"/><path d="M12 9.5v3a1 1 0 0 1-1 1H1.5a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1h3"/></g></svg>No logbooks submitted yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="card"><div class="card-body text-center text-muted py-5">
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="M3.85.5L10 6.65a.48.48 0 0 1 0 .7L3.85 13.5"/></svg>Select a student from the left to view their logbooks.
                </div></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleComment(id) {
    var el = document.getElementById('cf-' + id);
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
}
</script>
</body>
</html>
