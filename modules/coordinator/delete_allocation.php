<?php
// modules/coordinator/delete_allocation.php
require_once '../../config/database.php';

if (!isLoggedIn() || !hasRole('coordinator')) {
    redirect('index.php');
}

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $conn->query("DELETE FROM allocations WHERE id = $id");
    $_SESSION['success'] = "Allocation deleted.";
}
redirect('modules/coordinator/matching.php');
?>