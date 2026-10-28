<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use App\Support\CourseSetup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseBuilderTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();

        return $admin;
    }

    private function course(): Course
    {
        $course = Course::create(['title' => 'Basic Excel', 'slug' => 'basic-excel', 'is_published' => true]);
        $course->plans()->create(['days' => 365, 'price' => 49]);
        $m1 = $course->modules()->create(['title' => 'Module 1', 'position' => 1]);
        $m2 = $course->modules()->create(['title' => 'Module 2', 'position' => 2]);
        $course->lessons()->create(['course_module_id' => $m1->id, 'title' => 'A', 'slug' => 'a', 'position' => 1, 'body' => '<p>x</p>']);
        $course->lessons()->create(['course_module_id' => $m1->id, 'title' => 'B', 'slug' => 'b', 'position' => 2, 'body' => '<p>x</p>']);
        $quiz = $course->quizzes()->create(['course_module_id' => $m2->id, 'type' => 'quiz', 'title' => 'Q', 'slug' => 'q', 'position' => 1]);
        $question = $quiz->questions()->create(['type' => 'single', 'prompt' => '?', 'points' => 1]);
        $question->options()->create(['text' => 'Yes', 'is_correct' => true]);

        return $course;
    }

    public function test_drag_and_drop_order_is_saved_including_moves_between_modules(): void
    {
        $course = $this->course();
        [$m1, $m2] = $course->modules->all();
        [$a, $b] = $course->lessons->all();
        $quiz = $course->quizzes->first();

        $this->actingAs($this->admin())->postJson(route('admin.courses.curriculum.reorder', $course), [
            'modules' => [$m2->id, $m1->id],
            'items' => [$m2->id => ["lesson:{$b->id}", "quiz:{$quiz->id}"], $m1->id => ["lesson:{$a->id}"]],
        ])->assertOk();

        $this->assertSame([$m2->id, $m1->id], $course->modules()->pluck('id')->all());
        $this->assertSame($m2->id, $b->fresh()->course_module_id);
        $this->assertSame(1, $b->fresh()->position);
        $this->assertSame(2, $quiz->fresh()->position);
    }

    public function test_reorder_ignores_items_from_other_courses(): void
    {
        $course = $this->course();
        $other = Course::create(['title' => 'Other', 'slug' => 'other']);
        $foreign = $other->lessons()->create(['title' => 'X', 'slug' => 'x', 'position' => 7]);

        $this->actingAs($this->admin())->postJson(route('admin.courses.curriculum.reorder', $course), [
            'items' => [$course->modules->first()->id => ["lesson:{$foreign->id}"]],
        ])->assertOk();

        $this->assertNull($foreign->fresh()->course_module_id);
        $this->assertSame(7, $foreign->fresh()->position);
    }

    public function test_quick_add_creates_hidden_lessons_from_lines(): void
    {
        $course = $this->course();
        $module = $course->modules->first();

        $this->actingAs($this->admin())->post(route('admin.courses.curriculum.quick-add', $course), [
            'module' => $module->id, 'titles' => "Charts\n\nPivotTables\n",
        ])->assertRedirect();

        $new = $course->lessons()->whereIn('title', ['Charts', 'PivotTables'])->get();
        $this->assertCount(2, $new);
        $this->assertFalse($new->first()->is_published);
        $this->assertSame([3, 4], $new->pluck('position')->all());
    }

    public function test_duplicate_copies_the_curriculum_as_a_hidden_draft(): void
    {
        $course = $this->course();

        $this->actingAs($this->admin())->post(route('admin.courses.duplicate', $course))->assertRedirect();

        $copy = Course::where('title', 'Basic Excel (copy)')->firstOrFail();
        $this->assertFalse($copy->is_published);
        $this->assertNotSame($course->slug, $copy->slug);
        $this->assertSame(2, $copy->modules()->count());
        $this->assertSame(2, $copy->lessons()->count());
        $this->assertSame(1, $copy->quizzes()->first()->questions()->first()->options()->count());
        $this->assertSame($copy->modules()->pluck('id')->sort()->values()->all(), $copy->lessons()->pluck('course_module_id')->unique()->merge($copy->quizzes()->pluck('course_module_id'))->unique()->sort()->values()->all());
    }

    public function test_setup_checklist_points_at_what_is_missing(): void
    {
        $course = $this->course();
        $setup = CourseSetup::for($course);
        $labels = array_column($setup->missing(), 'label');

        $this->assertContains('Cover image', $labels);
        $this->assertContains('Final exam for the certificate', $labels);
        $this->assertNotContains('Lessons', $labels);
        $this->assertGreaterThan(0, $setup->percent);
        $this->assertLessThan(100, $setup->percent);
    }

    public function test_admin_can_view_the_course_page_as_a_visitor(): void
    {
        $course = $this->course();

        $this->actingAs($this->admin())->get('/courses/basic-excel?as=visitor')
            ->assertOk()->assertSee('/courses/basic-excel/checkout', false)->assertSee('admin-bar', false);
        $this->actingAs($this->admin())->get('/courses/basic-excel')
            ->assertOk()->assertSee('/learn/basic-excel', false);
    }
}
