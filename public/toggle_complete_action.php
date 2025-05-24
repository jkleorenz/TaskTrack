<?php
require_once '../includes/auth_check.php';
require_once '../includes/db_connect.php';

$redirect_view = 'editDelete'; // Default if not specified, or choose another sensible default

// Determine the view to return to from the GET parameter
if (isset($_GET['view']) && in_array($_GET['view'], ['addTask', 'markComplete', 'editDelete', 'viewCompleted'])) {
    $redirect_view = $_GET['view'];
}

if (isset($_GET['id'])) {
    $task_id = intval($_GET['id']);
    $user_id = $_SESSION['user_id'];

    // Check task status and parent relationship
    $stmt_check = $conn->prepare("SELECT is_completed, parent_task_id FROM tasks WHERE id = ? AND user_id = ?");
    $stmt_check->bind_param("ii", $task_id, $user_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($result_check->num_rows === 1) {
        $task = $result_check->fetch_assoc();
        $new_status = $task['is_completed'] ? 0 : 1;

        // If marking a subtask as incomplete, also mark the parent task as incomplete
        if ($new_status === 0 && $task['parent_task_id'] !== null) {
            $stmt_update_parent = $conn->prepare("UPDATE tasks SET is_completed = 0 WHERE id = ? AND user_id = ?");
            $stmt_update_parent->bind_param("ii", $task['parent_task_id'], $user_id);
            $stmt_update_parent->execute();
            $stmt_update_parent->close();
        }

        // Update the current task
        $stmt_update = $conn->prepare("UPDATE tasks SET is_completed = ? WHERE id = ? AND user_id = ?");
        $stmt_update->bind_param("iii", $new_status, $task_id, $user_id);
        if($stmt_update->execute()) {
            $_SESSION['success_message'] = "Task status updated.";
        } else {
            $_SESSION['error_message'] = "Failed to update task status.";
        }
        $stmt_update->close();
    } else {
        $_SESSION['error_message'] = "Invalid task or permission denied.";
    }
    $stmt_check->close();
}
$conn->close();

header("Location: dashboard.php?view=" . urlencode($redirect_view));
exit();
?>