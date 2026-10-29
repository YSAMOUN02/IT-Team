<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\Enrollment;
use App\Models\Inquiry;
use App\Models\Order;
use App\Models\Page;
use App\Models\Partner;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\RetakeRequest;
use App\Models\Setting;
use App\Models\Subscriber;
use App\Models\Testimonial;
use App\Models\User;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Every admin page opens; saving any form without changing anything changes nothing (a field the
 * form forgets to send back would be wiped); and every picture field takes a big phone photo.
 */
class AdminFormsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $student;

    private Course $course;

    private CourseLesson $lesson;

    private Quiz $quiz;

    private QuizQuestion $question;

    private Article $tutorial;

    private Article $news;

    private Testimonial $testimonial;

    private Partner $partner;

    private Order $order;

    private int $attemptId;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('local');

        $this->admin = User::factory()->create(['name' => 'Office Admin', 'username' => 'office']);
        $this->admin->forceFill(['role' => 'admin'])->save();
        $this->student = User::factory()->create(['name' => 'Sok Dara', 'phone' => '012 345 678']);

        Page::create(['slug' => 'home', 'template' => 'home', 'title' => 'TEAMIT Cambodia', 'is_published' => true, 'sections' => [
            'hero' => ['title' => 'Learn Excel', 'text' => "Line one\nLine two", 'button_label' => 'Courses', 'button_url' => '/courses'],
            'intro' => ['points' => ['One', 'Two']],
            'founder' => ['title' => 'Hello', 'text' => '<p>Bio</p>'],
        ]]);

        $this->course = Course::create(['title' => 'Pivot Table', 'slug' => 'pivot-table', 'summary' => 'Summary', 'duration' => '6 hours',
            'features' => ['Feature A', 'Feature B'], 'body' => '<p>About the course</p>', 'is_published' => true, 'certificate_enabled' => true,
            'currency' => 'USD', 'sort_order' => 3, 'cta_label' => 'Buy now', 'cta_url' => '/contact']);
        $this->course->plans()->create(['days' => 90, 'price' => 30, 'discount_percent' => 10, 'position' => 1]);
        $this->course->plans()->create(['days' => null, 'price' => 99, 'position' => 2]);
        $module = $this->course->modules()->create(['title' => 'Module 1', 'description' => 'Start here', 'position' => 1]);
        $this->lesson = $this->course->lessons()->create(['course_module_id' => $module->id, 'title' => 'First lesson', 'slug' => 'first-lesson',
            'duration' => '5:00', 'video_url' => 'https://www.youtube.com/watch?v=abc123', 'body' => '<p>Notes</p>', 'position' => 1, 'is_published' => true, 'is_preview' => true]);
        $this->quiz = $this->course->quizzes()->create(['course_module_id' => $module->id, 'type' => 'final', 'title' => 'Final exam', 'slug' => 'final-exam',
            'description' => 'The last test', 'position' => 2, 'pass_percent' => 75, 'time_limit' => 30, 'max_attempts' => 3, 'shuffle_questions' => true, 'show_answers' => true, 'is_published' => true]);
        $this->question = $this->quiz->questions()->create(['type' => 'single', 'prompt' => 'What is 2 + 2?', 'explanation' => 'Count.', 'points' => 2, 'position' => 1]);
        foreach (['3' => false, '4' => true, '5' => false] as $text => $correct) {
            $this->question->options()->create(['text' => (string) $text, 'is_correct' => $correct, 'position' => $text]);
        }

        $this->tutorial = Article::create(['type' => 'tutorial', 'title' => 'VLOOKUP', 'slug' => 'vlookup', 'excerpt' => 'Short', 'body' => '<p>Body</p>', 'published_at' => now()->subDay()->startOfMinute(), 'is_published' => true]);
        $this->news = Article::create(['type' => 'news', 'title' => 'New intake', 'slug' => 'new-intake', 'excerpt' => 'News', 'body' => '<p>News body</p>', 'published_at' => now()->subDays(2)->startOfMinute(), 'is_published' => true]);
        $this->testimonial = Testimonial::create(['name' => 'Chan Sreyneang', 'role' => 'Accountant', 'quote' => 'Great course.', 'rating' => 5, 'is_published' => true, 'sort_order' => 1]);
        $this->partner = Partner::create(['name' => 'ABA Bank', 'url' => 'https://www.ababank.com', 'is_published' => true, 'sort_order' => 2]);
        Setting::put(['site_name' => 'TEAMIT Cambodia', 'logo_text' => 'TEAMIT', 'phone' => '012 000 000', 'student_device_limit' => '2']);

        Enrollment::grant($this->student, $this->course, 90);
        $this->order = Order::create(['number' => Order::newNumber(), 'user_id' => $this->student->id, 'course_id' => $this->course->id, 'course_title' => 'Pivot Table',
            'amount' => 27, 'currency' => 'USD', 'access_days' => 90, 'status' => 'paid', 'gateway' => 'test', 'buyer_name' => 'Sok Dara', 'buyer_email' => $this->student->email, 'paid_at' => now()]);
        Inquiry::create(['name' => 'Lim Pisey', 'email' => 'pisey@example.com', 'subject' => 'Training', 'message' => 'Hello']);
        Subscriber::create(['email' => 'reader@example.com']);
        $attempt = $this->quiz->attempts()->create(['user_id' => $this->student->id, 'started_at' => now()->subMinutes(20), 'submitted_at' => now(), 'score' => 0, 'max_score' => 2, 'percent' => 0, 'passed' => false]);
        RetakeRequest::create(['quiz_id' => $this->quiz->id, 'user_id' => $this->student->id, 'message' => 'Please']);
        $this->attemptId = $attempt->id;
    }

    /** What a browser sends for the form posting to $action (file inputs, buttons and <template> rows left out). */
    private function formData(string $html, string $action): array
    {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
        $xpath = new DOMXPath($dom);
        // The page's main form when several post to the same address (Save and Delete do); never a delete form.
        $form = null;
        foreach ($xpath->query('//form') as $candidate) {
            if (! $candidate instanceof DOMElement || $candidate->getAttribute('action') !== $action || $xpath->query('.//input[@name="_method"][@value="DELETE"]', $candidate)->length) {
                continue;
            }
            $form ??= $candidate;
            if ($candidate->hasAttribute('data-main-form')) {
                $form = $candidate;
                break;
            }
        }
        $this->assertNotNull($form, "No form posting to $action");

        $pairs = [];
        foreach ($xpath->query('.//*[self::input or self::textarea or self::select][not(ancestor::template)]', $form) as $el) {
            $name = $el instanceof DOMElement ? $el->getAttribute('name') : '';
            if ($name === '' || $el->hasAttribute('disabled')) {
                continue;
            }
            if ($el->nodeName === 'input') {
                $type = strtolower($el->getAttribute('type') ?: 'text');
                if (in_array($type, ['file', 'submit', 'button', 'image', 'reset'], true) || (in_array($type, ['checkbox', 'radio'], true) && ! $el->hasAttribute('checked'))) {
                    continue;
                }
                $value = $el->hasAttribute('value') ? $el->getAttribute('value') : (in_array($type, ['checkbox', 'radio'], true) ? 'on' : '');
            } elseif ($el->nodeName === 'textarea') {
                $value = preg_replace('/^\r?\n/', '', $el->textContent); // a browser drops one leading newline
            } else {
                $option = $xpath->query('.//option[@selected]', $el)->item(0) ?? $xpath->query('.//option', $el)->item(0);
                if (! $option instanceof DOMElement) {
                    continue;
                }
                $value = $option->hasAttribute('value') ? $option->getAttribute('value') : $option->textContent;
            }
            $pairs[] = urlencode($name).'='.urlencode($value);
        }
        parse_str(implode('&', $pairs), $data);

        return $data;
    }

    /** Stored values, ignoring when it was saved, JSON key order and empty JSON entries (a missing key and null mean the same). */
    private function snapshot(Model $model): array
    {
        $attributes = $model->fresh()->getAttributes();
        unset($attributes['updated_at']);
        $clean = function ($value) use (&$clean) {
            return is_array($value) ? array_filter(array_map($clean, $value), fn ($v) => $v !== null && $v !== '' && $v !== []) : $value;
        };

        return array_map(fn ($value) => is_string($value) && json_validate($value) && in_array($value[0] ?? '', ['{', '['], true) ? $clean(json_decode($value, true)) : $value, $attributes);
    }

    public function test_every_admin_page_opens(): void
    {
        $c = $this->course->id;
        $urls = ['/admin', '/admin/visitors', '/admin/inquiries', '/admin/inquiries/'.Inquiry::value('id'), '/admin/subscribers',
            '/admin/pages', '/admin/pages/'.Page::value('id').'/edit',
            '/admin/courses', '/admin/courses/create', "/admin/courses/$c/edit", "/admin/courses/$c/curriculum", "/admin/courses/$c/students", "/admin/courses/$c/results",
            "/admin/courses/$c/lessons/create", "/admin/lessons/{$this->lesson->id}/edit", "/admin/courses/$c/quizzes/create", "/admin/quizzes/{$this->quiz->id}/edit",
            "/admin/quizzes/{$this->quiz->id}/questions/create", "/admin/questions/{$this->question->id}/edit",
            '/admin/articles?type=tutorial', '/admin/articles?type=news', '/admin/articles/create?type=tutorial', "/admin/articles/{$this->tutorial->id}/edit", "/admin/articles/{$this->news->id}/edit",
            '/admin/orders', "/admin/orders/{$this->order->number}", '/admin/students', "/admin/students/{$this->student->id}",
            '/admin/results', "/admin/results/{$this->attemptId}", '/admin/retake-requests',
            '/admin/testimonials', '/admin/testimonials/create', "/admin/testimonials/{$this->testimonial->id}/edit",
            '/admin/partners', '/admin/partners/create', "/admin/partners/{$this->partner->id}/edit",
            '/admin/users', '/admin/users/create', "/admin/users/{$this->admin->id}/edit", '/admin/ads', '/admin/settings', '/admin/search?q=pivot'];

        $this->actingAs($this->admin);
        foreach ($urls as $url) {
            $response = $this->get($url);
            $this->assertSame(200, $response->status(), "$url answered {$response->status()}");
            $this->assertStringNotContainsString('Undefined', $response->getContent(), $url);
        }
        $this->get("/admin/courses/$c/results.csv")->assertOk();
        $this->get('/admin/subscribers/export')->assertOk();
    }

    public function test_saving_a_form_without_changes_changes_nothing(): void
    {
        $forms = [
            'page' => [Page::first(), '/admin/pages/'.Page::value('id').'/edit', route('admin.pages.update', Page::first())],
            'course' => [$this->course, "/admin/courses/{$this->course->id}/edit", route('admin.courses.update', $this->course)],
            'lesson' => [$this->lesson, "/admin/lessons/{$this->lesson->id}/edit", route('admin.lessons.update', $this->lesson)],
            'quiz' => [$this->quiz, "/admin/quizzes/{$this->quiz->id}/edit", route('admin.quizzes.update', $this->quiz)],
            'question' => [$this->question, "/admin/questions/{$this->question->id}/edit", route('admin.questions.update', $this->question)],
            'tutorial' => [$this->tutorial, "/admin/articles/{$this->tutorial->id}/edit", route('admin.articles.update', $this->tutorial)],
            'news' => [$this->news, "/admin/articles/{$this->news->id}/edit", route('admin.articles.update', $this->news)],
            'testimonial' => [$this->testimonial, "/admin/testimonials/{$this->testimonial->id}/edit", route('admin.testimonials.update', $this->testimonial)],
            'partner' => [$this->partner, "/admin/partners/{$this->partner->id}/edit", route('admin.partners.update', $this->partner)],
            'user' => [$this->admin, "/admin/users/{$this->admin->id}/edit", route('admin.users.update', $this->admin)],
        ];

        $this->actingAs($this->admin);
        foreach ($forms as $label => [$model, $edit, $action]) {
            $before = $this->snapshot($model);
            $options = $model instanceof QuizQuestion ? $model->options()->get(['id', 'text', 'is_correct'])->toArray() : null;
            $plans = $model instanceof Course ? $model->plans()->get(['days', 'price', 'discount_percent', 'label'])->toArray() : null;

            $data = $this->formData($this->get($edit)->assertOk()->getContent(), $action);
            $this->post($action, $data)->assertRedirect()->assertSessionHasNoErrors();

            $this->assertEquals($before, $this->snapshot($model), "Saving the $label form unchanged changed it");
            if ($options !== null) {
                $this->assertEquals($options, $model->options()->get(['id', 'text', 'is_correct'])->toArray(), 'question options changed (or got new ids)');
            }
            if ($plans !== null) {
                $this->assertEquals($plans, $model->plans()->get(['days', 'price', 'discount_percent', 'label'])->toArray(), 'course plans changed');
            }
        }

        // Settings and ads keep their values in the settings table.
        foreach (['/admin/settings' => route('admin.settings.update'), '/admin/ads' => route('admin.ads.update')] as $edit => $action) {
            $before = Setting::pluck('value', 'key')->filter(fn ($v) => $v !== null)->all();
            $this->post($action, $this->formData($this->get($edit)->getContent(), $action))->assertRedirect()->assertSessionHasNoErrors();
            $after = Setting::pluck('value', 'key')->filter(fn ($v) => $v !== null)->all();
            $this->assertEquals(array_intersect_key($after, $before), $before, "Saving $edit unchanged changed a setting");
        }
    }

    public function test_every_picture_field_takes_a_big_phone_photo_and_says_which_field_is_wrong(): void
    {
        // A 3000 × 2000 JPEG, reported as 12 MB (over the old 8 MB limit).
        $photo = fn () => UploadedFile::fake()->image('IMG_0042.jpg', 3000, 2000)->size(12 * 1024);
        $fields = [
            'course cover' => [route('admin.courses.update', $this->course), "/admin/courses/{$this->course->id}/edit", 'image', 'Cover image', fn () => $this->course->fresh()->image],
            'course icon' => [route('admin.courses.update', $this->course), "/admin/courses/{$this->course->id}/edit", 'icon', 'Card icon', fn () => $this->course->fresh()->icon],
            'article' => [route('admin.articles.update', $this->tutorial), "/admin/articles/{$this->tutorial->id}/edit", 'image', 'Featured image', fn () => $this->tutorial->fresh()->image],
            'testimonial' => [route('admin.testimonials.update', $this->testimonial), "/admin/testimonials/{$this->testimonial->id}/edit", 'photo', 'Photo', fn () => $this->testimonial->fresh()->photo],
            'partner' => [route('admin.partners.update', $this->partner), "/admin/partners/{$this->partner->id}/edit", 'logo', 'Logo', fn () => $this->partner->fresh()->logo],
            'question' => [route('admin.questions.update', $this->question), "/admin/questions/{$this->question->id}/edit", 'image', 'Picture', fn () => $this->question->fresh()->image],
            'settings logo' => [route('admin.settings.update'), '/admin/settings', 'uploads.logo', 'Logo image (optional, replaces the logo text)', fn () => setting('logo')],
        ];

        $this->actingAs($this->admin);
        foreach ($fields as $label => [$action, $edit, $field, $name, $stored]) {
            $data = $this->formData($this->get($edit)->getContent(), $action);
            $this->get($edit)->assertSee('data-max-kb="20480"', false)->assertSee('up to 20 MB');

            data_set($data, $field, $photo());
            $this->post($action, $data)->assertSessionHasNoErrors();
            $path = $stored();
            $this->assertNotNull($path, "$label: nothing stored");
            $this->assertSame([2400, 1600], array_slice(getimagesizefromstring(Storage::disk('public')->get($path)), 0, 2), "$label: not scaled down");

            data_set($data, $field, UploadedFile::fake()->create('IMG_0042.heic', 500, 'image/heic'));
            $types = $field === 'uploads.logo' ? 'JPG, PNG, GIF, WebP, SVG or ICO' : 'JPG, PNG, GIF, WebP or SVG';
            $this->from($edit)->post($action, $data)->assertSessionHasErrors([$field => "$name: use a $types picture. iPhone HEIC photos need to be saved as JPG first."]);
        }
    }

    public function test_a_picture_is_saved_as_what_it_really_is_whatever_its_name(): void
    {
        // A real PNG called "cover.html" (fake test files guess their type from the name, so use a real file).
        $path = tempnam(sys_get_temp_dir(), 'png');
        imagepng(imagecreatetruecolor(40, 30), $path);
        $edit = "/admin/courses/{$this->course->id}/edit";
        $action = route('admin.courses.update', $this->course);

        $this->actingAs($this->admin);
        $data = $this->formData($this->get($edit)->getContent(), $action);
        $data['image'] = new UploadedFile($path, 'cover.html', null, null, true);
        $this->post($action, $data)->assertSessionHasNoErrors();

        $this->assertMatchesRegularExpression('#^courses/\d{4}/\d{2}/cover-[a-z0-9]{6}\.png$#', $this->course->fresh()->image);
    }

    public function test_lesson_files_show_the_real_limit_of_this_server(): void
    {
        $video = \App\Support\Media::uploadMaxKb(\App\Http\Controllers\Admin\LessonController::VIDEO_MAX_KB);
        $files = \App\Support\Media::uploadMaxKb(\App\Http\Controllers\Admin\LessonController::FILE_MAX_KB);

        $this->actingAs($this->admin)->get("/admin/lessons/{$this->lesson->id}/edit")
            ->assertSee('name="video" accept="video/mp4,video/webm,video/quicktime" data-max-kb="'.$video.'"', false)
            ->assertSee('up to '.\App\Support\Media::sizeLabel($video).' on this server')
            ->assertSee('data-max-kb="'.$files.'"', false)
            ->assertSee('<meta name="post-max-bytes"', false);
    }

    public function test_every_new_form_adds_a_record_and_delete_removes_it(): void
    {
        $this->actingAs($this->admin);
        $c = $this->course->id;
        // [create page, form action, fields to fill, the record it makes]
        $forms = [
            'course' => ['/admin/courses/create', route('admin.courses.store'), ['title' => 'Power Query'], fn () => Course::where('title', 'Power Query')->first()],
            'module' => ["/admin/courses/$c/curriculum", route('admin.modules.store', $this->course), ['title' => 'Module 2'], fn () => $this->course->modules()->where('title', 'Module 2')->first()],
            'lesson' => ["/admin/courses/$c/lessons/create", route('admin.courses.lessons.store', $this->course),
                ['title' => 'Second lesson', 'practice' => UploadedFile::fake()->create('practice.xlsx', 30, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')],
                fn () => CourseLesson::where('title', 'Second lesson')->first()],
            'quiz' => ["/admin/courses/$c/quizzes/create?type=quiz", route('admin.courses.quizzes.store', $this->course), ['title' => 'Module 2 quiz'], fn () => Quiz::where('title', 'Module 2 quiz')->first()],
            'question' => ["/admin/quizzes/{$this->quiz->id}/questions/create", route('admin.quizzes.questions.store', $this->quiz),
                ['prompt' => 'Which is a fruit?', 'options' => [['text' => 'Mango'], ['text' => 'Chair']], 'correct' => ['0']],
                fn () => QuizQuestion::where('prompt', 'Which is a fruit?')->first()],
            'news' => ['/admin/articles/create?type=news', route('admin.articles.store'), ['title' => 'Workshop on Saturday'], fn () => Article::where('title', 'Workshop on Saturday')->first()],
            'testimonial' => ['/admin/testimonials/create', route('admin.testimonials.store'), ['name' => 'Kim Visal', 'quote' => 'Very clear lessons.'], fn () => Testimonial::where('name', 'Kim Visal')->first()],
            'partner' => ['/admin/partners/create', route('admin.partners.store'), ['name' => 'Wing Bank'], fn () => Partner::where('name', 'Wing Bank')->first()],
        ];

        $made = [];
        foreach ($forms as $label => [$page, $action, $fields, $find]) {
            $data = $this->formData($this->get($page)->assertOk()->getContent(), $action);
            foreach ($fields as $key => $value) {
                $data[$key] = $value;
            }
            $this->post($action, $data)->assertRedirect()->assertSessionHasNoErrors();
            $this->assertNotNull($made[$label] = $find(), "the new $label form did not add one");
        }
        $this->assertSame(['Mango', 'Chair'], $made['question']->options()->orderBy('position')->pluck('text')->all());
        $this->assertTrue((bool) $made['question']->options()->where('text', 'Mango')->value('is_correct'));
        Storage::disk('local')->assertExists($made['lesson']->attachment_path);
        $this->assertSame('practice.xlsx', $made['lesson']->attachment_name);

        $deletes = [
            'lesson' => route('admin.lessons.destroy', $made['lesson']), 'question' => route('admin.questions.destroy', $made['question']),
            'quiz' => route('admin.quizzes.destroy', $made['quiz']), 'module' => route('admin.modules.destroy', $made['module']),
            'news' => route('admin.articles.destroy', $made['news']), 'testimonial' => route('admin.testimonials.destroy', $made['testimonial']),
            'partner' => route('admin.partners.destroy', $made['partner']), 'course' => route('admin.courses.destroy', $made['course']),
        ];
        foreach ($deletes as $label => $url) {
            $this->delete($url)->assertRedirect()->assertSessionHasNoErrors();
            $this->assertNull($made[$label]->fresh(), "$label was not deleted");
        }
        Storage::disk('local')->assertMissing($made['lesson']->attachment_path);

        $this->delete('/admin/inquiries/'.Inquiry::value('id'))->assertRedirect();
        $this->delete('/admin/subscribers/'.Subscriber::value('id'))->assertRedirect();
        $this->assertSame([0, 0], [Inquiry::count(), Subscriber::count()]);
    }

    public function test_editing_a_question_keeps_its_answers_so_students_past_answers_still_show(): void
    {
        [$three, $four, $five] = $this->question->options()->orderBy('position')->get()->all();
        $attempt = $this->quiz->attempts()->find($this->attemptId);
        $attempt->grade([$this->question->id => [$four->id]]); // the student picked "4", the right answer
        $edit = "/admin/questions/{$this->question->id}/edit";
        $action = route('admin.questions.update', $this->question);
        $this->actingAs($this->admin);

        // Fix a typo in the question and in answer "5", drop answer "3", add answer "6".
        $data = $this->formData($this->get($edit)->getContent(), $action);
        $data['prompt'] = 'What is 2 + 2 ?';
        $data['options'][$five->id]['text'] = 'five';
        unset($data['options'][$three->id]);
        $data['options'][$five->id + 100] = ['text' => '6'];
        $this->post($action, $data)->assertSessionHasNoErrors();

        $options = $this->question->options()->orderBy('position')->get();
        $this->assertSame(['4', 'five', '6'], $options->pluck('text')->all());
        $this->assertSame([$four->id, $five->id], $options->take(2)->pluck('id')->all(), 'kept answers keep their ids');
        $this->assertNull($three->fresh());
        $this->assertTrue((bool) $four->fresh()->is_correct);

        // The student's earlier answer still shows on their result.
        $this->get("/admin/results/{$attempt->id}")->assertSee("Student's answer · correct", false)->assertSee('Correct</div><div class="kpi-value">1<', false);
    }

    public function test_a_true_false_question_keeps_its_wording_and_answer_ids(): void
    {
        $question = $this->quiz->questions()->create(['type' => 'true_false', 'prompt' => 'Excel is a spreadsheet.', 'points' => 1, 'position' => 2]);
        $yes = $question->options()->create(['text' => 'ពិត', 'is_correct' => true, 'position' => 1]);
        $no = $question->options()->create(['text' => 'មិនពិត', 'is_correct' => false, 'position' => 2]);
        $edit = "/admin/questions/{$question->id}/edit";
        $action = route('admin.questions.update', $question);
        $this->actingAs($this->admin);
        app()->setLocale('en'); // the admin's language must not rename the answers

        $data = $this->formData($this->get($edit)->getContent(), $action);
        $data['tf_answer'] = 'false';
        $this->post($action, $data)->assertSessionHasNoErrors();

        $this->assertSame([[$yes->id, 'ពិត', false], [$no->id, 'មិនពិត', true]],
            $question->options()->orderBy('position')->get()->map(fn ($o) => [$o->id, $o->text, (bool) $o->is_correct])->all());
    }

    public function test_a_save_bigger_than_the_server_takes_gets_a_page_that_says_so(): void
    {
        $this->actingAs($this->admin)
            ->call('POST', route('admin.lessons.update', $this->lesson), ['_method' => 'PUT'], [], [], ['CONTENT_LENGTH' => 2 * 1024 ** 3])
            ->assertStatus(413)->assertSee('The files were too big to send')->assertSee('Nothing was saved.');
    }
}
