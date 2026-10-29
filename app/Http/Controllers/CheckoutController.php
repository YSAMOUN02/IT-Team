<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AsksGuestsToSignIn;
use App\Models\Course;
use App\Models\Order;
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

    public function __construct(private PayWay $payway, private OrderProcessor $processor)
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
            'available' => $this->payway->available(),
            'testGateway' => $this->payway->usesTestGateway(),
        ]);
    }

    public function store(Request $request, Course $course)
    {
        abort_unless($course->isPurchasable(), 404);
        if ($request->user()->courseAccessOff($course)) {
            return redirect()->route('courses.show', $course->slug)->with('checkout_notice', __('account.access_off'));
        }

        $data = $request->validate([
            'plan_id' => ['required', Rule::exists('course_plans', 'id')->where('course_id', $course->id)],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'payment_option' => ['nullable', Rule::in(PayWay::PAYMENT_OPTIONS)],
            'terms' => ['accepted'],
        ]);

        // The price always comes from the plan on the server, never from the form.
        $plan = $course->plans->firstWhere('id', (int) $data['plan_id']);
        $amount = $plan->currentPrice();
        abort_unless($amount <= 0 || $this->payway->available(), 404);

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
            'gateway' => $amount <= 0 ? 'free' : ($this->payway->usesTestGateway() ? 'test' : 'payway'),
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

    private function authorizeOrder(Request $request, Order $order): void
    {
        abort_unless($request->user()->id === $order->user_id || $request->user()->isAdmin(), 404);
    }
}
