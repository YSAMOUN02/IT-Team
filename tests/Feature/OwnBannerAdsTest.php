<?php

namespace Tests\Feature;

use App\Models\AdClick;
use App\Models\Setting;
use App\Models\User;
use App\Support\Ads;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Admin > Ads: uploading your own banner image instead of (or next to) Google AdSense. */
class OwnBannerAdsTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = 'Mozilla/5.0 (Linux; Android 14; SM-A546E) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0 Mobile Safari/537.36';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();

        return $admin;
    }

    private function banner(string $placement, array $extra = []): void
    {
        Setting::put($extra + [
            "ad_{$placement}_enabled" => '1',
            "ad_{$placement}_type" => 'image',
            "ad_{$placement}_image" => 'promos/2026/10/sale.png',
            "ad_{$placement}_link" => '/courses/pivot-table',
        ]);
    }

    public function test_admin_uploads_a_banner_and_it_shows_on_the_site(): void
    {
        $this->actingAs($this->admin())->put('/admin/ads', [
            'ad_footer_enabled' => '1',
            'ad_footer_type' => 'image',
            'ad_footer_image' => UploadedFile::fake()->image('sale.png', 1200, 200),
            'ad_footer_image_phone' => UploadedFile::fake()->image('sale-phone.png', 600, 300),
            'ad_footer_link' => 't.me/teamitcambodia',
            'ad_footer_alt' => 'Pivot Table course, only $19',
        ])->assertRedirect('/admin/ads')->assertSessionHasNoErrors();

        $image = setting('ad_footer_image');
        Storage::disk('public')->assertExists($image);
        $this->assertStringStartsWith('promos/', $image);
        $this->assertSame(['1200x200', 'https://t.me/teamitcambodia'], [setting('ad_footer_image_size'), setting('ad_footer_link')]);

        auth()->logout();
        $this->get('/courses')->assertOk()
            ->assertSee('class="promo promo-footer', false)
            ->assertSee('src="/storage/'.$image.'"', false)
            ->assertSee('width="1200" height="200"', false)
            ->assertSee('media="(max-width: 640px)"', false)
            ->assertSee('alt="Pivot Table course, only $19"', false)
            ->assertSee('href="'.route('promo.go', 'footer').'" target="_blank" rel="sponsored noopener"', false);
    }

    public function test_a_click_is_counted_then_goes_to_the_link(): void
    {
        $this->banner('footer');

        $this->withHeader('User-Agent', self::PHONE)->get('/go/footer')->assertRedirect(url('/courses/pivot-table'));
        $this->withHeader('User-Agent', 'Mozilla/5.0 (compatible; Googlebot/2.1)')->get('/go/footer')->assertRedirect(url('/courses/pivot-table'));
        $this->actingAs($this->admin())->withHeader('User-Agent', self::PHONE)->get('/go/footer')->assertRedirect();

        $this->assertSame(1, AdClick::count()); // the bot and the admin are not counted
        $this->assertSame(['footer' => 1], AdClick::countsSince(now()->subDays(30)));
        $this->get('/admin/ads')->assertOk()->assertSee('<b>1</b> click in the last 30 days', false)
            ->assertSee('type="text" name="ad_footer_link"', false)->assertSee('type="text" name="ad_footer_alt"', false);
    }

    public function test_banner_links_to_this_site_open_in_the_same_tab(): void
    {
        $this->banner('footer', ['ad_footer_link' => url('/courses')]);
        $this->get('/courses')->assertSee('href="'.route('promo.go', 'footer').'">', false);

        $this->banner('footer', ['ad_footer_link' => null]);
        $this->get('/courses')->assertSee('class="promo promo-footer', false)->assertDontSee(route('promo.go', 'footer'));
        $this->get('/go/footer')->assertNotFound(); // no link, nothing to go to
        $this->get('/go/nowhere')->assertNotFound();
    }

    public function test_removing_the_image_brings_back_the_placeholder(): void
    {
        Storage::disk('public')->put('promos/2026/10/sale.png', 'png');
        $this->banner('footer');

        $this->actingAs($this->admin())->put('/admin/ads', ['ad_footer_enabled' => '1', 'ad_footer_type' => 'image', 'remove_ad_footer_image' => '1'])->assertRedirect();

        Storage::disk('public')->assertMissing('promos/2026/10/sale.png');
        $this->assertSame('placeholder', Ads::state('footer'));
    }

    public function test_bad_files_and_links_are_refused(): void
    {
        $this->actingAs($this->admin())->from('/admin/ads')->put('/admin/ads', [
            'ad_footer_image' => UploadedFile::fake()->create('logo.svg', 4, 'image/svg+xml'),
            'ad_header_link' => 'javascript:alert(1)',
        ])->assertSessionHasErrors(['ad_footer_image', 'ad_header_link']);
    }

    public function test_image_spots_do_not_load_google_and_google_spots_still_work(): void
    {
        $this->banner('footer', ['ads_publisher_id' => 'ca-pub-1234567890123456']);
        $this->assertNull(Ads::headScript());
        $this->assertNull(Ads::slotHtml('footer'));

        Setting::put(['ad_header_enabled' => '1', 'ad_header_slot' => '1234567890']); // saved before there was a choice
        $this->assertSame(['google', 'image'], [Ads::type('header'), Ads::type('listing')]);
        $this->assertNotNull(Ads::headScript());
        $this->get('/courses')->assertSee('data-ad-slot="1234567890"', false)->assertSee('class="promo promo-footer', false);
    }
}
