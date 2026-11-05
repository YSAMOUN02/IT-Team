<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Quiz;
use App\Models\User;
use App\Support\NativeNumbersStatement;
use Illuminate\Database\Connection;
use Illuminate\Database\MariaDbConnection;
use Illuminate\Database\MySqlConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PDO;
use PDOStatement;
use Tests\Support\SqliteNativeNumbersStatement;
use Tests\TestCase;

/**
 * Some shared hosts run a MySQL driver that returns every number as text ("12", not 12). Comparing
 * ids with === then fails, and pages answer 404 on the live server only. Every kind of test, invoices
 * and orders must open there: with the site's conversion, and even if the conversion were not active.
 */
class NumbersAsTextDriverTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        // The in-memory database is kept between tests: leave it as it was found.
        if (! env('TEXT_NUMBERS')) {
            $pdo = DB::connection()->getPdo();
            $pdo->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, false);
            $pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, [PDOStatement::class]);
        }

        parent::tearDown();
    }

    public static function drivers(): array
    {
        return ['text numbers, converted back' => [true], 'text numbers, no conversion' => [false]];
    }

    /** @dataProvider drivers */
    public function test_taking_every_kind_of_test_works(bool $converted): void
    {
        $course = Course::create(['title' => 'Basic Excel', 'slug' => 'basic-excel', 'is_published' => true]);
        $student = User::factory()->create();
        Enrollment::grant($student, $course, null, 'manual');

        $tests = [];
        foreach (array_keys(Quiz::TYPES) as $i => $type) {
            $quiz = $course->quizzes()->create(['type' => $type, 'title' => "Test $type", 'slug' => "test-$type", 'position' => $i + 1, 'pass_percent' => 50, 'is_published' => true]);
            $question = $quiz->questions()->create(['type' => 'single', 'prompt' => 'Pick one', 'points' => 1, 'position' => 1]);
            $right = $question->options()->create(['text' => 'Right', 'is_correct' => true]);
            $question->options()->create(['text' => 'Wrong', 'is_correct' => false]);
            $tests[$type] = [$quiz, $question, $right];
        }
        $this->assertContains('midterm', array_keys($tests));

        $this->useTextNumbers($converted);
        $this->actingAs($student);

        foreach ($tests as $type => [$quiz, $question, $right]) {
            $base = "/learn/{$course->slug}/quizzes/{$quiz->slug}";
            $this->get($base)->assertOk();
            $this->post("$base/start")->assertRedirect();
            $attempt = $quiz->attempts()->latest('id')->firstOrFail();
            $this->get("$base/attempts/{$attempt->id}")->assertOk();
            if ($type === 'practice') {
                $this->postJson("$base/questions/{$question->id}/check", ['chosen' => [$right->id]])->assertOk()->assertJsonPath('correct', true);
            }
            $this->post("$base/attempts/{$attempt->id}", ['answers' => [$question->id => [$right->id]]])->assertRedirect("$base/attempts/{$attempt->id}/result");
            $this->get("$base/attempts/{$attempt->id}/result")->assertOk();
            $this->assertEquals(1, $attempt->fresh()->passed, "$type should be passed");
        }

        // Someone else's try stays closed.
        $this->actingAs(User::factory()->create());
        Enrollment::grant(auth()->user(), $course, null, 'manual');
        $this->get("/learn/{$course->slug}/quizzes/{$tests['quiz'][0]->slug}/attempts/{$tests['quiz'][0]->attempts()->value('id')}")->assertNotFound();
    }

    /** @dataProvider drivers */
    public function test_a_test_with_no_questions_has_no_start_button(bool $converted): void
    {
        $course = Course::create(['title' => 'Basic Excel', 'slug' => 'basic-excel', 'is_published' => true]);
        $quiz = $course->quizzes()->create(['type' => 'midterm', 'title' => 'Empty', 'slug' => 'empty', 'position' => 1, 'pass_percent' => 50, 'is_published' => true]);
        $student = User::factory()->create();
        Enrollment::grant($student, $course, null, 'manual');

        $this->useTextNumbers($converted);
        $this->actingAs($student)->get("/learn/{$course->slug}/quizzes/{$quiz->slug}")->assertOk()->assertDontSee('/start', false);
    }

    /** @dataProvider drivers */
    public function test_students_open_their_own_invoices_and_orders(bool $converted): void
    {
        $course = Course::create(['title' => 'Basic Excel', 'slug' => 'basic-excel', 'is_published' => true]);
        $student = User::factory()->create();
        $other = User::factory()->create();
        $order = Order::create(['user_id' => $student->id, 'course_id' => $course->id, 'number' => 'T1', 'amount' => 10, 'currency' => 'USD', 'status' => 'paid', 'course_title' => $course->title, 'buyer_name' => $student->name, 'buyer_email' => $student->email, 'paid_at' => now()]);
        $invoice = Invoice::create(['user_id' => $student->id, 'course_id' => $course->id, 'number' => 'INV-1', 'course_title' => $course->title, 'amount' => 10, 'currency' => 'USD', 'status' => 'paid', 'student_name' => $student->name, 'student_email' => $student->email, 'payment_method' => 'cash', 'issued_at' => now()]);

        $this->useTextNumbers($converted);
        $this->actingAs($student)->get(route('account.invoices.show', $invoice))->assertOk();
        $this->get(route('orders.show', $order))->assertOk();

        $this->actingAs($other)->get(route('account.invoices.show', $invoice))->assertNotFound();
        $this->get(route('orders.show', $order))->assertNotFound();
    }

    public function test_the_conversion_gives_back_numbers_and_leaves_text_alone(): void
    {
        $user = User::factory()->create(['phone' => '012345678', 'name' => '2024']);
        $message = $user->chatMessages()->create(['from_student' => true, 'body' => '42', 'sender_id' => null]);

        $this->useTextNumbers(true);
        $row = DB::table('users')->where('id', $user->id)->first();
        $this->assertSame([$user->id, '012345678', '2024'], [$row->id, $row->phone, $row->name]);

        $chat = DB::table('chat_messages')->first();
        $this->assertSame([$message->id, $user->id, null, 1, '42'], [$chat->id, $chat->student_id, $chat->sender_id, $chat->from_student, $chat->body]);
        $this->assertSame(1, DB::table('chat_messages')->count());
        $this->assertSame(1, User::withCount('chatMessages')->whereKey($user->id)->first()->chat_messages_count);
        $this->assertSame([$user->id], DB::table('users')->where('id', $user->id)->pluck('id')->all());
        $this->assertSame($user->id, ChatMessage::query()->cursor()->first()->student_id);
    }

    public function test_the_conversion_only_turns_on_for_other_drivers(): void
    {
        $withClient = fn (string $client) => new class($client) extends PDO
        {
            public function __construct(private string $client)
            {
                parent::__construct('sqlite::memory:');
            }

            public function getAttribute(int $attribute): mixed
            {
                return $attribute === PDO::ATTR_CLIENT_VERSION ? $this->client : parent::getAttribute($attribute);
            }
        };

        $mysqlnd = $withClient('mysqlnd 8.2.12');
        NativeNumbersStatement::applyIfNeeded($mysqlnd);
        $this->assertSame(PDOStatement::class, get_class($mysqlnd->prepare('SELECT 1')));

        $libmysql = $withClient('5.7.42');
        NativeNumbersStatement::applyIfNeeded($libmysql);
        $this->assertSame(NativeNumbersStatement::class, get_class($libmysql->prepare('SELECT 1')));

        // MySQL connections are built through it, without connecting early.
        foreach (['mysql' => MySqlConnection::class, 'mariadb' => MariaDbConnection::class] as $driver => $class) {
            $this->assertInstanceOf($class, Connection::getResolver($driver)(fn () => $mysqlnd, 'db', '', ['name' => $driver]));
        }
    }

    private function useTextNumbers(bool $converted): void
    {
        $this->databaseReturnsNumbersAsText();
        $this->assertSame('string', gettype(DB::getPdo()->query('SELECT 1')->fetchColumn()));
        DB::connection()->getPdo()->setAttribute(PDO::ATTR_STATEMENT_CLASS, [$converted ? SqliteNativeNumbersStatement::class : PDOStatement::class]);
    }
}
