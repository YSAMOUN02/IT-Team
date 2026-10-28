<?php

/*
|--------------------------------------------------------------------------
| Social platforms
|--------------------------------------------------------------------------
| Platforms offered in Admin > Settings > Social media. Each link the admin
| adds shows in the footer, on the contact page and in the search-engine data.
| "chat" platforms can fill the "Live chat" card on the contact page.
| Icons are simple line drawings in the site's own style.
*/

return [
    'facebook' => ['label' => 'Facebook', 'color' => '#1877F2', 'placeholder' => 'https://facebook.com/yourpage',
        'icon' => '<path d="M14 8h3V4h-3a4 4 0 0 0-4 4v2H7v4h3v7h4v-7h3l1-4h-4V8Z"/>'],
    'telegram' => ['label' => 'Telegram', 'color' => '#229ED9', 'placeholder' => 'https://t.me/yourname', 'chat' => true,
        'icon' => '<path d="M21 4 3 11l6 2 2 6 3-4 5 4 2-15Z"/><path d="m9 13 8-6"/>'],
    'tiktok' => ['label' => 'TikTok', 'color' => '#111111', 'placeholder' => 'https://www.tiktok.com/@yourname',
        'icon' => '<path d="M14 3v11.5a3.5 3.5 0 1 1-3.5-3.5"/><path d="M14 3c.5 3 2.5 5 5.5 5"/>'],
    'youtube' => ['label' => 'YouTube', 'color' => '#FF0000', 'placeholder' => 'https://www.youtube.com/@yourchannel',
        'icon' => '<rect x="2.5" y="5.5" width="19" height="13" rx="4"/><path d="m10 9 5 3-5 3V9Z"/>'],
    'instagram' => ['label' => 'Instagram', 'color' => '#E1306C', 'placeholder' => 'https://instagram.com/yourname',
        'icon' => '<rect x="3.5" y="3.5" width="17" height="17" rx="5"/><circle cx="12" cy="12" r="4"/><path d="M17.2 6.8h.01"/>'],
    'messenger' => ['label' => 'Messenger', 'color' => '#0A7CFF', 'placeholder' => 'https://m.me/yourpage', 'chat' => true,
        'icon' => '<path d="M12 3c-5 0-9 3.7-9 8.3 0 2.6 1.3 4.9 3.3 6.4V21l3-1.6c.9.2 1.8.4 2.7.4 5 0 9-3.7 9-8.5S17 3 12 3Z"/><path d="m7.5 13.5 3-3.2 2 2 3.5-2.8-3 3.2-2-2Z"/>'],
    'whatsapp' => ['label' => 'WhatsApp', 'color' => '#25D366', 'placeholder' => 'https://wa.me/85512345678', 'chat' => true,
        'icon' => '<path d="m4 20 1.3-3.8A8 8 0 1 1 8 19Z"/><path d="M9.2 8.6c.2 2.6 2.6 5 5.2 5.2l.9-1.3 1.7.7c-.1 1.2-1 2-2.2 2-3.4-.2-6.2-3-6.4-6.4 0-1.2.8-2.1 2-2.2l.7 1.7Z"/>'],
    'line' => ['label' => 'LINE', 'color' => '#06C755', 'placeholder' => 'https://line.me/ti/p/yourid', 'chat' => true,
        'icon' => '<path d="M12 4c-5 0-9 3.2-9 7.2 0 3.6 3.2 6.6 7.5 7.1l-.4 2.3c4.6-2.6 10.8-5.8 10.8-9.4C20.9 7.2 17 4 12 4Z"/><path d="M8 9.5v3.5h2M12 9.5V13M14.5 13V9.5l2.5 3.5V9.5"/>'],
    'linkedin' => ['label' => 'LinkedIn', 'color' => '#0A66C2', 'placeholder' => 'https://www.linkedin.com/company/yourcompany',
        'icon' => '<rect x="3.5" y="3.5" width="17" height="17" rx="3"/><path d="M8 10.5V16M8 7.8v.1M11.5 16v-5.5M11.5 13c0-1.7 1-2.7 2.3-2.7S16 11.2 16 13v3"/>'],
    'x' => ['label' => 'X (Twitter)', 'color' => '#111111', 'placeholder' => 'https://x.com/yourname',
        'icon' => '<path d="M4 4h4.5L20 20h-4.5ZM19.5 4l-6.6 7.3M4.5 20l6.6-7.3"/>'],
    'threads' => ['label' => 'Threads', 'color' => '#111111', 'placeholder' => 'https://www.threads.net/@yourname',
        'icon' => '<path d="M16.3 11.4c-.4-2.6-2-3.9-4.3-3.9-2.6 0-4.3 1.9-4.3 4.6s1.8 4.6 4.5 4.6c2 0 3.5-1.1 3.5-2.7S14.4 11.4 12.4 11.4c-1.6 0-2.6.8-2.6 1.8"/><path d="M19.5 12A7.5 7.5 0 1 1 12 4.5c3.3 0 5.6 1.8 6.6 4.4"/>'],
    'pinterest' => ['label' => 'Pinterest', 'color' => '#E60023', 'placeholder' => 'https://www.pinterest.com/yourname',
        'icon' => '<circle cx="12" cy="12" r="9"/><path d="m10.6 20 1.9-8M10.9 13.6c-.8-2.8.8-5.1 3.1-5.1 2 0 3 1.4 3 3.2 0 2.3-1.3 4.2-3.1 4.2-1 0-1.6-.6-1.4-1.6"/>'],
    'website' => ['label' => 'Other website', 'color' => '#5F59F7', 'placeholder' => 'https://',
        'icon' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>'],
];
