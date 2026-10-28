<?php

namespace Tests\Feature;

use App\Mail\OrderPaid;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\User;
use App\Payments\OrderProcessor;
use App\Payments\PayWay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'payway.merchant_id' => 'ec000002',
            'payway.api_key' => 'test-api-key',
            'payway.base_url' => 'https://checkout-sandbox.payway.com.kh',
            'payway.test_gateway' => false,
        ]);
        Mail::fake();
    }

    public function test_hash_is_base64_hmac_sha512_of_the_concatenated_values(): void
    {
        $expected = base64_encode(hash_hmac('sha512', '20250213065545ec00000217394277693', 'test-api-key', true));

        $this->assertSame($expected, app(PayWay::class)->hash(['20250213065545', 'ec000002', '17394277693']));
    }

    public function test_purchase_fields_are_signed_in_the_documented_order(): void
    {
        $order = $this->order();
        $fields = app(PayWay::class)->purchaseFields($order, 'cards', [
            'success' => 'https://site.test/ok', 'cancel' => 'https://site.test/cancel', 'pushback' => 'https://site.test/push',
        ]);

        $this->assertSame('ec000002', $fields['merchant_id']);
        $this->assertSame($order->number, $fields['tran_id']);
        $this->assertSame('49.00', $fields['amount']);
        $this->assertSame(base64_encode('https://site.test/push'), $fields['return_url']);
        $this->assertArrayNotHasKey('shipping', $fields); // empty optional fields are not sent

        $order_ = ['req_time', 'merchant_id', 'tran_id', 'amount', 'items', 'shipping', 'firstname', 'lastname', 'email', 'phone',
            'type', 'payment_option', 'return_url', 'cancel_url', 'continue_success_url', 'return_deeplink', 'currency',
            'custom_fields', 'return_params', 'payout', 'lifetime', 'additional_params', 'google_pay_token', 'skip_success_page'];
        $expected = app(PayWay::class)->hash(array_map(fn ($key) => $fields[$key] ?? '', $order_));
        $this->assertSame($expected, $fields['hash']);
    }

    public function test_approved_payment_grants_access_once_and_sends_one_receipt(): void
    {
        $order = $this->order();
        $this->fakePayWay('APPROVED', 49.00);

        $processor = app(OrderProcessor::class);
        $processor->refresh($order);
        $processor->refresh($order->fresh()); // browser return + pushback both arrive

        $order->refresh();
        $this->assertSame('paid', $order->status);
        $this->assertSame('753786', $order->apv);
        $this->assertSame(1, Enrollment::count());
        $this->assertTrue($order->user->canAccessCourse($order->course));
        $this->assertEqualsWithDelta(now()->addDays(365)->timestamp, Enrollment::first()->expires_at->timestamp, 5);
        Mail::assertSent(OrderPaid::class, 1);
    }

    public function test_amount_mismatch_is_flagged_for_review_without_access(): void
    {
        $order = $this->order();
        $this->fakePayWay('APPROVED', 0.10);

        app(OrderProcessor::class)->refresh($order);

        $this->assertSame('review', $order->fresh()->status);
        $this->assertSame(0, Enrollment::count());
        Mail::assertNothingSent();
    }

    public function test_declined_payment_marks_the_order_failed(): void
    {
        $order = $this->order();
        $this->fakePayWay('DECLINED', 49.00);

        app(OrderProcessor::class)->refresh($order);

        $this->assertSame('failed', $order->fresh()->status);
        $this->assertSame(0, Enrollment::count());
    }

    public function test_unknown_transaction_stays_pending(): void
    {
        $order = $this->order();
        Http::fake(['*/check-transaction-2' => Http::response(['status' => ['code' => 6, 'message' => 'Transaction not found']])]);

        app(OrderProcessor::class)->refresh($order);

        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_pushback_rechecks_the_order_with_payway(): void
    {
        $order = $this->order();
        $this->fakePayWay('APPROVED', 49.00);

        $this->postJson('/payway/pushback', ['tran_id' => $order->number, 'status' => 0])->assertOk();
        $this->postJson('/payway/pushback', ['tran_id' => 'NOPE'])->assertOk();

        $this->assertSame('paid', $order->fresh()->status);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'check-transaction-2')
            && $request['tran_id'] === $order->number
            && $request['hash'] === app(PayWay::class)->hash([$request['req_time'], 'ec000002', $order->number]));
    }

    public function test_checkout_creates_a_pending_order_and_posts_to_payway(): void
    {
        $course = $this->course();
        $user = User::factory()->create();

        $this->actingAs($user)->post("/courses/{$course->slug}/checkout", [
            'plan_id' => $course->plans()->first()->id, 'name' => 'Sok Dara', 'email' => 'dara@example.com', 'payment_option' => 'abapay_khqr', 'terms' => '1',
        ])->assertOk()->assertSee('checkout-sandbox.payway.com.kh/api/payment-gateway/v1/payments/purchase', false);

        $order = Order::firstOrFail();
        $this->assertSame('pending', $order->status);
        $this->assertSame('49.00', $order->amount);
        $this->assertSame($user->id, $order->user_id);
    }

    public function test_someone_else_cannot_see_an_order(): void
    {
        $order = $this->order();

        $this->actingAs(User::factory()->create())->get("/orders/{$order->number}")->assertNotFound();
    }

    private function course(): Course
    {
        $course = Course::create(['title' => 'Basic Excel', 'slug' => 'basic-excel', 'is_published' => true, 'currency' => 'USD']);
        $course->plans()->create(['days' => 365, 'price' => 49, 'position' => 1]);

        return $course;
    }

    private function order(): Order
    {
        $course = $this->course();
        $user = User::factory()->create();

        return Order::create([
            'number' => Order::newNumber(), 'user_id' => $user->id, 'course_id' => $course->id,
            'course_title' => $course->title, 'amount' => 49, 'currency' => 'USD', 'access_days' => 365,
            'status' => 'pending', 'buyer_name' => 'Sok Dara', 'buyer_email' => 'dara@example.com',
        ]);
    }

    private function fakePayWay(string $status, float $amount): void
    {
        Http::fake(['*/check-transaction-2' => Http::response([
            'data' => [
                'payment_status_code' => $status === 'APPROVED' ? 0 : 3,
                'payment_status' => $status,
                'total_amount' => $amount,
                'payment_amount' => $amount,
                'payment_currency' => 'USD',
                'apv' => '753786',
            ],
            'status' => ['code' => '00', 'message' => 'Success!', 'tran_id' => '17394277693'],
        ])]);
    }
}
