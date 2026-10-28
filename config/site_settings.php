<?php

/*
|--------------------------------------------------------------------------
| Site settings
|--------------------------------------------------------------------------
| Fields shown on Admin > Settings. Values are stored in the settings table
| and read in views with setting('key').
|
| Field types: text, textarea, url, email, number, image, socials.
*/

return [

    'brand' => ['label' => 'Brand', 'fields' => [
        'site_name' => ['type' => 'text', 'label' => 'Site name'],
        'logo_text' => ['type' => 'text', 'label' => 'Logo text (first part, bold)'],
        'logo_text_accent' => ['type' => 'text', 'label' => 'Logo text (second part)'],
        'logo' => ['type' => 'image', 'label' => 'Logo image (optional, replaces the logo text)'],
        'favicon' => ['type' => 'image', 'label' => 'Favicon / app icon'],
        'tagline' => ['type' => 'textarea', 'label' => 'Short description (footer and search engines)'],
    ]],

    'header' => ['label' => 'Header', 'fields' => [
        'header_button_label' => ['type' => 'text', 'label' => 'Header button label'],
        'header_button_url' => ['type' => 'url', 'label' => 'Header button link'],
    ]],

    'contact' => ['label' => 'Contact details', 'fields' => [
        'phone' => ['type' => 'text', 'label' => 'Phone'],
        'email' => ['type' => 'email', 'label' => 'Email'],
        'address' => ['type' => 'textarea', 'label' => 'Address'],
    ]],

    'social' => ['label' => 'Social media', 'fields' => [
        'social_links' => ['type' => 'socials', 'label' => 'Accounts (shown in the footer and on the contact page, in this order)'],
    ]],

    'footer' => ['label' => 'Footer', 'fields' => [
        'footer_text' => ['type' => 'textarea', 'label' => 'Footer text'],
        'newsletter_title' => ['type' => 'text', 'label' => 'Newsletter title (empty = default)'],
        'newsletter_text' => ['type' => 'textarea', 'label' => 'Newsletter text (empty = default)'],
        'copyright' => ['type' => 'text', 'label' => 'Copyright line (":year" is replaced with the current year)'],
    ]],

    'seo' => ['label' => 'Search engines & sharing', 'fields' => [
        'share_image' => ['type' => 'image', 'label' => 'Default share image (shown when a page is shared on Facebook / Telegram; 1200 × 630 is best)'],
        'google_site_verification' => ['type' => 'text', 'label' => 'Google Search Console verification code (the content="…" value)'],
        'bing_site_verification' => ['type' => 'text', 'label' => 'Bing Webmaster verification code (optional)'],
    ]],

    'students' => ['label' => 'Student accounts', 'fields' => [
        'student_device_limit' => ['type' => 'number', 'default' => 2, 'min' => 0, 'max' => 10, 'label' => 'Devices one student can be signed in on at the same time (stops sharing a paid account; signing in on one more device signs out the oldest; 0 = no limit)'],
    ]],

    'tracking' => ['label' => 'Tracking', 'fields' => [
        'google_analytics_id' => ['type' => 'text', 'label' => 'Google Analytics measurement ID (G-XXXX)'],
    ]],

];
