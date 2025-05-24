<?php
require_once '../includes/auth_check.php';
require_once '../includes/db_connect.php';
require_once '../includes/csrf_token.php';

$user_id = $_SESSION['user_id'];

// --- Helper function to structure tasks (remains the same as your last version) ---
function structure_tasks($tasks_flat_list) {
    $main_tasks = [];
    $sub_tasks_map = []; 
    $tasks_by_id = [];
    foreach ($tasks_flat_list as $task) {
        $tasks_by_id[$task['id']] = $task;
        if (!empty($task['parent_task_id'])) {
            if (!isset($sub_tasks_map[$task['parent_task_id']])) {
                $sub_tasks_map[$task['parent_task_id']] = [];
            }
            $sub_tasks_map[$task['parent_task_id']][] = $task;
        }
    }
    foreach ($tasks_flat_list as $task) {
        if (empty($task['parent_task_id'])) {
            $main_tasks[] = $task;
        }
    }
    return [$main_tasks, $sub_tasks_map];
}

// --- Fetch Data for Different Views ---
$parent_task_for_add_form = null; 
if (isset($_GET['view']) && $_GET['view'] === 'addTask' && isset($_GET['parent_id'])) {
    $stmt_parent_check = $conn->prepare("SELECT id, title FROM tasks WHERE id = ? AND user_id = ? AND parent_task_id IS NULL");
    $stmt_parent_check->bind_param("ii", $_GET['parent_id'], $user_id);
    $stmt_parent_check->execute(); $result_parent_check = $stmt_parent_check->get_result();
    if ($result_parent_check->num_rows > 0) { $parent_task_for_add_form = $result_parent_check->fetch_assoc(); }
    $stmt_parent_check->close();
}
$pending_tasks_flat = [];
$stmt_pending = $conn->prepare("SELECT id, title, description, parent_task_id, is_completed FROM tasks WHERE user_id = ? AND is_completed = 0");
$stmt_pending->bind_param("i", $user_id); $stmt_pending->execute(); $result_pending = $stmt_pending->get_result();
while ($row = $result_pending->fetch_assoc()) { $pending_tasks_flat[] = $row; }
$stmt_pending->close(); list($pending_main_tasks, $pending_sub_tasks_map) = structure_tasks($pending_tasks_flat);

$all_tasks_flat = [];
$stmt_all_manage = $conn->prepare("SELECT id, title, description, is_completed, parent_task_id FROM tasks WHERE user_id = ?"); 
$stmt_all_manage->bind_param("i", $user_id); $stmt_all_manage->execute(); $result_all_manage = $stmt_all_manage->get_result();
while ($row = $result_all_manage->fetch_assoc()) { $all_tasks_flat[] = $row; }
$stmt_all_manage->close(); list($all_main_tasks_for_management, $all_sub_tasks_map_for_management) = structure_tasks($all_tasks_flat);

$completed_tasks_flat = [];
$stmt_completed_view = $conn->prepare("SELECT id, title, description, parent_task_id, is_completed FROM tasks WHERE user_id = ? AND is_completed = 1");
$stmt_completed_view->bind_param("i", $user_id); $stmt_completed_view->execute(); $result_completed_view = $stmt_completed_view->get_result();
while ($row = $result_completed_view->fetch_assoc()) { $completed_tasks_flat[] = $row; }
$stmt_completed_view->close(); list($completed_main_tasks_to_view, $completed_sub_tasks_map_to_view) = structure_tasks($completed_tasks_flat);

$success_message = ''; if (isset($_SESSION['success_message'])) { $success_message = $_SESSION['success_message']; unset($_SESSION['success_message']); }
$error_message = ''; if (isset($_SESSION['error_message'])) { $error_message = $_SESSION['error_message']; unset($_SESSION['error_message']); }
$conn->close(); 

