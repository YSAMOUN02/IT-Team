<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;
use ZipArchive;

/** Pasted screenshots and Word imports in the admin editor. */
class EditorUploadsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();

        return $admin;
    }

    private function png(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 95, 89, 247));
        ob_start();
        imagepng($image);

        return ob_get_clean();
    }

    /** A small .docx: a heading, "Steps:", a two-item list and a picture under it. */
    private function docx(): string
    {
        $path = sys_get_temp_dir().'/'.Str::random(8).'.docx';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        $w = 'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture"';
        $item = fn ($text) => '<w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="1"/></w:numPr></w:pPr><w:r><w:t>'.$text.'</w:t></w:r></w:p>';
        $zip->addFromString('word/document.xml', '<?xml version="1.0"?><w:document '.$w.'><w:body>'
            .'<w:p><w:pPr><w:pStyle w:val="Heading1"/></w:pPr><w:r><w:t>Pivot basics</w:t></w:r></w:p>'
            .'<w:p><w:r><w:rPr><w:b/></w:rPr><w:t>Steps:</w:t></w:r></w:p>'
            .$item('Insert: click PivotTable').$item('Click OK')
            .'<w:p><w:r><w:drawing><wp:inline><a:graphic><a:graphicData><pic:pic><pic:blipFill><a:blip r:embed="rId9"/></pic:blipFill></pic:pic></a:graphicData></a:graphic></wp:inline></w:drawing></w:r></w:p>'
            .'</w:body></w:document>');
        $zip->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId9" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/image1.png"/></Relationships>');
        $zip->addFromString('word/media/image1.png', $this->png(40, 30));
        $zip->close();

        $bytes = file_get_contents($path);
        unlink($path);

        return $bytes;
    }

    public function test_a_pasted_full_screen_screenshot_is_scaled_down(): void
    {
        Storage::fake('public');

        $location = $this->actingAs($this->admin())
            ->post('/admin/uploads', ['file' => UploadedFile::fake()->createWithContent('blobid123.png', $this->png(2880, 1200))], ['Accept' => 'application/json'])
            ->assertOk()->json('location');

        $this->assertStringStartsWith('/storage/editor/', $location);
        $size = getimagesizefromstring(Storage::disk('public')->get(Str::after($location, '/storage/')));
        $this->assertSame(1600, $size[0]);
        $this->assertEqualsWithDelta(667, $size[1], 1); // keeps the shape
    }

    public function test_a_word_document_becomes_lesson_html_with_its_pictures(): void
    {
        Storage::fake('public');

        $json = $this->actingAs($this->admin())
            ->post('/admin/uploads/word', ['file' => UploadedFile::fake()->createWithContent('Pivot manual.docx', $this->docx())], ['Accept' => 'application/json'])
            ->assertOk()->json();

        $this->assertSame(1, $json['images']);
        $this->assertStringContainsString('<h2>Pivot basics</h2>', $json['html']);
        $this->assertStringContainsString('<ol class="steps"><li><strong>Insert</strong>: click PivotTable</li><li>Click OK<figure', $json['html']);
        $this->assertMatchesRegularExpression('#<img src="/storage/editor/\d{4}/\d{2}/pivot-manual-\w+\.png"#', $json['html']);
        $this->assertCount(1, Storage::disk('public')->allFiles('editor'));
    }

    public function test_files_that_are_not_word_documents_are_refused(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/uploads/word', ['file' => UploadedFile::fake()->createWithContent('notes.docx', 'not a zip')], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJson(['message' => 'This file could not be read as a Word document.']);

        $this->actingAs($this->admin())
            ->post('/admin/uploads/word', ['file' => UploadedFile::fake()->create('notes.pdf', 10)], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('file');
    }

    public function test_students_cannot_upload(): void
    {
        Storage::fake('public');

        $this->actingAs(User::factory()->create())
            ->post('/admin/uploads/word', ['file' => UploadedFile::fake()->createWithContent('a.docx', $this->docx())])
            ->assertRedirect();
        $this->assertSame([], Storage::disk('public')->allFiles());
    }
}
