<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Invoices: kept for every payment and every access given by hand; students download their own. */
class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();

        return $admin;
    }

    private function order(User $student, Course $course): Order
    {
        return Order::create([
            'number' => Order::newNumber(), 'user_id' => $student->id, 'course_id' => $course->id, 'course_title' => $course->title,
            'amount' => 49, 'currency' => 'USD', 'access_days' => 90, 'gateway' => 'payway', 'status' => 'pending',
            'buyer_name' => $student->name, 'buyer_email' => $student->email,
        ]);
    }

    public function test_a_paid_order_gets_one_kept_invoice_and_a_refund_marks_it(): void
    {
        $student = User::factory()->create(['name' => 'Sok Dara']);
        $course = Course::create(['title' => 'Pivot Table', 'slug' => 'pivot-table', 'is_published' => true]);
        $order = $this->order($student, $course);
        $this->assertSame(0, Invoice::count());

        Enrollment::grant($student, $course, 90, 'payment', $order);
        $order->update(['status' => 'paid', 'paid_at' => now(), 'apv' => 'APV123']);
        $order->update(['checked_at' => now()]); // saving again must not make a second one

        $invoice = Invoice::sole();
        $this->assertMatchesRegularExpression('/^INV-\d{4}-\d{5}$/', $invoice->number);
        $this->assertSame(['Pivot Table', 'Sok Dara', '49.00', 90, 'payway', 'APV123', 'paid'],
            [$invoice->course_title, $invoice->student_name, $invoice->amount, $invoice->access_days, $invoice->payment_method, $invoice->reference, $invoice->status]);
        $this->assertSame(now()->addDays(90)->toDateString(), $invoice->access_until->toDateString());

        // Kept as it was, even when the course is renamed later.
        $course->update(['title' => 'Pivot Table 2027']);
        $this->assertSame('Pivot Table', $invoice->fresh()->course_title);

        $order->update(['status' => 'refunded']);
        $this->assertSame('refunded', $invoice->fresh()->status);
    }

    public function test_access_given_by_hand_makes_an_invoice_with_what_was_paid(): void
    {
        $admin = $this->adminUser();
        $course = Course::create(['title' => 'Pivot Table', 'slug' => 'pivot-table', 'is_published' => true]);
        $page = "/admin/courses/{$course->id}/students";

        $this->actingAs($admin)->from($page)->post($page, ['email' => 'new@example.com', 'name' => 'Chan Sophea', 'days' => 60,
            'amount' => '35.5', 'payment_method' => 'cash', 'invoice_note' => 'Receipt 0042'])->assertSessionHasNoErrors();
        $invoice = Invoice::sole();
        $this->assertSame(['35.50', 'cash', 60, 'Receipt 0042', 'Chan Sophea'], [$invoice->amount, $invoice->payment_method, $invoice->access_days, $invoice->note, $invoice->student_name]);

        // No amount: a free invoice.
        $dara = User::factory()->create();
        $this->post("/admin/students/{$dara->id}/enrollments", ['course_id' => $course->id])->assertSessionHasNoErrors();
        $this->assertSame(['0.00', 'free'], [$dara->invoices()->sole()->amount, $dara->invoices()->sole()->payment_method]);

        $this->post('/admin/students', ['name' => 'Keo Lina', 'email' => 'lina@example.com', 'course_id' => $course->id, 'amount' => 20, 'payment_method' => 'transfer'])
            ->assertSessionHasNoErrors();
        $this->assertSame('transfer', User::findByEmail('lina@example.com')->invoices()->sole()->payment_method);

        $this->post($page, ['email' => 'x@example.com', 'name' => 'X', 'payment_method' => 'bitcoin'])->assertSessionHasErrors('payment_method');
    }

    public function test_students_download_only_their_own_invoices(): void
    {
        $course = Course::create(['title' => 'Pivot Table', 'slug' => 'pivot-table', 'is_published' => true]);
        $dara = User::factory()->create(['name' => 'Sok Dara']);
        $other = User::factory()->create();
        $mine = Invoice::forManualAccess(Enrollment::grant($dara, $course, 30, 'manual'), 30, 25, 'cash');
        $theirs = Invoice::forManualAccess(Enrollment::grant($other, $course, null, 'manual'), null);

        $this->actingAs($dara)->get('/my/invoices')->assertOk()->assertSee($mine->number)->assertDontSee($theirs->number)
            ->assertSee(route('account.invoices.show', $mine));
        $this->get('/my/courses')->assertSee(route('account.invoices'));
        $this->get(route('account.invoices.show', $mine))->assertOk()
            ->assertSee($mine->number)->assertSee('Sok Dara')->assertSee('Pivot Table')->assertSee('$25.00')->assertSee('window.print()', false);
        $this->get(route('account.invoices.show', $theirs))->assertNotFound();

        $this->actingAs($this->adminUser())->get(route('admin.students.show', $dara))->assertSee(route('admin.invoices.show', $mine));
        $this->get(route('admin.invoices.show', $theirs))->assertOk()->assertSee($theirs->number);
    }
}
