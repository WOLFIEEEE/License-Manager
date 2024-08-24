<?php

// Register settings
function clm_register_settings() {
    register_setting('clm_settings_group', 'clm_license_message');
    register_setting('clm_settings_group', 'clm_email_template');

    add_settings_section('clm_main_section', __('Main Settings', 'clm'), null, 'clm-settings');

    add_settings_field('clm_license_message', __('License Message', 'clm'), 'clm_license_message_callback', 'clm-settings', 'clm_main_section');
    add_settings_field('clm_email_template', __('Email Template', 'clm'), 'clm_email_template_callback', 'clm-settings', 'clm_main_section');
}
add_action('admin_init', 'clm_register_settings');

// Settings field callbacks

function clm_license_message_callback() {
    $value = get_option('clm_license_message', 'You have {license_count} licenses remaining.');
    echo '<input type="text" name="clm_license_message" value="' . esc_attr($value) . '" class="regular-text">';
    echo '<p class="description">Use {license_count} to display the number of licenses left.</p>';
}

function clm_email_template_callback() {
    $value = get_option('clm_email_template', 'Hello {user_name},<br><br>You have purchased {license_count} licenses for the {course_name} course.<br><br>Thank you!');
    echo '<textarea name="clm_email_template" class="large-text code" rows="10">' . esc_textarea($value) . '</textarea>';
    echo '<p class="description">Use {user_name}, {license_count}, and {course_name} as placeholders.</p>';
}

function clm_settings_page() {
    ?>
    <div class="wrap">
        <h1><?php _e('Company License Manager Settings', 'clm'); ?></h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('clm_settings_group');
            do_settings_sections('clm-settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

