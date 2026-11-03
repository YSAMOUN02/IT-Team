<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Payments\Khqr;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** KHQR to our own Bakong account: QR with the exact amount, seen by the Bakong API or confirmed by an admin. */
class KhqrTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['payway.merchant_id' => null, 'payway.api_key' => null, 'payway.test_gateway' => false, 'khqr.token' => null]);
        Mail::fake();
    }

    private function onKhqr(): void
    {
        Setting::put(['khqr_account_id' => 'teamit@aba', 'khqr_merchant_name' => 'TEAMIT Cambodia', 'khqr_merchant_city' => 'Phnom Penh']);
    }

    private function course(): Course
    {
        $course = Course::create(['title' => 'Pivot Table', 'slug' => 'pivot-table', 'is_published' => true, 'currency' => 'USD']);
        $course->plans()->create(['days' => 90, 'price' => 49, 'position' => 1]);

        return $course;
    }

    private function buy(User $student, Course $course)
    {
        return $this->actingAs($student)->post("/courses/{$course->slug}/checkout", [
            'plan_id' => $course->plans->first()->id, 'name' => 'Sok Dara', 'email' => 'dara@example.com', 'payment_option' => 'khqr', 'terms' => '1',
        ]);
    }

    public function test_the_qr_text_follows_khqr_and_its_checksum_is_right(): void
    {
        $this->onKhqr();
        $text = app(Khqr::class)->payload(49, 'USD', 'T2611031200ABCDE', 1762142400000, 1762143300000);

        $this->assertStringStartsWith('000201010212', $text);                    // format 01, dynamic
        $this->assertStringContainsString('29140010teamit@aba', $text);          // individual account
        $this->assertStringContainsString('520459995303840540549.00', $text);     // MCC, USD, 49.00
        $this->assertStringContainsString('5802KH5915TEAMIT Cambodia6010Phnom Penh', $text);
        $this->assertStringContainsString('62200116T2611031200ABCDE', $text);    // bill number
        $this->assertStringContainsString('99340013176214240000001131762143300000', $text); // created / expires
        $this->assertSame(app(Khqr::class)->crc16(substr($text, 0, -4)), substr($text, -4));
        $this->assertSame('6304', substr($text, -8, 4));

        $riel = app(Khqr::class)->payload(196000, 'KHR', 'T1', 1, 2);
        $this->assertStringContainsString('53031165406196000', $riel);
    }

    public function test_not_offered_until_the_account_is_filled_in(): void
    {
        $course = $this->course();
        $student = User::factory()->create();

        $this->actingAs($student)->get("/courses/{$course->slug}/checkout")->assertDontSee('value="khqr"', false);
        $this->buy($student, $course)->assertNotFound();
        $this->assertSame(0, Order::count());
    }

    public function test_pay_by_qr_then_an_admin_confirms_it(): void
    {
        $this->onKhqr();
        $course = $this->course();
        $student = User::factory()->create();

        $this->actingAs($student)->get("/courses/{$course->slug}/checkout")->assertSee('value="khqr"', false);
        $this->buy($student, $course)->assertRedirect();
        $order = Order::sole();
        $this->assertSame(['khqr', 'pending', '49.00'], [$order->gateway, $order->status, $order->amount]);

        $page = $this->get(route('checkout.khqr', $order))->assertOk()->assertSee('KHQR')->assertSee('49.00');
        $qr = $order->fresh()->gateway_payload['khqr']['qr'];
        $this->assertStringContainsString('data-qr="'.$qr.'"', $page->getContent());
        $this->assertStringContainsString($order->number, $qr);
        // Same QR while it is valid.
        $this->get(route('checkout.khqr', $order));
        $this->assertSame($qr, $order->fresh()->gateway_payload['khqr']['qr']);

        $this->getJson(route('checkout.khqr.status', $order))->assertJson(['paid' => false, 'redirect' => null]);
        $this->post(route('checkout.khqr.paid', $order))->assertRedirect(route('orders.show', $order));
        $this->assertSame('review', $order->fresh()->status);
        $this->assertFalse($student->fresh()->canAccessCourse($course));

        // Someone else's order stays hidden.
        $this->actingAs(User::factory()->create())->get(route('checkout.khqr', $order))->assertNotFound();

        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        $this->actingAs($admin)->get(route('admin.orders.show', $order))->assertSee('Confirm payment received');
        $this->post(route('admin.orders.confirm', $order))->assertSessionHas('status');

        $order->refresh();
        $this->assertSame('paid', $order->status);
        $this->assertTrue($student->fresh()->canAccessCourse($course));
        $this->assertSame('49.00', Invoice::where('order_id', $order->id)->sole()->amount);
        Mail::assertSent(\App\Mail\OrderPaid::class);
    }

    public function test_bakong_sees_the_payment_and_the_page_moves_on(): void
    {
        $this->onKhqr();
        config(['khqr.token' => 'bakong-test-token']);
        $course = $this->course();
        $student = User::factory()->create();
        $this->buy($student, $course);
        $order = Order::sole();
        $this->get(route('checkout.khqr', $order))->assertOk()->assertDontSee(__('account.khqr_i_paid'));
        $md5 = $order->fresh()->gateway_payload['khqr']['md5'];

        // First check: not paid yet; second: paid.
        Http::fake(['api-bakong.nbc.gov.kh/*' => Http::sequence()
            ->push(['responseCode' => 1, 'responseMessage' => 'Transaction could not be found', 'data' => null])
            ->push(['responseCode' => 0, 'data' => ['hash' => 'abc123hash', 'amount' => 49, 'currency' => 'USD', 'fromAccountId' => 'dara@wing']])]);
        $this->getJson(route('checkout.khqr.status', $order))->assertJson(['paid' => false]);

        $this->travel(5)->seconds();
        $this->getJson(route('checkout.khqr.status', $order))->assertJson(['paid' => true, 'redirect' => route('orders.show', $order)]);
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer bakong-test-token') && $request['md5'] === $md5);

        $order->refresh();
        $this->assertSame(['paid', 'abc123hash'], [$order->status, $order->apv]);
        $this->assertTrue($student->fresh()->canAccessCourse($course));
        $this->assertSame(1, Invoice::count());
    }

    public function test_a_wrong_amount_waits_for_an_admin(): void
    {
        $this->onKhqr();
        config(['khqr.token' => 'bakong-test-token']);
        $course = $this->course();
        $student = User::factory()->create();
        $this->buy($student, $course);
        $order = Order::sole();
        $this->get(route('checkout.khqr', $order));

        Http::fake(['api-bakong.nbc.gov.kh/*' => Http::response(['responseCode' => 0, 'data' => ['hash' => 'h', 'amount' => 1, 'currency' => 'USD']])]);
        $this->getJson(route('checkout.khqr.status', $order));

        $this->assertSame('review', $order->fresh()->status);
        $this->assertFalse($student->fresh()->canAccessCourse($course));
    }
}
