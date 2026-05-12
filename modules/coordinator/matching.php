<?php
// modules/coordinator/matching.php - Student-Organization matching algorithm
require_once '../../config/database.php';

if (!isLoggedIn() || !hasRole('coordinator')) {
    redirect('index.php');
}

// Get all students without allocation
$students = $conn->query("
    SELECT s.* FROM students s 
    LEFT JOIN allocations a ON s.id = a.student_id 
    WHERE a.id IS NULL
");

// Get all organizations with capacity
$organizations = $conn->query("
    SELECT o.*, 
           (SELECT COUNT(*) FROM allocations WHERE organization_id = o.id) as allocated_count 
    FROM organizations o
");

// Run matching algorithm
if (isset($_POST['run_matching'])) {
    // Clear existing allocations first
    $conn->query("DELETE FROM allocations");
    
    // Get students and organizations
    $student_list = [];
    $org_list = [];
    
    $students_res = $conn->query("SELECT * FROM students");
    while($s = $students_res->fetch_assoc()) {
        $student_list[] = $s;
    }
    
    $orgs_res = $conn->query("SELECT *, (SELECT COUNT(*) FROM allocations WHERE organization_id = o.id) as allocated_count FROM organizations o");
    while($o = $orgs_res->fetch_assoc()) {
        $org_list[$o['id']] = $o;
        $org_list[$o['id']]['allocated'] = 0;
    }
    
    // Scoring function
    function calculateMatchScore($student, $organization) {
        $score = 0;
        
        // Location preference matching
        if (strtolower($student['preferred_location']) == strtolower($organization['location'])) {
            $score += 30;
        } else {
            $score += 10; // Partial match for any location
        }
        
        // Project type vs required skills matching
        $student_project = strtolower($student['preferred_project_type']);
        $org_skills = strtolower($organization['required_skills']);
        
        if (strpos($org_skills, $student_project) !== false) {
            $score += 40;
        } else {
            // Check for related keywords
            $keywords = explode(' ', $student_project);
            foreach($keywords as $kw) {
                if(strlen($kw) > 3 && strpos($org_skills, $kw) !== false) {
                    $score += 15;
                }
            }
        }
        
        // Student skills matching
        $student_skills = strtolower($student['skills']);
        if (strpos($org_skills, $student_skills) !== false) {
            $score += 20;
        }
        
        return $score;
    }
    
    // Greedy matching algorithm
    $matches = [];
    $used_orgs = [];
    
    foreach($student_list as $student) {
        $best_score = -1;
        $best_org = null;
        
        foreach($org_list as $org) {
            if ($org['allocated'] < $org['capacity']) {
                $score = calculateMatchScore($student, $org);
                if ($score > $best_score) {
                    $best_score = $score;
                    $best_org = $org;
                }
            }
        }
        
        if ($best_org) {
            $matches[] = ['student_id' => $student['id'], 'organization_id' => $best_org['id'], 'score' => $best_score];
            $org_list[$best_org['id']]['allocated']++;
        }
    }
    
    // Save allocations
    foreach($matches as $match) {
        $student_id = $match['student_id'];
        $org_id = $match['organization_id'];
        $conn->query("INSERT INTO allocations (student_id, organization_id, status, allocated_by) 
                      VALUES ($student_id, $org_id, 'confirmed', {$_SESSION['user_id']})");
        
        // Get student user_id for notification
        $student = $conn->query("SELECT user_id FROM students WHERE id = $student_id")->fetch_assoc();
        $org = $conn->query("SELECT name FROM organizations WHERE id = $org_id")->fetch_assoc();
        createNotification($student['user_id'], "Attachment Allocation", 
                          "You have been allocated to {$org['name']}. Please check your dashboard.", "success");
    }
    
    $_SESSION['success'] = "Matching completed! " . count($matches) . " students allocated.";
    redirect('modules/coordinator/matching.php');
}

// Get current allocations
$current_allocations = $conn->query("
    SELECT a.*, s.full_name as student_name, s.student_id, o.name as org_name, o.location 
    FROM allocations a 
    JOIN students s ON a.student_id = s.id 
    JOIN organizations o ON a.organization_id = o.id 
    ORDER BY a.allocated_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Matching - IAMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f0f2f5; }
        .sidebar { position: fixed; top: 0; left: 0; height: 100vh; width: 260px; background: linear-gradient(135deg, #1a1a2e, #16213e); color: white; }
        .sidebar-header { padding: 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-menu a { display: block; padding: 12px 25px; color: rgba(255,255,255,0.8); text-decoration: none; }
        .sidebar-menu a:hover, .sidebar-menu a.active { background: rgba(255,255,255,0.1); color: white; padding-left: 30px; }
        .sidebar-menu a i { margin-right: 10px; width: 25px; }
        .content { margin-left: 260px; padding: 20px; }
        .btn-matching { background: linear-gradient(135deg, #4361ee, #3f37c9); color: white; border: none; padding: 12px 25px; border-radius: 10px; font-weight: 600; }
        .score-badge { background: #e8f0fe; color: #4361ee; padding: 3px 8px; border-radius: 20px; font-size: 12px; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header"><svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="M8.461 4.75V1.594c0-.56-.454-1.015-1.015-1.015h-4.79c-.562 0-1.016.454-1.016 1.015v11.827m-1.121 0h4.968M1.64 3.187H4.1M1.64 5.75h3.847m4.993 4.282a1.75 1.75 0 1 0 0-3.5a1.75 1.75 0 0 0 0 3.5m-3.001 3.389a3.04 3.04 0 0 1 .39-1.46a3.03 3.03 0 0 1 2.611-1.537a3.03 3.03 0 0 1 2.612 1.538c.25.445.385.947.39 1.459"/></svg><h4>IAMS Coordinator</h4></div>
        <div class="sidebar-menu">
            <a href="dashboard.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="M13.5 6.94a1 1 0 0 0-.32-.74L7 .5L.82 6.2a1 1 0 0 0-.32.74v5.56a1 1 0 0 0 1 1h11a1 1 0 0 0 1-1zM7 13.5v-4"/></svg> Dashboard</a>
            <a href="students.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="m7 1.367l6.5 2.817L7 7L.5 4.184z"/><path d="m3.45 5.469l.006 3.064S4.529 9.953 7 9.953s3.55-1.42 3.55-1.42l-.001-3.064m-8.854 5.132v-5.89m.001 8.282a1.196 1.196 0 1 0 0-2.392a1.196 1.196 0 0 0 0 2.392"/></g></svg> Students</a>
            <a href="organizations.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="M8.461 4.75V1.594c0-.56-.454-1.015-1.015-1.015h-4.79c-.562 0-1.016.454-1.016 1.015v11.827m-1.121 0h4.968M1.64 3.187H4.1M1.64 5.75h3.847m4.993 4.282a1.75 1.75 0 1 0 0-3.5a1.75 1.75 0 0 0 0 3.5m-3.001 3.389a3.04 3.04 0 0 1 .39-1.46a3.03 3.03 0 0 1 2.611-1.537a3.03 3.03 0 0 1 2.612 1.538c.25.445.385.947.39 1.459"/></svg> Organizations</a>
            <a href="matching.php" class="active"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="m.5 8.55l2.73 3.51a1 1 0 0 0 1.56.03L13.5 1.55"/></svg> Student Matching</a>
            <a href="allocations.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 9l-3 .54L5 6.5L10.73.79a1 1 0 0 1 1.42 0l1.06 1.06a1 1 0 0 1 0 1.42Z"/><path d="M12 9.5v3a1 1 0 0 1-1 1H1.5a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1h3"/></g></svg> Allocations</a>
            <a href="reports.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="m1.24 6.54l11.5-5.23M10.59.5l2.15.81l-.8 2.15m1.31 10.05h-2.5h0v-7a.5.5 0 0 1 .5-.5h1.5a.5.5 0 0 1 .5.5v7h0Zm-5 0h-2.5h0v-5.5a.5.5 0 0 1 .5-.5h1.5a.5.5 0 0 1 .5.5v5.5h0Zm-5 0H.75h0v-4a.5.5 0 0 1 .5-.5h1.5a.5.5 0 0 1 .5.5v4h0Z"/></svg> Reports</a>
            <a href="reminders.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="M6 13.25h2m3-7.5a4 4 0 0 0-8 0v3.5a1.5 1.5 0 0 1-1.5 1.5h11a1.5 1.5 0 0 1-1.5-1.5ZM.5 5.62A6 6 0 0 1 3 .75m10.5 4.87A6 6 0 0 0 11 .75"/></svg> Reminders</a>
            <a href="../../logout.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="M9.5 10.5v2a1 1 0 0 1-1 1h-7a1 1 0 0 1-1-1v-11a1 1 0 0 1 1-1h7a1 1 0 0 1 1 1v2M6.5 7h7m-2-2l2 2l-2 2"/></svg> Logout</a>
        </div>
    </div>
    
    <div class="content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:0.5rem;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="m.5 8.55l2.73 3.51a1 1 0 0 0 1.56.03L13.5 1.55"/></svg>Student-Organization Matching</h3>
            <form method="POST">
                <button type="submit" name="run_matching" class="btn btn-matching" onclick="return confirm('Run automatic matching algorithm? This will replace existing allocations.')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:0.5rem;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M7 13.5a6.5 6.5 0 1 0 0-13a6.5 6.5 0 0 0 0 13M5.5 10h3"/><path d="M7 10V6.5H6m1-2.25a.25.25 0 0 1 0-.5m0 .5a.25.25 0 0 0 0-.5"/></g></svg>Run Automatic Matching
                </button>
            </form>
        </div>
        
        <?php displayMessage(); ?>
        
        <div class="card">
            <div class="card-header bg-white">
                <h5><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:0.5rem;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 9l-3 .54L5 6.5L10.73.79a1 1 0 0 1 1.42 0l1.06 1.06a1 1 0 0 1 0 1.42Z"/><path d="M12 9.5v3a1 1 0 0 1-1 1H1.5a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1h3"/></g></svg>Current Allocations</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr><th>Student ID</th><th>Student Name</th><th>Organization</th><th>Location</th><th>Status</th><th>Allocated Date</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                            <?php if($current_allocations->num_rows > 0): ?>
                                <?php while($row = $current_allocations->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $row['student_id']; ?></td>
                                    <td><?php echo $row['student_name']; ?></td>
                                    <td><?php echo $row['org_name']; ?></td>
                                    <td><?php echo $row['location']; ?></td>
                                    <td><span class="badge bg-success"><?php echo $row['status']; ?></span></td>
                                    <td><?php echo date('d M Y', strtotime($row['allocated_at'])); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-danger" onclick="if(confirm('Remove allocation?')) window.location.href='delete_allocation.php?id=<?php echo $row['id']; ?>'">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><path fill="none" stroke-linecap="round" stroke-linejoin="round" d="m13.5.5l-13 13m0-13l13 13"/></svg>
                                        </button>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="7" class="text-center">No allocations yet. Run the matching algorithm.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-white"><h5><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:0.5rem;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M13.18 13.5a6.49 6.49 0 0 0-12.36 0z"/><path d="M7 9A4.232 4.232 0 1 0 7 .536A4.232 4.232 0 0 0 7 9"/><path d="M8.382 6.405s-.351.691-1.382.691s-1.382-.69-1.382-.69m5.537-2.444h-.028a4.12 4.12 0 0 1-3.09-1.392a4.12 4.12 0 0 1-3.091 1.392a4.1 4.1 0 0 1-1.973-.5a4.234 4.234 0 0 1 8.182.5"/></g></svg>Unallocated Students</h5></div>
                    <div class="card-body">
                        <?php
                        $unallocated = $conn->query("
                            SELECT s.* FROM students s 
                            LEFT JOIN allocations a ON s.id = a.student_id 
                            WHERE a.id IS NULL
                        ");
                        ?>
                        <?php if($unallocated->num_rows > 0): ?>
                            <ul class="list-group">
                                <?php while($s = $unallocated->fetch_assoc()): ?>
                                <li class="list-group-item"><?php echo $s['full_name']; ?> - Prefers: <?php echo $s['preferred_location']; ?> / <?php echo $s['preferred_project_type']; ?></li>
                                <?php endwhile; ?>
                            </ul>
                        <?php else: ?>
                            <p class="text-success">All students have been allocated!</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-white"><h5><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0;margin-right:0.5rem;"><g fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M7 13.5a6.5 6.5 0 1 0 0-13a6.5 6.5 0 0 0 0 13M5.5 10h3"/><path d="M7 10V6.5H6m1-2.25a.25.25 0 0 1 0-.5m0 .5a.25.25 0 0 0 0-.5"/></g></svg>Matching Statistics</h5></div>
                    <div class="card-body">
                        <?php
                        $stats = $conn->query("
                            SELECT o.name, o.capacity, COUNT(a.id) as allocated 
                            FROM organizations o 
                            LEFT JOIN allocations a ON o.id = a.organization_id 
                            GROUP BY o.id
                        ");
                        ?>
                        <table class="table table-sm">
                            <thead><tr><th>Organization</th><th>Capacity</th><th>Allocated</th><th>Remaining</th></tr></thead>
                            <tbody>
                                <?php while($row = $stats->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $row['name']; ?></td>
                                    <td><?php echo $row['capacity']; ?></td>
                                    <td><?php echo $row['allocated']; ?></td>
                                    <td><?php echo $row['capacity'] - $row['allocated']; ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>