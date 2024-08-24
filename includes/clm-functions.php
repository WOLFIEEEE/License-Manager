<?php
// Create the "Company" role with specific permissions
function clm_create_company_role() {
    add_role(
        'company',
        __('Company', 'clm'),
        [
            'read' => true,
            'manage_sensei_users' => true,
        ]
    );
}

// Create necessary pages
function clm_create_pages() {
    if (!get_page_by_path('manage-accounts')) {
        $manage_page_id = wp_insert_post([
            'post_title'    => 'Manage Accounts',
            'post_content'  => '[clm_company_manage_accounts]', // Shortcode for managing accounts
            'post_status'   => 'publish',
            'post_type'     => 'page',
            'post_name'     => 'manage-accounts',
        ]);
    }
}

// Track remaining licenses for each course
function clm_get_remaining_licenses($user_id, $course_id) {
    $licenses = get_user_meta($user_id, '_clm_licenses', true);

    if (isset($licenses[$course_id])) {
        $total_licenses = $licenses[$course_id]['total'];
        $used_licenses = $licenses[$course_id]['used'];

        return $total_licenses - $used_licenses;
    }

    return 0;
}

// Add licenses for a specific course
function clm_add_licenses($user_id, $course_id, $quantity) {
    // Retrieve the licenses from user meta and ensure it's an array
    $licenses = get_user_meta($user_id, '_clm_licenses', true);

    if (!is_array($licenses)) {
        // If the retrieved data is not an array, initialize it as an empty array
        error_log("Warning: Licenses meta for user ID {$user_id} was not an array. Initializing as a new array.");
        $licenses = [];
    }

    if (!isset($licenses[$course_id])) {
        // Initialize the license data structure for this specific course ID
        $licenses[$course_id] = ['total' => 0, 'used' => 0];
    }

    // Safely add the quantity of licenses to the 'total'
    $licenses[$course_id]['total'] += $quantity;

    // Update the user meta with the updated licenses array
    update_user_meta($user_id, '_clm_licenses', $licenses);
}


// Use a license for a specific course
function clm_use_license($user_id, $course_id) {
    $licenses = get_user_meta($user_id, '_clm_licenses', true);

    if (isset($licenses[$course_id])) {
        $licenses[$course_id]['used'] += 1;
        update_user_meta($user_id, '_clm_licenses', $licenses);
    }
}

// Assign the "Company" role and track licenses per course after purchasing multiple licenses
add_action('woocommerce_order_status_processing', 'clm_assign_company_role_after_purchase', 10, 1);
function clm_assign_company_role_after_purchase($order_id) {
    $order = wc_get_order($order_id);
    $user_id = $order->get_user_id();

    // Log the start of the function
    error_log("CLM: Processing order #{$order_id} for user ID {$user_id}");

    if ($user_id) {
        $items = $order->get_items(); // Get the order items
        foreach ($items as $item) {
            $product_name = $item->get_name(); // Get the product name
            $product_id = $item->get_product_id(); // Get the product ID

            // Extract the course name and number of licenses from the product name
            list($course_name, $licenses_count) = clm_extract_course_and_license($product_name);

            error_log("CLM: Found product '{$product_name}' with {$licenses_count} licenses.");

            if ($licenses_count > 0) {  // Ensure the license count is valid
                $user = new WP_User($user_id);
                
                // Assign the "Company" role
                $user->set_role('company');
                error_log("Assigned 'company' role to user ID {$user_id}.");
                
                // Add licenses for the purchased course
                clm_add_licenses($user_id, $product_id, $licenses_count);
                
                // Log the role assignment and license addition
                error_log("Added {$licenses_count} licenses for course '{$course_name}' to user ID {$user_id}.");
            
                // Send email notification
                clm_send_license_email($user_id, $product_id, $licenses_count);
            } else {
                // Log the case where no licenses were added
                error_log("No licenses were added because the license count for product '{$course_name}' was {$licenses_count}.");
            }
        }
    } else {
        // Log the case where no user ID is found
        error_log("CLM: No user ID found for order #{$order_id}");
    }
}

// Function to extract the course name and license count from the product name
function clm_extract_course_and_license($product_name) {
    // Regular expression to match course name and license count
    if (preg_match('/^(.+?)\s+(\d+)$/', $product_name, $matches)) {
        $course_name = $matches[1];  // Capture the course name (e.g., "Test")
        $license_count = intval($matches[2]);  // Capture the license count as an integer (e.g., "50")
        return [$course_name, $license_count];
    }

    // Log when extraction fails and default to 1 license
    error_log("CLM: Failed to extract license count from product name '{$product_name}'. Defaulting to 1 license.");
    return [$product_name, 1]; // Default to 1 license if no match found
}

