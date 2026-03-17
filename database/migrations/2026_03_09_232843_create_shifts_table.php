<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');            
            $table->foreignId('operation_desk_id')->constrained('operation_desks');  
            $table->foreignId('previous_shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->string('role'); 
            $table->timestamp('start');
            $table->timestamp('end')->nullable();
            $table->string('status')->default('in_progress');
            $table->text('briefing')->nullable();  
            $table->boolean('handover_acknowledged')->default(false);          
            $table->foreignId('next_operator_id')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
