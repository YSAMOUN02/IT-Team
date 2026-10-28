<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

/** Pricing page → Buy → create account / sign in (or Google) → back to the same checkout. */
class BuyingAndSignInTest extends TestCase
{
    use RefreshDatabase;

    private function course(): Course
    {
        $course = Course::create(['title' => 'Pivot Table', 'slug' => 'pivot-table', 'is_published' => true, 'currency' => 'USD']);
        $course->plans()->create(['days' => 90, 'price' => 19, 'position' => 1]);
        $course->plans()->create(['days' => 365, 'price' => 29, 'position' => 2, 'discount_percent' => 20]);

        return $course;
    }

    private function google(string $email, bool $verified = true, string $id = 'g-123'): void
    {
        config(['services.google.client_id' => 'id', 'services.google.client_secret' => 'secret']);
        $user = (new SocialiteUser)->setRaw(['email_verified' => $verified])->map([
            'id' => $id, 'name' => 'Sok Dara', 'email' => $email, 'avatar' => 'https://lh3.googleusercontent.com/a/photo',
        ]);
        $driver = Mockery::mock();
        $driver->shouldReceive('user')->andReturn($user);
        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);
    }

    public function test_prices_live_on_the_course_page_and_the_old_pricing_link_goes_to_courses(): void
    {
        $course = $this->course();
        [$quarter, $year] = $course->plans->all();

        $this->get('/courses/pivot-table')->assertOk()
            ->assertSee('$19.00')->assertSee('$23.20')->assertSee('−20%', false)
            ->assertSee('value="'.$quarter->id.'"', false)->assertSee('value="'.$year->id.'"', false);
        $this->get('/pricing')->assertRedirect('/courses')->assertStatus(301);
        $this->get('/')->assertDontSee('>Pricing<', false); // not in the menu any more
    }

    public function test_buying_while_signed_out_asks_to_sign_up_then_returns_to_the_same_plan(): void
    {
        $course = $this->course();
        $year = $course->plans->last();
        $checkout = "/courses/pivot-table/checkout?plan={$year->id}";

        $this->get($checkout)->assertOk()
            ->assertSee('Pivot Table')->assertSee('$23.20')
            ->assertSee(route('register.store'), false)->assertSee(route('login.store'), false);

        $this->post('/register', [
            'form' => 'register', 'name' => 'Sok Dara', 'email' => 'dara@example.com',
            'password' => 'secret-pass', 'password_confirmation' => 'secret-pass',
        ])->assertRedirect($checkout);

        $this->get($checkout)->assertOk()->assertSee('name="plan_id"', false)->assertSee('$23.20');
    }

    public function test_a_failed_sign_in_comes_back_to_the_sign_in_tab(): void
    {
        $this->course();
        User::factory()->create(['email' => 'dara@example.com']);

        $this->get('/courses/pivot-table/checkout');
        $this->from('/courses/pivot-table/checkout')
            ->post('/login', ['form' => 'login', 'email' => 'dara@example.com', 'password' => 'wrong'])
            ->assertRedirect('/courses/pivot-table/checkout');

        $page = $this->get('/courses/pivot-table/checkout')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/data-tab="login" aria-selected="true"/', $page);
        $this->assertMatchesRegularExpression('/data-panel="register"\s+hidden/', $page);
    }

    public function test_google_button_only_shows_when_set_up(): void
    {
        config(['services.google.client_id' => null, 'services.google.client_secret' => null]);
        $this->get('/login')->assertOk()->assertDontSee('/auth/google', false);
        $this->get('/auth/google')->assertNotFound();

        config(['services.google.client_id' => 'id', 'services.google.client_secret' => 'secret']);
        $this->get('/login')->assertSee('/auth/google', false);
        $this->get('/auth/google')->assertRedirectContains('accounts.google.com');
    }

    public function test_google_creates_a_student_and_returns_to_checkout(): void
    {
        $this->course();
        $this->get('/courses/pivot-table/checkout'); // remembers where to come back to
        $this->google('dara@gmail.com');

        $this->get('/auth/google/callback?code=x&state=y')->assertRedirect('/courses/pivot-table/checkout');

        $user = User::where('email', 'dara@gmail.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('student', $user->role);
        $this->assertSame('g-123', $user->google_id);
        $this->assertNull($user->password);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_google_signs_in_an_existing_student_with_the_same_email(): void
    {
        $student = User::factory()->create(['email' => 'Dara@Example.com']);
        $this->google('dara@example.com');

        $this->get('/auth/google/callback?code=x&state=y')->assertRedirect('/my/courses');

        $this->assertAuthenticatedAs($student);
        $this->assertSame('g-123', $student->fresh()->google_id);
        $this->assertSame(1, User::count());
    }

    public function test_google_never_signs_in_an_admin_or_an_unverified_email(): void
    {
        $admin = User::factory()->create(['email' => 'boss@example.com']);
        $admin->forceFill(['role' => 'admin'])->save();

        $this->google('boss@example.com');
        $this->get('/auth/google/callback?code=x&state=y')->assertRedirect('/login')->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->google('someone@example.com', verified: false, id: 'g-999');
        $this->get('/auth/google/callback?code=x&state=y')->assertRedirect('/login');
        $this->assertGuest();
        $this->assertSame(0, User::where('email', 'someone@example.com')->count());
    }

    public function test_a_google_only_student_is_told_to_use_google_and_can_set_a_password(): void
    {
        config(['services.google.client_id' => 'id', 'services.google.client_secret' => 'secret']);
        $student = User::factory()->create(['email' => 'dara@gmail.com']);
        $student->forceFill(['password' => null, 'google_id' => 'g-1'])->save();

        $this->post('/login', ['email' => 'dara@gmail.com', 'password' => 'anything'])
            ->assertSessionHasErrors(['email' => __('account.use_google')]);

        $this->actingAs($student->fresh())->put('/my/profile', [
            'name' => 'Dara', 'email' => 'dara@gmail.com', 'password' => 'new-pass-1', 'password_confirmation' => 'new-pass-1',
        ])->assertSessionHasNoErrors();
        $this->assertTrue(password_verify('new-pass-1', $student->fresh()->password));
    }
}
