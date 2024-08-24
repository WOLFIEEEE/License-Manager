<?php

// Shortcode for managing accounts
// Shortcode for managing accounts
add_shortcode('clm_company_manage_accounts', 'clm_company_manage_accounts_dashboard');

function custom_enroll_user_in_course($user_id, $course_id) {
    $result = Sensei_Utils::user_start_course($user_id, $course_id);
    $manual_enrol = Sensei_Course_Manual_Enrolment_Provider::instance();
    $enrolled = $manual_enrol->enrol_learner($user_id, $course_id);

    return $enrolled;
}

function custom_check_user_in_course($user_id, $course_id) {
    $user_id = 115;
    $course_enrolment = Sensei_Course_Enrolment::get_course_instance($course_id);

    if (!$course_enrolment) {
        error_log('CLM Error: Could not retrieve course instance for course ID ' . $course_id);
        return false; // Or handle the error appropriately
    }

    error_log('CLM Info: Checking enrollment for user ID ' . $user_id . ' in course ID ' . $course_id);
    $isenrolled = $course_enrolment->is_enrolled($user_id);

    if ($isenrolled === null) {
        error_log('CLM Error: is_enrolled returned null for user ID ' . $user_id . ' in course ID ' . $course_id);
    } else {
        error_log('CLM Info: Enrollment status for user ID ' . $user_id . ' in course ID ' . $course_id . ' is ' . var_export($isenrolled, true));
    }

    return $isenrolled;
}



function clm_company_manage_accounts_dashboard() {
    if (current_user_can('company')) {
        $user_id = get_current_user_id();
        $licenses = get_user_meta($user_id, '_clm_licenses', true);

        echo '<div class="clm-dashboard">';
        echo '<h2>' . __('Manage Accounts', 'clm') . '</h2>';

        if (!empty($licenses)) {
            foreach ($licenses as $course_id => $license_data) {
                $remaining_licenses = $license_data['total'] - $license_data['used'];
                $course_title = get_the_title($course_id);

                echo '<h3>' . esc_html($course_title) . '</h3>';
                echo '<p>' . str_replace('{license_count}', $remaining_licenses, get_option('clm_license_message', 'You have {license_count} licenses remaining.')) . '</p>';
                echo '<ul class="clm-sub-accounts">';

                // Display sub-accounts for this course
                $sub_accounts = clm_get_sub_accounts($user_id, $course_id);
                if (!empty($sub_accounts)) {
                    foreach ($sub_accounts as $sub_account) {
                        echo '<li>' . esc_html($sub_account->display_name) . ' (' . esc_html($sub_account->user_email) . ')</li>';
                    }
                } else {
                    echo '<li>' . __('No sub-accounts found.', 'clm') . '</li>';
                }

                echo '</ul>';

                // Add form to assign licenses
                if ($remaining_licenses > 0) {
                    echo '<h4>' . __('Assign License', 'clm') . '</h4>';
                    echo '<button class="clm-add-user-btn" data-course-id="' . esc_attr($course_id) . '">' . __('Add User', 'clm') . '</button>';
                } else {
                    echo '<p>' . __('No licenses remaining to assign.', 'clm') . '</p>';
                }
            }
        } else {
            echo '<p>' . __('No licenses found.', 'clm') . '</p>';
        }

        // Hidden form
        echo '<form id="clm-hidden-form" method="post" style="display:none;">';
        echo '<input type="hidden" name="clm_course_id" id="clm_course_id">';
        echo '<input type="hidden" name="clm_user_email" id="clm_user_email">';
        echo '<input type="hidden" name="clm_user_first_name" id="clm_user_first_name">';
        echo '<input type="hidden" name="clm_user_last_name" id="clm_user_last_name">';
        echo '<input type="hidden" name="action" value="clm_assign_license">';
        echo wp_nonce_field('clm_assign_license_nonce', '_clm_nonce');
        echo '</form>';

        echo '</div>';

        // Adding JavaScript for the dialog
        ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const addUserButtons = document.querySelectorAll('.clm-add-user-btn');

                addUserButtons.forEach(function(button) {
                    button.addEventListener('click', function() {
                        const courseId = this.getAttribute('data-course-id');

                        // Show a dialog to collect first name, last name, email, and email confirmation
                        const dialogContent = `
                            <div class="clm-dialog-overlay">
                                <div class="clm-dialog">
                                    <h3>Assign License</h3>
                                    <p>Once assigned, the license is used and cannot be altered. Contact admin if you need further help.</p>
                                    <form id="clm-dialog-form">
                                        <label>First Name</label>
                                        <input type="text" id="clm-first-name" required><br>
                                        <label>Last Name</label>
                                        <input type="text" id="clm-last-name" required><br>
                                        <label>Email</label>
                                        <input type="email" id="clm-email" required><br>
                                        <label>Confirm Email</label>
                                        <input type="email" id="clm-confirm-email" required><br>
                                        <button type="submit">Assign License</button>
                                        <button type="button" id="clm-cancel-btn">Cancel</button>
                                    </form>
                                </div>
                            </div>
                        `;

                        document.body.insertAdjacentHTML('beforeend', dialogContent);

                        const dialogForm = document.getElementById('clm-dialog-form');
                        const cancelBtn = document.getElementById('clm-cancel-btn');

                        cancelBtn.addEventListener('click', function() {
                            document.querySelector('.clm-dialog-overlay').remove();
                        });

                        dialogForm.addEventListener('submit', function(e) {
                            e.preventDefault();

                            const firstName = document.getElementById('clm-first-name').value.trim();
                            const lastName = document.getElementById('clm-last-name').value.trim();
                            const email = document.getElementById('clm-email').value.trim();
                            const confirmEmail = document.getElementById('clm-confirm-email').value.trim();

                            if (email !== confirmEmail) {
                                alert('Email and Confirm Email do not match.');
                                return;
                            }

                            document.getElementById('clm_course_id').value = courseId;
                            document.getElementById('clm_user_first_name').value = firstName;
                            document.getElementById('clm_user_last_name').value = lastName;
                            document.getElementById('clm_user_email').value = email;
                            document.getElementById('clm-hidden-form').submit();
                        });
                    });
                });
            });
        </script>
        <?php
    } else {
        echo '<p>' . __('You do not have permission to view this page.', 'clm') . '</p>';
    }
}

