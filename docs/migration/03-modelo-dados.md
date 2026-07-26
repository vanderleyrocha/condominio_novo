# Especificação do Novo Modelo de Dados

Este documento descreve o modelo de dados alvo para a migração do sistema de
administração de condomínio. Deve ser usado como referência ao gerar migrations,
models Eloquent, factories e seeders.

## Stack

- PHP / Laravel
- MySQL (InnoDB, utf8mb4_unicode_ci)
- Convenções Laravel padrão: chaves primárias bigint unsigned auto-increment,
  timestamps (created_at/updated_at), soft deletes onde indicado.

## Convenções obrigatórias

- Toda tabela financeira ou de entidade principal deve ter `deleted_at` (SoftDeletes).
- Toda FK deve ter índice explícito.
- Valores monetários: `decimal(12,2)` (exceto tabelas de rateio menor, que podem usar `decimal(10,2)`).
- Aritmética monetária SEMPRE via BCMath sobre strings decimais, nunca float
  (requisito de paridade com o legado — golden files).
- Convenção de sinal: pagamentos de estorno têm `valor_total`/`valor_aplicado`
  NEGATIVOS (mesma convenção do legado); `estorno_de_id` carrega a semântica.
- Enums: usar colunas `string` no MySQL + backed enums PHP em `app/Enums` com cast
  no Model (padrão já adotado no projeto — ex.: `StatusMensalidade`,
  `ResponsavelPagamento`). NÃO usar `enum()` do MySQL: alterar exige ALTER TABLE
  custoso e a validação já vive na aplicação.
- Todas as tabelas novas devem ter Model Eloquent correspondente com:
  - `$fillable` ou `$guarded` explícito
  - Relacionamentos (`belongsTo`, `hasMany`, `belongsToMany`) mapeados
  - Casts de data/decimal/enum quando aplicável

## Constraints de unicidade obrigatórias

| Tabela                 | Unique                                              |
|------------------------|-----------------------------------------------------|
| condominios            | cnpj                                                |
| pessoas                | cpf_cnpj (múltiplos NULLs permitidos)               |
| taxas_condominiais     | (unidade_id, competencia_ano, competencia_mes) — a duplicidade legada (N-01) foi saneada; a garantia sobe para o banco |
| pagamento_taxa         | (pagamento_id, taxa_condominial_id)                 |
| cobranca_extraordinaria_taxa | (cobranca_extraordinaria_id, taxa_condominial_id) |
| indices_economicos     | (tipo, ano, mes)                                    |
| configuracoes          | (condominio_id, chave)                              |
| condominio_user        | (condominio_id, user_id)                            |
| users                  | name (campo de login — BR-HUMANA-001), email        |

## Tabelas (ver 01-diagrama-er.mmd para o ER completo)

### condominios
Entidade raiz. Todo dado de negócio pertence, direta ou indiretamente, a um condomínio.
Prepara o sistema para multi-condomínio (SaaS) mesmo que hoje exista apenas um registro.

### blocos
Agrupamento opcional de unidades dentro de um condomínio (torres, blocos, alas).

### unidades
Substitui `imoveis`. Adiciona `fracao_ideal`, `area`, `vagas_garagem` e vínculo com bloco.
A fração ideal é essencial para ratear despesas extraordinárias proporcionalmente.

### pessoas
Entidade genérica de pessoa física ou jurídica. Substitui os campos soltos de
inquilino em `proprietarios`.

### unidade_pessoa
Tabela pivot que representa o vínculo de uma pessoa com uma unidade, com papel
(proprietário, inquilino, procurador) e período de vigência. Permite histórico
completo de ocupação da unidade ao longo do tempo.

Regra de negócio (validada em nível de aplicação, no Action de salvamento):
no máximo 1 vínculo com `responsavel_financeiro = true` vigente
(`data_fim` nula ou futura) por unidade a cada momento.

### planos_contas
Plano de contas simplificado para categorizar lançamentos financeiros
(substitui `despesa_tipos`, generalizado para receita e despesa).

### lancamentos_financeiros
Tabela unificada de receitas e despesas (substitui `receitas` + `despesas`).
Campo `origem_type`/`origem_id` (polimórfico) permite rastrear a origem do
lançamento (ex.: uma cobrança extraordinária, um contrato, etc.) sem precisar
de várias tabelas específicas. `plano_conta_id` é NOT NULL — todo lançamento
tem classificação contábil; a origem polimórfica é rastreio, não classificação.
Na migração, `data_competencia = data_lancamento` (o legado só tem uma data);
relatórios por competência ficarão idênticos aos por caixa até ajuste manual.

### taxas_condominiais
Substitui `mensalidades`. O campo `status` é derivado/recalculado a partir da
soma de pagamentos em `pagamento_taxa` — não deve ser a fonte de verdade isolada,
apenas um cache de leitura. O recálculo deve viver em um único serviço
(`valor_devido = valor_original + valor_acrescimo - valor_desconto`, BCMath),
usado tanto pela migração de dados quanto pela aplicação em produção.
Unique composta `(unidade_id, competencia_ano, competencia_mes)` no banco.
`contabilizado` é mantido (decisão fechada — ver 02-mapeamento-de-para.md, seção 3).