// --- Helper function to render a single task item (remains the same) ---
function render_task_item($task, $view_context, $is_sub_task = false, $extra_buttons_callback = null) {
    $item_class = "task-item"; if ($task['is_completed']) $item_class .= " completed"; if ($is_sub_task) $item_class .= " sub-task";
    echo '<li class="' . $item_class . '" data-task-id="' . $task['id'] . '">';
    echo '<div class="task-info">';
    echo '<h4 class="task-title">' . htmlspecialchars($task['title']) . '</h4>';
    if (!empty($task['description'])) { echo '<p class="task-description">' . nl2br(htmlspecialchars($task['description'])) . '</p>'; }
    echo '</div>';
    echo '<div class="task-actions">';
    if ($view_context === 'markComplete') { echo '<a href="toggle_complete_action.php?id=' . $task['id'] . '&view=' . $view_context . '&csrf_token=' . urlencode(generateCSRFToken()) . '" class="btn btn-success">Mark Complete</a>'; } 
    elseif ($view_context === 'editDelete') { $toggle_text = $task['is_completed'] ? 'Undo' : 'Done'; $toggle_class = $task['is_completed'] ? 'btn-secondary' : 'btn-success'; echo '<a href="toggle_complete_action.php?id=' . $task['id'] . '&view=' . $view_context . '&csrf_token=' . urlencode(generateCSRFToken()) . '" class="btn ' . $toggle_class . '">' . $toggle_text . '</a>'; } 
    elseif ($view_context === 'viewCompleted') { echo '<a href="toggle_complete_action.php?id=' . $task['id'] . '&view=' . $view_context . '&csrf_token=' . urlencode(generateCSRFToken()) . '" class="btn btn-secondary">Mark Incomplete</a>'; }
    if ($view_context === 'editDelete' || $view_context === 'viewCompleted') { $confirm_message = 'Are you sure you want to delete this task?'; if (!$is_sub_task) $confirm_message .= ' This may also delete its sub-tasks.'; echo '<a href="delete_task_action.php?id=' . $task['id'] . '&view=' . $view_context . '&csrf_token=' . urlencode(generateCSRFToken()) . '" onclick="return confirm(\'' . addslashes($confirm_message) . '\');" class="btn btn-danger btn-delete-confirm">Delete</a>'; }
    if (is_callable($extra_buttons_callback) && !$is_sub_task) { call_user_func($extra_buttons_callback, $task); }
    echo '</div></li>';
}
// --- Helper function to render a list of tasks and their sub-tasks (remains the same) ---
function render_task_list($main_tasks_data, $sub_tasks_map_data, $view_context, $no_tasks_message, $extra_button_callback_for_main_task = null) {
    $tasks_to_render_exist = false;
    foreach ($main_tasks_data as $main_task) { if (($view_context === 'markComplete' && $main_task['is_completed'] && !isset($sub_tasks_map_data[$main_task['id']])) || ($view_context === 'viewCompleted' && !$main_task['is_completed'] && !isset($sub_tasks_map_data[$main_task['id']]))) continue; $tasks_to_render_exist = true; break; }
    if (!$tasks_to_render_exist && ($view_context === 'markComplete' || $view_context === 'viewCompleted')) { foreach($sub_tasks_map_data as $parent_id => $subtasks) { foreach($subtasks as $sub) { if (($view_context === 'markComplete' && !$sub['is_completed']) || ($view_context === 'viewCompleted' && $sub['is_completed'])) {$tasks_to_render_exist = true; break 2;}} } } 
    elseif ($view_context === 'editDelete' && (!empty($main_tasks_data) || !empty($sub_tasks_map_data))) { $tasks_to_render_exist = true; }
    if (!$tasks_to_render_exist) { echo "<p>$no_tasks_message</p>"; return; }
    echo '<ul class="task-list">'; $any_task_rendered = false;
    foreach ($main_tasks_data as $main_task) {
        $show_main_task = false; if ($view_context === 'editDelete' || ($view_context === 'markComplete' && !$main_task['is_completed']) || ($view_context === 'viewCompleted' && $main_task['is_completed'])) { $show_main_task = true; }
        $has_relevant_subtasks = false; if (isset($sub_tasks_map_data[$main_task['id']])) { foreach ($sub_tasks_map_data[$main_task['id']] as $sub_task) { if ($view_context === 'editDelete' || ($view_context === 'markComplete' && !$sub_task['is_completed']) || ($view_context === 'viewCompleted' && $sub_task['is_completed'])) { $has_relevant_subtasks = true; break; } } }
        if ($show_main_task || $has_relevant_subtasks) { 
            if ($show_main_task) { render_task_item($main_task, $view_context, false, $extra_button_callback_for_main_task); $any_task_rendered = true; } 
            elseif ($has_relevant_subtasks) { echo '<li class="task-item parent-context-only ' . ($main_task['is_completed'] ? 'completed' : '') . '" data-task-id="' . $main_task['id'] . '"><div class="task-info"><h4 class="task-title" style="font-style: italic; color: #777;">' . htmlspecialchars($main_task['title']) . ' (Sub-tasks below)</h4></div></li>'; $any_task_rendered = true; }
            if (isset($sub_tasks_map_data[$main_task['id']]) && !empty($sub_tasks_map_data[$main_task['id']])) {
                echo '<ul class="sub-task-list">'; foreach ($sub_tasks_map_data[$main_task['id']] as $sub_task) { if ($view_context === 'editDelete' || ($view_context === 'markComplete' && !$sub_task['is_completed']) || ($view_context === 'viewCompleted' && $sub_task['is_completed'])) { render_task_item($sub_task, $view_context, true); $any_task_rendered = true; } } echo '</ul>';
            }
        }
    } if (!$any_task_rendered) { echo "<p>$no_tasks_message</p>"; } echo '</ul>';
}
?>

