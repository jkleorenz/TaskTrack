<?php
require_once '../includes/auth_check.php';
require_once '../includes/db_connect.php';
require_once '../includes/csrf_token.php';

$redirect_view = 'editDelete'; // Default if not specified, or choose another sensible default

// Determine the view to return to from the GET parameter
if (isset($_GET['view']) && in_array($_GET['view'], ['addTask', 'markComplete', 'editDelete', 'viewCompleted'])) {
    $redirect_view = $_GET['view'];
}

if (isset($_GET['id'])) {
    $task_id = intval($_GET['id']);
    $user_id = $_SESSION['user_id'];

    // Check task status, parent relationship, and if it has any incomplete subtasks
    $stmt_check = $conn->prepare("SELECT t1.is_completed, t1.parent_task_id, 
        (SELECT COUNT(*) FROM tasks t2 WHERE t2.parent_task_id = t1.id AND t2.is_completed = 0) as incomplete_subtasks_count 
        FROM tasks t1 WHERE t1.id = ? AND t1.user_id = ?");
    $stmt_check->bind_param("ii", $task_id, $user_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($result_check->num_rows === 1) {
        $task = $result_check->fetch_assoc();
        $new_status = $task['is_completed'] ? 0 : 1;

        // If trying to mark a main task as complete, check for incomplete subtasks
        if ($new_status === 1 && $task['parent_task_id'] === null && $task['incomplete_subtasks_count'] > 0) {
            $_SESSION['error_message'] = "Cannot mark task as complete until all sub-tasks are completed.";
            $stmt_check->close();
            $conn->close();
            header("Location: dashboard.php?view=" . urlencode($redirect_view));
            exit();
        }

        // If marking a main task as incomplete, mark all its subtasks as incomplete too
        if ($new_status === 0 && $task['parent_task_id'] === null) {
            $stmt_update_subtasks = $conn->prepare("UPDATE tasks SET is_completed = 0 WHERE parent_task_id = ? AND user_id = ?");
            $stmt_update_subtasks->bind_param("ii", $task_id, $user_id);
            $stmt_update_subtasks->execute();
            $stmt_update_subtasks->close();
        }
        // If marking a subtask as incomplete, also mark the parent task as incomplete
        else if ($new_status === 0 && $task['parent_task_id'] !== null) {
            $stmt_update_parent = $conn->prepare("UPDATE tasks SET is_completed = 0 WHERE id = ? AND user_id = ?");
            $stmt_update_parent->bind_param("ii", $task['parent_task_id'], $user_id);
            $stmt_update_parent->execute();
            $stmt_update_parent->close();
        }

        // Update the current task
        $stmt_update = $conn->prepare("UPDATE tasks SET is_completed = ? WHERE id = ? AND user_id = ?");
        $stmt_update->bind_param("iii", $new_status, $task_id, $user_id);
        
        if ($stmt_update->execute()) {
            $_SESSION['success_message'] = "Task status updated successfully!";
        } else {
            $_SESSION['error_message'] = "Error updating task status.";
        }
        $stmt_update->close();
    } else {
        $_SESSION['error_message'] = "Task not found or access denied.";
    }
    $stmt_check->close();
} else {
    $_SESSION['error_message'] = "No task specified.";
}

$conn->close();
header("Location: dashboard.php?view=" . urlencode($redirect_view));
exit();
?>