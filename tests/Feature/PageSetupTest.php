<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Admin > Pages: editing the fixed pages' sections and their photos (home founder, about "who we are" …). */
class PageSetupTest extends TestCase
{
    use RefreshDatabase;

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

    private function home(array $sections = []): Page
    {
        return Page::create(['slug' => 'home', 'template' => 'home', 'title' => 'TEAMIT Cambodia', 'is_published' => true, 'sections' => $sections + [
            'founder' => ['title' => 'ជម្រាបសួរ!', 'text' => 'ខ្ញុំឈ្មោះ សីហា', 'image' => null],
        ]]);
    }

    /** What the browser sends back when the form is saved without changes, plus $extra. */
    private function save(Page $page, array $extra = [])
    {
        return $this->actingAs($this->admin())->put("/admin/pages/{$page->id}", array_replace_recursive([
            'title' => $page->title,
            'is_published' => '1',
            'sections' => $page->sections,
        ], $extra));
    }

    /** A JPEG whose pixels are stored sideways: top half red, bottom half blue, with EXIF orientation $orientation. */
    private function phonePhoto(int $width, int $height, int $orientation): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefilledrectangle($image, 0, 0, $width, intdiv($height, 2), imagecolorallocate($image, 255, 0, 0));
        imagefilledrectangle($image, 0, intdiv($height, 2), $width, $height, imagecolorallocate($image, 0, 0, 255));
        ob_start();
        imagejpeg($image, null, 90);
        $jpeg = ob_get_clean();

        // APP1 "Exif" segment with one IFD0 entry: 0x0112 Orientation (SHORT).
        $tiff = "MM\x00\x2A\x00\x00\x00\x08\x00\x01\x01\x12\x00\x03\x00\x00\x00\x01".pack('n', $orientation)."\x00\x00\x00\x00\x00\x00";
        $exif = "Exif\x00\x00".$tiff;