<?php include '../templates/header.php'; ?>

<style>
.task-item {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 15px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.task-item:not(.sub-task) {
    display: flex;
    justify-content: space-between;
    align-items: center;
    min-height: 100px; /* Fixed height for main tasks */
    padding: 20px 25px; /* Increased padding */
    margin-bottom: 20px; /* More space between tasks */
}

.task-item:not(.sub-task) .task-info {
    flex: 1;
    margin-right: 25px;
}

.task-item:not(.sub-task) .task-actions {
    display: flex;
    gap: 12px; /* Slightly increased gap */
    align-items: center;
}

.task-item:not(.sub-task) .task-title {
    margin: 0;
    font-size: 20px; /* Larger font size for main tasks */
    color: #333;
    font-weight: 600; /* Bolder font */
    line-height: 1.4;
}

.task-item.sub-task {
    margin-left: 30px;
    min-height: 70px; /* Smaller fixed height for subtasks */
    padding: 15px 20px; /* Smaller padding for subtasks */
    border-left: 3px solid #4a90e2;
    background: #f8f9fa;
}

.task-item.sub-task .task-title {
    font-size: 16px; /* Smaller font size for subtasks */
    font-weight: 500;
}

/* General button styles */
.task-item .btn {
    min-width: 90px; /* Reduced from 100px */
    padding: 6px 12px; /* Standard padding */
}

/* Specific styles for Manage Tasks section */
#editDeleteSection .task-item .btn {
    min-width: 80px; /* Even smaller for manage tasks section */
    padding: 5px 10px; /* Smaller padding */
    font-size: 0.9rem; /* Slightly smaller font */
}

/* Make the "Add Sub-task" button consistent with others */
#editDeleteSection .btn-info {
    min-width: 80px;
    padding: 5px 10px;
    font-size: 0.9rem;
}

.task-item.completed {
    background-color: #f8f9fa;
    border-color: #ddd;
}

.task-item.completed .task-title {
    color: #666;
    text-decoration: line-through;
}

/* Enhanced shadow for main tasks */
.task-item:not(.sub-task) {
    box-shadow: 0 3px 8px rgba(0,0,0,0.1);
}

/* Subtask shadow */
.task-item.sub-task {
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}
</style>

