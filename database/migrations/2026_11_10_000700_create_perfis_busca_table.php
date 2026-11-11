<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Perfil de busca: o que o usuário quer ser avisado quando aparecer.
 *
 * Listas (palavras, UFs, modalidades) ficam em JSON porque são poucas,
 * sempre lidas junto com o perfil e nunca consultadas isoladamente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('perfis_busca', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('nome');

            // Precisa aparecer ao menos um destes termos no objeto.
            $table->json('palavras_chave');

            // Se qualquer um destes aparecer, a contratação é descartada.
            $table->json('palavras_excluidas')->nullable();

            // Vazio = qualquer UF / qualquer modalidade.
            $table->json('ufs')->nullable();
            $table->json('modalidades')->nullable();

            // Faixa de valor estimado. Null = sem limite.
            $table->decimal('valor_min', 15, 2)->nullable();
            $table->decimal('valor_max', 15, 2)->nullable();

            $table->boolean('ativo')->default(true);

            $table->timestamps();

            $table->index(['user_id', 'ativo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('perfis_busca');
    }
};
