<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Endereço base
    |--------------------------------------------------------------------------
    |
    | API de consulta do PNCP. É pública: não existe chave, token nem login.
    |
    */

    'base_url' => env('PNCP_BASE_URL', 'https://pncp.gov.br/api/consulta'),

    /*
    |--------------------------------------------------------------------------
    | Timeout (segundos)
    |--------------------------------------------------------------------------
    |
    | A API responde devagar em horário comercial. Timeout curto derruba
    | importação que estava funcionando, então o padrão é generoso.
    |
    */

    'timeout' => (int) env('PNCP_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Retry com backoff exponencial
    |--------------------------------------------------------------------------
    |
    | Só erro de servidor (5xx), conexão e timeout são repetidos. Erro de
    | cliente (4xx) é defeito no pedido — repetir não conserta, só demora.
    |
    | `sleep` é o primeiro intervalo em milissegundos; dobra a cada tentativa.
    |
    */

    'retry' => [
        'times' => (int) env('PNCP_RETRY_TIMES', 3),
        'sleep' => (int) env('PNCP_RETRY_SLEEP', 1000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Importação
    |--------------------------------------------------------------------------
    |
    | `tamanho_pagina` — o PNCP aceita no máximo 50 (acima disso devolve 400).
    | `janela_dias` — quantos dias à frente olhar. O parâmetro `dataFinal` da
    |   API é o TETO da data de encerramento de propostas: pedir hoje + janela
    |   devolve tudo que ainda aceita proposta até essa data.
    | `paginas_por_execucao` — freio de segurança para o comando não varrer
    |   centenas de páginas numa execução só.
    |
    */

    'importacao' => [
        'tamanho_pagina' => 50,
        'janela_dias' => (int) env('PNCP_JANELA_DIAS', 30),
        'paginas_por_execucao' => (int) env('PNCP_PAGINAS_POR_EXECUCAO', 20),
    ],

];