<div class="container dashboard">
    <h2>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h2>

    <?php if (!empty($success_message)): ?><div class="alert alert-success"><p><?php echo htmlspecialchars($success_message); ?></p></div><?php endif; ?>
    <?php if (!empty($error_message)): ?><div class="alert alert-danger"><p><?php echo htmlspecialchars($error_message); ?></p></div><?php endif; ?>

    <p style="margin-bottom: 1.5rem; font-size: 1.1em; color: var(--secondary-color);">What would you like to do?</p>
    <div class="dashboard-options-container">
        <div class="dashboard-option-box" data-view="addTask"><h4>1. Add Task</h4><p class="option-description">Create a new task or sub-task.</p></div>
        <div class="dashboard-option-box" data-view="markComplete"><h4>2. Mark Complete</h4><p class="option-description">View pending tasks and mark them as done.</p></div>
        <div class="dashboard-option-box" data-view="editDelete"><h4>3. Manage Tasks</h4><p class="option-description">Edit, delete, or add sub-tasks.</p></div>
        <div class="dashboard-option-box" data-view="viewCompleted"><h4>4. View Completed</h4><p class="option-description">Review all tasks you've finished.</p></div>
    </div>

    <!-- 1. Add Task Section -->
    <section id="addTaskSection" class="dashboard-section" style="display:none;">
        <?php 
        $add_task_heading = "1. Add New Task";
        if ($parent_task_for_add_form) {
            $add_task_heading = "Add Sub-task for: \"" . htmlspecialchars($parent_task_for_add_form['title']) . "\"";
        }
        ?>
        <h3><?php echo $add_task_heading; ?></h3>
        <form action="add_task_action.php" method="POST" class="task-form">
            <?php echo getCSRFTokenField(); ?>
            <input type="hidden" name="current_view_source" value="<?php echo isset($_GET['return_to_view']) ? htmlspecialchars($_GET['return_to_view']) : 'addTask'; ?>">
            
            <?php if ($parent_task_for_add_form): ?>
                <input type="hidden" name="parent_task_id" value="<?php echo htmlspecialchars($parent_task_for_add_form['id']); ?>">
            <?php endif; ?>

            <div class="form-group">
                <label for="title">Title <?php if (!$parent_task_for_add_form) echo "(Main Task)"; ?>:</label>
                <input type="text" id="title" name="title" required>
            </div>

            <?php if (!$parent_task_for_add_form): // Only show "Add sub-task now?" option when creating a NEW MAIN task ?>
            <div class="form-group" style="margin-top: 1rem; margin-bottom: 1rem; padding:0.75rem 0; border-top: 1px solid #eee; border-bottom: 1px solid #eee;">
                <label for="add_sub_task_now_checkbox" class="checkbox-label">
                    <input type="checkbox" id="add_sub_task_now_checkbox" name="add_sub_task_now" value="1" style="width:auto; margin-right: 8px; transform: scale(1.2);">
                    Add one or more subtasks to this new task?
                </label>
            </div>

            <div id="sub_tasks_wrapper" style="display:none; border: 1px solid var(--primary-color-light, #cfe2ff); padding: 1rem; margin-bottom: 1.5rem; border-radius: var(--border-radius); background-color: #f8f9fc;">
                <h4 style="margin-top:0; margin-bottom:1rem; font-size: 1.1em; color: var(--primary-color);">Sub-task Details:</h4>
                
                <div id="initial_sub_task_fields"> <!-- Container for the first sub-task -->
                    <div class="form-group sub-task-entry">
                        <label for="sub_title_0">Sub-task Title 1:</label>
                        <div style="display:flex; gap: 5px;">
                             <input type="text" id="sub_title_0" name="sub_titles[]" class="sub-title-input" style="flex-grow: 1;">
                             <!-- No remove button for the first one, or make it appear conditionally -->
                        </div>
                    </div>
                </div>

                <div id="additional_sub_tasks_container">
                    <!-- Dynamically added sub-task fields will go here -->
                </div>
                
                <button type="button" id="add_another_sub_task_btn" class="btn btn-sm btn-info" style="margin-top: 0.5rem;">+ Add Another Sub-task</button>
            </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="description">Description <?php if (!$parent_task_for_add_form) echo "for Main Task"; else echo "for Sub-task"; ?> (Optional):</label>
                <textarea id="description" name="description"></textarea>
            </div>
            
            <button type="submit" class="btn btn-success">
                <?php echo $parent_task_for_add_form ? 'Add This Sub-task' : 'Add Task'; ?>
            </button>
            <?php if ($parent_task_for_add_form && isset($_GET['return_to_view'])): ?>
                <a href="dashboard.php?view=<?php echo htmlspecialchars($_GET['return_to_view']); ?>" class="btn btn-secondary" style="margin-left: 10px;">Cancel</a>
            <?php endif; ?>
        </form>
    </section>

    <!-- Other sections (markComplete, editDelete, viewCompleted) remain the same -->
    <section id="markCompleteSection" class="dashboard-section" style="display:none;">
        <h3>2. Mark Task as Complete (Pending Tasks)</h3>
        <?php render_task_list($pending_main_tasks, $pending_sub_tasks_map, 'markComplete', 'No pending tasks to mark as complete. Well done!'); ?>
    </section>
    <section id="editDeleteSection" class="dashboard-section" style="display:none;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin: 0;">3. Manage Tasks (All Tasks)</h3>
            <?php if (!empty($all_tasks_flat)): ?>
                <a href="delete_all_tasks_action.php?csrf_token=<?php echo urlencode(generateCSRFToken()); ?>" onclick="return confirm('Are you sure you want to delete ALL your tasks? This action cannot be undone!');" class="btn btn-sm" style="background-color: #ff8c00; color: white; border-color: #ff8c00; text-decoration: none !important; transition: all 0.3s ease;" onmouseover="this.style.backgroundColor='#e67e00'; this.style.borderColor='#e67e00';" onmouseout="this.style.backgroundColor='#ff8c00'; this.style.borderColor='#ff8c00';">Delete All Tasks</a>
            <?php endif; ?>
        </div>
        <?php $add_subtask_button_callback = function($task) { if (empty($task['parent_task_id'])) { echo '<a href="dashboard.php?view=addTask&parent_id=' . $task['id'] . '&return_to_view=editDelete" class="btn btn-info">Add Sub-task</a>'; }};
        render_task_list($all_main_tasks_for_management, $all_sub_tasks_map_for_management, 'editDelete', 'No tasks found. Add one to get started!', $add_subtask_button_callback); ?>
    </section>
    <section id="viewCompletedSection" class="dashboard-section" style="display:none;">
        <h3>4. View Completed Tasks</h3>
        <?php render_task_list($completed_main_tasks_to_view, $completed_sub_tasks_map_to_view, 'viewCompleted', 'No tasks have been completed yet.'); ?>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const addTaskCheckbox = document.getElementById('add_sub_task_now_checkbox');
    const subTasksWrapper = document.getElementById('sub_tasks_wrapper'); // The main wrapper for all sub-task UI
    
    const firstSubTitleInput = document.getElementById('sub_title_0'); 
    
    const addAnotherSubTaskBtn = document.getElementById('add_another_sub_task_btn');
    const additionalSubTasksContainer = document.getElementById('additional_sub_tasks_container');
    
    const mainForm = document.querySelector('#addTaskSection .task-form');
    const mainFormSubmitButton = mainForm ? mainForm.querySelector('button[type="submit"]') : null;
    
    let initialButtonText = 'Add Task'; 
    if (mainFormSubmitButton) {
        initialButtonText = mainFormSubmitButton.textContent.trim(); 
    }
    let nextSubTaskIndex = 1; // Index suffix for dynamically added sub-tasks (0 is the first)

    if (addTaskCheckbox && subTasksWrapper && firstSubTitleInput && mainFormSubmitButton) {
        addTaskCheckbox.addEventListener('change', function() {
            if (this.checked) {
                subTasksWrapper.style.display = 'block';
                firstSubTitleInput.required = true; // Make the first one required when section is visible
                mainFormSubmitButton.textContent = 'Add Task';
            } else {
                subTasksWrapper.style.display = 'none';
                firstSubTitleInput.required = false;
                mainFormSubmitButton.textContent = initialButtonText; 
                
                // Clear and reset dynamic sub-tasks
                additionalSubTasksContainer.innerHTML = ''; 
                firstSubTitleInput.value = ''; // Clear the first one too
                nextSubTaskIndex = 1; // Reset counter for dynamic adds
            }
        });
    }

    if (addAnotherSubTaskBtn && additionalSubTasksContainer) {
        addAnotherSubTaskBtn.addEventListener('click', function() {
            const newSubTaskEntry = document.createElement('div');
            newSubTaskEntry.classList.add('form-group', 'sub-task-entry', 'dynamic-sub-task');
            newSubTaskEntry.style.marginTop = '0.5rem'; // Add some space

            const label = document.createElement('label');
            const currentDisplayNumber = document.querySelectorAll('#sub_tasks_wrapper .sub-task-entry').length + 1;
            label.setAttribute('for', 'sub_title_' + nextSubTaskIndex);
            label.textContent = 'Sub-task Title ' + currentDisplayNumber + ':';

            const inputDiv = document.createElement('div');
            inputDiv.style.display = 'flex';
            inputDiv.style.gap = '5px';
            inputDiv.style.alignItems = 'center';

            const input = document.createElement('input');
            input.type = 'text';
            input.id = 'sub_title_' + nextSubTaskIndex;
            input.name = 'sub_titles[]'; // All sub-task titles use this array name
            input.classList.add('sub-title-input');
            input.style.flexGrow = '1';
            // input.required = true; // Could make dynamically added ones required too

            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.classList.add('btn', 'btn-sm', 'btn-danger');
            removeBtn.textContent = 'Remove';
            removeBtn.style.lineHeight = '1'; // Adjust for small button
            removeBtn.addEventListener('click', function() {
                newSubTaskEntry.remove();
                // Re-number labels of remaining dynamic sub-tasks for UX (optional but good)
                const dynamicEntries = additionalSubTasksContainer.querySelectorAll('.dynamic-sub-task');
                dynamicEntries.forEach((entry, index) => {
                    const lbl = entry.querySelector('label');
                    if (lbl) {
                        // The first sub-task is #1, so dynamic ones start from #2 + index
                        lbl.textContent = 'Sub-task Title ' + (2 + index) + ':';
                    }
                });
            });

            inputDiv.appendChild(input);
            inputDiv.appendChild(removeBtn);
            newSubTaskEntry.appendChild(label);
            newSubTaskEntry.appendChild(inputDiv);
            additionalSubTasksContainer.appendChild(newSubTaskEntry);

            nextSubTaskIndex++; // Increment for the next potential ID
        });
    }

    // --- Existing view switching logic (remains the same) ---
    const optionBoxes = document.querySelectorAll('.dashboard-option-box');
    const sections = document.querySelectorAll('.dashboard-section');
    const defaultView = 'addTask'; 

    function showView(viewId, preserveQuery = false) {
        sections.forEach(section => section.style.display = 'none');
        optionBoxes.forEach(box => box.classList.remove('active'));
        const targetSection = document.getElementById(viewId + 'Section');
        if (targetSection) { targetSection.style.display = 'block'; }
        const targetBox = document.querySelector(`.dashboard-option-box[data-view="${viewId}"]`);
        if (targetBox) { targetBox.classList.add('active'); }
        if (history.pushState) {
            let newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
            let currentParams = new URLSearchParams(window.location.search); let newParams = new URLSearchParams();
            if (preserveQuery && viewId === 'addTask') { 
                 if(currentParams.has('parent_id')) newParams.set('parent_id', currentParams.get('parent_id'));
                 if(currentParams.has('return_to_view')) newParams.set('return_to_view', currentParams.get('return_to_view'));
            } newParams.set('view', viewId); 
            newUrl += '?' + newParams.toString(); history.pushState({path:newUrl}, '', newUrl);
        }
        if (viewId === 'addTask') {
            const currentUrlParams = new URLSearchParams(window.location.search);
            if (!currentUrlParams.has('parent_id')) { // Resetting for new main task
                if (addTaskCheckbox) {
                    addTaskCheckbox.checked = false;
                    // Manually trigger change to hide section and clear fields via its event listener
                    const changeEvent = new Event('change');
                    addTaskCheckbox.dispatchEvent(changeEvent);
                }
                if (mainFormSubmitButton) mainFormSubmitButton.textContent = 'Add Task'; 
            }
        }
    }
    optionBoxes.forEach(box => { box.addEventListener('click', function() { const viewId = this.getAttribute('data-view'); showView(viewId, false); }); });
    const urlParamsOnLoad = new URLSearchParams(window.location.search);
    let currentViewOnLoad = urlParamsOnLoad.get('view'); let preserveQueryOnLoad = false;
    if (currentViewOnLoad === 'addTask' && (urlParamsOnLoad.has('parent_id'))) { preserveQueryOnLoad = true; }
    if (currentViewOnLoad && document.getElementById(currentViewOnLoad + 'Section')) { showView(currentViewOnLoad, preserveQueryOnLoad); } 
    else { showView(defaultView); }
});
</script>

<?php include '../templates/footer.php'; ?>