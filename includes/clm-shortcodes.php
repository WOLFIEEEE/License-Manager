<?php

// Shortcode for managing accounts
add_shortcode('clm_company_manage_accounts', 'clm_company_manage_accounts_dashboard');


function custom_enroll_user_in_course($user_id, $course_id) {

    $result = Sensei_Utils::user_start_course($user_id, $course_id);
    $manual_enrol = Sensei_Course_Manual_Enrolment_Provider::instance();
    $enrolled = $manual_enrol->enrol_learner( $user_id, $course_id );

    return $enrolled;
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
                    echo '<input type="email" class="clm-add-user-email" placeholder="' . __('Enter user email', 'clm') . '">';
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
        echo '<input type="hidden" name="action" value="clm_assign_license">';
        echo wp_nonce_field('clm_assign_license_nonce', '_clm_nonce');
        echo '</form>';

        echo '</div>';

        // Adding JavaScript
        ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const addUserButtons = document.querySelectorAll('.clm-add-user-btn');

                addUserButtons.forEach(function(button) {
                    button.addEventListener('click', function() {
                        const courseId = this.getAttribute('data-course-id');
                        const emailField = this.previousElementSibling;
                        const email = emailField.value.trim();

                        if (email) {
                            document.getElementById('clm_course_id').value = courseId;
                            document.getElementById('clm_user_email').value = email;
                            document.getElementById('clm-hidden-form').submit();
                        } else {
                            alert('Please enter a valid email address.');
                        }
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
            wp_die(__('Security check failed', 'clm'));
        }

        $course_id = 4269;
        $email = sanitize_email($_POST['clm_user_email']);

        if (!is_email($email)) {
            error_log('CLM Error: Invalid email address - ' . $email);
            wp_die(__('Invalid email address', 'clm'));
        }

        // Get the user by email
        $user = get_user_by('email', $email);

        if (!$user) {
            // Create a new user if not exists
            $random_password = wp_generate_password(12, false);
            $user_id = wp_create_user($email, $random_password, $email);

            if (is_wp_error($user_id)) {
                error_log('CLM Error: Failed to create user - ' . $user_id->get_error_message());
                wp_die(__('Failed to create user', 'clm'));
            }

            $user = get_user_by('id', $user_id);
            error_log('CLM Info: New user created with ID - ' . $user_id);

            // Optionally send the user their new credentials
            wp_new_user_notification($user_id, null, 'user');
        }
        
        // Check if the user is already registered for the course
        $is_registered = Sensei_Course::is_user_enrolled($course_id, $user->ID);

        if ($is_registered) {
            error_log('CLM Error: User ' . $user->ID . ' is already registered for course ID ' . $course_id);
            wp_die(__('This user is already registered for the course.', 'clm'));
        }
        // Assign the course to the user
        error_log('CLM Error: User ' . $user->ID . ' is already registered for course ID ' . $course_id);
        // $course_enrolment = Sensei_Course_Enrolment::get_course_instance($course_id);
        // $course_enrolment->enrol($user->ID);    

        $enrolled = custom_enroll_user_in_course($user->ID, $course_id);

        if ($enrolled) {
            // Reduce the available licenses
            $current_user_id = get_current_user_id();
            $licenses = get_user_meta($current_user_id, '_clm_licenses', true);

            $licenses[$course_id]['used'] += 1;
            update_user_meta($current_user_id, '_clm_licenses', $licenses);

            error_log('CLM Info: License assigned to user ID ' . $user->ID . ' for course ID ' . $course_id);
            wp_redirect(add_query_arg('success', '1', wp_get_referer()));
            exit;
        } else {
            error_log('CLM Error: Failed to assign the license to user ID ' . $user->ID . ' for course ID ' . $course_id);
            wp_die(__('Failed to assign the license.', 'clm'));
        }
    }
}