// Handle the form submission and log errors
add_action('init', 'clm_handle_license_assignment');
function clm_handle_license_assignment() {
    // Check if the form was submitted
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clm_assign_license') {
        if (!wp_verify_nonce($_POST['_clm_nonce'], 'clm_assign_license_nonce')) {
            error_log('CLM Error: Security check failed.');
            clm_show_message('error', __('Security check failed', 'clm'));
            return;
        }

        $course_id = 4269;
        $first_name = sanitize_text_field($_POST['clm_user_first_name']);
        $last_name = sanitize_text_field($_POST['clm_user_last_name']);
        $email = sanitize_email($_POST['clm_user_email']);

        if (!is_email($email)) {
            error_log('CLM Error: Invalid email address - ' . $email);
            clm_show_message('error', __('Invalid email address', 'clm'));
            return;
        }

        // Get the user by email
        $user = get_user_by('email', $email);

        if (!$user) {
            // Create a new user if not exists
            $random_password = wp_generate_password(12, false);
            $user_id = wp_create_user($email, $random_password, $email);

            if (is_wp_error($user_id)) {
                error_log('CLM Error: Failed to create user - ' . $user_id->get_error_message());
                clm_show_message('error', __('Failed to create user', 'clm'));
                return;
            }

            wp_update_user([
                'ID' => $user_id,
                'first_name' => $first_name,
                'last_name' => $last_name
            ]);

            // Assign metadata to link the user to the parent user
            $current_user_id = get_current_user_id();
            update_user_meta($user_id, '_clm_parent_user', $current_user_id);

            $user = get_user_by('id', $user_id);
            error_log('CLM Info: New user created with ID - ' . $user_id);

            // Optionally send the user their new credentials
            wp_new_user_notification($user_id, null, 'user');
        } else {
            // If user exists, still link to the parent user if not already done
            $current_user_id = get_current_user_id();
            if (!get_user_meta($user->ID, '_clm_parent_user', true)) {
                update_user_meta($user->ID, '_clm_parent_user', $current_user_id);
            }
        }

        // Check if the user is already registered for the course
        error_log('CLM Info: Checking if user is enrolled in course ID ' . $course_id);


        $is_registered = custom_check_user_in_course($user->ID, $course_id);

        if ($is_registered) {
            error_log('CLM Error: User ' . $user->ID . ' is already registered for course ID ' . $course_id);
            clm_show_message('error', __('This user is already registered for the course.', 'clm'));
            return;
        } else {
            error_log('CLM Info: User ' . $user->ID . ' is not registered for course ID ' . $course_id);
        }

        $enrolled = Sensei_Course::is_user_enrolled( $course_id, $user_id );
        
        error_log('CLM Info: Enrolleds Message is  ' . $enrolled );

        if ($enrolled) {
            // Reduce the available licenses
            $licenses = get_user_meta($current_user_id, '_clm_licenses', true);
            $licenses[$course_id]['used'] += 1;
            update_user_meta($current_user_id, '_clm_licenses', $licenses);

            error_log('CLM Info: License assigned to user ID ' . $user->ID . ' for course ID ' . $course_id);
            clm_show_message('success', __('License successfully assigned.', 'clm'));
        } else {
            error_log('CLM Error: Failed to assign the license to user ID ' . $user->ID . ' for course ID ' . $course_id);
            clm_show_message('error', __('Failed to assign the license.', 'clm'));
        }
    }
}

// Function to get sub-accounts
function clm_get_sub_accounts($parent_user_id, $course_id) {
    $args = array(
        'meta_key' => '_clm_parent_user',
        'meta_value' => $parent_user_id,
        'meta_compare' => '=',
    );

    $sub_accounts = get_users($args);
    return $sub_accounts;
}

// Function to display success or error messages in a dialog
function clm_show_message($type, $message) {
    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const messageType = '<?php echo esc_js($type); ?>';
            const messageText = '<?php echo esc_js($message); ?>';
            const dialogContent = `
                <div class="clm-dialog-overlay">
                    <div class="clm-dialog">
                        <h3>${messageType === 'success' ? 'Success' : 'Error'}</h3>
                        <p>${messageText}</p>
                        <button id="clm-close-dialog-btn">Close</button>
                    </div>
                </div>
            `;

            document.body.insertAdjacentHTML('beforeend', dialogContent);

            document.getElementById('clm-close-dialog-btn').addEventListener('click', function() {
                document.querySelector('.clm-dialog-overlay').remove();
            });
        });
    </script>
    <?php
}
