<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AsksGuestsToSignIn;
use App\Models\Course;
use App\Models\Order;
use App\Payments\Khqr;
use App\Payments\OnlinePayment;
use App\Payments\OrderProcessor;
use App\Payments\PayWay;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Buying a course: summary -> signed form to PayWay -> back here, where the
 * result is confirmed with PayWay before access is given.
 */
class CheckoutController extends Controller
{
    use AsksGuestsToSignIn;

    public function __construct(private PayWay $payway, private OrderProcessor $processor, private Khqr $khqr)
    {
    }

    public function show(Request $request, Course $course)
    {
        if ($course->isFree()) {
            return redirect()->route('courses.enroll', $course->slug);
        }
        abort_unless($course->isPurchasable(), 404);

        $plans = $course->plans;
        $selected = $plans->firstWhere('id', $request->integer('plan')) ?? $course->cheapestPlan();

        // Online payment switched off by an admin: show the price and how to get in touch instead
        // (no sign-in needed for that); an admin then gives the course by hand.
        if (! OnlinePayment::enabled()) {
            return view('checkout.contact', ['course' => $course, 'plans' => $plans, 'selected' => $selected,
                'contactUrl' => OnlinePayment::contactUrl(), 'telegram' => OnlinePayment::contactIsTelegram()]);
        }

        if (! $request->user()) {
            return $this->askToSignIn($request, $course, $selected);
        }
        if ($request->user()->courseAccessOff($course)) {
            return redirect()->route('courses.show', $course->slug)->with('checkout_notice', __('account.access_off'));
        }
        if ($request->user()->canAccessCourse($course) && ! $request->boolean('renew')) {
            return redirect()->route('learn.course', $course->slug);
        }

        return view('checkout.show', [
            'course' => $course,
            'plans' => $plans,
            'selected' => $selected,
            'user' => $request->user(),
            'enrollment' => $request->user()->enrollments()->where('course_id', $course->id)->first(),
            'available' => $this->payway->available() || $this->khqr->available(),
            'cardsAvailable' => $this->payway->available(),
            'khqrAvailable' => $this->khqr->available(),
            'testGateway' => $this->payway->usesTestGateway(),
        ]);
    }

    public function store(Request $request, Course $course)
    {
        abort_unless($course->isPurchasable(), 404);
        if ($request->user()->courseAccessOff($course)) {
            return redirect()->route('courses.show', $course->slug)->with('checkout_notice', __('account.access_off'));
        }
        // Online payment is off: no order is made, the checkout page says how to get in touch.
        if (! OnlinePayment::enabled()) {
            return redirect()->route('checkout.show', $course->slug);
        }

        $data = $request->validate([
            'plan_id' => ['required', Rule::exists('course_plans', 'id')->where('course_id', $course->id)],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'payment_option' => ['nullable', Rule::in([...PayWay::PAYMENT_OPTIONS, 'khqr'])],
            'terms' => ['accepted'],
        ]);

        // The price always comes from the plan on the server, never from the form.
        $plan = $course->plans->firstWhere('id', (int) $data['plan_id']);
        $amount = $plan->currentPrice();
        $khqr = ($data['payment_option'] ?? null) === 'khqr';
        abort_unless($amount <= 0 || ($khqr ? $this->khqr->available() : $this->payway->available()), 404);

        $order = Order::create([
            'number' => Order::newNumber(),
            'user_id' => $request->user()->id,
            'course_id' => $course->id,
            'plan_id' => $plan->id,
            'plan_label' => $plan->periodLabel(),
            'course_title' => $course->title,
            'amount' => $amount,
            'original_amount' => $plan->price,
            'discount_percent' => $plan->discountActive() ? $plan->discount_percent : 0,
            'currency' => $course->currency ?: config('payway.currency'),
            'access_days' => $plan->days,
            'payment_option' => $data['payment_option'] ?? null,
            'gateway' => $amount <= 0 ? 'free' : ($khqr ? 'khqr' : ($this->payway->usesTestGateway() ? 'test' : 'payway')),
            'status' => 'pending',
            'buyer_name' => $data['name'],
            'buyer_email' => $data['email'],
            'buyer_phone' => $data['phone'] ?? null,
        ]);

        // Fully discounted: nothing to pay, give access straight away.
        if ($amount <= 0) {
            $this->processor->apply($order, ['status' => 'paid', 'gateway_status' => 'FREE', 'amount' => 0.0, 'currency' => $order->currency, 'apv' => null, 'payload' => ['free' => true]]);

            return redirect()->route('orders.show', $order);
        }

        if ($khqr) {
            return redirect()->route('checkout.khqr', $order);
        }
        if ($this->payway->usesTestGateway()) {
            return redirect()->route('checkout.test', $order);
        }

        // An auto-submitting form posts the signed fields to PayWay's checkout page.
        return view('checkout.redirect', [
            'order' => $order,
            'action' => $this->payway->purchaseUrl(),
            'fields' => $this->payway->purchaseFields($order, $data['payment_option'] ?? '', [
                'success' => route('checkout.return', $order),
                'cancel' => route('checkout.cancel', $order),
                'pushback' => route('payway.pushback'),
            ]),
        ]);
    }

