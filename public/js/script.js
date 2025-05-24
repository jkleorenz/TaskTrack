document.addEventListener('DOMContentLoaded', function() {
    // Get all option boxes
    const optionBoxes = document.querySelectorAll('.dashboard-option-box');
    // Collect all dashboard sections
    const dashboardSections = document.querySelectorAll('.dashboard-section');

    const sectionsMap = {
        addTask: document.getElementById('addTaskSection'),
        markComplete: document.getElementById('markCompleteSection'),
        editDelete: document.getElementById('editDeleteSection'),
        viewCompleted: document.getElementById('viewCompletedSection')
    };

    function showSection(sectionIdToShow) {
        // Hide all sections
        dashboardSections.forEach(section => {
            if (section) section.style.display = 'none';
        });

        // Show the target section
        if (sectionsMap[sectionIdToShow]) {
            sectionsMap[sectionIdToShow].style.display = 'block';
        } else if (sectionsMap.addTask) { // Fallback if ID is invalid
             sectionsMap.addTask.style.display = 'block';
        }
    }

    function setActiveOptionBox(viewId) {
        optionBoxes.forEach(box => {
            if (box.dataset.view === viewId) {
                box.classList.add('active');
            } else {
                box.classList.remove('active');
            }
        });
    }

    if (optionBoxes.length > 0) {
        optionBoxes.forEach(box => {
            box.addEventListener('click', function() {
                const selectedView = this.dataset.view;
                showSection(selectedView);
                setActiveOptionBox(selectedView);

                // Update URL
                if (history.pushState) {
                    const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + '?view=' + selectedView;
                    window.history.pushState({path:newUrl},'',newUrl);
                }
            });
        });

        // Check for URL parameter 'view' to set initial view
        const urlParams = new URLSearchParams(window.location.search);
        const initialView = urlParams.get('view');
        let viewToLoad = 'addTask'; // Default view

        if (initialView && sectionsMap[initialView]) {
            viewToLoad = initialView;
        }

        showSection(viewToLoad);
        setActiveOptionBox(viewToLoad);

    } else {
        // Fallback if no option boxes are found (should not happen with correct HTML)
        if (sectionsMap.addTask) {
            showSection('addTask');
        }
    }

    // Re-add confirmation for delete buttons
    const deleteButtons = document.querySelectorAll('.btn-delete-confirm');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(event) {
            const message = this.dataset.confirmMessage || 'Are you sure you want to delete this item?';
            if (!confirm(message)) {
                event.preventDefault();
            }
        });
    });
});