<?php
require_once '../includes/auth_check.php';
require_once '../includes/db_connect.php';
require_once '../includes/csrf_token.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['task_id'])) {
    // Verify CSRF token
    if (!validateCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error_message'] = "Invalid security token. Please try again.";
        header("Location: dashboard.php?view=editDelete");
        exit();
    }

    $task_id = intval($_POST['task_id']);
    $user_id = $_SESSION['user_id'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $deadline = !empty($_POST['deadline']) ? $_POST['deadline'] : null;

    // Validate title
    if (empty($title)) {
        $_SESSION['error_message'] = "Task title cannot be empty.";
        header("Location: dashboard.php?view=editDelete");
        exit();
    }

    // Check if task belongs to user and if it's a sub-task
    $stmt_check = $conn->prepare("SELECT id, parent_task_id FROM tasks WHERE id = ? AND user_id = ?");
    $stmt_check->bind_param("ii", $task_id, $user_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($result_check->num_rows === 0) {
        $_SESSION['error_message'] = "Task not found or access denied.";
        header("Location: dashboard.php?view=editDelete");
        exit();
    }

    $task = $result_check->fetch_assoc();
    $is_sub_task = !empty($task['parent_task_id']);
    $stmt_check->close();

    // For sub-tasks, don't update the deadline
    if ($is_sub_task) {
        $stmt_update = $conn->prepare("UPDATE tasks SET title = ?, description = ? WHERE id = ? AND user_id = ?");
        $stmt_update->bind_param("ssii", $title, $description, $task_id, $user_id);
    } else {
        $stmt_update = $conn->prepare("UPDATE tasks SET title = ?, description = ?, deadline = ? WHERE id = ? AND user_id = ?");
        $stmt_update->bind_param("sssii", $title, $description, $deadline, $task_id, $user_id);
    }
    
    if ($stmt_update->execute()) {
        $_SESSION['success_message'] = ($is_sub_task ? "Sub-task" : "Task") . " updated successfully!";
    } else {
        $_SESSION['error_message'] = "Error updating " . ($is_sub_task ? "sub-task" : "task") . ". Please try again.";
    }
    $stmt_update->close();
} else {
    $_SESSION['error_message'] = "Invalid request.";
}

$conn->close();
header("Location: dashboard.php?view=editDelete");
exit(); 