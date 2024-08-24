jQuery(document).ready(function($) {
    // Handle course dropdown changes
    $('.clm-course-dropdown').change(function() {
        var action = $(this).val();
        var courseId = $(this).find(':selected').data('course-id');
        var userManagementDiv = $(this).next('.clm-user-management');

        userManagementDiv.empty(); // Clear previous content

        if (action === 'add_user') {
            // Show form to add user
            userManagementDiv.append('<input type="email" class="clm-add-user-email" placeholder="Enter user email"><button class="clm-add-user-btn" data-course-id="' + courseId + '">Add User</button>');
        } else if (action === 'remove_user') {
            // Show form to remove user
            userManagementDiv.append('<input type="email" class="clm-remove-user-email" placeholder="Enter user email"><button class="clm-remove-user-btn" data-course-id="' + courseId + '">Remove User</button>');
        }
    });

    // Handle add user button click
    $(document).on('click', '.clm-add-user-btn', function() {
        var email = $(this).prev('.clm-add-user-email').val();
        var courseId = $(this).data('course-id');

        if (email) {
            // AJAX call to add user to course
            $.post(ajaxurl, {
                action: 'clm_add_user_to_course',
                email: email,
                course_id: courseId
            }, function(response) {
                alert(response.data);
            });
        }
    });

    // Handle remove user button click
    $(document).on('click', '.clm-remove-user-btn', function() {
        var email = $(this).prev('.clm-remove-user-email').val();
        var courseId = $(this).data('course-id');

        if (email) {
            // AJAX call to remove user from course
            $.post(ajaxurl, {
                action: 'clm_remove_user_from_course',
                email: email,
                course_id: courseId
            }, function(response) {
                alert(response.data);
            });
        }
    });
});