### pagamentos
Registra pagamentos efetuados. `estorno_de_id` (auto-relacionamento) substitui
a combinação confusa de `estornado` + `pagamento_origem_id` do modelo antigo.
Estornos mantêm `valor_total` negativo (convenção do legado) — somas diretas
permanecem corretas sem tratamento especial. `unidade_id` é nullable
(o legado possui pagamentos sem imóvel associado).

Nome durante a coexistência: a tabela antiga `pagamentos` não pode ser alterada
até a Fase 4, então a nova tabela nasce como **`pagamentos_novo`** (Model
`PagamentoNovo`). No cutover, RENAME TABLE para `pagamentos` e renomeação da
classe para `Pagamento` (a antiga é removida no mesmo passo). É a única tabela
do modelo novo com nome temporário.

### pagamento_taxa
Pivot N:N entre pagamentos e taxas condominiais, permitindo que um pagamento
quite múltiplas taxas ou que uma taxa seja paga em partes.

### cobrancas_extraordinarias
Substitui `cobrancas_extras`. Adiciona `metodo_rateio` para definir como o
valor é distribuído entre as unidades (fração ideal, igualitário, manual).

### cobranca_extraordinaria_taxa
Substitui `cobranca_extra_mensalidade`. Vincula uma cobrança extraordinária
às taxas condominiais geradas para cada unidade.

### indices_economicos
Generaliza `ipcas` para suportar múltiplos tipos de índice (IPCA, IGPM, etc.).

### regras_reajuste
Nova tabela. Define a periodicidade e o índice usado para reajustar taxas
condominiais de um condomínio — inexistente no modelo antigo.
A regra referencia o **tipo** do índice (`tipo_indice`, enum PHP
`TipoIndiceEconomico`), não uma FK para `indices_economicos`: a série mensal
inteira é usada no cálculo, não uma linha específica (correção sobre a versão
inicial do ER).

### configuracoes
Substitui parcialmente `parametros`, mas escopada por condomínio (ou global
se `condominio_id` for nulo).

### users
Mantida, com adição de `pessoa_id` (nullable) para vincular o usuário logado
a um registro de pessoa (proprietário/inquilino) quando aplicável.
`name` continua sendo o campo de login e permanece unique (BR-HUMANA-001).
`papel` convertido de enum MySQL para string(20) + backed enum PHP
(`PapelUsuario`), aceitando os papéis do novo controle de acesso.

## Controle de acesso (decisão fechada em 2026-07)

Três papéis (`users.papel`, enum PHP `PapelUsuario`) + dois eixos de escopo.
Autorização via Policies (padrão já usado no projeto) — sem tabela de
permissões dinâmicas: neste porte, papéis fixos + Policies são mais simples,
testáveis e suficientes; se surgir necessidade de permissões por usuário,
migrar para spatie/laravel-permission SEM mudar as assinaturas das Policies.

| Papel          | Alcance                                                              |
|----------------|----------------------------------------------------------------------|
| `admin`        | Tudo: usuários, configurações, cadastros, financeiro, relatórios.    |
| `sindico`      | Gestão completa (cadastros/financeiro/relatórios) dos condomínios vinculados em `condominio_user`. Não gerencia usuários nem configurações globais. |
| `proprietario` | Portal do condômino: leitura das próprias unidades, taxas, pagamentos e recibos — resolvidos via `users.pessoa_id` → `unidade_pessoa`. Requer `pessoa_id` preenchido. |

Eixos de escopo:
1. **Condomínio** — `condominio_user` limita o que `sindico` enxerga
   (Global Scope no cutover; hoje há 1 condomínio).
2. **Pessoa** — `pessoa_id` limita o que `proprietario` enxerga.

Transição: o papel legado `level_one` (16 users) é remapeado para `sindico` no
cutover (Fase 4), quando as Policies são reescritas e a regra
`data_corte_level_one` é aposentada (era restrição do modelo antigo). O case
`LevelOne` do enum permanece como deprecated até lá e é removido na Fase 5.
O papel `proprietario` nasce sem usuários — contas são criadas/vinculadas
manualmente (proprietários do legado não têm e-mail para match automático).

### condominio_user
Pivot que escopa o acesso de cada usuário aos condomínios que ele administra.
Completa a preparação multi-condomínio (SaaS): sem ela, todo usuário enxergaria
todos os condomínios. Na migração, vincular todos os users existentes ao
condomínio único criado na fase 1. Enquanto houver apenas 1 condomínio, um
Global Scope no Model resolve o filtro de forma transparente.

### audits
Auditoria via pacote `owen-it/laravel-auditing` (decisão fechada — substitui a
tabela `auditorias` manual das versões anteriores deste documento). A migration
e o schema da tabela `audits` são os do próprio pacote; basta implementar a
interface `Auditable` nos Models relevantes (financeiros e de cadastro).

## Tabelas de infraestrutura Laravel (sem alteração)

`sessions`, `jobs`, `job_batches`, `failed_jobs`, `cache`, `cache_locks`,
`migrations`, `password_reset_tokens`, `accesses` — mantidas como estão.
