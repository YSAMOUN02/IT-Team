<?php

/*
|--------------------------------------------------------------------------
| Page templates
|--------------------------------------------------------------------------
| Each fixed page stores its editable content in pages.sections (JSON). The
| admin form is generated from these definitions, and the Blade template for
| the page reads the values with $page->field('group.field').
|
| Field types: text, textarea, html, image, url, list (one item per line).
*/

$button = fn (string $label = 'Button', string $prefix = 'button') => [
    $prefix.'_label' => ['type' => 'text', 'label' => $label.' label'],
    $prefix.'_url' => ['type' => 'url', 'label' => $label.' link'],
];

return [

    'default' => [
        'label' => 'Simple page',
        'body' => true,
        'groups' => [],
    ],

    'home' => [
        'label' => 'Home page',
        'body' => false,
        'groups' => [
            'hero' => ['label' => 'Hero', 'fields' => [
                'title' => ['type' => 'text', 'label' => 'Title'],
                'text' => ['type' => 'textarea', 'label' => 'Text'],
                'image' => ['type' => 'image', 'label' => 'Image'],
            ] + $button('Main button') + $button('Second button', 'button2')],
            'courses' => ['label' => 'Courses section', 'fields' => [
                'title' => ['type' => 'text', 'label' => 'Title'],
                'text' => ['type' => 'textarea', 'label' => 'Text'],
            ] + $button()],
            'lessons' => ['label' => 'Lessons section', 'fields' => [
                'title' => ['type' => 'text', 'label' => 'Title'],
                'text' => ['type' => 'textarea', 'label' => 'Text'],
            ] + $button()],
            'news' => ['label' => 'Latest news section (shown when there is news)', 'fields' => [
                'title' => ['type' => 'text', 'label' => 'Title'],
                'text' => ['type' => 'textarea', 'label' => 'Text'],
            ]],
            'intro' => ['label' => 'Introduction', 'fields' => [
                'title' => ['type' => 'text', 'label' => 'Title'],
                'text' => ['type' => 'html', 'label' => 'Text'],
                'points' => ['type' => 'list', 'label' => 'Checklist (one per line)'],
                'after' => ['type' => 'textarea', 'label' => 'Text after the checklist'],
                'image' => ['type' => 'image', 'label' => 'Image'],
            ] + $button()],
            'corporate' => ['label' => 'Corporate training', 'fields' => [
                'title' => ['type' => 'text', 'label' => 'Title'],
                'text' => ['type' => 'html', 'label' => 'Text'],
                'points' => ['type' => 'list', 'label' => 'Checklist (one per line)'],
                'image' => ['type' => 'image', 'label' => 'Image'],
            ] + $button()],
            'testimonials' => ['label' => 'Testimonials section', 'fields' => [
                'title' => ['type' => 'text', 'label' => 'Title'],
                'text' => ['type' => 'textarea', 'label' => 'Text'],
            ]],
            'partners' => ['label' => 'Partners section', 'fields' => [
                'title' => ['type' => 'text', 'label' => 'Title'],
            ]],
            'founder' => ['label' => 'Founder', 'fields' => [
                'title' => ['type' => 'text', 'label' => 'Title'],
                'text' => ['type' => 'html', 'label' => 'Text'],
                'image' => ['type' => 'image', 'label' => 'Photo'],
            ]],
        ],
    ],

    'about' => [
        'label' => 'About page',
        'body' => false,
        'groups' => [
            'intro' => ['label' => 'Page header', 'fields' => [
                'title' => ['type' => 'text', 'label' => 'Title'],
                'text' => ['type' => 'textarea', 'label' => 'Text'],
            ] + $button('Main button') + $button('Second button', 'button2')],
            'who' => ['label' => 'Who we are', 'fields' => [
                'title' => ['type' => 'text', 'label' => 'Title'],
                'image' => ['type' => 'image', 'label' => 'Photo'],
                'name' => ['type' => 'text', 'label' => 'Name (shown on the photo)'],
                'role' => ['type' => 'text', 'label' => 'Job title (under the name)'],
                'credentials' => ['type' => 'list', 'label' => 'Education & certificates (one per line, "Title៖ detail")'],
                'experience_years' => ['type' => 'text', 'label' => 'Years of experience (big number, e.g. ១០)'],
                'experience_title' => ['type' => 'text', 'label' => 'Experience headline'],
                'experience' => ['type' => 'list', 'label' => 'Experience areas (one per line, shown as tags)'],
                'story' => ['type' => 'textarea', 'label' => 'Why the courses exist (a few sentences)'],
                'offers_title' => ['type' => 'text', 'label' => 'Course strengths: heading'],
                'offers' => ['type' => 'list', 'label' => 'Course strengths (one per line, "Title៖ detail", 3 is best)'],
                'text' => ['type' => 'html', 'label' => 'Extra text (optional, shown under the story)'],
            ]],
            'mission' => ['label' => 'Our mission', 'fields' => [
                'title' => ['type' => 'text', 'label' => 'Title'],
                'text' => ['type' => 'html', 'label' => 'Text'],
                'image' => ['type' => 'image', 'label' => 'Image'],
            ]],
            'benefits' => ['label' => 'What you get', 'fields' => [
                'title' => ['type' => 'text', 'label' => 'Title'],
                'points' => ['type' => 'list', 'label' => 'Checklist (one per line)'],
            ]],
        ],
    ],

    'contact' => [
        'label' => 'Contact page',
        'body' => false,
        'groups' => [
            'intro' => ['label' => 'Page header', 'fields' => [
                'title' => ['type' => 'text', 'label' => 'Title'],
                'text' => ['type' => 'textarea', 'label' => 'Text'],
            ]],
            'phone' => ['label' => 'Phone card', 'fields' => [
                'title' => ['type' => 'text', 'label' => 'Title'],
                'text' => ['type' => 'textarea', 'label' => 'Text'],
            ]],
            'email' => ['label' => 'Email card', 'fields' => [
                'title' => ['type' => 'text', 'label' => 'Title'],
                'text' => ['type' => 'textarea', 'label' => 'Text'],
            ]],
            'chat' => ['label' => 'Live chat card', 'fields' => [
                'title' => ['type' => 'text', 'label' => 'Title'],
                'text' => ['type' => 'textarea', 'label' => 'Text'],
            ]],
        ],
    ],

    'listing' => [
        'label' => 'Listing page header (courses / tutorials)',
        'body' => false,
        'groups' => [
            'intro' => ['label' => 'Page header', 'fields' => [
                'title' => ['type' => 'text', 'label' => 'Title'],
                'text' => ['type' => 'textarea', 'label' => 'Text'],
            ]],
        ],
    ],

    'coming-soon' => [
        'label' => 'Coming soon',
        'body' => true,
        'groups' => [
            'intro' => ['label' => 'Message', 'fields' => [
                'title' => ['type' => 'text', 'label' => 'Title'],
                'text' => ['type' => 'textarea', 'label' => 'Text'],
                'image' => ['type' => 'image', 'label' => 'Image'],
            ]],
        ],
    ],

];
