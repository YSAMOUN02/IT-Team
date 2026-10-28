<?php

namespace Tests\Feature;

use App\Models\Course;
use Database\Seeders\PivotTableCourseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** The complete course built from the Word manual and practice workbook in example/. */
class PivotTableCourseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_manual_becomes_a_complete_course(): void
    {
        if (! is_file(base_path('example/ការប្រើប្រាស Pivot Table.docx'))) {
            $this->markTestSkipped('The example/ source files are not here.');
        }
        Storage::fake('public');
        Storage::fake('local');

        $this->seed(PivotTableCourseSeeder::class);

        $course = Course::where('slug', 'pivot-table')->firstOrFail();
        $this->assertTrue($course->isPurchasable());
        $this->assertSame(7, $course->modules()->count());
        $this->assertSame(19, $course->lessons()->count());
        $this->assertSame(['final', 'mock', 'practice', 'quiz'], $course->quizzes()->pluck('type')->unique()->sort()->values()->all());
        $this->assertSame(8, $course->quizzes()->count());
        $this->assertSame(1, $course->lessons()->where('is_preview', true)->count());
        $this->assertSame(3, $course->lessons()->whereNotNull('attachment_path')->count());
        $this->assertGreaterThan(80, count(Storage::disk('public')->files('courses/pivot-table')));

        // Screenshots, sub-headings and lists came through; Word's duplicate fallback pictures did not.
        $body = $course->lessons()->where('title', 'like', 'Layout%')->value('body');
        $this->assertStringContainsString('<h3>Subtotals:</h3>', $body);
        $this->assertStringContainsString('<li><strong>Off for Rows and Columns</strong>:', $body);
        $this->assertSame(6, substr_count($body, '<figure class="lesson-shot"'));
        $this->assertStringContainsString('<ol class="steps">', $course->lessons()->where('title', 'like', 'ឧទាហរណ៍%')->value('body'));

        // Running it again leaves the course alone.
        $this->seed(PivotTableCourseSeeder::class);
        $this->assertSame(1, Course::where('slug', 'pivot-table')->count());
    }
}
