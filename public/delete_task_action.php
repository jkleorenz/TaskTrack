<?php
/**
 * Delete Single Task Action
 * 
 * This script handles the deletion of a single task.
 * It includes security checks (authentication and CSRF protection)
 * and provides feedback messages to the user.
 */

// Include required files
require_once '../includes/auth_check.php';    // Ensures user is logged in
require_once '../includes/db_connect.php';    // Database connection
require_once '../includes/csrf_token.php';    // Security token functions

// ===== Setup Return URL =====
// Determine where to redirect after the operation
$redirect_url = "dashboard.php";
// Add any filter parameters to maintain the current view
if (isset($_GET['filter']) && in_array($_GET['filter'], ['all', 'pending', 'completed'])) {
    $redirect_url .= '?filter=' . urlencode($_GET['filter']);
}

// ===== Security Check: CSRF Protection =====
// Verify that the request includes a valid CSRF token
if (!isset($_GET['csrf_token']) || !validateCSRFToken($_GET['csrf_token'])) {
    // If the token is missing or invalid, show an error
    $_SESSION['error_message'] = "Invalid security token. Please try again.";
    header("Location: " . $redirect_url);
    exit();
}

// ===== Task Deletion Process =====
if (isset($_GET['id'])) {
    // Get the task ID and user ID
    $task_id = intval($_GET['id']);          // Convert to integer for security
    $user_id = $_SESSION['user_id'];

    // Prepare the SQL statement to delete the task
    // The WHERE clause ensures users can only delete their own tasks
    $stmt = $conn->prepare("DELETE FROM tasks WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $task_id, $user_id);  // 'ii' indicates two integer parameters

    // Execute the delete operation and check the result
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            // Success: Task was deleted
            $_SESSION['success_message'] = "Task deleted successfully.";
        } else {
            // No task was found with that ID for this user
            $_SESSION['error_message'] = "Task not found or permission denied.";
        }
    } else {
        // An error occurred during deletion
        $_SESSION['error_message'] = "Failed to delete task: " . $stmt->error;
    }
    
    // Clean up: Close the statement
    $stmt->close();
}

// Clean up: Close the database connection
$conn->close();

// Redirect back to the appropriate page
header("Location: " . $redirect_url);
exit();
?>