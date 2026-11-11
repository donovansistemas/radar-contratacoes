# Estrutura do projeto — onde fica cada coisa

## Antes de tudo: aqui não existe controller separado da view

Este projeto usa **Livewire 4 com componentes single-file**. Cada tela é **um único arquivo**:

```
resources/views/pages/perfis/⚡index.blade.php
                             │
                             ├── topo  → <?php ... class extends Component { ... }
                             │           isto é o CONTROLLER: propriedades = estado,
                             │           métodos públicos = ações
                             │
                             └── baixo → <section> ... </section>
                                         isto é a VIEW (Blade + Flux)
```

O `⚡` faz parte do nome do arquivo (U+26A1). É a convenção do Livewire para
componente de arquivo único. `app/Http/Controllers/` está vazio de propósito — o kit só
mantém o `Controller.php` base, sem uso.

Quem liga URL → arquivo é `Route::livewire()`:

```php
// routes/web.php
Route::livewire('perfis', 'pages::perfis.index');
//                 ↑ URL     ↑ namespace "pages::" = resources/views/pages
```

O namespace `pages::` e o layout padrão (`layouts::app`) vêm do `config/livewire.php` do
pacote — não são configuração nossa.

## Árvore

```
radar-contratacoes/
│
├── routes/
│   ├── web.php ................. TODAS as rotas de tela
│   ├── console.php ............. comandos e agendamento (v0.2 entra aqui)
│   └── settings.php ............ rotas de perfil/segurança (kit)
│
├── app/
│   ├── Models/
│   │   ├── Contratacao.php ..... tabela `contratacoes` (scopes, urlPncp())
│   │   ├── PerfilBusca.php ..... tabela `perfis_busca` (casts, termosDeBusca())
│   │   └── User.php ............ kit + relação perfisBusca()
│   │
│   ├── Services/ ............... A REGRA DE NEGÓCIO. Não conhece Blade.
│   │   ├── Pncp/
│   │   │   ├── PncpClient.php ........ faz a requisição (retry, 204, 429)
│   │   │   ├── ContratacaoData.php ... converte o JSON nas colunas da tabela
│   │   │   ├── ContratacoesPage.php .. envelope da paginação
│   │   │   └── PncpException.php ..... erro de API
│   │   ├── Match/
│   │   │   └── MotorDeMatch.php ...... cruza contratação × perfil
│   │   └── Dashboard/
│   │       └── ResumoDoRadar.php ..... agrega os números da tela inicial
│   │
│   ├── Support/
│   │   └── Texto.php ........... normalizar acento / casar termo
│   │
│   ├── Console/Commands/
│   │   └── ImportarContratacoes.php .. `php artisan radar:importar`
│   │
│   ├── Providers/
│   │   └── AppServiceProvider.php ... registra PncpClient e MotorDeMatch
│   │
│   ├── Actions/Fortify/ ......... criação de usuário e reset de senha (kit)
│   └── Concerns/ ................ regras de validação do kit
│
├── resources/views/
│   ├── pages/ ................... AS TELAS (controller + view no mesmo arquivo)
│   │   ├── dashboard/⚡index.blade.php
│   │   ├── oportunidades/⚡index.blade.php
│   │   ├── perfis/⚡index.blade.php
│   │   ├── auth/ ................ login, cadastro, etc (kit)
│   │   └── settings/ ............ perfil, segurança (kit)
│   │
│   ├── components/
│   │   └── barra.blade.php ...... barrinha de proporção do dashboard (nosso)
│   │
│   └── layouts/
│       ├── app.blade.php ........ layout de página logada
│       └── app/sidebar.blade.php  MENU LATERAL — é aqui que se cria item novo
│
├── config/
│   ├── pncp.php ................. base_url, timeout, retry, janela de importação
│   └── radar.php ................ modalidades confirmadas, sinônimos, UFs
│
├── database/
│   ├── migrations/
│   │   ├── 2026_11_10_000638_create_contratacoes_table.php
│   │   ├── 2026_11_10_000700_create_perfis_busca_table.php
│   │   └── 0001_* 2024_* 2025_* . tabelas do kit
│   └── factories/
│       ├── ContratacaoFactory.php ... com estado `encerrada()`
│       └── PerfilBuscaFactory.php ... com estado `inativo()`
│
└── tests/Feature/
    ├── Pncp/PncpClientTest.php ................ 14 testes (fixture real da API)
    ├── Console/ImportarContratacoesTest.php ... 6 testes (deduplicação)
    ├── Match/MotorDeMatchTest.php ............. 15 testes (regras do match)
    ├── Perfis/PerfilBuscaTest.php ............. 10 testes (CRUD + isolamento)
    ├── Oportunidades/OportunidadesTest.php .... 7 testes
    └── Dashboard/ResumoDoRadarTest.php ........ 7 testes (agregação)
        Dashboard/DashboardPageTest.php ........ 4 testes (tela)
```

