<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('connect_id')->nullable()->constrained('connects')->nullOnDelete();
            $table->string('name');
            $table->string('uniq_name', 16);
            $table->string('target');
            $table->string('ext', 8);
            // 'is_new' — "viewed" flag, not a state machine.
            // Stays true until an admin opens the file; default true.
            $table->boolean('is_new')->default(true);
            // Detected column positions per KeyDetector, e.g.
            //   {"inn": "B", "tel": "D", "okved": "F"}
            // Persisted once at upload time; rows are read with these fixed positions.
            $table->json('detected_columns')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};