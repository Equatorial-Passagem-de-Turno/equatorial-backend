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
        Schema::create('unavailable_equipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->constrained('shifts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');

            $table->string('equipment_number'); // Número do equipamento
            $table->string('equipment_type'); // Tipo de equipamento
            $table->string('feeder'); // Alimentador

            $table->string('responsible_sector'); // Setor responsável
            $table->text('observations')->nullable(); // Observações
            $table->dateTime('deadline'); // Prazo

            $table->enum('status', ['indisponivel', 'disponivel'])->default('indisponivel');

            $table->json('attachments')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unavailable_equipment');
    }
};
