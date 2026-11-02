<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Flagged addresses: Tether freezes, OFAC sanctions, your own list
        Schema::create('flagged_addresses', function (Blueprint $table) {
            $table->id();
            $table->string('address', 34);
            $table->string('source', 20); // tether | ofac | manual
            $table->string('note')->nullable();
            $table->timestamp('flagged_at')->nullable();
            $table->timestamps();
            $table->unique(['address', 'source']);
            $table->index('address');
        });

        Schema::create('scans', function (Blueprint $table) {
            $table->id();
            $table->string('address', 34)->index();
            $table->unsignedTinyInteger('risk_level');
            $table->decimal('usdt_balance', 24, 6)->default(0);
            $table->decimal('taint_percent', 6, 2)->default(0);
            $table->string('exchanges')->nullable();
            $table->json('result');
            $table->string('ip', 45)->nullable();
            $table->timestamps();
        });

        Schema::create('watches', function (Blueprint $table) {
            $table->id();
            $table->string('address', 34);
            $table->string('chat_id', 32);
            $table->string('label')->nullable();
            $table->unsignedBigInteger('last_seen_ms')->default(0);
            $table->boolean('was_blacklisted')->default(false);
            $table->timestamps();
            $table->unique(['address', 'chat_id']);
        });

        Schema::create('sync_states', function (Blueprint $table) {
            $table->string('name')->primary();
            $table->string('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_states');
        Schema::dropIfExists('watches');
        Schema::dropIfExists('scans');
        Schema::dropIfExists('flagged_addresses');
    }
};
