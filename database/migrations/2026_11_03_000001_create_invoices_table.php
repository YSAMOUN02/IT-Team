<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Invoices: a kept copy of each sale or course access (who, what, how much, how long, paid how).
 * They never change when a course, price or student is edited later, only "refunded" can be set.
 * Existing paid orders and access given by hand get their invoices here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->nullable()->unique(); // INV-2026-00001, set right after insert
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->string('course_title');
            $table->string('student_name');
            $table->string('student_email');
            $table->string('student_phone', 40)->nullable();
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->unsignedInteger('access_days')->nullable(); // null = lifetime
            $table->timestamp('access_until')->nullable();
            $table->string('payment_method', 30); // payway, cash, transfer, free
            $table->string('reference', 60)->nullable(); // PayWay APV / order number
            $table->string('note')->nullable();
            $table->string('status', 20)->default('paid'); // paid, refunded
            $table->timestamp('issued_at')->useCurrent(); // MariaDB needs a default on a NOT NULL timestamp
            $table->timestamps();
            $table->index(['user_id', 'issued_at']);
        });

        $make = function (array $row) {
            $id = DB::table('invoices')->insertGetId($row + ['created_at' => now(), 'updated_at' => now()]);
            DB::table('invoices')->where('id', $id)->update(['number' => 'INV-'.substr((string) $row['issued_at'], 0, 4).'-'.str_pad((string) $id, 5, '0', STR_PAD_LEFT)]);
        };

        // Paid (or since refunded) orders.
        foreach (DB::table('orders')->whereIn('status', ['paid', 'refunded'])->whereNotNull('paid_at')->orderBy('paid_at')->get() as $order) {
            $enrollment = DB::table('enrollments')->where('order_id', $order->id)->first();
            $make([
                'user_id' => $order->user_id, 'order_id' => $order->id, 'course_id' => $order->course_id,
                'course_title' => $order->course_title, 'student_name' => $order->buyer_name, 'student_email' => $order->buyer_email,
                'student_phone' => $order->buyer_phone, 'amount' => $order->amount, 'currency' => $order->currency,
                'access_days' => $order->access_days, 'access_until' => $enrollment?->expires_at,
                'payment_method' => $order->gateway === 'payway' ? 'payway' : $order->gateway, 'reference' => $order->apv ?: $order->number,
                'status' => $order->status, 'issued_at' => $order->paid_at,
            ]);
        }

        // Access an admin gave by hand.
        $manual = DB::table('enrollments')->join('users', 'users.id', '=', 'enrollments.user_id')->join('courses', 'courses.id', '=', 'enrollments.course_id')
            ->where('enrollments.source', 'manual')->whereNull('enrollments.order_id')
            ->orderBy('enrollments.created_at')
            ->get(['enrollments.*', 'users.name as u_name', 'users.email as u_email', 'users.phone as u_phone', 'courses.title as c_title']);
        foreach ($manual as $e) {
            $make([
                'user_id' => $e->user_id, 'course_id' => $e->course_id, 'course_title' => $e->c_title,
                'student_name' => $e->u_name, 'student_email' => $e->u_email, 'student_phone' => $e->u_phone,
                'amount' => 0, 'currency' => 'USD', 'access_until' => $e->expires_at,
                'access_days' => $e->expires_at ? max(1, (int) round((strtotime($e->expires_at) - strtotime($e->starts_at)) / 86400)) : null,
                'payment_method' => 'free', 'note' => 'Access given by an admin', 'status' => 'paid', 'issued_at' => $e->created_at,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
