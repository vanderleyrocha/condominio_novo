# Contexto do Projeto — Sistema Condomínio (condominio-novo)

## O que é a aplicação
Sistema de gestão financeira de condomínio, reconstruído do zero (rebuild de um sistema legado) em Laravel. O idioma da aplicação e do domínio é **português do Brasil** (locale `pt_BR`, timezone `America/Rio_Branco`). O rebuild busca **paridade com o sistema legado** (relatórios/PDFs validados contra "golden files" do legado, incluindo detalhes como uso de `bcadd` na soma simples e data-base do recibo igual ao legado).

## Stack técnica
- **PHP** ^8.3 (ambiente local roda PHP 8.4.7 NTS x64)
- **Laravel Framework** ^13.8
- **Livewire** ^4.3 — toda a UI é feita com componentes Livewire full-page (rotas apontam direto para classes Livewire; quase não há controllers)
- **Tailwind CSS** ^4 via plugin `@tailwindcss/vite`
- **Vite** ^8 + `laravel-vite-plugin` ^3.1 (Node 22, npm 11)
- **barryvdh/laravel-dompdf** ^3.1 — geração de PDFs (recibos e relatórios)
- **MySQL** (banco `u815349007_condnovo` local via Laragon; `.env.example` sugere SQLite como default do skeleton, mas o ambiente real usa MySQL)
- Sessão, cache e filas usam driver **database**; mail em modo `log`

## Ferramentas de desenvolvimento
- **Pest** ^4.7 + `pest-plugin-laravel` (testes; PHPUnit 12 por baixo) — rodar com `composer test` ou `php artisan test`
- **Laravel Pint** ^1.27 (code style)
- **Laravel Pail** (tail de logs), **Collision**, **Faker** (locale pt_BR), **Mockery**, **Tinker**
- Ambiente local: **Windows 11 + Laragon** (`http://condominio-novo.test`)
- Scripts Composer:
  - `composer setup` — install, `.env`, key:generate, migrate, npm install, build
  - `composer dev` — sobe em paralelo: `artisan serve`, `queue:listen`, `pail` e `vite` (via concurrently)
  - `composer test` — limpa config e roda a suíte

## Arquitetura e organização do código (`app/`)
- `Livewire/` — componentes de página, organizados por módulo: `Acesso` (Login, Usuarios, Perfil), `Cadastros` (Imoveis, Proprietarios), `Financeiro` (Mensalidades, Pagamentos, Receitas, Despesas, Dividas, Resumo, Ipca, CobrancasExtras, Parametros, PainelInicial)
- `Actions/` — ações de domínio por módulo (Acesso, Cadastros, Financeiro)
- `Services/` — `CorrecaoMonetariaService` (correção monetária por IPCA)
- `Enums/` — `StatusMensalidade` (paga, paga_parcial, vencida, em_aberto), `MetodoCorrecao` (soma_simples, composta), `PapelUsuario` (admin, level_one), `ResponsavelPagamento` (proprietario, inquilino)
- `Http/Controllers/PdfController` — único controller relevante: rotas `/pdf/...` para recibos e relatórios em PDF (fluxo não-Livewire, dompdf)
- `Models/` — User, Access, Proprietario, Imovel, Mensalidade, Pagamento, PagamentoMensalidade, Receita, Despesa, DespesaTipo, Ipca, CobrancaExtra, Parametro
- `Policies/`, `Rules/`, `Listeners/`, `Support/`, `Console/Commands/`
- `lang/` — traduções pt_BR (inclusive mensagens de auth)

## Modelo de dados (migrations consolidadas por módulo)
- **Acesso**: `users`, `sessions`, `password_reset_tokens`, `accesses` (log de acessos)
- **Cadastros**: `proprietarios`, `imoveis`
- **Financeiro**: `mensalidades`, `pagamentos`, `pagamento_mensalidades` (pivot pagamento↔mensalidade), `receitas`, `despesas`, `despesa_tipos`, `ipcas` (índices mensais), `cobrancas_extras`, `cobranca_extra_mensalidade` (pivot), `parametros` (configurações financeiras)
- Infra Laravel: `cache`, `jobs`, `failed_jobs`, `job_batches`

## Regras e convenções importantes
- Código de domínio nomeado em **português** (Mensalidade, Divida, Cobranca, Proprietario etc.); manter esse padrão
- `declare(strict_types=1)` nos arquivos PHP
- Rotas todas **nomeadas e explícitas** (sem catch-all, sem endpoints JSON auxiliares) — decisão de arquitetura documentada em comentários (referências DA-xx / BR-DESCARTAR-xxx do levantamento de requisitos)
- Painel inicial (`/`) é o resumo financeiro real
- Cálculos financeiros usam **BCMath** (`bcadd` etc.) para reproduzir centavo a centavo os valores do sistema legado
- Autenticação simples com dois papéis (`admin`, `level_one`); login via componente Livewire, logout via POST

## Fluxos principais
1. **Mensalidades**: lançamento em lote, edição individual, grade anual por imóvel, relatórios
2. **Pagamentos**: registro (podendo quitar várias mensalidades), detalhe, estorno, recibo em PDF
3. **Dívidas**: listagem geral e por imóvel, com correção monetária pelo IPCA (soma simples ou composta), PDFs consolidado e por imóvel
4. **Receitas/Despesas**: lançamentos avulsos, despesas por tipo, relatório de despesas por período em PDF
5. **Resumo financeiro**: histórico e por intervalo, com exportação em PDF
6. **Administração**: gestão de índices IPCA, cobranças extras vinculadas a mensalidades, parâmetros do sistema

## Instruções para o assistente (InnerAI)
- Responda sempre em **português do Brasil**
- Ao sugerir código, siga: Laravel 13, Livewire 4 (componentes full-page), Tailwind 4, Pest para testes, Pint para style
- Preserve nomes de domínio em português e o padrão de módulos (Acesso / Cadastros / Financeiro)
- Em cálculos financeiros, use BCMath e mantenha paridade com o comportamento legado (não "arredondar diferente" sem motivo)
- Não crie rotas catch-all nem endpoints JSON auxiliares; toda rota deve ser nomeada
- PDFs são gerados com dompdf via `PdfController` + views Blade dedicadas; atenção a larguras de coluna (houve correções de corte em PDFs largos)
