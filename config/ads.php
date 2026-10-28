<?php

/*
|--------------------------------------------------------------------------
| Ad placements
|--------------------------------------------------------------------------
| Spots on the public site where an ad may appear: either your own banner
| image (uploaded in Admin > Ads) or Google AdSense. A spot renders nothing
| at all - no box, no label - unless it is switched on and has something to
| show. Ads Google cannot fill are hidden too. Ads never appear in the admin,
| checkout or the student course player.
|
| "where" picks the little layout sketch shown in the admin.
| "size" is the banner size suggested next to the image upload.
*/

return [
    'header' => [
        'label' => 'Below the header',
        'description' => 'A wide banner at the top of every public page.',
        'where' => 'top',
        'format' => 'horizontal',
        'size' => '1200 × 200 px',
    ],
    'home_middle' => [
        'label' => 'Home page, middle',
        'description' => 'Between the lessons and the introduction on the home page.',
        'where' => 'middle',
        'format' => 'auto',
        'size' => '1200 × 300 px',
    ],
    'listing' => [
        'label' => 'Tutorials / news lists',
        'description' => 'Above the cards on the Tutorials, News and Blog pages.',
        'where' => 'listing',
        'format' => 'horizontal',
        'size' => '1200 × 200 px',
    ],
    'article_top' => [
        'label' => 'Lesson / article, top',
        'description' => 'Under the title of a tutorial, news item or blog post.',
        'where' => 'article_top',
        'format' => 'in-article',
        'size' => '800 × 200 px',
    ],
    'article_bottom' => [
        'label' => 'Lesson / article, end',
        'description' => 'After the text of a tutorial, news item or blog post.',
        'where' => 'article_bottom',
        'format' => 'in-article',
        'size' => '800 × 250 px',
    ],
    'footer' => [
        'label' => 'Above the footer',
        'description' => 'A wide banner at the bottom of every public page.',
        'where' => 'footer',
        'format' => 'horizontal',
        'size' => '1200 × 200 px',
    ],
];
