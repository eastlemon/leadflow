<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user bank connection (the multi-tenant bit).
 *
 * A "system_name" (alfa/psb/vtb/ural) is just a bank type.
 * Each user has their own credentials + per-bank settings,
 * and only their user_connects are picked up when scoring a lead
 * that originated from that user.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_connects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('system_name', 32);
            $table->boolean('is_active')->default(true);
            $table->string('display_name', 64)->nullable();
            /** @see App\Models\Connect for the schema of `tune` (api_url, api_key, ...) */
            $table->json('tune')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'system_name']);
            $table->index(['system_name', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_connects');
    }
};