        return substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($exif) + 2).$exif.substr($jpeg, 2);
    }

    public function test_every_page_opens_in_the_admin_with_its_sections(): void
    {
        $admin = $this->admin();
        foreach (array_keys(config('page_templates')) as $template) {
            $page = Page::create(['slug' => "page-$template", 'template' => $template, 'title' => ucfirst($template), 'sections' => []]);
            $response = $this->actingAs($admin)->get("/admin/pages/{$page->id}/edit")->assertOk();

            foreach (config("page_templates.$template.groups") as $group => $config) {
                $response->assertSee($config['label']);
                foreach ($config['fields'] as $field => $meta) {
                    $name = $meta['type'] === 'image' ? "uploads[$group][$field]" : "sections[$group][$field]";
                    $response->assertSee('name="'.$name.'"', false);
                }
            }
        }
    }

    public function test_founder_photo_uploads_and_shows_on_the_home_page(): void
    {
        $page = $this->home();

        $this->save($page, [
            'sections' => ['founder' => ['title' => 'Hello everyone!']],
            'uploads' => ['founder' => ['image' => UploadedFile::fake()->image('Founder Photo.JPG', 700, 940)]],
        ])->assertRedirect("/admin/pages/{$page->id}/edit")->assertSessionHasNoErrors();

        $path = $page->fresh()->field('founder.image');
        $this->assertMatchesRegularExpression('#^pages/\d{4}/\d{2}/founder-photo-[a-z0-9]{6}\.jpg$#', $path);
        Storage::disk('public')->assertExists($path);
        $this->assertSame([700, 940], array_slice(getimagesizefromstring(Storage::disk('public')->get($path)), 0, 2));
        $this->assertSame(['Hello everyone!', 'ខ្ញុំឈ្មោះ សីហា'], [$page->fresh()->field('founder.title'), $page->fresh()->field('founder.text')]);

        $this->get('/')->assertOk()->assertSee('Hello everyone!')->assertSee(Storage::disk('public')->url($path));
        $this->get("/admin/pages/{$page->id}/edit")->assertSee(Storage::disk('public')->url($path))->assertSee(basename($path));
    }

    public function test_a_new_photo_replaces_the_old_one_and_leaves_other_sections_alone(): void
    {
        Storage::disk('public')->put('wp/2025/06/old-founder.jpg', 'old');
        Storage::disk('public')->put('wp/2025/06/hero.jpg', 'hero');
        $page = $this->home([
            'hero' => ['title' => 'Learn Excel', 'image' => 'wp/2025/06/hero.jpg'],
            'intro' => ['points' => ['One', 'Two']],
            'founder' => ['title' => 'Hi', 'text' => '<p>Bio</p>', 'image' => 'wp/2025/06/old-founder.jpg'],
        ]);

        $this->save($page, [
            'sections' => ['intro' => ['points' => "One\r\nTwo"]],
            'uploads' => ['founder' => ['image' => UploadedFile::fake()->image('new.png', 400, 500)]],
        ])->assertSessionHasNoErrors();

        $page->refresh();
        Storage::disk('public')->assertMissing('wp/2025/06/old-founder.jpg');
        Storage::disk('public')->assertExists($page->field('founder.image'));
        Storage::disk('public')->assertExists('wp/2025/06/hero.jpg');
        $this->assertSame(['wp/2025/06/hero.jpg', 'Learn Excel', ['One', 'Two'], '<p>Bio</p>'], [
            $page->field('hero.image'), $page->field('hero.title'), $page->field('intro.points'), $page->field('founder.text'),
        ]);
    }

    public function test_remove_takes_the_photo_off_the_page(): void
    {
        Storage::disk('public')->put('pages/2026/10/founder.jpg', 'x');
        $page = $this->home(['founder' => ['title' => 'Hi', 'image' => 'pages/2026/10/founder.jpg']]);

        $this->save($page, ['remove' => ['founder' => ['image' => '1']]])->assertSessionHasNoErrors();

        $this->assertNull($page->fresh()->field('founder.image'));
        Storage::disk('public')->assertMissing('pages/2026/10/founder.jpg');
        $this->get('/')->assertOk()->assertDontSee('founder-photo');
    }

    public function test_about_page_who_we_are_photo_uploads(): void
    {
        $page = Page::create(['slug' => 'about', 'template' => 'about', 'title' => 'About', 'is_published' => true, 'sections' => [
            'who' => ['title' => 'Who we are', 'name' => 'Mang Seyha'],
        ]]);

        $this->save($page, ['uploads' => ['who' => ['image' => UploadedFile::fake()->image('seyha.webp', 600, 800)]]])
            ->assertSessionHasNoErrors();

        $path = $page->fresh()->field('who.image');
        $this->assertStringEndsWith('.webp', $path);
        $this->get('/about')->assertOk()->assertSee(Storage::disk('public')->url($path))->assertSee('Mang Seyha');
    }

    public function test_a_big_phone_photo_is_accepted_scaled_down_and_turned_upright(): void
    {
        $page = $this->home();
        // Stored 4000×3000 with "turn right" (6): it really is a 3000×4000 portrait.
        $file = UploadedFile::fake()->createWithContent('IMG_0042.jpg', $this->phonePhoto(4000, 3000, 6))->size(12 * 1024);

        $this->save($page, ['uploads' => ['founder' => ['image' => $file]]])->assertSessionHasNoErrors();

        $image = imagecreatefromstring(Storage::disk('public')->get($page->fresh()->field('founder.image')));
        $this->assertSame([2400, 3200], [imagesx($image), imagesy($image)]);
        // The stored top (red) ends up on the right once turned the right way.
        $this->assertGreaterThan(200, imagecolorat($image, 2100, 1600) >> 16 & 0xFF);
        $this->assertGreaterThan(200, imagecolorat($image, 300, 1600) & 0xFF);
    }

    public function test_a_wrong_type_or_too_big_file_says_which_photo_and_why(): void
    {
        $page = $this->home();
        $edit = "/admin/pages/{$page->id}/edit";

        $this->from($edit)->save($page, ['uploads' => ['founder' => ['image' => UploadedFile::fake()->create('IMG_0042.heic', 900, 'image/heic')]]])
            ->assertRedirect($edit)
            ->assertSessionHasErrors(['uploads.founder.image' => 'Founder › Photo: use a JPG, PNG, GIF, WebP or SVG picture. iPhone HEIC photos need to be saved as JPG first.']);

        $this->from($edit)->save($page, ['uploads' => ['founder' => ['image' => UploadedFile::fake()->image('huge.jpg', 50, 50)->size(25 * 1024)]]])
            ->assertSessionHasErrors(['uploads.founder.image' => 'Founder › Photo: the picture is bigger than 20 MB. Choose a smaller one.']);

        // The message also sits under the photo field itself, and nothing was changed.
        $this->assertMatchesRegularExpression('/data-dz-error\s*>Founder › Photo: the picture is bigger than 20 MB/u', $this->get($edit)->getContent());
        $this->assertNull($page->fresh()->field('founder.image'));
    }

    public function test_the_picker_only_offers_types_the_server_takes_and_knows_the_limit(): void
    {
        $page = $this->home();

        $this->actingAs($this->admin())->get("/admin/pages/{$page->id}/edit")
            ->assertSee('name="uploads[founder][image]" accept=".jpg,.jpeg,.png,.gif,.webp,.svg"', false)
            ->assertSee('data-max-kb="20480"', false)
            ->assertSee('up to 20 MB');
    }
}
