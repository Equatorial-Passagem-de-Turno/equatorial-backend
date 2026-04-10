<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->index(['user_id', 'status'], 'idx_shifts_user_status');
            $table->index(['operation_desk_id', 'status'], 'idx_shifts_desk_status');
            $table->index(['start'], 'idx_shifts_start');
        });

        Schema::table('occurrences', function (Blueprint $table) {
            $table->index(['shift_id', 'status'], 'idx_occ_shift_status');
            $table->index(['status', 'created_at'], 'idx_occ_status_created_at');
            $table->index(['user_id', 'created_at'], 'idx_occ_user_created_at');
        });
    }

    public function down(): void
    {
        Schema::table('occurrences', function (Blueprint $table) {
            $table->dropIndex('idx_occ_shift_status');
            $table->dropIndex('idx_occ_status_created_at');
            $table->dropIndex('idx_occ_user_created_at');
        });

        Schema::table('shifts', function (Blueprint $table) {
            $table->dropIndex('idx_shifts_user_status');
            $table->dropIndex('idx_shifts_desk_status');
            $table->dropIndex('idx_shifts_start');
        });
    }
};
