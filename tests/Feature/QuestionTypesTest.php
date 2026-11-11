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

        $post(['type' => 'short', 'prompt' => 'Which function adds up a range?', 'options' => [['text' => 'SUM'], ['text' => '=SUM()'], ['text' => '']]]);
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

        $this->assertSame(['SUM', '=SUM()'], $q['short']->options->pluck('text')->all());
        $this->assertSame(['Copy', 'Paste', 'Undo'], $q['matching']->options->pluck('match_text')->all());
        $this->assertSame(['Select the data', 'Insert > PivotTable', 'Drag the fields'], $q['ordering']->options->pluck('text')->all());
        $this->assertSame([[1, true], [2, true], [null, false]], $q['dragdrop']->options->map(fn ($o) => [$o->slot, $o->is_correct])->all());
        $this->assertSame(2, $q['dragdrop']->blankCount());

        $this->get(route('admin.questions.edit', $q['matching']))->assertOk()->assertSee('value="Paste"', false);
        $this->get(route('admin.quizzes.questions.create', [$this->quiz, 'type' => 'dragdrop']))->assertOk()->assertSee('three underscores');

        $fails = [
            ['short', 'Q', [['text' => '']], 'Add at least one accepted answer.'],
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

        // All right: capitals and spaces in the short answer do not matter, the extra word is not used.
        $this->post("$base/attempts/{$attempt->id}", ['answers' => [
            $q['short']->id => '  sum ',
            $q['matching']->id => [$id('matching', 'Ctrl + C') => $id('matching', 'Copy', 'match_text'), $id('matching', 'Ctrl + V') => $id('matching', 'Paste', 'match_text'), $id('matching', 'Ctrl + Z') => $id('matching', 'Undo', 'match_text')],
            $q['ordering']->id => $q['ordering']->options->pluck('id')->all(),
            $q['dragdrop']->id => [1 => $id('dragdrop', 'SUM'), 2 => $id('dragdrop', 'COUNT')],
        ]])->assertRedirect();
        $attempt->refresh();
        $this->assertSame([4, 4, true], [$attempt->score, $attempt->max_score, $attempt->passed]);
        $this->get("$base/attempts/{$attempt->id}/result")->assertOk()->assertSee('review-part is-right', false)->assertDontSee('review-part is-wrong', false);

        // All wrong: shown part by part, the right answer beside the student's.
        $this->post("$base/start");
        $second = $this->quiz->attempts()->latest('id')->firstOrFail();
        $this->post("$base/attempts/{$second->id}", ['answers' => [
            $q['short']->id => 'ADD',
            $q['matching']->id => [$id('matching', 'Ctrl + C') => $id('matching', 'Paste', 'match_text'), $id('matching', 'Ctrl + V') => $id('matching', 'Copy', 'match_text'), $id('matching', 'Ctrl + Z') => $id('matching', 'Undo', 'match_text')],
            $q['ordering']->id => array_reverse($q['ordering']->options->pluck('id')->all()),
            $q['dragdrop']->id => [1 => $id('dragdrop', 'VLOOKUP'), 2 => $id('dragdrop', 'COUNT')],
        ]]);
        $second->refresh();
        $this->assertSame([0, false], [$second->score, $second->passed]);
        $page = $this->get("$base/attempts/{$second->id}/result")->assertOk();
        $page->assertSee('ADD')->assertSee('SUM / =SUM()')->assertSee('VLOOKUP')->assertSee(__('account.place_n', ['n' => 3]));
        // Wrong: the short answer, 2 pairs, 2 places (the middle one stayed put), 1 blank. Right: Ctrl + Z, the middle place, blank 2.
        $this->assertSame([6, 3], [substr_count($page->getContent(), 'class="review-part is-wrong"'), substr_count($page->getContent(), 'class="review-part is-right"')]);

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

        $check($q['short'], 'Sum')->assertJson(['correct' => true, 'parts' => ['text' => true]]);
        $check($q['short'], 'ADD')->assertJson(['correct' => false, 'solution' => 'SUM / =SUM()']);

        $ids = $q['ordering']->options->pluck('id')->all();
        $check($q['ordering'], [$ids[1], $ids[0], $ids[2]])->assertJson(['correct' => false, 'parts' => [false, false, true], 'solution' => 'Select the data → Insert > PivotTable → Drag the fields']);

        $words = $q['dragdrop']->options->pluck('id', 'text');
        $check($q['dragdrop'], [1 => $words['SUM'], 2 => $words['VLOOKUP']])->assertJson(['correct' => false, 'parts' => ['1' => true, '2' => false]]);

        $pairs = $q['matching']->options;
        $check($q['matching'], $pairs->mapWithKeys(fn ($o) => [$o->id => $o->id])->all())->assertJson(['correct' => true]);
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
