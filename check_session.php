<?php
session_start();

header('Content-Type: application/json');

// Check if the user_id session variable is set
if (isset($_SESSION['user_id'])) {
    // User is logged in
    echo json_encode(['loggedIn' => true]);
} else {
    // User is not logged in
    echo json_encode(['loggedIn' => false]);
}
?>

