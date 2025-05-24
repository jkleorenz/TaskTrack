<?php
require_once '../includes/auth_check.php'; // Ensures user is logged in
require_once '../includes/db_connect.php';

$redirect_view = 'addTask'; // Default view to return to

if (isset($_POST['current_view_source']) && in_array($_POST['current_view_source'], ['addTask', 'markComplete', 'editDelete', 'viewCompleted'])) {
    $redirect_view = $_POST['current_view_source'];
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = trim($_POST['title']);
    $description = isset($_POST['description']) ? trim($_POST['description']) : null;
    $user_id = $_SESSION['user_id'];

    $parent_task_id_from_existing_flow = isset($_POST['parent_task_id']) && !empty($_POST['parent_task_id']) ? (int)$_POST['parent_task_id'] : null;

    $add_sub_task_now = isset($_POST['add_sub_task_now']) && $_POST['add_sub_task_now'] == '1';
    
    $submitted_sub_titles = [];
    if ($add_sub_task_now && !$parent_task_id_from_existing_flow && isset($_POST['sub_titles']) && is_array($_POST['sub_titles'])) {
        foreach ($_POST['sub_titles'] as $st) {
            $trimmed_st = trim($st);
            if (!empty($trimmed_st)) { // Only consider non-empty sub-task titles
                $submitted_sub_titles[] = $trimmed_st;
            }
        }
    }

    // Basic validation
    if (empty($title)) {
        $_SESSION['error_message'] = "Task title cannot be empty.";
    } elseif ($add_sub_task_now && !$parent_task_id_from_existing_flow && empty($submitted_sub_titles)) {
        // If checkbox is checked to add sub-tasks, at least one valid sub-task title must be provided.
        $_SESSION['error_message'] = "If adding sub-tasks, at least one sub-task title is required.";
    } else {
        $conn->begin_transaction(); 

        try {
            // Insert main task OR sub-task (for existing parent)
            $stmt_main = $conn->prepare("INSERT INTO tasks (user_id, title, description, parent_task_id) VALUES (?, ?, ?, ?)");
            $stmt_main->bind_param("issi", $user_id, $title, $description, $parent_task_id_from_existing_flow);

            if ($stmt_main->execute()) {
                $newly_created_task_id = $conn->insert_id; 
                $success_message_text = "Task added successfully!";

                // If adding immediate sub-tasks and there are valid titles
                if ($add_sub_task_now && !$parent_task_id_from_existing_flow && !empty($submitted_sub_titles)) {
                    $all_subs_added_successfully = true;
                    foreach ($submitted_sub_titles as $current_sub_title) {
                        $stmt_sub = $conn->prepare("INSERT INTO tasks (user_id, title, description, parent_task_id) VALUES (?, ?, NULL, ?)");
                        // description for immediate sub-tasks is NULL
                        $stmt_sub->bind_param("isi", $user_id, $current_sub_title, $newly_created_task_id); 
                        
                        if (!$stmt_sub->execute()) {
                            $all_subs_added_successfully = false;
                            // It's better to throw an exception to trigger rollback for atomicity
                            throw new Exception("Failed to add sub-task: '" . htmlspecialchars($current_sub_title) . "'. Error: " . $stmt_sub->error);
                        }
                        $stmt_sub->close();
                    }
                    
                    if ($all_subs_added_successfully) {
                        $num_subs = count($submitted_sub_titles);
                        $success_message_text .= " " . $num_subs . " sub-task" . ($num_subs > 1 ? "s" : "") . " also added!";
                    }
                }
                $_SESSION['success_message'] = $success_message_text;
                
                $redirect_view = 'editDelete';

            } else {
                throw new Exception("Failed to add task: " . $stmt_main->error);
            }
            $stmt_main->close();
            $conn->commit(); 

        } catch (Exception $e) {
            $conn->rollback(); 
            $_SESSION['error_message'] = "An error occurred: " . $e->getMessage();
        }
    }
} else {
    $_SESSION['error_message'] = "Invalid request method.";
}

$conn->close();
header("Location: dashboard.php?view=" . urlencode($redirect_view));
exit();
?>