    /** PayWay sends the buyer back here after paying. */
    public function return(Request $request, Order $order)
    {
        $this->authorizeOrder($request, $order);
        $order = $this->processor->refresh($order);

        return redirect()->route('orders.show', $order);
    }

    /** Buyer closed or cancelled the PayWay page. */
    public function cancel(Request $request, Order $order)
    {
        $this->authorizeOrder($request, $order);
        $order = $this->processor->refresh($order); // they may have paid in another tab
        if ($order->isPending()) {
            $order->update(['status' => 'cancelled']);
        }

        return $order->isPaid()
            ? redirect()->route('orders.show', $order)
            : redirect()->route('courses.show', $order->course?->slug ?? '')->with('checkout_notice', __('account.payment_cancelled'));
    }

    /** Server-to-server notice from PayWay. Only a cue: the order is re-checked with PayWay. */
    public function pushback(Request $request)
    {
        $number = $request->input('tran_id') ?? $request->input('return_params') ?? data_get($request->json()->all(), 'tran_id');
        if ($number && $order = Order::where('number', $number)->first()) {
            $this->processor->refresh($order);
        }

        return response()->json(['status' => 'ok']);
    }

    /** Receipt / status page for one order. */
    public function receipt(Request $request, Order $order)
    {
        $this->authorizeOrder($request, $order);
        if ($order->isPending() && (! $order->checked_at || $order->checked_at->lt(now()->subSeconds(5)))) {
            $order = $this->processor->refresh($order);
        }

        return view('checkout.receipt', ['order' => $order->load('course')]);
    }

    /** Local stand-in for PayWay when no merchant account is configured. */
    public function testGateway(Request $request, Order $order)
    {
        abort_unless($this->payway->usesTestGateway() && $order->gateway === 'test', 404);
        $this->authorizeOrder($request, $order);
        abort_unless($order->isPending(), 410);

        return view('checkout.test-gateway', ['order' => $order]);
    }

    public function testGatewayResult(Request $request, Order $order)
    {
        abort_unless($this->payway->usesTestGateway() && $order->gateway === 'test', 404);
        $this->authorizeOrder($request, $order);
        $result = $request->validate(['result' => ['required', Rule::in(['APPROVED', 'DECLINED', 'CANCELLED'])]])['result'];

        $order->update(['gateway_payload' => array_merge((array) $order->gateway_payload, ['test_result' => $result])]);

        return $result === 'CANCELLED'
            ? redirect()->route('checkout.cancel', $order)
            : redirect()->route('checkout.return', $order);
    }


    /** KHQR: the QR code with the exact amount, for any Cambodian bank app. */
    public function khqr(Request $request, Order $order)
    {
        $this->authorizeOrder($request, $order);
        abort_unless($order->gateway === 'khqr', 404);
        if (! in_array($order->status, ['pending', 'review'], true)) {
            return redirect()->route('orders.show', $order);
        }

        return view('checkout.khqr', [
            'order' => $order,
            'khqr' => $this->khqr->forOrder($order),
            'autoCheck' => $this->khqr->autoCheck(),
            'simulate' => $this->payway->usesTestGateway(),
        ]);
    }

    /** Polled by the KHQR page: has the money arrived? */
    public function khqrStatus(Request $request, Order $order)
    {
        $this->authorizeOrder($request, $order);
        abort_unless($order->gateway === 'khqr', 404);
        if ($order->isPending() || $order->status === 'review') {
            // Bakong is asked at most every 4 seconds per order.
            if (! $order->checked_at || $order->checked_at->lt(now()->subSeconds(4))) {
                $order = $this->processor->refresh($order);
            }
        }
        $expired = $order->isPending() && now()->gte(data_get($order->gateway_payload, 'khqr.expires_at', now()));

        return response()->json([
            'status' => $order->status,
            'paid' => $order->isPaid(),
            'expired' => $expired,
            'redirect' => $order->isPaid() || ! in_array($order->status, ['pending', 'review'], true) ? route('orders.show', $order) : null,
        ]);
    }

    /** "I have paid" (no Bakong token): the order waits for an admin to confirm it. */
    public function khqrPaid(Request $request, Order $order)
    {
        $this->authorizeOrder($request, $order);
        abort_unless($order->gateway === 'khqr', 404);
        if ($order->isPending()) {
            $order->update(['status' => 'review', 'gateway_status' => 'BUYER_SAYS_PAID']);
        }

        return redirect()->route('orders.show', $order);
    }

    /** On this computer only (no real bank): act as if the QR was paid. */
    public function khqrSimulate(Request $request, Order $order)
    {
        $this->authorizeOrder($request, $order);
        abort_unless($order->gateway === 'khqr' && $this->payway->usesTestGateway(), 404);
        $this->processor->apply($order, ['status' => 'paid', 'gateway_status' => 'KHQR_TEST', 'amount' => (float) $order->amount,
            'currency' => $order->currency, 'apv' => 'TEST-KHQR', 'payload' => ['simulated' => true]]);

        return redirect()->route('orders.show', $order);
    }

    private function authorizeOrder(Request $request, Order $order): void
    {
        abort_unless($request->user()->id === $order->user_id || $request->user()->isAdmin(), 404);
    }
}
