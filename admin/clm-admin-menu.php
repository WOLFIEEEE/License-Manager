<?php

// Add the Company License Manager settings menu
function clm_admin_menu() {
    add_menu_page(
        'Company License Manager',
        'License Manager',
        'manage_options',
        'clm-settings',
        'clm_settings_page',
        'dashicons-admin-users',
        100
    );

    // Add submenu for viewing company accounts
    add_submenu_page(
        'clm-settings',
        'View Company Accounts',
        'Company Accounts',
        'manage_options',
        'clm-view-accounts',
        'clm_view_accounts_page'
    );

    add_submenu_page(
        null, // Parent slug (null because it's hidden)
        'Company Details',
        'Company Details',
        'manage_options',
        'clm-company-details',
        'clm_company_details_page' // Ensure this matches exactly with the function name
    );
}
add_action('admin_menu', 'clm_admin_menu');