## Mapa mental: Adianti → aqui

| Adianti | Neste projeto |
|---|---|
| Classe de controle (`extends TPage`) | classe anônima no topo de `⚡index.blade.php` |
| Arquivo `.xml` da tela | a parte de baixo do mesmo arquivo (Blade) |
| `onShow` / `onEdit` / `onSave` | `mount()` / `editar()` / `salvar()` |
| `TSession::setValue()` | propriedades públicas do componente |
| `TField`, `TCombo`, `TCheck` | `flux:input`, `flux:select`, `flux:checkbox` |
| `TDatagrid` | `flux:table` |
| `TButton action="onSave"` | `wire:click="salvar"` |
| `TRepository` / service | `app/Services/**` |
| `TRecord` | `app/Models/**` |
| `$this->form->setData()` | ligação automática via `wire:model` |
| Registro no `menu.xml` | `resources/views/layouts/app/sidebar.blade.php` |
| `TApplication::run()` | `routes/web.php` + `Route::livewire()` |

## O fluxo real, de ponta a ponta

Abrir `/oportunidades`:

```
1. routes/web.php
   Route::livewire('oportunidades', 'pages::oportunidades.index')
        ↓
2. resources/views/pages/oportunidades/⚡index.blade.php
   with() → app(MotorDeMatch::class)
        ↓
3. app/Services/Match/MotorDeMatch.php
   candidatos() → SQL em app/Models/Contratacao.php
   avalia()     → app/Support/Texto.php (acento, plural, sinônimo de config/radar.php)
        ↓
4. Blade renderiza <flux:card> por oportunidade
   layout: resources/views/layouts/app.blade.php
        ↓
5. clique em "Editar" num perfil → wire:click="editar(3)"
   volta para o passo 2, método editar() roda no servidor
```

## Onde mexer para cada tarefa

| Quero… | Vou em |
|---|---|
| mudar texto/botão de uma tela | parte Blade do `⚡index.blade.php` da tela |
| criar uma tela nova | `resources/views/pages/<nome>/⚡index.blade.php` + `Route::livewire()` |
| mudar regra de match | `app/Services/Match/MotorDeMatch.php` |
| adicionar sinônimo | `config/radar.php` → `sinonimos` |
| liberar modalidade nova | `config/radar.php` → `modalidades` |
| ajustar retry/timeout do PNCP | `config/pncp.php` |
| mudar coluna da tabela `contratacoes` | migration nova + `Contratacao` (fillable/casts) + `ContratacaoData` |
| novo filtro na listagem | `MotorDeMatch::candidatos()` se der SQL; Blade se for visual |
| novo item no menu | `resources/views/layouts/app/sidebar.blade.php` |
| novo comando artisan | `app/Console/Commands/` |
| agendamento diário | `routes/console.php` |
| teste | `tests/Feature/<Assunto>/` — arquivos do kit: `tests/Feature/Auth/`, `Settings/` |

## Três coisas que confundem no começo

1. **Não existe "controller" para procurar.** Se você procura `PerfilBuscaController`, não
   existe: está dentro do `⚡index.blade.php`.
2. **Método público do componente = ação chamável do navegador.** Qualquer `public function`
   ali pode ser disparado por `wire:click`. É a mesma lógica de método de tela do Adianti,
   mas sem `TButton` amarrando.
3. **`app/Services` não é do framework.** É convenção nossa, criada porque o projeto tem
   regra de negócio que não pertence nem ao model nem à tela.

## O que é do starter kit (não escrevemos)

`app/Actions/Fortify/`, `app/Concerns/`, `app/Providers/FortifyServiceProvider.php`,
`app/Livewire/Actions/Logout.php`, `resources/views/pages/auth/`,
`resources/views/pages/settings/`, `resources/views/layouts/`, e as migrations
`0001_*`, `2024_*`, `2025_*`. Se algo ali mudar, é atualização do kit.
