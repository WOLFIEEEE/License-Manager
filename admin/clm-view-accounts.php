<?php

// View Company Accounts Page
function clm_view_accounts_page() {
    $company_users = get_users(['role' => 'company']);

    echo '<div class="wrap">';
    echo '<h1>' . __('Company Accounts', 'clm') . '</h1>';

    if (!empty($company_users)) {
        echo '<div class="clm-company-cards">';

        foreach ($company_users as $company_user) {
            $company_name = $company_user->display_name;
            $registration_date = $company_user->user_registered;
            $company_email = $company_user->user_email;

            echo '<div class="clm-company-card">';
            echo '<h2>' . esc_html($company_name) . '</h2>';
            echo '<p>' . __('Registered on: ', 'clm') . esc_html(date('F j, Y', strtotime($registration_date))) . '</p>';
            echo '<p>' . __('Email: ', 'clm') . esc_html($company_email) . '</p>';

            // Link to the company details page
            $details_url = admin_url('admin.php?page=clm-company-details&company_id=' . $company_user->ID);
            echo '<a href="' . esc_url($details_url) . '" class="button button-primary">' . __('View Details', 'clm') . '</a>';

            echo '</div>'; // Close company card
        }

        echo '</div>'; // Close company cards container
    } else {
        echo '<p>' . __('There are no company accounts at the moment. Please make a purchase to create a company account.', 'clm') . '</p>';
    }

    echo '</div>'; // Close wrap
}
