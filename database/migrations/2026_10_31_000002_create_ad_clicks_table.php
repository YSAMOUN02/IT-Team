<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** One row per click on your own banner (Admin > Ads shows the counts). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_clicks', function (Blueprint $table) {
            $table->id();
            $table->string('placement', 40);
            $table->char('visitor_id', 32)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['placement', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_clicks');
    }
};
