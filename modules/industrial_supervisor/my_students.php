<?php
require_once '../../config/database.php';
if (!isLoggedIn() || !hasRole('industrial_supervisor')) { redirect('index.php'); }

$user_id = $_SESSION['user_id'];
$sup = $conn->query("SELECT is2.*, o.name AS org_name FROM industrial_supervisors is2 JOIN organizations o ON is2.organization_id=o.id WHERE is2.user_id=$user_id")->fetch_assoc();
$org_id = $sup['organization_id'];

$students = $conn->query("
    SELECT s.*, a.status AS alloc_status, a.allocated_at, u.email,
           COUNT(l.id) AS logbook_count,
           SUM(CASE WHEN l.status='submitted' THEN 1 ELSE 0 END) AS submitted_logs,
           SUM(CASE WHEN l.supervisor_comments IS NOT NULL AND l.supervisor_comments!='' THEN 1 ELSE 0 END) AS commented_logs
    FROM allocations a
    JOIN students s ON a.student_id = s.id
    JOIN users u ON s.user_id = u.id
    LEFT JOIN logbooks l ON s.id = l.student_id
    WHERE a.organization_id = $org_id
    GROUP BY s.id ORDER BY s.full_name
");
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>My Students - IAMS</title>
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
    </style>
</head>
<body>
<div class="sidebar">
    <div class="sidebar-header"><svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M7 13.5a6.5 6.5 0 1 0 0-13a6.5 6.5 0 0 0 0 13M5.5 10h3"/><path d="M7 10V6.5H6m1-2.25a.25.25 0 0 1 0-.5m0 .5a.25.25 0 0 0 0-.5"/></g></svg><h6 class="mb-0"><?php echo htmlspecialchars($sup['full_name']); ?></h6><small style="color:rgba(255,255,255,0.7);"><?php echo htmlspecialchars($sup['org_name']); ?></small></div>
    <div class="sidebar-menu">
        <a href="dashboard.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="M13.5 6.94a1 1 0 0 0-.32-.74L7 .5L.82 6.2a1 1 0 0 0-.32.74v5.56a1 1 0 0 0 1 1h11a1 1 0 0 0 1-1zM7 13.5v-4"/></svg> Dashboard</a>
        <a href="view_logbooks.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 9l-3 .54L5 6.5L10.73.79a1 1 0 0 1 1.42 0l1.06 1.06a1 1 0 0 1 0 1.42Z"/><path d="M12 9.5v3a1 1 0 0 1-1 1H1.5a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1h3"/></g></svg> Student Logbooks</a>
        <a href="my_students.php" class="active"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M13.18 13.5a6.49 6.49 0 0 0-12.36 0z"/><path d="M7 9A4.232 4.232 0 1 0 7 .536A4.232 4.232 0 0 0 7 9"/><path d="M8.382 6.405s-.351.691-1.382.691s-1.382-.69-1.382-.69m5.537-2.444h-.028a4.12 4.12 0 0 1-3.09-1.392a4.12 4.12 0 0 1-3.091 1.392a4.1 4.1 0 0 1-1.973-.5a4.234 4.234 0 0 1 8.182.5"/></g></svg> My Students</a>
        <a href="submit_report.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M7 13.5a6.5 6.5 0 1 0 0-13a6.5 6.5 0 0 0 0 13M5.5 10h3"/><path d="M7 10V6.5H6m1-2.25a.25.25 0 0 1 0-.5m0 .5a.25.25 0 0 0 0-.5"/></g></svg> Submit Report</a>
        <a href="my_reports.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="m1.24 6.54l11.5-5.23M10.59.5l2.15.81l-.8 2.15m1.31 10.05h-2.5h0v-7a.5.5 0 0 1 .5-.5h1.5a.5.5 0 0 1 .5.5v7h0Zm-5 0h-2.5h0v-5.5a.5.5 0 0 1 .5-.5h1.5a.5.5 0 0 1 .5.5v5.5h0Zm-5 0H.75h0v-4a.5.5 0 0 1 .5-.5h1.5a.5.5 0 0 1 .5.5v4h0Z"/></svg> My Reports</a>
        <a href="../../logout.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="M9.5 10.5v2a1 1 0 0 1-1 1h-7a1 1 0 0 1-1-1v-11a1 1 0 0 1 1-1h7a1 1 0 0 1 1 1v2M6.5 7h7m-2-2l2 2l-2 2"/></svg> Logout</a>
    </div>
</div>
<div class="content">
    <h4 class="mb-4"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:0.5rem;color:var(--bs-success);"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M13.18 13.5a6.49 6.49 0 0 0-12.36 0z"/><path d="M7 9A4.232 4.232 0 1 0 7 .536A4.232 4.232 0 0 0 7 9"/><path d="M8.382 6.405s-.351.691-1.382.691s-1.382-.69-1.382-.69m5.537-2.444h-.028a4.12 4.12 0 0 1-3.09-1.392a4.12 4.12 0 0 1-3.091 1.392a4.1 4.1 0 0 1-1.973-.5a4.234 4.234 0 0 1 8.182.5"/></g></svg>My Allocated Students — <?php echo htmlspecialchars($sup['org_name']); ?></h4>
    <?php displayMessage(); ?>
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr class="table-light"><th class="ps-4">Student ID</th><th>Name</th><th>Email</th><th>Programme</th><th>Year</th><th>Logbooks</th><th>Submitted</th><th>Commented</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php while($r=$students->fetch_assoc()): ?>
                    <tr>
                        <td class="ps-4"><strong><?php echo htmlspecialchars($r['student_id']); ?></strong></td>
                        <td><?php echo htmlspecialchars($r['full_name']); ?></td>
                        <td style="font-size:0.82rem;"><?php echo htmlspecialchars($r['email']); ?></td>
                        <td><?php echo htmlspecialchars($r['program']); ?></td>
                        <td>Year <?php echo $r['year_of_study']; ?></td>
                        <td><span class="badge bg-primary"><?php echo $r['logbook_count']; ?></span></td>
                        <td><span class="badge bg-info text-dark"><?php echo $r['submitted_logs']; ?></span></td>
                        <td><span class="badge bg-success"><?php echo $r['commented_logs']; ?></span></td>
                        <td>
                            <a href="view_logbooks.php?student_id=<?php echo $r['id']; ?>" class="btn btn-sm btn-outline-primary me-1"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 9l-3 .54L5 6.5L10.73.79a1 1 0 0 1 1.42 0l1.06 1.06a1 1 0 0 1 0 1.42Z"/><path d="M12 9.5v3a1 1 0 0 1-1 1H1.5a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1h3"/></g></svg></a>
                            <a href="submit_report.php?student_id=<?php echo $r['id']; ?>" class="btn btn-sm btn-outline-success"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M7 13.5a6.5 6.5 0 1 0 0-13a6.5 6.5 0 0 0 0 13M5.5 10h3"/><path d="M7 10V6.5H6m1-2.25a.25.25 0 0 1 0-.5m0 .5a.25.25 0 0 0 0-.5"/></g></svg></a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body></html>
