<?php

namespace App\Services\Pncp;

use RuntimeException;

/**
 * Falha ao conversar com a API do PNCP.
 *
 * Existe para separar "o PNCP não respondeu" de "o meu código quebrou":
 * o comando de importação trata esta exceção e segue, em vez de estourar
 * um stack trace no meio da madrugada.
 */
class PncpException extends RuntimeException
{
    /**
     * Erro de cliente (4xx): o pedido está errado, repetir não adianta.
     */
    public static function requisicaoInvalida(int $status, ?string $mensagem = null): self
    {
        return new self(sprintf(
            'PNCP recusou a requisição (HTTP %d)%s',
            $status,
            $mensagem !== null && $mensagem !== '' ? ": {$mensagem}" : '.',
        ));
    }

    /**
     * Erro de servidor (5xx), conexão ou timeout: já tentamos de novo e não foi.
     */
    public static function indisponivel(int $tentativas, ?string $motivo = null): self
    {
        return new self(sprintf(
            'PNCP indisponível após %d tentativa(s)%s',
            $tentativas,
            $motivo !== null && $motivo !== '' ? ": {$motivo}" : '.',
        ));
    }

    /**
     * Resposta 200 com corpo que não é o JSON esperado.
     */
    public static function respostaInvalida(string $motivo): self
    {
        return new self("Resposta do PNCP em formato inesperado: {$motivo}.");
    }
}
