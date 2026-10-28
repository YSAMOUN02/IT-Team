<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Creates the first admin account and empty page shells. The real content
     * comes from the old WordPress site: php artisan wp:import
     */
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@teamitcambodia.com');
        if (! User::where('email', $email)->exists()) {
            $password = env('ADMIN_PASSWORD') ?: Str::password(16, symbols: false);
            $admin = new User(['name' => 'Admin', 'username' => env('ADMIN_USERNAME'), 'email' => $email, 'password' => $password]);
            $admin->role = 'admin';
            $admin->save();
            $this->command?->info("Admin login: $email".(env('ADMIN_PASSWORD') ? ' (password from ADMIN_PASSWORD in .env)' : " / $password"));
        }

        $pages = [
            'home' => ['home', 'TEAMIT Cambodia'],
            'about' => ['about', 'About'],
            'contact' => ['contact', 'Contact'],
            'courses' => ['listing', 'Courses'],
            'tutorials' => ['listing', 'Tutorials'],
            'news' => ['listing', 'News'],
            'coming-soon' => ['coming-soon', 'Coming Soon'],
        ];
        foreach ($pages as $slug => [$template, $title]) {
            Page::firstOrCreate(['slug' => $slug], ['template' => $template, 'title' => $title, 'sections' => []]);
        }

        if (! Setting::whereKey('site_name')->exists()) {
            Setting::put([
                'site_name' => 'TEAMIT Cambodia',
                'logo_text' => 'TEAMIT',
                'logo_text_accent' => 'Cambodia',
                'copyright' => '© :year TEAMIT Cambodia',
            ]);
        }
    }
}
