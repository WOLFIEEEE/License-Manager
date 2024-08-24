<?php
/*
Plugin Name: Company License Manager
Plugin URI: https://yourwebsite.com
Description: A custom plugin to manage company licenses and sub-accounts in Sensei LMS and WooCommerce.
Version: 1.3
Author: Khushwant Parihar
Author URI: https://yourwebsite.com
License: GPL2
*/

// Include necessary files
// Include necessary files
include_once plugin_dir_path(__FILE__) . 'includes/clm-functions.php';
include_once plugin_dir_path(__FILE__) . 'includes/clm-shortcodes.php';
include_once plugin_dir_path(__FILE__) . 'admin/clm-admin-menu.php';
include_once plugin_dir_path(__FILE__) . 'admin/clm-settings.php';
include_once plugin_dir_path(__FILE__) . 'admin/clm-view-accounts.php';
include_once plugin_dir_path(__FILE__) . 'admin/clm-company-details.php'; // Include the company details file


// Create the "Company" role and pages upon activation
register_activation_hook(__FILE__, 'clm_activate_plugin');
function clm_activate_plugin() {
    clm_create_company_role();
    clm_create_pages();
}


function clm_enqueue_assets() {
    // Enqueue the CSS file
    wp_enqueue_style('clm-style', plugin_dir_url(__FILE__) . 'assets/css/style.css');

    // Enqueue the JavaScript file
    wp_enqueue_script('clm-script', plugin_dir_url(__FILE__) . 'assets/js/script.js', ['jquery'], '1.0', true);

    // Localize the script with new data
    wp_localize_script('clm-script', 'clm_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('clm_nonce')
    ]);
}
add_action('wp_enqueue_scripts', 'clm_enqueue_assets');