<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Modalidades de contratação
    |--------------------------------------------------------------------------
    |
    | Lista montada consultando o PNCP código por código: para cada um, a API
    | devolveu `modalidadeNome` nos registros. Nada aqui foi copiado de
    | documentação nem deduzido.
    |
    | Códigos ausentes e o motivo:
    |
    |   2, 14  → HTTP 204 (nenhum registro em 30 dias; nome não confirmado)
    |   8, 9   → HTTP 500 (o próprio PNCP quebra nesses códigos)
    |
    | Faltar é melhor do que chutar: um filtro com nome errado faz o usuário
    | acreditar que não existe oportunidade quando existe.
    |
    */

    'modalidades' => [
        1 => 'Leilão - Eletrônico',
        3 => 'Concurso',
        4 => 'Concorrência - Eletrônica',
        5 => 'Concorrência - Presencial',
        6 => 'Pregão - Eletrônico',
        7 => 'Pregão - Presencial',
        10 => 'Manifestação de Interesse',
        11 => 'Pré-qualificação',
        12 => 'Credenciamento',
        13 => 'Leilão - Presencial',
        15 => 'Chamada pública',
    ],

    /*
    |--------------------------------------------------------------------------
    | Sinônimos usados pelo motor de match
    |--------------------------------------------------------------------------
    |
    | Cada grupo vale nos dois sentidos: quem cadastra "uniforme" também
    | encontra "fardamento". Os termos são comparados sem acento e em
    | minúsculas.
    |
    | É uma lista curta de propósito. Sinônimo inventado gera falso positivo,
    | e falso positivo neste produto custa tempo do usuário lendo edital que
    | não serve.
    |
    */

    'sinonimos' => [
        ['uniforme', 'fardamento', 'farda'],
        ['merenda', 'alimentação escolar', 'gêneros alimentícios'],
        ['limpeza', 'asseio', 'higienização'],
        ['medicamento', 'fármaco', 'medicamentos'],
        ['combustível', 'gasolina', 'diesel', 'etanol'],
        ['informática', 'computador', 'microcomputador', 'notebook'],
        ['veículo', 'automóvel', 'carro', 'caminhonete'],
        ['obra', 'construção', 'reforma', 'edificação'],
        ['vigilância', 'segurança patrimonial', 'vigia'],
        ['transporte escolar', 'locação de veículos', 'ônibus escolar'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Unidades da federação
    |--------------------------------------------------------------------------
    */

    'ufs' => [
        'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO',
        'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI',
        'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO',
    ],

];
