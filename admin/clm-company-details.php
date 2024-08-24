<?php

// View Details for a Specific Company Account
function clm_company_details_page() {
    if (!isset($_GET['company_id'])) {
        echo '<div class="wrap"><p>' . __('Invalid company ID.', 'clm') . '</p></div>';
        return;
    }

    $company_id = intval($_GET['company_id']);
    $company_user = get_userdata($company_id);

    if (!$company_user || !in_array('company', $company_user->roles)) {
        echo '<div class="wrap"><p>' . __('Company account not found or invalid.', 'clm') . '</p></div>';
        return;
    }

    $company_name = $company_user->display_name;
    $registration_date = $company_user->user_registered;
    $licenses = get_user_meta($company_id, '_clm_licenses', true);

    echo '<div class="wrap">';
    echo '<h1>' . esc_html($company_name) . ' - ' . __('Details', 'clm') . '</h1>';
    echo '<p>' . __('Registered on: ', 'clm') . esc_html(date('F j, Y', strtotime($registration_date))) . '</p>';
    echo '<p>' . __('Email: ', 'clm') . esc_html($company_user->user_email) . '</p>';

    if (!empty($licenses)) {
        foreach ($licenses as $course_id => $license_data) {
            $course_title = get_the_title($course_id);
            $remaining_licenses = $license_data['total'] - $license_data['used'];

            echo '<h3>' . esc_html($course_title) . '</h3>';
            echo '<p>' . __('Total Licenses: ', 'clm') . esc_html($license_data['total']) . '</p>';
            echo '<p>' . __('Licenses Used: ', 'clm') . esc_html($license_data['used']) . '</p>';
            echo '<p>' . __('Licenses Remaining: ', 'clm') . esc_html($remaining_licenses) . '</p>';

            // Dropdown for managing users
            echo '<select class="clm-course-dropdown">';
            echo '<option value="">' . __('Select an option', 'clm') . '</option>';
            echo '<option value="add_user" data-course-id="' . esc_attr($course_id) . '">' . __('Add User', 'clm') . '</option>';
            echo '<option value="remove_user" data-course-id="' . esc_attr($course_id) . '">' . __('Remove User', 'clm') . '</option>';
            echo '</select>';

            // Placeholder for user management actions
            echo '<div class="clm-user-management"></div>';
        }
    } else {
        echo '<p>' . __('No licenses found.', 'clm') . '</p>';
    }

    echo '</div>'; // Close wrap
}
