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
}
