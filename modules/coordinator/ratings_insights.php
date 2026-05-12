<?php
// modules/coordinator/ratings_insights.php
// Shows two-way rating insights: best/worst companies, top students, problematic placements.
require_once '../../config/database.php';
if (!isLoggedIn() || !hasRole('coordinator')) { redirect('index.php'); }

// ── Company ratings (rated by students) ──────────────────────────────────────
$companyRatings = $conn->query("
    SELECT o.id, o.name, o.location, o.industry_type,
           COUNT(r.id)                              AS total_ratings,
           ROUND(AVG(r.overall_rating), 1)          AS avg_overall,
           ROUND(AVG(r.mentorship_rating), 1)        AS avg_mentorship,
           ROUND(AVG(r.work_environment_rating), 1)  AS avg_environment,
           ROUND(AVG(r.learning_opportunities_rating),1) AS avg_learning,
           ROUND(AVG(r.support_rating), 1)           AS avg_support,
           SUM(r.would_recommend)                   AS recommend_count,
           ROUND(SUM(r.would_recommend)/COUNT(r.id)*100, 0) AS recommend_pct
    FROM organizations o
    LEFT JOIN student_company_ratings r ON o.id = r.organization_id
    GROUP BY o.id
    ORDER BY avg_overall DESC, total_ratings DESC
");

// ── Student performance (rated by industrial supervisors) ────────────────────
$studentRatings = $conn->query("
    SELECT s.id, s.full_name, s.student_id AS sid, s.program,
           o.name AS org_name,
           ROUND(AVG(r.overall_rating), 1)           AS avg_overall,
           ROUND(AVG(r.attendance_rating), 1)         AS avg_attendance,
           ROUND(AVG(r.technical_skills_rating), 1)   AS avg_technical,
           ROUND(AVG(r.communication_rating), 1)      AS avg_communication,
           ROUND(AVG(r.teamwork_rating), 1)            AS avg_teamwork,
           r.recommendation
    FROM students s
    JOIN industrial_reports r ON s.id = r.student_id
    JOIN organizations o ON r.organization_id = o.id
    GROUP BY s.id
    ORDER BY avg_overall DESC
");

// ── Problematic placements (students rated company ≤ 4 OR supervisor rated student ≤ 4) ──
$problematic = $conn->query("
    SELECT DISTINCT
           s.full_name AS student_name,
           s.student_id AS sid,
           o.name AS org_name,
           ROUND(AVG(cr.overall_rating), 1)  AS student_gave,
           ROUND(AVG(ir.overall_rating), 1)  AS company_gave,
           CASE
               WHEN AVG(cr.overall_rating) <= 4 AND AVG(ir.overall_rating) <= 4 THEN 'Both parties dissatisfied'
               WHEN AVG(cr.overall_rating) <= 4 THEN 'Student dissatisfied with company'
               ELSE 'Company dissatisfied with student'
           END AS flag
    FROM students s
    JOIN allocations a ON s.id = a.student_id
    JOIN organizations o ON a.organization_id = o.id
    LEFT JOIN student_company_ratings cr ON s.id = cr.student_id AND o.id = cr.organization_id
    LEFT JOIN industrial_reports ir ON s.id = ir.student_id AND o.id = ir.organization_id
    GROUP BY s.id, o.id
    HAVING (AVG(cr.overall_rating) <= 4 OR AVG(ir.overall_rating) <= 4)
    ORDER BY flag, student_name
");

// ── Summary stats ─────────────────────────────────────────────────────────────
$statsRow = $conn->query("
    SELECT
        (SELECT COUNT(*) FROM student_company_ratings) AS total_student_ratings,
        (SELECT COUNT(*) FROM industrial_reports)       AS total_company_ratings,
        (SELECT ROUND(AVG(overall_rating),1) FROM student_company_ratings) AS avg_company_score,
        (SELECT ROUND(AVG(overall_rating),1) FROM industrial_reports)       AS avg_student_score,
        (SELECT COUNT(*) FROM student_company_ratings WHERE would_recommend=1) AS recommenders,
        (SELECT COUNT(*) FROM student_company_ratings) AS total_cr
")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Ratings &amp; Insights - IAMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background:#f0f2f5; font-family:'Segoe UI',Tahoma,sans-serif; }
        .sidebar { position:fixed;top:0;left:0;height:100vh;width:260px;background:linear-gradient(135deg,#1a1a2e,#4361ee);color:white;overflow-y:auto; }
        .sidebar-header { padding:20px;text-align:center;border-bottom:1px solid rgba(255,255,255,0.1); }
        .sidebar-menu a { display:block;padding:12px 25px;color:rgba(255,255,255,0.8);text-decoration:none;transition:all 0.3s; }
        .sidebar-menu a:hover,.sidebar-menu a.active { background:rgba(255,255,255,0.1);color:white;padding-left:30px; }
        .content { margin-left:260px;padding:24px; }
        .card { border-radius:16px;border:none;box-shadow:0 2px 12px rgba(0,0,0,0.06); }
        .stat-card { border-radius:16px;padding:20px;color:white;text-align:center; }
        .stat-card h2 { font-size:2rem;font-weight:700;margin:0; }
        .star-rating { color:#f59e0b; }
        .badge-flag { font-size:0.75rem;border-radius:20px;padding:4px 10px; }
        .table th { font-size:0.82rem;font-weight:600;color:#555;white-space:nowrap; }
        .score-cell { font-weight:700;font-size:1rem; }
        .score-high { color:#059669; }
        .score-mid  { color:#d97706; }
        .score-low  { color:#dc3545; }
        .progress { height:8px;border-radius:4px; }
        .section-header { border-left:4px solid #4361ee;padding-left:12px;margin-bottom:1rem; }
    </style>
</head>
<body>
<div class="sidebar">
    <div class="sidebar-header">
        <i class="fas fa-chart-pie fa-2x mb-2"></i>
        <h6 class="mb-0">Coordinator</h6>
        <small style="color:rgba(255,255,255,0.7);">IAMS Admin</small>
    </div>
    <div class="sidebar-menu">
        <a href="dashboard.php"><i class="fas fa-home me-2"></i>Dashboard</a>
        <a href="students.php"><i class="fas fa-users me-2"></i>Students</a>
        <a href="organizations.php"><i class="fas fa-building me-2"></i>Organizations</a>
        <a href="allocations.php"><i class="fas fa-link me-2"></i>Allocations</a>
        <a href="view_logbooks.php"><i class="fas fa-book me-2"></i>Logbooks</a>
        <a href="reports.php"><i class="fas fa-file-alt me-2"></i>Reports</a>
        <a href="ratings_insights.php" class="active"><i class="fas fa-star me-2"></i>Ratings &amp; Insights</a>
        <a href="reminders.php"><i class="fas fa-bell me-2"></i>Reminders</a>
        <a href="../../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
    </div>
</div>

<div class="content">
    <h4 class="mb-4"><i class="fas fa-star text-warning me-2"></i>Two-Way Ratings &amp; Insights</h4>
    <?php displayMessage(); ?>

    <!-- Summary Stats -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="stat-card" style="background:linear-gradient(135deg,#4361ee,#3a0ca3);">
                <div><i class="fas fa-star fa-lg mb-2 opacity-75"></i></div>
                <h2><?php echo $statsRow['total_student_ratings'] ?? 0; ?></h2>
                <div style="font-size:0.85rem;opacity:0.85;">Student Ratings of Companies</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card" style="background:linear-gradient(135deg,#059669,#10b981);">
                <div><i class="fas fa-clipboard-list fa-lg mb-2 opacity-75"></i></div>
                <h2><?php echo $statsRow['total_company_ratings'] ?? 0; ?></h2>
                <div style="font-size:0.85rem;opacity:0.85;">Company Ratings of Students</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card" style="background:linear-gradient(135deg,#d97706,#f59e0b);">
                <div><i class="fas fa-building fa-lg mb-2 opacity-75"></i></div>
                <h2><?php echo $statsRow['avg_company_score'] ?? '–'; ?>/10</h2>
                <div style="font-size:0.85rem;opacity:0.85;">Avg Company Score</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card" style="background:linear-gradient(135deg,#7c3aed,#a78bfa);">
                <div><i class="fas fa-user-graduate fa-lg mb-2 opacity-75"></i></div>
                <h2><?php echo $statsRow['avg_student_score'] ?? '–'; ?>/10</h2>
                <div style="font-size:0.85rem;opacity:0.85;">Avg Student Score</div>
            </div>
        </div>
    </div>

    <?php if ($statsRow['total_cr'] > 0): ?>
    <div class="alert alert-success mb-4">
        <i class="fas fa-thumbs-up me-2"></i>
        <strong><?php echo $statsRow['recommenders']; ?></strong> out of
        <strong><?php echo $statsRow['total_cr']; ?></strong> students
        (<?php echo round($statsRow['recommenders'] / max(1,$statsRow['total_cr']) * 100); ?>%)
        would recommend their company to other students.
    </div>
    <?php endif; ?>

    <!-- Company Rankings -->
    <div class="card mb-4">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0 section-header">
                <i class="fas fa-trophy text-warning me-2"></i>Company Rankings (Rated by Students)
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Organisation</th>
                            <th>Industry</th>
                            <th>Overall</th>
                            <th>Mentorship</th>
                            <th>Environment</th>
                            <th>Learning</th>
                            <th>Support</th>
                            <th>Recommend</th>
                            <th>Ratings</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php $rank=1; while($r=$companyRatings->fetch_assoc()): ?>
                        <?php
                        $score = $r['avg_overall'];
                        $cls   = $score >= 7 ? 'score-high' : ($score >= 5 ? 'score-mid' : ($score ? 'score-low' : 'text-muted'));
                        ?>
                        <tr>
                            <td>
                                <?php if($rank==1 && $r['total_ratings']>0): ?>
                                    <i class="fas fa-trophy text-warning"></i>
                                <?php elseif($rank==2 && $r['total_ratings']>0): ?>
                                    <i class="fas fa-medal" style="color:#aaa;"></i>
                                <?php elseif($rank==3 && $r['total_ratings']>0): ?>
                                    <i class="fas fa-medal" style="color:#cd7f32;"></i>
                                <?php else: ?>
                                    <span class="text-muted"><?php echo $rank; ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($r['name']); ?></strong>
                                <div style="font-size:0.75rem;color:#777;"><?php echo htmlspecialchars($r['location']); ?></div>
                            </td>
                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($r['industry_type']); ?></span></td>
                            <td class="score-cell <?php echo $cls; ?>">
                                <?php echo $r['total_ratings'] > 0 ? $score : '<span class="text-muted">–</span>'; ?>
                            </td>
                            <td><?php echo $r['avg_mentorship'] ?: '<span class="text-muted">–</span>'; ?></td>
                            <td><?php echo $r['avg_environment'] ?: '<span class="text-muted">–</span>'; ?></td>
                            <td><?php echo $r['avg_learning'] ?: '<span class="text-muted">–</span>'; ?></td>
                            <td><?php echo $r['avg_support'] ?: '<span class="text-muted">–</span>'; ?></td>
                            <td>
                                <?php if($r['total_ratings'] > 0): ?>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress flex-grow-1" style="width:60px;">
                                            <div class="progress-bar bg-success" style="width:<?php echo $r['recommend_pct']; ?>%"></div>
                                        </div>
                                        <span style="font-size:0.8rem;"><?php echo $r['recommend_pct']; ?>%</span>
                                    </div>
                                <?php else: ?><span class="text-muted">–</span><?php endif; ?>
                            </td>
                            <td><span class="badge bg-primary"><?php echo $r['total_ratings']; ?></span></td>
                        </tr>
                    <?php $rank++; endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Student Performance Rankings -->
    <div class="card mb-4">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0 section-header">
                <i class="fas fa-user-graduate text-primary me-2"></i>Student Performance (Rated by Companies)
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Student</th>
                            <th>Programme</th>
                            <th>Company</th>
                            <th>Overall</th>
                            <th>Attendance</th>
                            <th>Technical</th>
                            <th>Communication</th>
                            <th>Teamwork</th>
                            <th>Recommendation</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php $rank=1; while($r=$studentRatings->fetch_assoc()): ?>
                        <?php
                        $score = $r['avg_overall'];
                        $cls   = $score >= 7 ? 'score-high' : ($score >= 5 ? 'score-mid' : 'score-low');
                        $recBadge = [
                            'excellent' => 'bg-success',
                            'good'      => 'bg-primary',
                            'average'   => 'bg-warning text-dark',
                            'poor'      => 'bg-danger',
                        ][$r['recommendation']] ?? 'bg-secondary';
                        ?>
                        <tr>
                            <td class="text-muted"><?php echo $rank; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($r['full_name']); ?></strong>
                                <div style="font-size:0.75rem;color:#777;"><?php echo htmlspecialchars($r['sid']); ?></div>
                            </td>
                            <td><?php echo htmlspecialchars($r['program']); ?></td>
                            <td><?php echo htmlspecialchars($r['org_name']); ?></td>
                            <td class="score-cell <?php echo $cls; ?>"><?php echo $score; ?></td>
                            <td><?php echo $r['avg_attendance']; ?></td>
                            <td><?php echo $r['avg_technical']; ?></td>
                            <td><?php echo $r['avg_communication']; ?></td>
                            <td><?php echo $r['avg_teamwork']; ?></td>
                            <td><span class="badge <?php echo $recBadge; ?>"><?php echo ucfirst($r['recommendation']); ?></span></td>
                        </tr>
                    <?php $rank++; endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Problematic Placements -->
    <div class="card mb-4">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0 section-header" style="border-left-color:#dc3545;">
                <i class="fas fa-exclamation-triangle text-danger me-2"></i>Problematic Placements
                <small class="text-muted fw-normal ms-2">(ratings ≤ 4/10 by either party)</small>
            </h5>
        </div>
        <div class="card-body">
            <?php if ($problematic->num_rows == 0): ?>
                <div class="text-center text-muted py-4">
                    <i class="fas fa-check-circle fa-3x text-success mb-3 d-block"></i>
                    No problematic placements detected at this time.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Student</th>
                                <th>Organisation</th>
                                <th>Student Gave Company</th>
                                <th>Company Gave Student</th>
                                <th>Issue</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while($r=$problematic->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($r['student_name']); ?></strong>
                                    <div style="font-size:0.75rem;color:#777;"><?php echo htmlspecialchars($r['sid']); ?></div>
                                </td>
                                <td><?php echo htmlspecialchars($r['org_name']); ?></td>
                                <td>
                                    <?php if($r['student_gave']): ?>
                                        <span class="score-cell score-low"><?php echo $r['student_gave']; ?>/10</span>
                                    <?php else: ?><span class="text-muted">Not rated</span><?php endif; ?>
                                </td>
                                <td>
                                    <?php if($r['company_gave']): ?>
                                        <span class="score-cell score-low"><?php echo $r['company_gave']; ?>/10</span>
                                    <?php else: ?><span class="text-muted">Not rated</span><?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-flag bg-danger"><?php echo htmlspecialchars($r['flag']); ?></span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
