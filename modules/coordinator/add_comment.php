<?php
// modules/coordinator/add_comment.php
require_once '../../config/database.php';

if (!isLoggedIn() || !hasRole('coordinator')) { redirect('index.php'); }

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $logbook_id = (int)($_POST['logbook_id'] ?? 0);
    $comment    = trim($_POST['comment'] ?? '');
    $student_id = (int)($_POST['student_id'] ?? 0);

    if ($logbook_id && !empty($comment)) {
        $coordinator_name = $_SESSION['user_email'] ?? 'Coordinator';
        $now = date('Y-m-d H:i:s');
        $stmt = $conn->prepare("UPDATE logbooks SET supervisor_comments = ?, commented_by = ?, commented_at = ?, status = 'reviewed' WHERE id = ?");
        $stmt->bind_param("sssi", $comment, $coordinator_name, $now, $logbook_id);
        $stmt->execute();
        logActivity('ADD_COMMENT', "Coordinator commented on logbook #$logbook_id");
        $_SESSION['success'] = "Comment posted successfully on Logbook #$logbook_id.";
    } else {
        $_SESSION['error'] = "Comment cannot be empty.";
    }
}

$student_id = (int)($_POST['student_id'] ?? 0);
redirect('view_logbooks.php' . ($student_id ? "?student_id=$student_id" : ''));
