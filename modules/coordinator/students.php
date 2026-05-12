<?php
// modules/coordinator/students.php - Manage students
require_once '../../config/database.php';

if (!isLoggedIn() || !hasRole('coordinator')) { redirect('index.php'); }

// ── Delete ────────────────────────────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $sid = (int)$_GET['delete'];
    // Cascade-delete all related records manually (in case FK cascades are not active)
    $s = $conn->query("SELECT user_id FROM students WHERE id = $sid")->fetch_assoc();
    if ($s) {
        $conn->query("DELETE FROM industrial_reports       WHERE student_id = $sid");
        $conn->query("DELETE FROM university_assessments  WHERE student_id = $sid");
        $conn->query("DELETE FROM final_reports           WHERE student_id = $sid");
        $conn->query("DELETE FROM logbooks                WHERE student_id = $sid");
        $conn->query("DELETE FROM allocations             WHERE student_id = $sid");
        $conn->query("DELETE FROM notifications           WHERE user_id = {$s['user_id']}");
        $conn->query("DELETE FROM students                WHERE id = $sid");
        $conn->query("DELETE FROM users                   WHERE id = {$s['user_id']}");
        logActivity('DELETE_STUDENT', "Coordinator deleted student ID $sid");
        $_SESSION['success'] = "Student deleted successfully.";
    } else {
        $_SESSION['error'] = "Student not found.";
    }
    redirect('modules/coordinator/students.php');
}

// ── Fetch ─────────────────────────────────────────────────────────────────────
$search = trim($_GET['q'] ?? '');
$where  = $search ? "WHERE s.full_name LIKE '%{$conn->real_escape_string($search)}%' OR s.student_id LIKE '%{$conn->real_escape_string($search)}%'" : '';

