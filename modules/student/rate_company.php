<?php
// modules/student/rate_company.php
// Allows a student to rate their host company after attachment.
require_once '../../config/database.php';
if (!isLoggedIn() || !hasRole('student')) { redirect('index.php'); }

$user_id    = $_SESSION['user_id'];
$student    = $conn->query("SELECT * FROM students WHERE user_id=$user_id")->fetch_assoc();
$student_id = $student['id'];

// Find the student's allocation (confirmed or completed)
$alloc = $conn->query("SELECT a.*, o.name AS org_name, o.id AS org_id
                       FROM allocations a
                       JOIN organizations o ON a.organization_id = o.id
                       WHERE a.student_id = $student_id
                       AND a.status IN ('confirmed','completed')
                       ORDER BY a.allocated_at DESC LIMIT 1")->fetch_assoc();

$error   = '';
$existing = null;

if ($alloc) {
    $existing = $conn->query("SELECT * FROM student_company_ratings
                               WHERE student_id=$student_id
                               AND organization_id={$alloc['org_id']}")->fetch_assoc();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $alloc) {
    $org_id = (int)$alloc['org_id'];
    $fields = ['overall_rating','mentorship_rating','work_environment_rating',
               'learning_opportunities_rating','support_rating'];
    $vals   = []; $ok = true;
    foreach ($fields as $f) {
        $v = (int)($_POST[$f] ?? 0);
        if ($v < 1 || $v > 10) { $error = "All ratings must be between 1 and 10."; $ok = false; break; }
        $vals[$f] = $v;
    }
    if ($ok) {
        $recommend = isset($_POST['would_recommend']) ? 1 : 0;
        $comments  = $conn->real_escape_string(trim($_POST['comments'] ?? ''));

        if ($existing) {
            $conn->query("UPDATE student_company_ratings
                          SET overall_rating={$vals['overall_rating']},
                              mentorship_rating={$vals['mentorship_rating']},
                              work_environment_rating={$vals['work_environment_rating']},
                              learning_opportunities_rating={$vals['learning_opportunities_rating']},
                              support_rating={$vals['support_rating']},
                              would_recommend=$recommend,
                              comments='$comments',
                              submitted_at=NOW()
                          WHERE student_id=$student_id AND organization_id=$org_id");
        } else {
            $conn->query("INSERT INTO student_company_ratings
                          (student_id, organization_id, overall_rating, mentorship_rating,
                           work_environment_rating, learning_opportunities_rating, support_rating,
                           would_recommend, comments)
                          VALUES ($student_id, $org_id,
                                  {$vals['overall_rating']}, {$vals['mentorship_rating']},
                                  {$vals['work_environment_rating']}, {$vals['learning_opportunities_rating']},
                                  {$vals['support_rating']}, $recommend, '$comments')");
        }
        logActivity('RATE_COMPANY', "Student $student_id rated organisation $org_id");
        $_SESSION['success'] = "Thank you! Your rating has been submitted.";
        redirect('modules/student/rate_company.php');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Rate Your Company - IAMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background:#f0f2f5; font-family:'Segoe UI',Tahoma,sans-serif; }
        .sidebar { position:fixed;top:0;left:0;height:100vh;width:260px;background:linear-gradient(135deg,#1e3a5f,#2563eb);color:white;overflow-y:auto; }
        .sidebar-header { padding:20px;text-align:center;border-bottom:1px solid rgba(255,255,255,0.1); }
        .sidebar-menu a { display:block;padding:12px 25px;color:rgba(255,255,255,0.8);text-decoration:none;transition:all 0.3s; }
        .sidebar-menu a:hover,.sidebar-menu a.active { background:rgba(255,255,255,0.1);color:white;padding-left:30px; }
        .content { margin-left:260px;padding:24px; }
        .card { border-radius:16px;border:none;box-shadow:0 2px 12px rgba(0,0,0,0.06); }
        .rating-group { display:flex;align-items:center;gap:12px; }
        .rating-group input[type=range] { flex:1; }
        .rating-value { min-width:32px;text-align:center;font-weight:700;font-size:1.1rem;color:#2563eb; }
        .star-display { color:#f59e0b;font-size:1.1rem; }
        .company-badge { background:linear-gradient(135deg,#1e3a5f,#2563eb);color:white;border-radius:16px;padding:16px 20px; }
    </style>
</head>
<body>
<div class="sidebar">
    <div class="sidebar-header">
        <i class="fas fa-user-graduate fa-2x mb-2"></i>
        <h6 class="mb-0"><?php echo htmlspecialchars($student['full_name']); ?></h6>
        <small style="color:rgba(255,255,255,0.7);"><?php echo htmlspecialchars($student['student_id']); ?></small>
    </div>
    <div class="sidebar-menu">
        <a href="dashboard.php"><i class="fas fa-home me-2"></i>Dashboard</a>
        <a href="logbooks.php"><i class="fas fa-book me-2"></i>My Logbook</a>
        <a href="my_allocation.php"><i class="fas fa-building me-2"></i>My Allocation</a>
        <a href="final_report.php"><i class="fas fa-file-alt me-2"></i>Final Report</a>
        <a href="rate_company.php" class="active"><i class="fas fa-star me-2"></i>Rate My Company</a>
        <a href="notifications.php"><i class="fas fa-bell me-2"></i>Notifications</a>
        <a href="../../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
    </div>
</div>

<div class="content">
    <h4 class="mb-4"><i class="fas fa-star text-warning me-2"></i>Rate Your Host Company</h4>
    <?php displayMessage(); ?>

    <?php if (!$alloc): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            You don't have a confirmed placement yet. Ratings become available once you've been allocated to an organisation.
        </div>
    <?php else: ?>

        <!-- Company Info Banner -->
        <div class="company-badge mb-4">
            <div class="d-flex align-items-center gap-3">
                <i class="fas fa-building fa-2x opacity-75"></i>
                <div>
                    <h5 class="mb-0"><?php echo htmlspecialchars($alloc['org_name']); ?></h5>
                    <small class="opacity-75">Your host organisation</small>
                </div>
                <?php if ($existing): ?>
                    <span class="badge bg-success ms-auto"><i class="fas fa-check me-1"></i>Rating Submitted</span>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <?php if ($existing): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-1"></i>
                        You've already submitted a rating. You can update it below.
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <h6 class="fw-bold mb-3" style="color:#1e3a5f;">
                        <i class="fas fa-sliders-h me-2"></i>Rate Your Experience (1 = Poor, 10 = Excellent)
                    </h6>

                    <?php
                    $ratingFields = [
                        'overall_rating'               => ['Overall Experience',          'fas fa-star'],
                        'mentorship_rating'            => ['Mentorship & Guidance',        'fas fa-chalkboard-teacher'],
                        'work_environment_rating'      => ['Work Environment',             'fas fa-briefcase'],
                        'learning_opportunities_rating'=> ['Learning Opportunities',       'fas fa-graduation-cap'],
                        'support_rating'               => ['Support & Communication',      'fas fa-hands-helping'],
                    ];
                    foreach ($ratingFields as $fname => [$label, $icon]):
                        $val = $existing[$fname] ?? 5;
                    ?>
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="<?php echo $icon; ?> me-1 text-primary"></i><?php echo $label; ?>
                        </label>
                        <div class="rating-group">
                            <span class="text-muted" style="font-size:0.8rem;">1</span>
                            <input type="range" name="<?php echo $fname; ?>" id="<?php echo $fname; ?>"
                                   min="1" max="10" value="<?php echo $val; ?>" class="form-range"
                                   oninput="document.getElementById('v_<?php echo $fname; ?>').textContent=this.value">
                            <span class="text-muted" style="font-size:0.8rem;">10</span>
                            <span class="rating-value" id="v_<?php echo $fname; ?>"><?php echo $val; ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="would_recommend"
                                   id="recommend" <?php echo ($existing['would_recommend'] ?? 1) ? 'checked' : ''; ?>>
                            <label class="form-check-label fw-semibold" for="recommend">
                                <i class="fas fa-thumbs-up me-1 text-success"></i>
                                I would recommend this organisation to other students
                            </label>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">
                            <i class="fas fa-comment-alt me-1"></i>Comments (optional)
                        </label>
                        <textarea name="comments" class="form-control" rows="4"
                            placeholder="Share your experience — what went well, what could be improved…"><?php echo htmlspecialchars($existing['comments'] ?? ''); ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane me-2"></i>
                        <?php echo $existing ? 'Update Rating' : 'Submit Rating'; ?>
                    </button>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
