<?php

/*
| Main menu (top of every page). "route" is a named route; "children" makes a drop-down.
| The footer repeats these links and adds Contact (see the footer in layouts/site.blade.php).
*/

return [
    ['label' => 'Home', 'route' => 'home'],
    ['label' => 'Courses', 'route' => 'courses.index', 'active' => 'courses.*'],
    ['label' => 'Tutorials', 'route' => 'tutorials.index', 'active' => 'tutorials.*'],
    ['label' => 'News', 'route' => 'news.index', 'active' => 'news.*'],
    ['label' => 'About', 'route' => 'about'],
];