// Send email notification with customizable template
function clm_send_license_email($user_id, $course_id, $quantity) {
    $user = get_user_by('id', $user_id);
    $course_title = get_the_title($course_id);

    // Get the email template from options
    $email_template = get_option('clm_email_template', '');
    if (empty($email_template)) {
        $email_template = 'Hello {user_name},<br><br>You have purchased {license_count} licenses for the {course_name} course.<br><br>Thank you!';
    }

    // Replace placeholders with actual values
    $email_body = str_replace(
        ['{user_name}', '{license_count}', '{course_name}'],
        [$user->display_name, $quantity, $course_title],
        $email_template
    );

    // Log email sending
    error_log("CLM: Sending license email to {$user->user_email} for course '{$course_title}' with {$quantity} licenses.");

    // Send email
    wp_mail($user->user_email, 'Your Course Licenses', $email_body, ['Content-Type: text/html; charset=UTF-8']);
}

// Link a sub-account to the Company user for a specific course
function clm_link_sub_account($sub_account_id, $company_user_id, $course_id) {
    add_user_meta($sub_account_id, 'company_parent_id', $company_user_id);
    add_user_meta($sub_account_id, 'company_course_id', $course_id);

    // Enroll the sub-account in the course
    if (class_exists('Sensei_Utils')) {
        Sensei_Utils::sensei_start_course($course_id, $sub_account_id);
        // Log the enrollment
        error_log("CLM: Enrolled sub-account ID {$sub_account_id} in course ID {$course_id} under company user ID {$company_user_id}.");
    } else {
        // Fallback or error handling if the class doesn't exist
        error_log('Sensei LMS: Could not enroll user in the course. Sensei_Utils class not found.');
    }
}

// Get sub-accounts for a Company user for a specific course
function clm_get_sub_accounts($company_user_id, $course_id = '') {
    $meta_query = [
        [
            'key' => 'company_parent_id',
            'value' => $company_user_id,
            'compare' => '='
        ]
    ];

    if ($course_id) {
        $meta_query[] = [
            'key' => 'company_course_id',
            'value' => $course_id,
            'compare' => '='
        ];
    }

    return get_users([
        'meta_query' => $meta_query
    ]);
}

// AJAX handler for adding user to course
add_action('wp_ajax_clm_add_user_to_course', 'clm_add_user_to_course');
function clm_add_user_to_course() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied.');
    }

    $email = sanitize_email($_POST['email']);
    $course_id = intval($_POST['course_id']);

    if ($email && $course_id) {
        $user = get_user_by('email', $email);

        if ($user) {
            // Enroll the user in the course
            if (class_exists('Sensei_Utils')) {
                Sensei_Utils::sensei_start_course($course_id, $user->ID);
                wp_send_json_success('User successfully added to the course.');
                // Log successful addition
                error_log("CLM: Successfully added user ID {$user->ID} to course ID {$course_id}.");
            } else {
                wp_send_json_error('Sensei Utils class not found.');
                error_log('CLM: Sensei Utils class not found.');
            }
        } else {
            wp_send_json_error('User not found.');
            error_log("CLM: User not found with email {$email}.");
        }
    } else {
        wp_send_json_error('Invalid email or course ID.');
        error_log("CLM: Invalid email ({$email}) or course ID ({$course_id}).");
    }
}

// AJAX handler for removing user from course
add_action('wp_ajax_clm_remove_user_from_course', 'clm_remove_user_from_course');
function clm_remove_user_from_course() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied.');
    }

    $email = sanitize_email($_POST['email']);
    $course_id = intval($_POST['course_id']);

    if ($email && $course_id) {
        $user = get_user_by('email', $email);

        if ($user) {
            // Remove the user from the course
            if (class_exists('Sensei_Course')) {
                Sensei_Course::withdraw_user_from_course($course_id, $user->ID);
                wp_send_json_success('User successfully removed from the course.');
                // Log successful removal
                error_log("CLM: Successfully removed user ID {$user->ID} from course ID {$course_id}.");
            } else {
                wp_send_json_error('Sensei Course class not found.');
                error_log('CLM: Sensei Course class not found.');
            }
        } else {
            wp_send_json_error('User not found.');
            error_log("CLM: User not found with email {$email}.");
        }
    } else {
        wp_send_json_error('Invalid email or course ID.');
        error_log("CLM: Invalid email ({$email}) or course ID ({$course_id}).");
    }
}

