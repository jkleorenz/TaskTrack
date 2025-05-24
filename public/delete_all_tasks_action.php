<?php
/**
 * Delete All Tasks Action
 * 
 * This script handles the deletion of all tasks for the current user.
 * It includes security checks (authentication and CSRF protection)
 * and provides feedback messages to the user.
 */

// Include required files
require_once '../includes/auth_check.php';    // Ensures user is logged in
require_once '../includes/db_connect.php';    // Database connection
require_once '../includes/csrf_token.php';    // Security token functions

// Get the current user's ID from their session
$user_id = $_SESSION['user_id'];

// ===== Security Check: CSRF Protection =====
// Verify that the request includes a valid CSRF token
if (!isset($_GET['csrf_token']) || !validateCSRFToken($_GET['csrf_token'])) {
    // If the token is missing or invalid, show an error
    $_SESSION['error_message'] = "Invalid security token. Please try again.";
    header("Location: dashboard.php?view=editDelete");
    exit();
}

// ===== Database Operation: Delete All Tasks =====
// Prepare the SQL statement to delete all tasks for this user
$stmt = $conn->prepare("DELETE FROM tasks WHERE user_id = ?");
$stmt->bind_param("i", $user_id);  // 'i' indicates an integer parameter

// Execute the delete operation and check the result
if ($stmt->execute()) {
    // Check how many tasks were actually deleted
    if ($stmt->affected_rows > 0) {
        // Success: Some tasks were deleted
        $_SESSION['success_message'] = "All tasks have been deleted successfully.";
    } else {
        // No tasks were found to delete
        $_SESSION['error_message'] = "No tasks found to delete.";
    }
} else {
    // An error occurred during deletion
    $_SESSION['error_message'] = "Failed to delete tasks: " . $stmt->error;
}

// Clean up: Close database connections
$stmt->close();
$conn->close();

// Redirect back to the dashboard's manage tasks view
header("Location: dashboard.php?view=editDelete");
exit();
?> 