$students = $conn->query("
    SELECT s.*,
           CASE WHEN a.id IS NOT NULL THEN 'Allocated' ELSE 'Unallocated' END AS allocation_status,
           o.name AS org_name,
           u.email
    FROM students s
    LEFT JOIN users u ON s.user_id = u.id
    LEFT JOIN allocations a ON s.id = a.student_id
    LEFT JOIN organizations o ON a.organization_id = o.id
    $where
    ORDER BY s.created_at DESC
");

$total     = $conn->query("SELECT COUNT(*) c FROM students")->fetch_assoc()['c'];
$allocated = $conn->query("SELECT COUNT(DISTINCT student_id) c FROM allocations")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students - IAMS</title>
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
        .stat-card { border-radius:16px; padding:20px; color:white; }
        .card { border-radius:16px; border:none; box-shadow:0 2px 12px rgba(0,0,0,0.06); }
        .card-header { border-radius:16px 16px 0 0 !important; border-bottom:1px solid #eee; background:white; }
        .table th { font-size:0.82rem; text-transform:uppercase; letter-spacing:.5px; color:#6c757d; border-bottom:2px solid #eee; }
        .table td { vertical-align:middle; font-size:0.9rem; }
        .badge-allocated   { background:#d1fae5; color:#065f46; font-size:0.78rem; padding:4px 10px; border-radius:20px; }
        .badge-unallocated { background:#fef3c7; color:#92400e; font-size:0.78rem; padding:4px 10px; border-radius:20px; }
        .btn-delete-row { background:none; border:none; color:#dc3545; padding:4px 8px; border-radius:8px; transition:0.2s; }
        .btn-delete-row:hover { background:#fee2e2; }
        .search-box { max-width:320px; }
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
        <a href="students.php" class="active"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="m7 1.367l6.5 2.817L7 7L.5 4.184z"/><path d="m3.45 5.469l.006 3.064S4.529 9.953 7 9.953s3.55-1.42 3.55-1.42l-.001-3.064m-8.854 5.132v-5.89m.001 8.282a1.196 1.196 0 1 0 0-2.392a1.196 1.196 0 0 0 0 2.392"/></g></svg> Students</a>
        <a href="organizations.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="M8.461 4.75V1.594c0-.56-.454-1.015-1.015-1.015h-4.79c-.562 0-1.016.454-1.016 1.015v11.827m-1.121 0h4.968M1.64 3.187H4.1M1.64 5.75h3.847m4.993 4.282a1.75 1.75 0 1 0 0-3.5a1.75 1.75 0 0 0 0 3.5m-3.001 3.389a3.04 3.04 0 0 1 .39-1.46a3.03 3.03 0 0 1 2.611-1.537a3.03 3.03 0 0 1 2.612 1.538c.25.445.385.947.39 1.459"/></svg> Organisations</a>
        <a href="matching.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="m.5 8.55l2.73 3.51a1 1 0 0 0 1.56.03L13.5 1.55"/></svg> Student Matching</a>
        <a href="allocations.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 9l-3 .54L5 6.5L10.73.79a1 1 0 0 1 1.42 0l1.06 1.06a1 1 0 0 1 0 1.42Z"/><path d="M12 9.5v3a1 1 0 0 1-1 1H1.5a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1h3"/></g></svg> Allocations</a>
        <a href="view_logbooks.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 9l-3 .54L5 6.5L10.73.79a1 1 0 0 1 1.42 0l1.06 1.06a1 1 0 0 1 0 1.42Z"/><path d="M12 9.5v3a1 1 0 0 1-1 1H1.5a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1h3"/></g></svg> View Logbooks</a>
        <a href="view_final_reports.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 9l-3 .54L5 6.5L10.73.79a1 1 0 0 1 1.42 0l1.06 1.06a1 1 0 0 1 0 1.42Z"/><path d="M12 9.5v3a1 1 0 0 1-1 1H1.5a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1h3"/></g></svg> Final Reports</a>
        <a href="reports.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="m1.24 6.54l11.5-5.23M10.59.5l2.15.81l-.8 2.15m1.31 10.05h-2.5h0v-7a.5.5 0 0 1 .5-.5h1.5a.5.5 0 0 1 .5.5v7h0Zm-5 0h-2.5h0v-5.5a.5.5 0 0 1 .5-.5h1.5a.5.5 0 0 1 .5.5v5.5h0Zm-5 0H.75h0v-4a.5.5 0 0 1 .5-.5h1.5a.5.5 0 0 1 .5.5v4h0Z"/></svg> Assessments</a>
        <a href="reminders.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="M6 13.25h2m3-7.5a4 4 0 0 0-8 0v3.5a1.5 1.5 0 0 1-1.5 1.5h11a1.5 1.5 0 0 1-1.5-1.5ZM.5 5.62A6 6 0 0 1 3 .75m10.5 4.87A6 6 0 0 0 11 .75"/></svg> Reminders</a>
        <a href="../../logout.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="M9.5 10.5v2a1 1 0 0 1-1 1h-7a1 1 0 0 1-1-1v-11a1 1 0 0 1 1-1h7a1 1 0 0 1 1 1v2M6.5 7h7m-2-2l2 2l-2 2"/></svg> Logout</a>
    </div>
</div>

<div class="content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:0.5rem;color:var(--bs-primary);"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="m7 1.367l6.5 2.817L7 7L.5 4.184z"/><path d="m3.45 5.469l.006 3.064S4.529 9.953 7 9.953s3.55-1.42 3.55-1.42l-.001-3.064m-8.854 5.132v-5.89m.001 8.282a1.196 1.196 0 1 0 0-2.392a1.196 1.196 0 0 0 0 2.392"/></g></svg>Registered Students</h4>
            <small class="text-muted">Manage all student registrations and allocations</small>
        </div>
        <a href="../../register_student.php" class="btn btn-primary">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:0.5rem;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="M7 .5v13M.5 6.96h13"/></svg>Add New Student
        </a>
    </div>

    <!-- Stats -->
    <div class="row mb-4">
        <div class="col-md-4 mb-3">
            <div class="stat-card" style="background:linear-gradient(135deg,#4361ee,#3a0ca3);">
                <div class="d-flex justify-content-between">
                    <div><div style="font-size:2rem;font-weight:700;"><?php echo $total; ?></div><div>Total Students</div></div>
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M13.18 13.5a6.49 6.49 0 0 0-12.36 0z"/><path d="M7 9A4.232 4.232 0 1 0 7 .536A4.232 4.232 0 0 0 7 9"/><path d="M8.382 6.405s-.351.691-1.382.691s-1.382-.69-1.382-.69m5.537-2.444h-.028a4.12 4.12 0 0 1-3.09-1.392a4.12 4.12 0 0 1-3.091 1.392a4.1 4.1 0 0 1-1.973-.5a4.234 4.234 0 0 1 8.182.5"/></g></svg>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="stat-card" style="background:linear-gradient(135deg,#10b981,#059669);">
                <div class="d-flex justify-content-between">
                    <div><div style="font-size:2rem;font-weight:700;"><?php echo $allocated; ?></div><div>Allocated</div></div>
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="m.5 8.55l2.73 3.51a1 1 0 0 0 1.56.03L13.5 1.55"/></svg>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="stat-card" style="background:linear-gradient(135deg,#f59e0b,#d97706);">
                <div class="d-flex justify-content-between">
                    <div><div style="font-size:2rem;font-weight:700;"><?php echo $total - $allocated; ?></div><div>Unallocated</div></div>
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="M6 13.25h2m3-7.5a4 4 0 0 0-8 0v3.5a1.5 1.5 0 0 1-1.5 1.5h11a1.5 1.5 0 0 1-1.5-1.5ZM.5 5.62A6 6 0 0 1 3 .75m10.5 4.87A6 6 0 0 0 11 .75"/></svg>
                </div>
            </div>
        </div>
    </div>

    <?php displayMessage(); ?>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center py-3">
            <h5 class="mb-0">Student List</h5>
            <form method="GET" class="search-box">
                <div class="input-group input-group-sm">
                    <input type="text" name="q" class="form-control" placeholder="Search by name or ID…" value="<?php echo htmlspecialchars($search); ?>">
                    <button class="btn btn-outline-secondary" type="submit"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><circle cx="5.92" cy="5.92" r="5.42"/><path d="M13.5 13.5L9.75 9.75M6 3.5v5M3.5 6h5"/></g></svg></button>
                    <?php if($search): ?><a href="students.php" class="btn btn-outline-danger"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="m13.5.5l-13 13m0-13l13 13"/></svg></a><?php endif; ?>
                </div>
            </form>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">#</th>
                            <th>Student ID</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Programme</th>
                            <th>Year</th>
                            <th>Preferred Location</th>
                            <th>Status</th>
                            <th>Allocated To</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i=1; while($row = $students->fetch_assoc()): ?>
                        <tr>
                            <td class="ps-4 text-muted"><?php echo $i++; ?></td>
                            <td><strong><?php echo htmlspecialchars($row['student_id']); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                            <td style="font-size:0.82rem;color:#6c757d;"><?php echo htmlspecialchars($row['email'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($row['program']); ?></td>
                            <td>Year <?php echo $row['year_of_study']; ?></td>
                            <td><?php echo htmlspecialchars($row['preferred_location']); ?></td>
                            <td>
                                <?php if($row['allocation_status'] === 'Allocated'): ?>
                                    <span class="badge-allocated"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:0.25rem;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="m.5 8.55l2.73 3.51a1 1 0 0 0 1.56.03L13.5 1.55"/></svg>Allocated</span>
                                <?php else: ?>
                                    <span class="badge-unallocated"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:0.25rem;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="M6 13.25h2m3-7.5a4 4 0 0 0-8 0v3.5a1.5 1.5 0 0 1-1.5 1.5h11a1.5 1.5 0 0 1-1.5-1.5ZM.5 5.62A6 6 0 0 1 3 .75m10.5 4.87A6 6 0 0 0 11 .75"/></svg>Pending</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($row['org_name'] ?? '—'); ?></td>
                            <td>
                                <button class="btn-delete-row"
                                    onclick="confirmDelete(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars(addslashes($row['full_name'])); ?>')"
                                    title="Delete student">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M7 13.5a6.5 6.5 0 1 0 0-13a6.5 6.5 0 0 0 0 13M5.5 10h3"/><path d="M7 10V6.5H6m1-2.25a.25.25 0 0 1 0-.5m0 .5a.25.25 0 0 0 0-.5"/></g></svg>
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <?php if($students->num_rows === 0): ?>
                        <tr><td colspan="10" class="text-center text-muted py-4">
                            <?php echo $search ? "No students match your search." : "No students registered yet."; ?>
                        </td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Delete confirmation modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title text-danger"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:0.5rem;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><circle cx="7" cy="7" r="6.5"/><path d="M7 3.5v3"/><circle cx="7" cy="9.5" r=".5"/></g></svg>Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete <strong id="deleteStudentName"></strong>?</p>
                <p class="text-muted small">This will permanently remove the student and all their associated logbooks, allocations and reports.</p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <a id="deleteConfirmBtn" href="#" class="btn btn-danger"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:0.5rem;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M7 13.5a6.5 6.5 0 1 0 0-13a6.5 6.5 0 0 0 0 13M5.5 10h3"/><path d="M7 10V6.5H6m1-2.25a.25.25 0 0 1 0-.5m0 .5a.25.25 0 0 0 0-.5"/></g></svg>Delete</a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function confirmDelete(id, name) {
    document.getElementById('deleteStudentName').textContent = name;
    document.getElementById('deleteConfirmBtn').href = 'students.php?delete=' + id;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>
</body>
</html>
