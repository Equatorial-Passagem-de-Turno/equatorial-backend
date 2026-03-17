<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('occurrences', function (Blueprint $table) {
            $table->string('id')->primary();

            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->onDelete('cascade');
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->onDelete('set null');

            $table->string('title');
            $table->string('category');
            $table->string('priority')->default('medium');
            $table->string('status')->default('open');
            $table->text('description');

            $table->json('location')->nullable();
            $table->string('link_type')->nullable();
            $table->string('link_value')->nullable();
            $table->json('attachments')->nullable();
            $table->json('comments')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('occurrences');
    }
};
