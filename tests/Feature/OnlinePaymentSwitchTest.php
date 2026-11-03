<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Admin > Settings > Payments: with online payment off, students are sent to Telegram and no order can be made. */
class OnlinePaymentSwitchTest extends TestCase
{
    use RefreshDatabase;

    private function course(): Course
    {
        $course = Course::create(['title' => 'Pivot Table', 'slug' => 'pivot-table', 'is_published' => true, 'currency' => 'USD']);
        $course->plans()->create(['days' => 90, 'price' => 49, 'position' => 1]);

        return $course;
    }

    public function test_off_until_an_admin_turns_it_on(): void
    {
        config(['payway.online_by_default' => false]); // as on the live site
        $this->assertFalse(\App\Payments\OnlinePayment::enabled());

        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        $this->actingAs($admin)->get('/admin/settings')->assertOk()->assertSee('name="settings[online_payment]"', false);

        $this->put('/admin/settings', ['settings' => ['online_payment' => '1']])->assertSessionHasNoErrors();
        $this->assertTrue(\App\Payments\OnlinePayment::enabled());
        $this->put('/admin/settings', ['settings' => ['online_payment' => '0']]);
        $this->assertFalse(\App\Payments\OnlinePayment::enabled());
    }

    public function test_while_off_students_contact_on_telegram_and_cannot_pay(): void
    {
        Setting::put(['online_payment' => '0', 'telegram_url' => 'https://t.me/mangseyha', 'khqr_account_id' => 'teamit@aba']);
        $course = $this->course();

        // Guests see the price and the Telegram button, no sign-in needed.
        $this->get("/courses/{$course->slug}")->assertSee(__('account.contact_to_buy'))->assertDontSee('<span>Mastercard</span>', false);
        $this->get("/courses/{$course->slug}/checkout")->assertOk()
            ->assertSee('https://t.me/mangseyha')->assertSee('$49.00')->assertDontSee('name="payment_option"', false);

        $student = User::factory()->create();
        $this->actingAs($student)->post("/courses/{$course->slug}/checkout", [
            'plan_id' => $course->plans->first()->id, 'name' => 'Sok Dara', 'email' => 'dara@example.com', 'payment_option' => 'khqr', 'terms' => '1',
        ])->assertRedirect(route('checkout.show', $course->slug));
        $this->assertSame(0, Order::count());

        // A separate contact link wins over Telegram.
        Setting::put(['payment_contact_url' => 'https://m.me/teamitcambodia']);
        $this->get("/courses/{$course->slug}/checkout")->assertSee('https://m.me/teamitcambodia');
    }
}
