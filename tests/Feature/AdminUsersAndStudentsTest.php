<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Admin > Users holds admin accounts only; students live under Admin > Students. */
class AdminUsersAndStudentsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(array $attributes = []): User
    {
        $admin = User::factory()->create($attributes);
        $admin->forceFill(['role' => 'admin'])->save();

        return $admin;
    }

    public function test_users_lists_admins_and_students_lists_students(): void
    {
        $admin = $this->admin();
        $this->admin(['name' => 'Office Admin', 'email' => 'office@teamit.test']);
        User::factory()->create(['name' => 'Sok Dara', 'email' => 'dara@example.com']);

        $this->actingAs($admin)->get('/admin/users')->assertOk()
            ->assertSee('office@teamit.test')->assertDontSee('dara@example.com');

        // (The signed-in admin's own email is in the page header, so look for the other one.)
        $this->get('/admin/students')->assertOk()
            ->assertSee('dara@example.com')->assertDontSee('office@teamit.test');
    }

    public function test_a_new_user_is_an_admin_who_can_sign_in_to_the_admin(): void
    {
        $this->actingAs($this->admin())->post('/admin/users', [
            'name' => 'New Staff',
            'email' => 'staff@teamit.test',
            'password' => 'Secret-pass-1',
            'password_confirmation' => 'Secret-pass-1',
        ])->assertRedirect('/admin/users')->assertSessionHasNoErrors();

        $staff = User::where('email', 'staff@teamit.test')->firstOrFail();
        $this->assertSame('admin', $staff->role);
        $this->get('/admin/users')->assertSee('staff@teamit.test');
        $this->get('/admin/students')->assertDontSee('staff@teamit.test');

        auth()->logout();
        $this->post('/admin/login', ['login' => 'staff@teamit.test', 'password' => 'Secret-pass-1'])->assertRedirect('/admin');
        $this->get('/admin')->assertOk();
    }

    public function test_students_cannot_be_opened_changed_or_deleted_from_users(): void
    {
        $admin = $this->admin();
        $student = User::factory()->create(['name' => 'Sok Dara', 'email' => 'dara@example.com']);

        $this->actingAs($admin)->get("/admin/users/{$student->id}/edit")->assertNotFound();
        $this->put("/admin/users/{$student->id}", ['name' => 'Changed', 'email' => 'changed@example.com'])->assertNotFound();
        $this->delete("/admin/users/{$student->id}")->assertNotFound();

        $student->refresh();
        $this->assertSame(['Sok Dara', 'dara@example.com', 'student'], [$student->name, $student->email, $student->role]);
        $this->get("/admin/students/{$student->id}")->assertOk()->assertSee('Sok Dara');
    }

    public function test_admins_are_still_managed_from_users(): void
    {
        $admin = $this->admin();
        $other = $this->admin(['name' => 'Second Admin']);

        $this->actingAs($admin)->get("/admin/users/{$admin->id}/edit")->assertOk();
        $this->put("/admin/users/{$other->id}", ['name' => 'Renamed Admin', 'email' => $other->email])->assertRedirect('/admin/users');
        $this->assertSame(['Renamed Admin', 'admin'], [$other->fresh()->name, $other->fresh()->role]);

        $this->delete("/admin/users/{$other->id}")->assertRedirect('/admin/users');
        $this->assertModelMissing($other);
        $this->get("/admin/students/{$admin->id}")->assertNotFound();
    }

    public function test_course_access_by_email_is_for_students_only(): void
    {
        $admin = $this->admin(['email' => 'office@teamit.test']);
        $student = User::factory()->create(['email' => 'dara@example.com']);
        $course = Course::create(['title' => 'Pivot Table', 'slug' => 'pivot-table', 'is_published' => true]);

        $this->actingAs($admin)->from("/admin/courses/{$course->id}/students")
            ->post("/admin/courses/{$course->id}/students", ['email' => 'OFFICE@teamit.test'])
            ->assertSessionHasErrors(['email' => 'That email belongs to an admin account. Admins can already open every course.']);
        $this->assertSame(0, $admin->enrollments()->count());

        $this->post("/admin/courses/{$course->id}/students", ['email' => 'dara@example.com'])->assertSessionHasNoErrors();
        $this->assertSame(1, $student->enrollments()->count());

        $this->post("/admin/students/{$admin->id}/enrollments", ['course_id' => $course->id])->assertNotFound();
    }

    public function test_admin_adds_a_student_with_a_made_up_password_and_a_course(): void
    {
        $admin = $this->admin(['email' => 'office@teamit.test']);
        $course = Course::create(['title' => 'Pivot Table', 'slug' => 'pivot-table', 'is_published' => true]);

        $this->actingAs($admin)->get('/admin/students')->assertSee(route('admin.students.create'));
        $this->get('/admin/students/create')->assertOk()->assertSee('Pivot Table');

        $response = $this->post('/admin/students', ['name' => 'Sok Dara', 'email' => ' Dara@Example.com ', 'phone' => '012 345 678', 'course_id' => $course->id]);
        $student = User::where('email', 'dara@example.com')->firstOrFail();
        $response->assertRedirect(route('admin.students.show', $student));
        $this->assertSame('student', $student->role);
        $this->assertSame(1, $student->enrollments()->where('course_id', $course->id)->count());

        $password = session('new_student')['password'];
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check($password, $student->password));
        $this->get(route('admin.students.show', $student))->assertSee($password);
        // Shown once only.
        $this->get(route('admin.students.show', $student))->assertDontSee($password);

        $this->from('/admin/students/create')->post('/admin/students', ['name' => 'Again', 'email' => 'office@teamit.test'])
            ->assertSessionHasErrors(['email' => 'A student or admin already uses this email.']);
        // Admin users stay separate from students.
        $this->get('/admin/users')->assertSee('Admin users')->assertDontSee('dara@example.com');
    }

    public function test_give_access_picks_an_existing_student_or_makes_a_new_one(): void
    {
        $admin = $this->admin();
        $dara = User::factory()->create(['name' => 'Sok Dara', 'email' => 'dara@example.com']);
        $course = Course::create(['title' => 'Pivot Table', 'slug' => 'pivot-table', 'is_published' => true]);
        $page = "/admin/courses/{$course->id}/students";

        $this->actingAs($admin)->get($page)->assertSee('<datalist id="pickable-students">', false)->assertSee('<option value="dara@example.com">Sok Dara</option>', false);

        $this->from($page)->post($page, ['email' => 'dara@example.com'])->assertSessionHasNoErrors();
        $this->assertSame(1, $dara->enrollments()->count());
        // Already enrolled: no longer offered.
        $this->get($page)->assertDontSee('<option value="dara@example.com">', false);

        $this->from($page)->post($page, ['email' => 'new@example.com'])
            ->assertSessionHasErrors(['name' => 'No account uses that email yet. Type the student’s name to create one.']);
        $this->assertNull(User::findByEmail('new@example.com'));

        $this->from($page)->post($page, ['email' => 'New@Example.com', 'name' => 'Chan Sophea'])->assertSessionHasNoErrors();
        $new = User::findByEmail('new@example.com');
        $this->assertSame(['Chan Sophea', 'student', 1], [$new->name, $new->role, $new->enrollments()->count()]);
        $password = session('new_student')['password'];
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check($password, $new->password));
        $this->get($page)->assertSee('Sign-in for Chan Sophea')->assertSee($password);
    }

    public function test_the_student_is_emailed_the_course_link_and_sign_in(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        config(['app.public_url' => 'https://live.test']);
        $admin = $this->admin();
        $course = Course::create(['title' => 'Pivot Table', 'slug' => 'pivot-table', 'is_published' => true]);
        $dara = User::factory()->create(['email' => 'dara@example.com']);
        $page = "/admin/courses/{$course->id}/students";

        // New student from the course page: course link + email + password.
        $this->actingAs($admin)->from($page)->post($page, ['email' => 'new@example.com', 'name' => 'Chan Sophea', 'days' => 30, 'send_email' => '1'])
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'The student was emailed.'));
        $password = session('new_student')['password'];
        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\CourseAccess::class, function ($mail) use ($course, $password) {
            $html = $mail->render();

            return $mail->hasTo('new@example.com') && $mail->password === $password && $mail->course->is($course)
                && str_contains($html, $password)
                // Links go to the live site (PUBLIC_URL), with the course period.
                && str_contains($html, 'https://live.test/login') && str_contains($html, 'https://live.test'.route('courses.show', $course, false))
                && str_contains($html, now()->addDays(30)->format('d/m/Y'));
        });

        // Existing student: course link, no password in the mail.
        $this->from($page)->post($page, ['email' => 'dara@example.com', 'send_email' => '1']);
        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\CourseAccess::class, fn ($mail) => $mail->hasTo('dara@example.com') && $mail->password === null);

        // Box not ticked: no mail.
        $this->post("/admin/students/{$dara->id}/enrollments", ['course_id' => $course->id]);
        \Illuminate\Support\Facades\Mail::assertSentCount(2);
    }

    public function test_no_false_emailed_message_and_expired_students_can_be_picked_again(): void
    {
        config(['mail.default' => 'log']);
        $admin = $this->admin();
        $course = Course::create(['title' => 'Pivot Table', 'slug' => 'pivot-table', 'is_published' => true]);
        $page = "/admin/courses/{$course->id}/students";

        // Mail only goes to the log: say so instead of "emailed".
        $this->actingAs($admin)->from($page)->post($page, ['email' => 'new@example.com', 'name' => 'Chan Sophea', 'send_email' => '1'])
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'mail is not set up yet') && ! str_contains($s, 'was emailed'));

        // Turned-off account: no "you can open the course" email.
        \Illuminate\Support\Facades\Mail::fake();
        $off = User::factory()->create(['email' => 'off@example.com']);
        $off->forceFill(['disabled_at' => now()])->save();
        $this->from($page)->post($page, ['email' => 'off@example.com', 'send_email' => '1'])
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'account is turned off'));
        \Illuminate\Support\Facades\Mail::assertNothingSent();

        // Access ran out: offered in the list again.
        $dara = User::factory()->create(['name' => 'Sok Dara', 'email' => 'dara@example.com']);
        \App\Models\Enrollment::grant($dara, $course, 30, 'manual');
        $this->get($page)->assertDontSee('<option value="dara@example.com">', false);
        $dara->enrollments()->update(['expires_at' => now()->subDay()]);
        $this->get($page)->assertSee('<option value="dara@example.com">Sok Dara</option>', false);
    }

    public function test_a_broken_mail_setup_does_not_stop_access_being_given(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1, 'mail.mailers.smtp.timeout' => 2]);
        $admin = $this->admin();
        $course = Course::create(['title' => 'Pivot Table', 'slug' => 'pivot-table', 'is_published' => true]);

        $this->actingAs($admin)->post('/admin/students', ['name' => 'Sok Dara', 'email' => 'dara@example.com', 'course_id' => $course->id, 'send_email' => '1'])
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'could not be sent'));
        $this->assertSame(1, User::findByEmail('dara@example.com')->enrollments()->count());
    }
}
