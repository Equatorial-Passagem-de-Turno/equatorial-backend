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
            
            // Relacionamentos
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null'); // O autor da ocorrência
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->onDelete('cascade'); 
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->onDelete('set null');
            
            // Dados Principais
            $table->string('title');
            $table->string('category'); // Mudamos de 'type' para 'category' para bater com o React
            $table->string('priority')->default('média'); // baixa, média, alta, crítica
            $table->string('status')->default('Aberta');
            $table->text('description');
            
            // Dados Opcionais e Aninhados (JSON)
            $table->json('location')->nullable(); // Vai guardar o objeto { address, city, etc }
            $table->string('link_type')->nullable(); // 'OS' ou 'External'
            $table->string('link_value')->nullable(); // O número da OS
            $table->json('attachments')->nullable(); // Vai guardar as strings em Base64 das imagens
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('occurrences');
    }
};