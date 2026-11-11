<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabela modelada a partir do JSON real de /v1/contratacoes/proposta.
 *
 * Nomes em português porque o domínio é brasileiro e o resto do sistema
 * (telas, perfis, relatórios) fala português. O que veio da API mantém o
 * significado original; nada foi renomeado por gosto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contratacoes', function (Blueprint $table) {
            $table->id();

            // Identidade no PNCP. O formato é
            // {cnpj do órgão}-{poder/ente}-{sequencial}/{ano} e é único por compra.
            // É a chave da deduplicação: o job pode rodar quantas vezes quiser.
            $table->string('numero_controle_pncp')->unique();

            // órgãoEntidade
            $table->string('orgao_cnpj', 14)->nullable();
            $table->string('orgao_razao_social')->nullable();
            $table->string('orgao_poder', 1)->nullable();   // N, E, L...
            $table->string('orgao_esfera', 1)->nullable();  // F, E, M, D

            // unidadeOrgao
            $table->string('unidade_nome')->nullable();
            $table->string('unidade_codigo', 20)->nullable();
            $table->char('uf', 2)->nullable();
            $table->string('municipio_nome')->nullable();
            $table->string('municipio_codigo_ibge', 7)->nullable();

            // objetoCompra / informacaoComplementar
            $table->text('objeto')->nullable();
            $table->text('informacao_complementar')->nullable();

            // valorTotalEstimado / valorTotalHomologado
            $table->decimal('valor_estimado', 15, 2)->nullable();
            $table->decimal('valor_homologado', 15, 2)->nullable();

            // modalidade / modo de disputa / situação
            $table->smallInteger('modalidade_id')->nullable();
            $table->string('modalidade_nome')->nullable();
            $table->smallInteger('modo_disputa_id')->nullable();
            $table->string('modo_disputa_nome')->nullable();
            $table->smallInteger('situacao_id')->nullable();
            $table->string('situacao_nome')->nullable();
            $table->boolean('srp')->default(false);

            // identificação da compra
            $table->smallInteger('ano_compra')->nullable();
            $table->integer('sequencial_compra')->nullable();
            $table->string('numero_compra')->nullable();
            $table->string('processo')->nullable();
            $table->string('amparo_legal')->nullable();
            $table->string('tipo_instrumento')->nullable();

            // links publicados pelo órgão
            $table->text('link_sistema_origem')->nullable();
            $table->text('link_processo_eletronico')->nullable();

            // datas — o PNCP manda `2026-11-13T09:00:00`, sem fuso
            $table->dateTime('data_abertura_proposta')->nullable();
            $table->dateTime('data_encerramento_proposta')->nullable();
            $table->dateTime('data_publicacao')->nullable();
            $table->dateTime('data_inclusao')->nullable();
            $table->dateTime('data_atualizacao')->nullable();

            // controle local
            $table->dateTime('importado_em')->nullable();
            $table->timestamps();

            // Índices pelo que as telas e o match consultam: filtrar por UF,
            // por prazo de proposta e por modalidade. A busca por palavra-chave
            // no objeto é feita em PHP — ver README.
            $table->index('uf');
            $table->index('data_encerramento_proposta');
            $table->index(['uf', 'data_encerramento_proposta']);
            $table->index('modalidade_id');
            $table->index('orgao_cnpj');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contratacoes');
    }
};
