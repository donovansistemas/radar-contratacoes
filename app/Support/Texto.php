<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Comparação de texto do motor de match.
 *
 * O PNCP escreve o objeto como quer: "Aquisição", "AQUISICAO", "aquisição ".
 * Se a comparação for literal, o usuário perde oportunidade por causa de
 * acento. Então tudo é reduzido a minúsculas e sem acento antes de comparar.
 */
final class Texto
{
    /**
     * Minúsculas, sem acento e com espaços normalizados.
     */
    public static function normalizar(?string $texto): string
    {
        if ($texto === null) {
            return '';
        }

        $texto = preg_replace('/\s+/u', ' ', Str::ascii($texto)) ?? $texto;

        return mb_strtolower(trim($texto));
    }

    /**
     * O termo aparece no texto começando em uma palavra.
     *
     * O `\b` inicial evita "obra" casar com "manobra". Não há `\b` no fim de
     * propósito: em português o plural muda a última letra ("uniforme" →
     * "uniformes"), e o que importa é o começo da palavra.
     */
    public static function contemTermo(string $texto, string $termo): bool
    {
        if ($termo === '') {
            return false;
        }

        return preg_match('/\b'.preg_quote($termo, '/').'/u', $texto) === 1;
    }
}
