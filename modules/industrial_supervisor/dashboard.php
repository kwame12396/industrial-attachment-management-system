<?php
// modules/industrial_supervisor/dashboard.php
require_once '../../config/database.php';

if (!isLoggedIn() || !hasRole('industrial_supervisor')) { redirect('index.php'); }

$user_id = $_SESSION['user_id'];

// Get supervisor + org info
$sup = $conn->query("
    SELECT is2.*, o.name AS org_name, o.location AS org_location, o.industry_type,
           o.capacity, o.description AS org_desc, o.contact_email, o.contact_phone
    FROM industrial_supervisors is2
    JOIN organizations o ON is2.organization_id = o.id
    WHERE is2.user_id = $user_id
")->fetch_assoc();

if (!$sup) { redirect('index.php'); }

$org_id = $sup['organization_id'];
$sup_id = $sup['id'];

// Stats
$allocated_students = $conn->query("SELECT COUNT(*) c FROM allocations WHERE organization_id = $org_id")->fetch_assoc()['c'];
$logbook_count      = $conn->query("SELECT COUNT(*) c FROM logbooks l JOIN allocations a ON l.student_id = a.student_id WHERE a.organization_id = $org_id")->fetch_assoc()['c'];
$report_count       = $conn->query("SELECT COUNT(*) c FROM industrial_reports WHERE supervisor_id = $sup_id")->fetch_assoc()['c'];
$pending_review     = $conn->query("SELECT COUNT(*) c FROM logbooks l JOIN allocations a ON l.student_id = a.student_id WHERE a.organization_id = $org_id AND l.status='submitted' AND (l.supervisor_comments IS NULL OR l.supervisor_comments='')")->fetch_assoc()['c'];

// My students
$students = $conn->query("
    SELECT s.*, a.status AS alloc_status, a.allocated_at,
           COUNT(l.id) AS logbook_count,
           SUM(CASE WHEN l.status='submitted' AND (l.supervisor_comments IS NULL OR l.supervisor_comments='') THEN 1 ELSE 0 END) AS pending_logs
    FROM allocations a
    JOIN students s ON a.student_id = s.id
    LEFT JOIN logbooks l ON s.id = l.student_id
    WHERE a.organization_id = $org_id
    GROUP BY s.id
    ORDER BY s.full_name
");

// Recent logbooks
$recent_logs = $conn->query("
    SELECT l.*, s.full_name, s.student_id AS sid
    FROM logbooks l
    JOIN students s ON l.student_id = s.id
    JOIN allocations a ON s.id = a.student_id
    WHERE a.organization_id = $org_id AND l.status = 'submitted'
    ORDER BY l.submitted_at DESC LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Industrial Supervisor - IAMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background:#f0f2f5; font-family:'Segoe UI',Tahoma,sans-serif; }
        .sidebar { position:fixed; top:0; left:0; height:100vh; width:260px; background:linear-gradient(135deg,#064e3b,#065f46); color:white; overflow-y:auto; }
        .sidebar-header { padding:20px; text-align:center; border-bottom:1px solid rgba(255,255,255,0.1); }
        .sidebar-menu a { display:block; padding:12px 25px; color:rgba(255,255,255,0.8); text-decoration:none; transition:all 0.3s; }
        .sidebar-menu a:hover, .sidebar-menu a.active { background:rgba(255,255,255,0.1); color:white; padding-left:30px; }
        .sidebar-menu a i { margin-right:10px; width:20px; }
        .content { margin-left:260px; padding:24px; }
        .stat-card { border-radius:16px; padding:20px; color:white; margin-bottom:1rem; }
        .card { border-radius:16px; border:none; box-shadow:0 2px 12px rgba(0,0,0,0.06); }
        .card-header { border-radius:16px 16px 0 0!important; background:white; border-bottom:1px solid #eee; }
        .student-card { background:white; border-radius:14px; padding:18px; margin-bottom:14px; box-shadow:0 1px 8px rgba(0,0,0,0.06); border-left:4px solid #10b981; transition:transform 0.2s; }
        .student-card:hover { transform:translateY(-2px); }
        .log-item { padding:12px 0; border-bottom:1px solid #f0f0f0; }
        .log-item:last-child { border-bottom:none; }
        .org-info-card { background:linear-gradient(135deg,#064e3b,#059669); color:white; border-radius:20px; padding:24px; margin-bottom:24px; }
        .notif-badge { background:#ef4444; color:white; border-radius:50%; width:20px; height:20px; font-size:0.7rem; display:inline-flex; align-items:center; justify-content:center; }
    </style>
</head>
<body>
<div class="sidebar">
    <div class="sidebar-header">
        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M7 13.5a6.5 6.5 0 1 0 0-13a6.5 6.5 0 0 0 0 13M5.5 10h3"/><path d="M7 10V6.5H6m1-2.25a.25.25 0 0 1 0-.5m0 .5a.25.25 0 0 0 0-.5"/></g></svg>
        <h6 class="mb-0"><?php echo htmlspecialchars($sup['full_name']); ?></h6>
        <small style="color:rgba(255,255,255,0.7);"><?php echo htmlspecialchars($sup['position'] ?? 'Industrial Supervisor'); ?></small>
    </div>
    <div class="sidebar-menu">
        <a href="dashboard.php" class="active"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="M13.5 6.94a1 1 0 0 0-.32-.74L7 .5L.82 6.2a1 1 0 0 0-.32.74v5.56a1 1 0 0 0 1 1h11a1 1 0 0 0 1-1zM7 13.5v-4"/></svg> Dashboard</a>
        <a href="view_logbooks.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 9l-3 .54L5 6.5L10.73.79a1 1 0 0 1 1.42 0l1.06 1.06a1 1 0 0 1 0 1.42Z"/><path d="M12 9.5v3a1 1 0 0 1-1 1H1.5a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1h3"/></g></svg> Student Logbooks <?php if($pending_review>0): ?><span class="notif-badge ms-1"><?php echo $pending_review; ?></span><?php endif; ?></a>
        <a href="my_students.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M13.18 13.5a6.49 6.49 0 0 0-12.36 0z"/><path d="M7 9A4.232 4.232 0 1 0 7 .536A4.232 4.232 0 0 0 7 9"/><path d="M8.382 6.405s-.351.691-1.382.691s-1.382-.69-1.382-.69m5.537-2.444h-.028a4.12 4.12 0 0 1-3.09-1.392a4.12 4.12 0 0 1-3.091 1.392a4.1 4.1 0 0 1-1.973-.5a4.234 4.234 0 0 1 8.182.5"/></g></svg> My Students</a>
        <a href="submit_report.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M7 13.5a6.5 6.5 0 1 0 0-13a6.5 6.5 0 0 0 0 13M5.5 10h3"/><path d="M7 10V6.5H6m1-2.25a.25.25 0 0 1 0-.5m0 .5a.25.25 0 0 0 0-.5"/></g></svg> Submit Report</a>
        <a href="my_reports.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="m1.24 6.54l11.5-5.23M10.59.5l2.15.81l-.8 2.15m1.31 10.05h-2.5h0v-7a.5.5 0 0 1 .5-.5h1.5a.5.5 0 0 1 .5.5v7h0Zm-5 0h-2.5h0v-5.5a.5.5 0 0 1 .5-.5h1.5a.5.5 0 0 1 .5.5v5.5h0Zm-5 0H.75h0v-4a.5.5 0 0 1 .5-.5h1.5a.5.5 0 0 1 .5.5v4h0Z"/></svg> My Reports</a>
        <a href="profile.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M7 13.5a6.5 6.5 0 1 0 0-13a6.5 6.5 0 0 0 0 13M5.5 10h3"/><path d="M7 10V6.5H6m1-2.25a.25.25 0 0 1 0-.5m0 .5a.25.25 0 0 0 0-.5"/></g></svg> My Profile</a>
        <a href="../../logout.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="M9.5 10.5v2a1 1 0 0 1-1 1h-7a1 1 0 0 1-1-1v-11a1 1 0 0 1 1-1h7a1 1 0 0 1 1 1v2M6.5 7h7m-2-2l2 2l-2 2"/></svg> Logout</a>
    </div>
</div>

<div class="content">
    <!-- Org banner -->
    <div class="org-info-card">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h4 class="mb-1"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:0.5rem;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="M8.461 4.75V1.594c0-.56-.454-1.015-1.015-1.015h-4.79c-.562 0-1.016.454-1.016 1.015v11.827m-1.121 0h4.968M1.64 3.187H4.1M1.64 5.75h3.847m4.993 4.282a1.75 1.75 0 1 0 0-3.5a1.75 1.75 0 0 0 0 3.5m-3.001 3.389a3.04 3.04 0 0 1 .39-1.46a3.03 3.03 0 0 1 2.611-1.537a3.03 3.03 0 0 1 2.612 1.538c.25.445.385.947.39 1.459"/></svg><?php echo htmlspecialchars($sup['org_name']); ?></h4>
                <p class="mb-0 opacity-75">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:0.25rem;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M7 13.5a6.5 6.5 0 1 0 0-13a6.5 6.5 0 0 0 0 13M5.5 10h3"/><path d="M7 10V6.5H6m1-2.25a.25.25 0 0 1 0-.5m0 .5a.25.25 0 0 0 0-.5"/></g></svg><?php echo htmlspecialchars($sup['org_location']); ?>
                    &nbsp;|&nbsp;
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:0.25rem;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M7 13.5a6.5 6.5 0 1 0 0-13a6.5 6.5 0 0 0 0 13M5.5 10h3"/><path d="M7 10V6.5H6m1-2.25a.25.25 0 0 1 0-.5m0 .5a.25.25 0 0 0 0-.5"/></g></svg><?php echo htmlspecialchars($sup['industry_type'] ?? 'N/A'); ?>
                    &nbsp;|&nbsp;
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:0.25rem;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M13.18 13.5a6.49 6.49 0 0 0-12.36 0z"/><path d="M7 9A4.232 4.232 0 1 0 7 .536A4.232 4.232 0 0 0 7 9"/><path d="M8.382 6.405s-.351.691-1.382.691s-1.382-.69-1.382-.69m5.537-2.444h-.028a4.12 4.12 0 0 1-3.09-1.392a4.12 4.12 0 0 1-3.091 1.392a4.1 4.1 0 0 1-1.973-.5a4.234 4.234 0 0 1 8.182.5"/></g></svg>Capacity: <?php echo $sup['capacity']; ?> students
                </p>
            </div>
            <div class="col-md-4 text-end">
                <div style="opacity:0.8;font-size:0.85rem;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:0.25rem;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 9l-3 .54L5 6.5L10.73.79a1 1 0 0 1 1.42 0l1.06 1.06a1 1 0 0 1 0 1.42Z"/><path d="M12 9.5v3a1 1 0 0 1-1 1H1.5a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1h3"/></g></svg><?php echo htmlspecialchars($sup['contact_email'] ?? ''); ?><br>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:0.25rem;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M7 13.5a6.5 6.5 0 1 0 0-13a6.5 6.5 0 0 0 0 13M5.5 10h3"/><path d="M7 10V6.5H6m1-2.25a.25.25 0 0 1 0-.5m0 .5a.25.25 0 0 0 0-.5"/></g></svg><?php echo htmlspecialchars($sup['contact_phone'] ?? ''); ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats -->
    <div class="row mb-4">
        <div class="col-md-3 col-6">
            <div class="stat-card" style="background:linear-gradient(135deg,#10b981,#059669);">
                <div class="d-flex justify-content-between align-items-center">
                    <div><div style="font-size:2rem;font-weight:700;"><?php echo $allocated_students; ?></div><div style="font-size:0.85rem;">Allocated Students</div></div>
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="m7 1.367l6.5 2.817L7 7L.5 4.184z"/><path d="m3.45 5.469l.006 3.064S4.529 9.953 7 9.953s3.55-1.42 3.55-1.42l-.001-3.064m-8.854 5.132v-5.89m.001 8.282a1.196 1.196 0 1 0 0-2.392a1.196 1.196 0 0 0 0 2.392"/></g></svg>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card" style="background:linear-gradient(135deg,#4361ee,#3a0ca3);">
                <div class="d-flex justify-content-between align-items-center">
                    <div><div style="font-size:2rem;font-weight:700;"><?php echo $logbook_count; ?></div><div style="font-size:0.85rem;">Logbook Entries</div></div>
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 9l-3 .54L5 6.5L10.73.79a1 1 0 0 1 1.42 0l1.06 1.06a1 1 0 0 1 0 1.42Z"/><path d="M12 9.5v3a1 1 0 0 1-1 1H1.5a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1h3"/></g></svg>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card" style="background:linear-gradient(135deg,#f59e0b,#d97706);">
                <div class="d-flex justify-content-between align-items-center">
                    <div><div style="font-size:2rem;font-weight:700;"><?php echo $pending_review; ?></div><div style="font-size:0.85rem;">Pending Review</div></div>
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="M6 13.25h2m3-7.5a4 4 0 0 0-8 0v3.5a1.5 1.5 0 0 1-1.5 1.5h11a1.5 1.5 0 0 1-1.5-1.5ZM.5 5.62A6 6 0 0 1 3 .75m10.5 4.87A6 6 0 0 0 11 .75"/></svg>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card" style="background:linear-gradient(135deg,#8b5cf6,#7c3aed);">
                <div class="d-flex justify-content-between align-items-center">
                    <div><div style="font-size:2rem;font-weight:700;"><?php echo $report_count; ?></div><div style="font-size:0.85rem;">Reports Submitted</div></div>
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 9l-3 .54L5 6.5L10.73.79a1 1 0 0 1 1.42 0l1.06 1.06a1 1 0 0 1 0 1.42Z"/><path d="M12 9.5v3a1 1 0 0 1-1 1H1.5a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1h3"/></g></svg>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- My Students -->
        <div class="col-md-7">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center py-3">
                    <h6 class="mb-0"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:0.5rem;color:var(--bs-success);"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M13.18 13.5a6.49 6.49 0 0 0-12.36 0z"/><path d="M7 9A4.232 4.232 0 1 0 7 .536A4.232 4.232 0 0 0 7 9"/><path d="M8.382 6.405s-.351.691-1.382.691s-1.382-.69-1.382-.69m5.537-2.444h-.028a4.12 4.12 0 0 1-3.09-1.392a4.12 4.12 0 0 1-3.091 1.392a4.1 4.1 0 0 1-1.973-.5a4.234 4.234 0 0 1 8.182.5"/></g></svg>My Students</h6>
                    <a href="my_students.php" class="btn btn-sm btn-outline-success">View All</a>
                </div>
                <div class="card-body">
                    <?php
                    $stRow = null;
                    $rows = [];
                    while($r = $students->fetch_assoc()) $rows[] = $r;
                    if (empty($rows)): ?>
                        <p class="text-muted text-center py-3">No students allocated to your organisation yet.</p>
                    <?php else:
                        foreach($rows as $r): ?>
                        <div class="student-card">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <strong><?php echo htmlspecialchars($r['full_name']); ?></strong>
                                    <span class="text-muted ms-2" style="font-size:0.82rem;"><?php echo htmlspecialchars($r['student_id']); ?></span>
                                    <div style="font-size:0.82rem;color:#6c757d;"><?php echo htmlspecialchars($r['program']); ?> · Year <?php echo $r['year_of_study']; ?></div>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-primary"><?php echo $r['logbook_count']; ?> logs</span>
                                    <?php if($r['pending_logs'] > 0): ?>
                                        <br><span class="badge bg-warning text-dark mt-1"><?php echo $r['pending_logs']; ?> to review</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="mt-2">
                                <a href="view_logbooks.php?student_id=<?php echo $r['id']; ?>" class="btn btn-sm btn-outline-primary me-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:0.25rem;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 9l-3 .54L5 6.5L10.73.79a1 1 0 0 1 1.42 0l1.06 1.06a1 1 0 0 1 0 1.42Z"/><path d="M12 9.5v3a1 1 0 0 1-1 1H1.5a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1h3"/></g></svg>Logbooks
                                </a>
                                <a href="submit_report.php?student_id=<?php echo $r['id']; ?>" class="btn btn-sm btn-outline-success">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:0.25rem;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M7 13.5a6.5 6.5 0 1 0 0-13a6.5 6.5 0 0 0 0 13M5.5 10h3"/><path d="M7 10V6.5H6m1-2.25a.25.25 0 0 1 0-.5m0 .5a.25.25 0 0 0 0-.5"/></g></svg>Performance Report
                                </a>
                            </div>
                        </div>
                        <?php endforeach; endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent activity -->
        <div class="col-md-5">
            <div class="card mb-4">
                <div class="card-header py-3">
                    <h6 class="mb-0"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:0.5rem;color:var(--bs-warning);"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="M6 13.25h2m3-7.5a4 4 0 0 0-8 0v3.5a1.5 1.5 0 0 1-1.5 1.5h11a1.5 1.5 0 0 1-1.5-1.5ZM.5 5.62A6 6 0 0 1 3 .75m10.5 4.87A6 6 0 0 0 11 .75"/></svg>Recent Logbook Submissions</h6>
                </div>
                <div class="card-body p-0 px-3">
                    <?php if($recent_logs->num_rows === 0): ?>
                        <p class="text-muted text-center py-4">No recent submissions.</p>
                    <?php else: while($rl = $recent_logs->fetch_assoc()): ?>
                    <div class="log-item">
                        <div class="d-flex justify-content-between">
                            <div>
                                <strong style="font-size:0.88rem;"><?php echo htmlspecialchars($rl['full_name']); ?></strong>
                                <span class="text-muted ms-1" style="font-size:0.78rem;">(<?php echo htmlspecialchars($rl['sid']); ?>)</span>
                                <div style="font-size:0.8rem;color:#6c757d;">Week <?php echo $rl['week_number']; ?> &mdash; <?php echo $rl['submitted_at'] ? date('d M Y', strtotime($rl['submitted_at'])) : 'N/A'; ?></div>
                            </div>
                            <a href="view_logbooks.php?student_id=<?php echo $rl['student_id']; ?>" class="btn btn-xs btn-outline-primary btn-sm" style="height:fit-content;">View</a>
                        </div>
                    </div>
                    <?php endwhile; endif; ?>
                </div>
            </div>

            <!-- Quick actions -->
            <div class="card">
                <div class="card-header py-3">
                    <h6 class="mb-0"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:0.5rem;color:var(--bs-primary);"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M7 13.5a6.5 6.5 0 1 0 0-13a6.5 6.5 0 0 0 0 13M5.5 10h3"/><path d="M7 10V6.5H6m1-2.25a.25.25 0 0 1 0-.5m0 .5a.25.25 0 0 0 0-.5"/></g></svg>Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="view_logbooks.php" class="btn btn-outline-primary btn-sm"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:0.5rem;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 9l-3 .54L5 6.5L10.73.79a1 1 0 0 1 1.42 0l1.06 1.06a1 1 0 0 1 0 1.42Z"/><path d="M12 9.5v3a1 1 0 0 1-1 1H1.5a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1h3"/></g></svg>Review All Logbooks</a>
                        <a href="submit_report.php" class="btn btn-outline-success btn-sm"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:0.5rem;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M7 13.5a6.5 6.5 0 1 0 0-13a6.5 6.5 0 0 0 0 13M5.5 10h3"/><path d="M7 10V6.5H6m1-2.25a.25.25 0 0 1 0-.5m0 .5a.25.25 0 0 0 0-.5"/></g></svg>Submit Performance Report</a>
                        <a href="my_reports.php" class="btn btn-outline-secondary btn-sm"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:0.5rem;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="m1.24 6.54l11.5-5.23M10.59.5l2.15.81l-.8 2.15m1.31 10.05h-2.5h0v-7a.5.5 0 0 1 .5-.5h1.5a.5.5 0 0 1 .5.5v7h0Zm-5 0h-2.5h0v-5.5a.5.5 0 0 1 .5-.5h1.5a.5.5 0 0 1 .5.5v5.5h0Zm-5 0H.75h0v-4a.5.5 0 0 1 .5-.5h1.5a.5.5 0 0 1 .5.5v4h0Z"/></svg>View Submitted Reports</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
