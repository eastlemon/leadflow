<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pipeline_receivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pipeline_id')->constrained()->cascadeOnDelete();
            $table->string('system_name', 32);        // 'alfa', 'psb', 'vtb', 'ural'
            $table->boolean('is_active')->default(true);
            $table->json('tune')->nullable();         // bank-specific config for this pipeline
            $table->timestamps();

            $table->unique(['pipeline_id', 'system_name']);
            $table->index(['system_name', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pipeline_receivers');
    }
};