<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\User;
use App\Support\CourseDuplicator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Short answer, matching, ordering and drag & drop: written in the admin, taken, graded, reviewed. */
class QuestionTypesTest extends TestCase
{
    use RefreshDatabase;

    private Course $course;

    private Quiz $quiz;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
        $this->admin->forceFill(['role' => 'admin'])->save();
        $this->course = Course::create(['title' => 'Basic Excel', 'slug' => 'basic-excel', 'is_published' => true]);
        $this->quiz = $this->course->quizzes()->create(['type' => 'quiz', 'title' => 'Quiz', 'slug' => 'quiz', 'pass_percent' => 100, 'show_answers' => true, 'is_published' => true]);
    }

    /** The four questions, made through the admin form. @return array<string, QuizQuestion> */
    private function makeQuestions(?Quiz $quiz = null): array
    {
        $quiz ??= $this->quiz;
        $this->actingAs($this->admin);
        $post = fn (array $data) => $this->post(route('admin.quizzes.questions.store', $quiz), $data + ['points' => 1])->assertSessionHasNoErrors();

        $post(['type' => 'short', 'prompt' => 'Why use a PivotTable?', 'explanation' => 'To summarise a large table quickly.', 'options' => [['text' => 'left over']]]);
        $post(['type' => 'matching', 'prompt' => 'Match the shortcut', 'options' => [
            ['text' => 'Ctrl + C', 'match' => 'Copy'], ['text' => 'Ctrl + V', 'match' => 'Paste'], ['text' => 'Ctrl + Z', 'match' => 'Undo'],
        ]]);
        $post(['type' => 'ordering', 'prompt' => 'Make a PivotTable', 'options' => [['text' => 'Select the data'], ['text' => 'Insert > PivotTable'], ['text' => 'Drag the fields']]]);
        $post(['type' => 'dragdrop', 'prompt' => 'Use ___ to add, and ___ to count.', 'options' => [
            ['text' => 'SUM', 'slot' => 1], ['text' => 'COUNT', 'slot' => 2], ['text' => 'VLOOKUP', 'slot' => ''],
        ]]);

        return $quiz->questions()->with('options')->get()->keyBy('type')->all();
    }

    public function test_admins_write_each_type_and_mistakes_are_explained(): void
    {
        $q = $this->makeQuestions();

        $this->assertSame([], $q['short']->options->all()); // no answer key: a teacher marks it
        $this->assertSame(['Copy', 'Paste', 'Undo'], $q['matching']->options->pluck('match_text')->all());
        $this->assertSame(['Select the data', 'Insert > PivotTable', 'Drag the fields'], $q['ordering']->options->pluck('text')->all());
        $this->assertSame([[1, true], [2, true], [null, false]], $q['dragdrop']->options->map(fn ($o) => [$o->slot, $o->is_correct])->all());
        $this->assertSame(2, $q['dragdrop']->blankCount());

        $this->get(route('admin.questions.edit', $q['matching']))->assertOk()->assertSee('value="Paste"', false);
        // The test's page offers a button for every type.
        $page = $this->get(route('admin.quizzes.edit', $this->quiz))->assertOk();
        foreach (['short', 'matching', 'ordering', 'dragdrop'] as $type) {
            $page->assertSee(e(route('admin.quizzes.questions.create', [$this->quiz, 'type' => $type])), false);
        }
        $this->get(route('admin.quizzes.questions.create', [$this->quiz, 'type' => 'dragdrop']))->assertOk()->assertSee('three underscores');

        $fails = [
            ['matching', 'Q', [['text' => 'A', 'match' => '1'], ['text' => 'B', 'match' => '']], 'Fill in both sides of every pair.'],
            ['matching', 'Q', [['text' => 'A', 'match' => '1']], 'Add at least two pairs.'],
            ['ordering', 'Q', [['text' => 'Only one']], 'Add at least two items.'],
            ['dragdrop', 'No blanks here', [['text' => 'SUM', 'slot' => 1]], 'Write ___ (three underscores) in the question where each blank goes.'],
            ['dragdrop', 'Use ___ and ___', [['text' => 'SUM', 'slot' => 1]], 'Give blank 2 exactly one word.'],
            ['dragdrop', 'Use ___', [['text' => 'SUM', 'slot' => 1], ['text' => 'COUNT', 'slot' => 1]], 'Give blank 1 exactly one word.'],
            ['dragdrop', 'Use ___', [['text' => 'SUM', 'slot' => 1], ['text' => 'COUNT', 'slot' => 3]], 'The question has 1 blank, but a word is set for blank 3.'],
        ];
        foreach ($fails as [$type, $prompt, $options, $message]) {
            $this->post(route('admin.quizzes.questions.store', $this->quiz), ['type' => $type, 'prompt' => $prompt, 'points' => 1, 'options' => $options])
                ->assertSessionHasErrors(['options' => $message]);
        }
        $this->assertSame(4, $this->quiz->questions()->count());
    }

    public function test_a_student_takes_every_type_and_it_is_graded(): void
    {
        $q = $this->makeQuestions();
        $student = User::factory()->create();
        Enrollment::grant($student, $this->course, null, 'manual');
        $base = "/learn/{$this->course->slug}/quizzes/{$this->quiz->slug}";
        $id = fn ($type, $text, $field = 'text') => $q[$type]->options->firstWhere($field, $text)->id;

        // The page offers each kind of answer.
        $this->actingAs($student)->post("$base/start")->assertRedirect();
        $attempt = $this->quiz->attempts()->latest('id')->firstOrFail();
        $this->get("$base/attempts/{$attempt->id}")->assertOk()
            ->assertSee('name="answers['.$q['short']->id.']"', false)
            ->assertSee('name="answers['.$q['matching']->id.']['.$id('matching', 'Ctrl + C').']"', false)
            ->assertSee('class="q-order"', false)->assertSee('data-chip="'.$id('dragdrop', 'VLOOKUP').'"', false)
            ->assertSee('name="answers['.$q['dragdrop']->id.'][2]"', false)
            ->assertSee(__('account.hint_dragdrop'));

        // All right (the extra word is not used); the short answer waits for the teacher, and is cut at 255 characters.
        $this->post("$base/attempts/{$attempt->id}", ['answers' => [
            $q['short']->id => '  To summarise data. '.str_repeat('x', 300),
            $q['matching']->id => [$id('matching', 'Ctrl + C') => $id('matching', 'Copy', 'match_text'), $id('matching', 'Ctrl + V') => $id('matching', 'Paste', 'match_text'), $id('matching', 'Ctrl + Z') => $id('matching', 'Undo', 'match_text')],
            $q['ordering']->id => $q['ordering']->options->pluck('id')->all(),
            $q['dragdrop']->id => [1 => $id('dragdrop', 'SUM'), 2 => $id('dragdrop', 'COUNT')],
        ]])->assertRedirect();
        $attempt->refresh();
        $this->assertSame(255, mb_strlen($attempt->answers[$q['short']->id]));
        $this->assertSame([3, 4, false, true], [$attempt->score, $attempt->max_score, $attempt->passed, $attempt->needs_marking]);
        $this->get("$base/attempts/{$attempt->id}/result")->assertOk()->assertSee(__('account.result_waiting'))->assertSee(__('account.waiting_mark'))
            ->assertSee('review-part is-right', false)->assertDontSee('review-part is-wrong', false);

        // The teacher marks it right: 4 out of 4, passed.
        $this->actingAs($this->admin)->get(route('admin.results.index'))->assertSee('To mark');
        $this->get(route('admin.results.index', ['result' => 'to_mark']))->assertSee(e(route('admin.results.show', $attempt)), false);
        $this->get(route('admin.results.show', $attempt))->assertOk()->assertSee('To summarise data.')->assertSee('To summarise a large table quickly.')->assertSee('name="percent" value="100"', false);
        $this->post(route('admin.results.mark', $attempt), ['question_id' => $q['short']->id, 'percent' => 100])
            ->assertRedirect(route('admin.results.show', $attempt).'#q'.$q['short']->id)->assertSessionHas('status', 'Marked 100% (1 of 1 points). All marked: 100%, passed.');
        $attempt->refresh();
        $this->assertSame([4, true, false], [$attempt->score, $attempt->passed, $attempt->needs_marking]);
        // Only short answers are marked by hand.
        $this->post(route('admin.results.mark', $attempt), ['question_id' => $q['matching']->id, 'percent' => 0])->assertNotFound();
        $this->actingAs($student)->get("$base/attempts/{$attempt->id}/result")->assertDontSee(__('account.result_waiting'));

        // All wrong: shown part by part, the right answer beside the student's.
        $this->post("$base/start");
        $second = $this->quiz->attempts()->latest('id')->firstOrFail();
        $this->post("$base/attempts/{$second->id}", ['answers' => [
            $q['short']->id => 'No idea',
            $q['matching']->id => [$id('matching', 'Ctrl + C') => $id('matching', 'Paste', 'match_text'), $id('matching', 'Ctrl + V') => $id('matching', 'Copy', 'match_text'), $id('matching', 'Ctrl + Z') => $id('matching', 'Undo', 'match_text')],
            $q['ordering']->id => array_reverse($q['ordering']->options->pluck('id')->all()),
            $q['dragdrop']->id => [1 => $id('dragdrop', 'VLOOKUP'), 2 => $id('dragdrop', 'COUNT')],
        ]]);
        $this->actingAs($this->admin)->post(route('admin.results.mark', $second), ['question_id' => $q['short']->id, 'percent' => 0]);
        $second->refresh();
        $this->assertSame([0, false, false], [$second->score, $second->passed, $second->needs_marking]);
        $page = $this->actingAs($student)->get("$base/attempts/{$second->id}/result")->assertOk();
        $page->assertSee('No idea')->assertSee('VLOOKUP')->assertSee(__('account.place_n', ['n' => 3]));
        // Wrong: 2 pairs, 2 places (the middle one stayed put), 1 blank. Right: Ctrl + Z, the middle place, blank 2.
        $this->assertSame([5, 3], [substr_count($page->getContent(), 'class="review-part is-wrong"'), substr_count($page->getContent(), 'class="review-part is-right"')]);
        $page->assertSee('class="review-part review-written is-wrong"', false);

        // The teacher sees the same, part by part.
        $this->actingAs($this->admin)->get(route('admin.results.show', $second))->assertOk()->assertSee('class="review-parts"', false)->assertSee('Insert &gt; PivotTable', false);
    }

    public function test_answers_only_count_real_options_and_complete_shapes(): void
    {
        $q = $this->makeQuestions();
        $other = $this->course->quizzes()->create(['type' => 'quiz', 'title' => 'Other', 'slug' => 'other', 'is_published' => true]);
        $foreign = $this->makeQuestions($other)['ordering']->options->first()->id;
        $ids = $q['ordering']->options->pluck('id')->all();

        $this->assertSame([], $q['ordering']->normalizeAnswer([$ids[0], $ids[1]])); // one missing: not an order
        $this->assertSame([], $q['ordering']->normalizeAnswer([$ids[0], $ids[1], $foreign]));
        $this->assertSame([], $q['matching']->normalizeAnswer([$foreign => $ids[0]]));
        $this->assertSame([1 => $q['dragdrop']->options[0]->id], $q['dragdrop']->normalizeAnswer([1 => $q['dragdrop']->options[0]->id, 5 => $q['dragdrop']->options[1]->id]));
        // One word cannot fill two blanks.
        $word = $q['dragdrop']->options[0]->id;
        $this->assertSame([1 => $word], $q['dragdrop']->normalizeAnswer([1 => $word, 2 => $word]));
        $this->assertFalse($q['short']->isAnsweredCorrectly(''));
        $this->assertTrue(QuizQuestion::sameText("SUM\u{200B}", ' sum'));
    }

    public function test_practice_checks_each_type_part_by_part(): void
    {
        $this->quiz->update(['type' => 'practice']);
        $q = $this->makeQuestions();
        $student = User::factory()->create();
        Enrollment::grant($student, $this->course, null, 'manual');
        $check = fn ($question, $answer) => $this->actingAs($student)->postJson("/learn/{$this->course->slug}/quizzes/{$this->quiz->slug}/questions/{$question->id}/check", ['answer' => $answer])->assertOk();

        // A short answer has no instant check: the page says the teacher marks it.
        $start = $this->actingAs($student)->post("/learn/{$this->course->slug}/quizzes/{$this->quiz->slug}/start");
        $this->get($start->headers->get('Location'))->assertOk()->assertSee(__('account.teacher_marks'));

        $ids = $q['ordering']->options->pluck('id')->all();
        $check($q['ordering'], [$ids[1], $ids[0], $ids[2]])->assertJson(['correct' => false, 'parts' => [false, false, true], 'solution' => 'Select the data → Insert > PivotTable → Drag the fields']);

        $words = $q['dragdrop']->options->pluck('id', 'text');
        $check($q['dragdrop'], [1 => $words['SUM'], 2 => $words['VLOOKUP']])->assertJson(['correct' => false, 'parts' => ['1' => true, '2' => false]]);

        $pairs = $q['matching']->options;
        $check($q['matching'], $pairs->mapWithKeys(fn ($o) => [$o->id => $o->id])->all())->assertJson(['correct' => true]);
    }

    public function test_a_try_waiting_for_the_teacher_is_not_a_fail(): void
    {
        $this->actingAs($this->admin)->post(route('admin.quizzes.questions.store', $this->quiz), ['type' => 'short', 'prompt' => 'Explain VLOOKUP', 'points' => 1]);
        $question = $this->quiz->questions()->sole();
        $student = User::factory()->create(['name' => 'Sok Dara']);
        Enrollment::grant($student, $this->course, null, 'manual');
        $base = "/learn/{$this->course->slug}/quizzes/{$this->quiz->slug}";
        $this->actingAs($student)->post("$base/start");
        $attempt = $this->quiz->attempts()->sole();
        $this->post("$base/attempts/{$attempt->id}", ['answers' => [$question->id => 'It finds a value in a table.']]);

        $this->assertTrue($attempt->fresh()->needs_marking);
        $this->get($base)->assertSee(__('account.waiting_mark'))->assertDontSee(__('account.not_passed'));
        // The teacher's pages: a red count in the sidebar, and no "needs to retake" for this course yet.
        $book = \App\Http\Controllers\Admin\ResultController::gradebook($this->course);
        $this->assertNotSame('retake', $book['rows']->sole()['status']);
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertSee('<b class="nav-badge" title="Unread">1</b>', false);
        // An empty short answer needs no marking: it is simply wrong.
        $this->actingAs($student)->post("$base/start");
        $empty = $this->quiz->attempts()->latest('id')->first();
        $this->post("$base/attempts/{$empty->id}", ['answers' => [$question->id => '   ']]);
        $this->assertSame([false, 0], [$empty->fresh()->needs_marking, $empty->fresh()->score]);
    }

    public function test_a_teacher_gives_part_of_the_points_to_a_long_answer(): void
    {
        $this->actingAs($this->admin)->post(route('admin.quizzes.questions.store', $this->quiz), ['type' => 'long', 'prompt' => 'Explain how you would clean this table', 'points' => 2, 'options' => [['text' => 'ignored']]]);
        $this->post(route('admin.quizzes.questions.store', $this->quiz), ['type' => 'true_false', 'prompt' => 'Ctrl + Z undoes', 'points' => 1, 'tf_answer' => 'true']);
        [$long, $tf] = $this->quiz->questions()->orderBy('id')->get()->all();
        $this->assertSame(0, $long->options()->count());
        $this->quiz->update(['pass_percent' => 70]);

        $student = User::factory()->create();
        Enrollment::grant($student, $this->course, null, 'manual');
        $base = "/learn/{$this->course->slug}/quizzes/{$this->quiz->slug}";
        $this->actingAs($student)->post("$base/start");
        $attempt = $this->quiz->attempts()->sole();
        $this->get("$base/attempts/{$attempt->id}")->assertSee('maxlength="2000"', false)->assertSee(__('account.hint_long'));
        $this->post("$base/attempts/{$attempt->id}", ['answers' => [
            $long->id => str_repeat('a', 2100),
            $tf->id => [$tf->options()->where('is_correct', true)->value('id')],
        ]]);
        $attempt->refresh();
        $this->assertSame([2000, 1, true], [mb_strlen($attempt->answers[$long->id]), $attempt->score, $attempt->needs_marking]);

        // The mark line: 0% to 100%, points shown beside each step.
        $this->actingAs($this->admin)->get(route('admin.results.show', $attempt))->assertOk()
            ->assertSee('name="percent" value="70"', false)->assertSee('1.4 pt')->assertDontSee('name="right"', false);
        $this->post(route('admin.results.mark', $attempt), ['question_id' => $long->id, 'percent' => 35])->assertSessionHasErrors('percent');

        // 70% of 2 points = 1.4; with the true/false point: 2.4 of 3 = 80%, passed (pass mark 70%).
        $this->post(route('admin.results.mark', $attempt), ['question_id' => $long->id, 'percent' => 70])
            ->assertSessionHas('status', 'Marked 70% (1.4 of 2 points). All marked: 80%, passed.');
        $attempt->refresh();
        $this->assertSame([2.4, 80, true, false], [$attempt->score, $attempt->percent, $attempt->passed, $attempt->needs_marking]);
        $this->get(route('admin.results.show', $attempt))->assertSee('70% · 1.4 / 2 pt');
        $this->actingAs($student)->get("$base/attempts/{$attempt->id}/result")->assertOk()
            ->assertSee(__('account.marked_points', ['points' => '1.4', 'max' => 2, 'percent' => 70]))->assertSee('is-partial', false);

        // Lower the mark: 30% = 0.6, 1.6 of 3 = 53%, not passed.
        $this->actingAs($this->admin)->post(route('admin.results.mark', $attempt), ['question_id' => $long->id, 'percent' => 30]);
        $attempt->refresh();
        $this->assertSame([1.6, 53, false], [$attempt->score, $attempt->percent, $attempt->passed]);

        // A mark saved as true / false before the 10% steps still counts as 100% / 0%.
        $attempt->forceFill(['marks' => [$long->id => true]])->save();
        $this->assertSame([100, 3], [$attempt->markPercent($long), $attempt->rescore()->score]);
    }

    public function test_copying_a_course_keeps_pairs_and_blanks(): void
    {
        $this->makeQuestions();
        $copy = app(CourseDuplicator::class)->duplicate($this->course);
        $questions = $copy->quizzes()->first()->questions()->with('options')->get()->keyBy('type');

        $this->assertSame(['Copy', 'Paste', 'Undo'], $questions['matching']->options->pluck('match_text')->all());
        $this->assertSame([1, 2, null], $questions['dragdrop']->options->pluck('slot')->all());
    }
}
