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
        Schema::create('circuit_switchings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->constrained('shifts')->cascadeOnDelete(); // Vincula ao turno
            $table->foreignId('user_id')->constrained('users'); // Quem registrou

            $table->string('feeder'); // Alimentador
            $table->string('equipment'); // Equipamento
            $table->integer('affected_clients')->default(0); // Clientes afetados
            $table->string('responsible_sector'); // Setor responsável
            $table->text('reason'); // Causa da manobra
            $table->text('observations')->nullable(); // Observações

            $table->dateTime('deadline'); // Prazo inicial
            $table->dateTime('new_deadline')->nullable(); // Novo prazo (histórico)

            $table->enum('status', ['manobrado', 'normalizado'])->default('manobrado'); // Estado atual

            $table->json('attachments')->nullable();
            $table->timestamps();
        });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('circuit_switchings');
    }
};
