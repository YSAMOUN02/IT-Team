<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Support\XlsxWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use ZipArchive;

/** Admin > Classes > a class, and Admin > Students > a student: "Progress report" as an Excel file. */
class ProgressReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Course $course;

    private Classroom $classroom;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-12 10:00:00');
        $this->admin = User::factory()->create();
        $this->admin->forceFill(['role' => 'admin'])->save();

        $this->course = Course::create(['title' => 'Basic Excel', 'slug' => 'basic-excel', 'is_published' => true]);
        $module = $this->course->modules()->create(['title' => 'Module 1', 'position' => 1]);
        $this->course->lessons()->create(['course_module_id' => $module->id, 'title' => 'Open Excel', 'slug' => 'open', 'position' => 1, 'is_published' => true]);
        $this->course->lessons()->create(['course_module_id' => $module->id, 'title' => 'Cells', 'slug' => 'cells', 'position' => 2, 'is_published' => true]);
        $this->course->lessons()->create(['course_module_id' => $module->id, 'title' => 'Hidden draft', 'slug' => 'draft', 'position' => 3, 'is_published' => false]);
        $quiz = $this->course->quizzes()->create(['course_module_id' => $module->id, 'type' => 'quiz', 'title' => 'Module 1 quiz', 'slug' => 'q1', 'position' => 4, 'pass_percent' => 60, 'is_published' => true]);

        $this->classroom = Classroom::create(['name' => 'Saturday morning']);
        $this->classroom->syncCourses([$this->course->id]);

        $dara = User::factory()->create(['name' => 'សុខ ដារា', 'email' => 'dara@example.com']);
        Enrollment::grant($dara, $this->course, 90, 'manual');
        DB::table('lesson_progress')->insert(['user_id' => $dara->id, 'course_lesson_id' => $this->course->lessons()->where('slug', 'open')->value('id'), 'completed_at' => '2026-11-10 09:00:00']);
        $quiz->attempts()->create(['user_id' => $dara->id, 'started_at' => '2026-11-11 09:00:00', 'submitted_at' => '2026-11-11 09:12:00', 'score' => 1.4, 'max_score' => 3, 'percent' => 46, 'passed' => false]);
        $quiz->attempts()->create(['user_id' => $dara->id, 'started_at' => '2026-11-11 10:00:00', 'submitted_at' => '2026-11-11 10:05:00', 'score' => 3, 'max_score' => 3, 'percent' => 100, 'passed' => true]);
        $quiz->attempts()->create(['user_id' => $dara->id, 'started_at' => '2026-11-11 11:00:00']); // not submitted: left out

        $keo = User::factory()->create(['name' => 'Keo Nary', 'email' => 'keo@example.com']); // in the class, no access yet
        $this->classroom->students()->attach([$dara->id, $keo->id]);
    }

    /** sheet name => rows of cell text, from the downloaded file. */
    private function read(\Symfony\Component\HttpFoundation\BinaryFileResponse $response): array
    {
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($response->getFile()->getPathname()));
        $workbook = simplexml_load_string($zip->getFromName('xl/workbook.xml'));
        $sheets = [];
        foreach ($workbook->sheets->sheet as $i => $sheet) {
            $sheets[] = (string) $sheet['name'];
        }
        $out = [];
        foreach ($sheets as $n => $name) {
            $xml = simplexml_load_string($zip->getFromName('xl/worksheets/sheet'.($n + 1).'.xml'));
            foreach ($xml->sheetData->row as $row) {
                $cells = [];
                foreach ($row->c as $c) {
                    preg_match('/^[A-Z]+/', (string) $c['r'], $m);
                    $col = 0;
                    foreach (str_split($m[0]) as $ch) {
                        $col = $col * 26 + ord($ch) - 64;
                    }
                    $cells[$col - 1] = isset($c->is) ? (string) $c->is->t : (string) $c->v;
                }
                $out[$name][] = $cells;
            }
        }
        $zip->close();

        return $out;
    }

    public function test_a_class_report_has_a_summary_every_lesson_test_and_try(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.classes.report', $this->classroom))->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('saturday-morning-progress-2026-11-12.xlsx', $response->headers->get('Content-Disposition'));
        $book = $this->read($response->baseResponse);
        $this->assertSame(['Summary', 'Lessons', 'Tests', 'Tries'], array_keys($book));

        // Students in name order (Keo before the Khmer name). Progress: 1 of 2 lessons + 1 of 1 test = 66%.
        [$head, $keo, $dara] = $book['Summary'];
        $this->assertSame(['Student', 'Email', 'Phone', 'Class', 'Course', 'Access from', 'Access until', 'Progress %'], array_slice($head, 0, 8));
        $this->assertSame(['សុខ ដារា', 'dara@example.com'], [$dara[0], $dara[1]]);
        $this->assertSame(['Saturday morning', 'Basic Excel', '2026-11-12', '2027-02-10', '66', '1 / 2', '1 / 1', 'In progress', '2026-11-11 10:05'],
            [$dara[3], $dara[4], $dara[5], $dara[6], $dara[7], $dara[8], $dara[9], $dara[11], $dara[12]]);
        $this->assertSame(['Keo Nary', '0', 'No access'], [$keo[0], $keo[7], $keo[11]]);

        // Lessons: only published ones, done or not.
        $lessons = collect($book['Lessons'])->slice(1)->filter(fn ($r) => $r[0] === 'សុខ ដារា')->values();
        $this->assertSame([['Module 1', '1', 'Open Excel', 'Yes', '2026-11-10 09:00'], ['Module 1', '2', 'Cells', 'No']],
            $lessons->map(fn ($r) => array_values(array_slice($r, 2)))->all());

        // Tests: 2 submitted tries, the best one passed. Tries: decimals kept, minutes worked out.
        $test = collect($book['Tests'])->first(fn ($r) => $r[0] === 'សុខ ដារា');
        $this->assertSame(['Module 1 quiz', 'Module quiz', 'Yes', '2', '100', '3 / 3', 'Passed'], array_slice($test, 3, 7));
        $tries = collect($book['Tries'])->slice(1)->values();
        $this->assertCount(2, $tries);
        $this->assertSame(['1', '2026-11-11 09:00', '2026-11-11 09:12', '12', '1.4', '3', '46', 'Not passed'], array_slice($tries[0], 3));
    }

    public function test_one_student_report_and_who_can_download(): void
    {
        $dara = User::where('email', 'dara@example.com')->sole();
        $response = $this->actingAs($this->admin)->get(route('admin.students.report', $dara))->assertOk();
        $this->assertStringContainsString('student-'.$dara->id.'-progress-2026-11-12.xlsx', $response->headers->get('Content-Disposition')); // a Khmer name makes no file name
        $book = $this->read($response->baseResponse);
        $this->assertCount(2, $book['Summary']);
        $this->assertSame('សុខ ដារា', $book['Summary'][1][0]);
        $this->get(route('admin.students.show', $dara))->assertSee(route('admin.students.report', $dara), false);
        $this->get(route('admin.classes.show', $this->classroom))->assertSee(route('admin.classes.report', $this->classroom), false)->assertSee(route('admin.students.report', $dara), false);

        $this->actingAs($dara)->get(route('admin.students.report', $dara))->assertRedirect();
        $this->actingAs($this->admin)->get(route('admin.students.report', $this->admin))->assertNotFound();
    }

    public function test_column_letters(): void
    {
        $this->assertSame(['A', 'Z', 'AA', 'AZ', 'BA'], array_map([XlsxWriter::class, 'column'], [0, 25, 26, 51, 52]));
    }
}