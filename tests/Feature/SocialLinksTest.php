<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocialLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_saves_social_accounts_in_order_and_drops_empty_rows(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();

        $this->actingAs($admin)->put('/admin/settings', ['socials' => [
            ['platform' => 'tiktok', 'url' => 'https://www.tiktok.com/@teamit'],
            ['platform' => 'facebook', 'url' => ''],
            ['platform' => 'telegram', 'url' => 'https://t.me/teamit'],
        ]])->assertRedirect('/admin/settings');

        $links = social_links();
        $this->assertSame(['tiktok', 'telegram'], array_column($links, 'platform'));
        $this->assertSame('TikTok', $links[0]['label']);
        $this->assertTrue($links[1]['chat']);
    }

    public function test_links_must_be_full_web_addresses(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();

        $this->actingAs($admin)->from('/admin/settings')
            ->put('/admin/settings', ['socials' => [['platform' => 'facebook', 'url' => 'facebook page']]])
            ->assertSessionHasErrors('socials.0.url');
    }

    public function test_old_single_fields_are_used_until_the_list_is_saved(): void
    {
        Setting::put(['telegram_url' => 'https://t.me/old', 'facebook_url' => 'https://facebook.com/old']);

        $this->assertSame(['facebook', 'telegram'], array_column(social_links(), 'platform'));
    }

    public function test_footer_and_contact_page_show_the_accounts(): void
    {
        Setting::put(['social_links' => json_encode([
            ['platform' => 'instagram', 'url' => 'https://instagram.com/teamit'],
            ['platform' => 'whatsapp', 'url' => 'https://wa.me/85512345678'],
        ])]);
        \App\Models\Page::create(['slug' => 'contact', 'title' => 'Contact', 'template' => 'contact', 'sections' => []]);

        $this->get('/contact')->assertOk()
            ->assertSee('https://instagram.com/teamit', false)
            ->assertSee('https://wa.me/85512345678', false)
            ->assertSee('follow-card', false);
    }
}
