<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('start');
            $table->timestamp('end')->nullable();
            $table->string('status'); // 'in_progress', 'finished'
            $table->string('voltage_level'); // 'low', 'medium', 'high'
            $table->foreignId('previous_shift_id')->nullable()->constrained('shifts');
            $table->text('observations')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
