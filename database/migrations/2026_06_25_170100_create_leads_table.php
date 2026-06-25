<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('inn', 12)->index();
            $table->string('phone', 32)->nullable()->index();
            $table->string('email', 191)->nullable();
            $table->string('first_name', 64)->nullable();
            $table->string('last_name', 64)->nullable();
            $table->string('middle_name', 64)->nullable();
            $table->string('company_name', 191)->nullable();
            $table->string('city', 128)->nullable();
            $table->string('region', 128)->nullable();
            $table->string('okved', 16)->nullable();
            $table->json('extra')->nullable();
            $table->string('source', 32)->default('skorozvon');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
