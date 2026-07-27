# Plano — Composição de Taxas Condominiais e Finalidade das Receitas

> **Status: executado em 2026-07-26.** Etapas 0–5 e 7 concluídas e aplicadas no
> banco `u815349007_condnovo`; Etapa 6 entregue mas **deliberadamente não
> aplicada** — o gate `composicao:conferir-pivo` bloqueia o drop enquanto a
> inconsistência N-02 (taxa #2010) não for decidida. Resultado da execução no
> §8, ao final.

Complementa `03-modelo-dados.md` (especificação) e `04-plano-migracao.md` (fases da
remodelagem, já concluídas). Este documento é a fonte de verdade da **evolução
pós‑cutover** que transforma a taxa condominial de um lançamento único em uma
**composição de itens**, e introduz **finalidade** (destinação) em todas as fontes
de receita.

## 1. Problema

`taxas_condominiais.valor_original` é um valor único por competência. Consequências:

- Não é possível cobrar, na mesma mensalidade, a taxa ordinária **mais** uma
  contribuição adicional de forma discriminada.
- Hoje existem **482 taxas com `valor_original = 150,00`** (competências 2024‑10 a
  2026‑09) que na verdade são **100,00 de taxa condominial + 50,00 de taxa para
  pintura do prédio**. O condômino não vê a discriminação, e o condomínio não sabe
  quanto já arrecadou especificamente para a pintura.
- Não há como responder "quanto entrou para a finalidade X", nem para taxas nem
  para `lancamentos_financeiros` (ex.: `Rendimento(s) da conta`, que são rendimentos
  da poupança da pintura, e as 6 contribuições para o conserto da bomba).

### Estado real do banco (levantado em 2026‑07‑26)

| Fato | Valor |
|---|---|
| Taxas com `valor_original = 150,00` | 482 (2024‑10 a 2026‑09) |
| Dessas, com desconto ou acréscimo ≠ 0 | 0 |
| Dessas, com pagamento aplicado | 201 (181 `pago`, 20 `pago_parcial`, 281 `aberto`) |
| Total de taxas na tabela | 2.496 (demais faixas: 0,00 / 50 / 55 / 60 / 70 / 75 / 100) |
| Taxas com `valor_original = 0,00` | 54 (2014‑01 a 2018‑06) |
| `cobrancas_extraordinarias` | 1 — "Poupança pintura do prédio", 50,00, vigência 2024‑04‑30 → 2026‑12‑31 |
| `cobranca_extraordinaria_taxa` | 182 linhas — **cobertura parcial e inconsistente** |
| Receitas em `lancamentos_financeiros` | 11 (5 de rendimento da conta = R$ 1.111,01; 6 contribuições de R$ 30 para a bomba) |

**Inconsistência N‑02 (nova):** das 182 linhas do pivô da cobrança extraordinária,
181 apontam para taxas de 150,00 (onde os 50,00 estão embutidos) e **1 aponta para
uma taxa de 100,00** (onde não estão). E **300 das 482 taxas de 150,00 não têm
anotação nenhuma** no pivô. O pivô, portanto, não é utilizável como fonte de
verdade da composição — será substituído pelos itens e descontinuado.

## 2. Decisões fechadas (2026‑07‑26)

| # | Decisão | Escolha |
|---|---|---|
| D‑01 | Estrutura da composição | Nova tabela `itens_taxa_condominial`; a taxa passa a ser o **contêiner** (a "fatura mensal") |
| D‑02 | `taxas_condominiais.valor_original` | **Permanece** como cache de leitura = `SUM(itens.valor)`, escrito exclusivamente por `ComposicaoTaxaService` (mesmo padrão de `status` / `StatusTaxaService`) |
| D‑03 | Rateio do pago entre itens | **Cascata por ordem** — o total pago quita os itens na ordem `ordem` (taxa ordinária primeiro, contribuições depois). Derivado, sem tabela de alocação |
| D‑04 | Modelo da contribuição recorrente | `cobrancas_extraordinarias` permanece como a **campanha** (ganha `finalidade_id`) e passa a **gerar** um item em cada taxa dentro da vigência. `cobranca_extraordinaria_taxa` é descontinuado |
| D‑05 | Finalidade de `Rendimento(s) da conta` | Finalidade **"Pintura do prédio"** — receita financeira segue a destinação do principal aplicado |
| D‑06 | Sinal em estornos | Inalterado: `valor_aplicado` negativo. O rateio em cascata opera sobre a **soma** de `pagamento_taxa`, então estornos se anulam naturalmente |

**Premissa assumida (confirmar ou ajustar):** as 6 receitas "Contribuição … conserto
da bomba" recebem uma finalidade nova **"Conserto da bomba"** (encerrada). Se
preferir deixá‑las sem finalidade, basta remover esse trecho do backfill.

### Consequência de D‑03 a registrar no manual

Numa taxa 100 (ordinária) + 50 (pintura), um pagamento parcial de 75,00 é rateado
como **75 para o custeio ordinário e 0 para a pintura**. A arrecadação da campanha
só é reconhecida depois de quitada a parcela ordinária da competência. Isso é
intencional (protege o custeio), mas precisa estar explícito no relatório de
finalidade, senão a leitura de "quanto arrecadamos para a pintura" parece baixa.

## 3. Modelo alvo

### 3.1 Nova tabela `finalidades`

Destinação/afetação de receita — "para que serve o dinheiro que entra".
Transversal: referenciada por itens de taxa, lançamentos financeiros e campanhas.

| Coluna | Tipo | Notas |
|---|---|---|
| `id` | bigint UN AI | |
| `condominio_id` | FK `condominios` | `restrictOnDelete` |
| `nome` | string(100) | |
| `descricao` | text nullable | |
| `meta_valor` | decimal(12,2) nullable | meta de arrecadação (ex.: orçamento da pintura) |
| `restrita` | boolean default false | recursos carimbados — o saldo **não** entra no disponível para custeio ordinário (§3.1.1) |
| `vigencia_inicio` | date nullable | |
| `vigencia_fim` | date nullable | `null` = permanente |
| `ativa` | boolean default true | |
| `timestamps`, `deleted_at` | | SoftDeletes (regra de `03-modelo-dados.md`) |

Unique: `(condominio_id, nome)` → `uk_finalidade_nome`.
Índice: `ativa`.

Seed inicial: **"Custeio ordinário"** (permanente, é a finalidade default da taxa
condominial) e **"Pintura do prédio"** (`meta_valor` a definir, vigência espelhando
a campanha).

#### 3.1.1 Segregação do saldo (`restrita`)

O problema: um saldo final de R$ 11.535,82 no Resumo financeiro dá a impressão
de caixa livre, quando R$ 10.129,01 dele estão destinados à pintura do prédio.

Uma finalidade `restrita` é **dinheiro carimbado**: está em caixa, mas não pode
custear despesa corrente de manutenção e administração. Regras:

- **Saldo do fundo** = `arrecadado − gasto`, onde `arrecadado` é o já definido
  (rateio em cascata + receitas com a finalidade) e `gasto` são as despesas de
  `lancamentos_financeiros` lançadas contra ela. Sem descontar o gasto o fundo
  só cresceria, e a pintura, depois de paga, continuaria bloqueando caixa.
- **Total vinculado** = soma dos saldos das finalidades restritas, com cada
  saldo **truncado em zero**. Fundo negativo (gastou-se mais do que se
  arrecadou) não vira crédito para os outros: o buraco aparece na linha da
  finalidade, no relatório, não no total.
- **Disponível para custeio** = `saldo final − total vinculado`, sempre por
  SUBTRAÇÃO do saldo final — nunca somando as finalidades livres em paralelo,
  senão os dois números divergem quando algo escapa da classificação.
- A leitura é **acumulada desde o início** (um fundo não tem recorte de
  período), o que casa com o saldo final da tela, que inclui o saldo anterior
  ao filtro `a partir de`.
- **Disponível negativo** = o custeio corrente está consumindo recursos
  vinculados. A tela avisa explicitamente em vez de esconder.

Default `false`: nada muda para o que já existe; marcar é decisão do síndico,
no CRUD de finalidades. Consumido por `RateioPorFinalidadeService::
vinculadoPorFinalidade()` / `somarSaldoVinculado()`, no Resumo financeiro (tela
e PDF) e no relatório por finalidade.

### 3.2 Nova tabela `itens_taxa_condominial`

Um item = uma linha cobrada dentro da mensalidade.

| Coluna | Tipo | Notas |
|---|---|---|
| `id` | bigint UN AI | |
| `taxa_condominial_id` | FK `taxas_condominiais` | `cascadeOnDelete` |
| `plano_conta_id` | FK `planos_contas` | NOT NULL — classificação contábil do item |
| `finalidade_id` | FK `finalidades` nullable | destinação; `null` = custeio geral |
| `descricao` | string | ex.: "Taxa condominial", "Taxa para pintura do prédio" |
| `valor` | decimal(10,2) | tabela de rateio menor (regra de `03-modelo-dados.md`) |
| `ordem` | unsignedSmallInteger default 0 | **ordem de quitação em cascata** (D‑03); 0 = ordinária |
| `origem_type` / `origem_id` | `nullableMorphs('origem')` | rastreio: `CobrancaExtraordinaria` quando gerado por campanha |
| `timestamps`, `deleted_at` | | SoftDeletes |

Índices: `(taxa_condominial_id, ordem)`, `finalidade_id`.
Unique: `(taxa_condominial_id, descricao)` → `uk_item_taxa_descricao` — impede
duplicar a mesma linha na mesma competência (e torna o ETL idempotente por
construção).

### 3.3 Alterações em tabelas existentes

- `lancamentos_financeiros`: `+ finalidade_id` (FK nullable, `restrictOnDelete`, índice).
- `cobrancas_extraordinarias`: `+ finalidade_id` (FK nullable) e `+ valor_por_unidade`
  decimal(10,2) nullable — o valor do item gerado por competência (hoje 50,00; o
  `valor_total` da campanha continua sendo o alvo global).
- `taxas_condominiais`: **sem mudança estrutural**. `valor_original` passa a ser
  documentado como cache derivado (D‑02), como já é o caso de `status`.
- `cobranca_extraordinaria_taxa`: mantida até a etapa 6, depois removida.

### 3.4 Invariante do modelo

> Para toda taxa não excluída: `valor_original` = `SUM(itens_taxa_condominial.valor)`
> dos itens não excluídos.

Não é expressável como constraint no MySQL. Garantia em três camadas:
`ComposicaoTaxaService` como único ponto de escrita, comando
`taxas:verificar-composicao` (auditoria, sai com código ≠ 0 se divergir) e teste
automatizado sobre o dataset real na suíte de paridade.

## 4. Etapas de execução

Cada etapa é um commit revisável. As migrations de dados são Artisan Commands
idempotentes e reversíveis, como exige `CLAUDE.md`.

### Etapa 0 — Rede de segurança (antes de qualquer escrita)

1. Dump do banco (`u815349007_condnovo`) com timestamp.
2. Comando `composicao:snapshot` grava em `storage/app/golden/composicao/` os
   agregados que **não podem mudar**: saldo total, `ResumoFinanceiro::totaisEntre`
   por ano, `valor_original`/`valor_desconto`/`valor_acrescimo`/`status` das 2.496
   taxas, soma de `pagamento_taxa`, contagem de inadimplência por unidade.
3. Teste de paridade que compara snapshot antes × depois de todas as etapas.
   **Critério de aceite global: diferença zero em todos os agregados.**

### Etapa 1 — Schema

Migration `create_finalidades_e_itens_taxa`: cria `finalidades` e
`itens_taxa_condominial`, adiciona `finalidade_id` em `lancamentos_financeiros` e
`finalidade_id` + `valor_por_unidade` em `cobrancas_extraordinarias`. `down()`
reverte na ordem inversa. Nenhuma linha de dado tocada.

### Etapa 2 — Models, enums e serviços

- Models `Finalidade` e `ItemTaxa` (`itens_taxa_condominial`) — `$fillable`, casts
  decimal/date/boolean, `Auditable`, `SoftDeletes`.
- `TaxaCondominial`: `+ itens(): HasMany` (ordenada por `ordem`), `+ finalidades()`
  via itens; docblock de `valor_original` como cache.
- `LancamentoFinanceiro` / `CobrancaExtraordinaria`: `+ finalidade(): BelongsTo`.
- **`ComposicaoTaxaService`** — único ponto de escrita de `valor_original`:
  - `recalcular(TaxaCondominial): string` — soma os itens em BCMath e persiste via
    `forceFill` (espelha `StatusTaxaService::recalcular`);
  - `adicionarItem` / `atualizarItem` / `removerItem` — em transação, recalculam o
    valor e chamam `StatusTaxaService::recalcular` em seguida (o devido mudou).
- **`RateioPorFinalidadeService`** — cascata de D‑03, puro e testável:
  `distribuir(valorPagoTotal, itens): array<itemId, valorAtribuido>`, BCMath,
  `min(valor do item, saldo)` percorrendo por `ordem` e desempatando por `id`.
- Factories para `Finalidade` e `ItemTaxa`; `TaxaCondominialFactory` ganha state
  `comItens()`.
- Policies `FinalidadePolicy` e `ItemTaxaPolicy` (espelham `TaxaCondominialPolicy`),
  registradas em `AppServiceProvider`.

Testes desta etapa (unitários, sem banco): cascata com pagamento zero, parcial
abaixo do 1º item, parcial entre itens, integral, excedente, e com estorno
resultando em soma negativa.

### Etapa 3 — Decomposição das 482 taxas + backfill (o núcleo)

Comando `php artisan taxas:decompor-composicao [--dry-run] [--reverter]`.
Tudo em uma transação, com relatório final por categoria.

1. **Finalidades**: `firstOrCreate` de "Custeio ordinário" e "Pintura do prédio".
2. **Plano de contas**: item ordinário → `R-001` (Receita de Taxa Condominial);
   item de pintura → `R-002` (Cobranças Extraordinárias). Ambos já existem.
3. **Campanha**: `cobrancas_extraordinarias` #1 recebe `finalidade_id` = Pintura e
   `valor_por_unidade` = 50,00.
4. **As 482 taxas de 150,00** → para cada uma, dois itens:
   - `ordem` 0 · "Taxa condominial" · **100,00** · R‑001 · Custeio ordinário;
   - `ordem` 1 · "Taxa para pintura do prédio" · **50,00** · R‑002 · Pintura ·
     `origem` = `CobrancaExtraordinaria` #1.

   `valor_original` **permanece 150,00** — a soma dos itens é idêntica. Nenhum
   pagamento, pivô `pagamento_taxa` ou `status` é tocado. **É por isso que a
   operação é segura para as 201 taxas já pagas ou parciais.**
5. **Backfill das outras 2.014 taxas** → um único item `ordem` 0 · "Taxa
   condominial" · `valor_original` · R‑001 · Custeio ordinário, para que a
   invariante da §3.4 valha globalmente. Inclui as 54 taxas de 0,00 (item de 0,00,
   preservando a semântica de competência isenta).
6. **Reconciliação da inconsistência N‑02**: a taxa de 100,00 com linha no pivô da
   pintura **não** é decomposta automaticamente (decompor mudaria o valor devido de
   uma taxa existente, o que este comando nunca faz). Ela sai em uma lista de
   exceções no relatório, para decisão manual: ou a linha do pivô era indevida, ou
   falta cobrar os 50,00 daquela competência.
7. **Finalidade nos lançamentos financeiros**: `Rendimento(s) da conta` (ambas as
   grafias, 5 linhas) → Pintura do prédio (D‑05); "Contribuição … conserto da bomba"
   (6 linhas) → finalidade "Conserto da bomba" (premissa da §2).

**Idempotência**: garantida pela unique `(taxa_condominial_id, descricao)` +
verificação prévia de existência de itens por taxa; rodar duas vezes não duplica
nem altera nada.
**Reversibilidade**: `--reverter` faz `forceDelete` apenas dos itens criados pelo
comando, anula os `finalidade_id` do backfill e recalcula `valor_original` a partir
do snapshot da Etapa 0.
**Verificação obrigatória ao fim**: `taxas:verificar-composicao` + teste de paridade
da Etapa 0 com diferença zero.

### Etapa 4 — Escrita: lançamento e edição passam a ser por item

- `LancarTaxas`: além da taxa ordinária (`ConfiguracoesCondominio::taxaMensalidadePadrao()`,
  cujo default hoje é `150.00` e **precisa voltar para `100.00`**), gera um item por
  cada `cobranca_extraordinaria` ativa cuja vigência cubra a competência, com
  `valor_por_unidade`. `valor_original` deixa de ser gravado direto: vem do
  `ComposicaoTaxaService`.
- `AtualizarTaxa`: `valor_original` sai do formulário; edita‑se desconto, acréscimo,
  vencimento e `contabilizado`. Valor passa a ser consequência dos itens.
- Novas actions `SalvarItemTaxa` / `RemoverItemTaxa` (com `Gate`, recálculo de valor
  e de status) e `AplicarCobrancaEmTaxas` (aplica/retira uma campanha de um intervalo
  de competências, substituindo o papel do pivô descontinuado).
- `SalvarLancamento` e `SalvarCobrancaExtraordinaria`: aceitam `finalidade_id`.
- Nova action `SalvarFinalidade` (CRUD).

### Etapa 5 — Leitura: UI, relatórios e PDF

- **Nova tela `Financeiro/Finalidades/Gestao`** — CRUD, com arrecadado × `meta_valor`.
- **Novo relatório `Financeiro/Relatorios/PorFinalidade`** — o payoff: arrecadado por
  finalidade somando (a) rateio em cascata das aplicações em `pagamento_taxa` de
  taxas contabilizadas e (b) `lancamentos_financeiros` de receita com aquela
  finalidade. Deve exibir a nota da §2 sobre a ordem de quitação.
- `Taxas/EdicaoIndividual` + view: editor de itens (adicionar/editar/remover, total
  calculado em tela).
- `Taxas/Listagem` + view: coluna de composição (ex.: "150,00 (100 + 50)") e filtro
  por finalidade.
- `Taxas/Lancamento` + view: prévia da composição do ano antes de confirmar.
- `CobrancasExtraordinarias/Gestao`: campos de finalidade e `valor_por_unidade`;
  substitui a manipulação do pivô por `AplicarCobrancaEmTaxas`.
- `Lancamentos/Listagem`: coluna e filtro de finalidade.
- `Inadimplencia/PorUnidade` e `PdfController` (`pdf/dividas-imovel`, recibos):
  discriminar os itens da competência — é o que dá transparência ao condômino.
- `Resumo/Index` e `Resumo/Intervalo`: `ResumoFinanceiro` **não muda de semântica**
  (continua somando `pagamento_taxa`); ganha apenas um corte opcional por finalidade.

### Etapa 6 — Descomissionamento do pivô

Somente após a Etapa 5 em produção e com `taxas:verificar-composicao` limpo:
comando de conferência de que todo conteúdo de `cobranca_extraordinaria_taxa` está
representado nos itens, e migration que remove a tabela, a relação
`TaxaCondominial::cobrancasExtraordinarias()` e a linha correspondente em
`03-modelo-dados.md`.

### Etapa 7 — Documentação

- `03-modelo-dados.md`: as duas tabelas novas, as colunas novas, `valor_original`
  como cache derivado, a invariante da §3.4, as uniques novas na tabela de
  constraints.
- `01-diagrama-er.mmd` + `Diagram.svg`: `finalidades` e `itens_taxa_condominial`.
- `02-mapeamento-de-para.md`: nota de que a taxa do legado (valor único) mapeia
  para taxa + 1 item, e o registro da inconsistência N‑02.
- `CLAUDE.md`: apontar este documento como referência obrigatória para código de
  taxas.

## 5. Testes exigidos

Financeiro é crítico — `CLAUDE.md` exige teste para todo script de migração de dados
desse domínio. Suíte Pest, `sqlite :memory:` (a guarda de conexão da suíte continua
valendo).

1. **Unitário `RateioPorFinalidadeService`** — os 6 casos da Etapa 2.
2. **Unitário `ComposicaoTaxaService`** — soma BCMath, item removido, item de 0,00,
   e recálculo de status disparado após mudança de valor.
3. **Feature `DecomposicaoTaxasTest`** — cenário 100+50: `valor_original` intacto,
   status intacto, soma de `pagamento_taxa` intacta; **rodar o comando duas vezes
   não muda nada** (idempotência); `--reverter` restaura o estado inicial; taxa de
   0,00 tratada; a taxa inconsistente cai na lista de exceções e não é alterada.
4. **Feature `ComposicaoInvarianteTest`** — após o comando, nenhuma taxa viola
   `valor_original = SUM(itens)`.
5. **Feature `LancarTaxasComposicaoTest`** — ano lançado gera 2 itens/competência
   dentro da vigência da campanha e 1 fora dela.
6. **Feature `RelatorioPorFinalidadeTest`** — pagamento integral, parcial (checando
   explicitamente a cascata) e estorno; soma com receitas de `lancamentos_financeiros`.
7. **`ResumoTest` existente deve passar sem alteração** — é o sinal de que a
   semântica dos agregados não mudou.
8. **Paridade da Etapa 0** — snapshot antes × depois, diferença zero.

## 6. Riscos

| Risco | Mitigação |
|---|---|
| Alterar valor devido de taxas já pagas | O comando **nunca** muda `valor_original`, `valor_desconto`, `valor_acrescimo`, `status` ou `pagamento_taxa`. Só insere itens que somam o mesmo total. Verificado por snapshot |
| `valor_original` dessincronizar dos itens | Escrita única no `ComposicaoTaxaService` + `taxas:verificar-composicao` + teste de invariante |
| `taxa_mensalidade_padrao = 150.00` continuar em uso e dobrar a cobrança | Etapa 4 muda a configuração para `100.00` no mesmo commit em que `LancarTaxas` passa a somar as campanhas; teste 5 cobre |
| Arrecadação da pintura parecer baixa por causa da cascata (D‑03) | Nota explícita no relatório por finalidade, mostrando também o "a receber" da finalidade |
| Pivô da cobrança extraordinária lido em dois lugares durante a transição | Descomissionar só na Etapa 6, após conferência automatizada |

## 7. Ordem de execução (dependências de FK e de leitura)

```
0 snapshot → 1 schema → 2 models/serviços → 3 ETL 482 + backfill
  → 4 escrita (lançamento/edição) → 5 leitura (UI/relatórios/PDF)
  → 6 descomissionar pivô → 7 docs
```

As etapas 0–3 podem ir para produção antes das 4–7: depois da Etapa 3 o banco já
está correto e a aplicação continua funcionando pelo cache `valor_original`, sem
enxergar os itens ainda. Esse é o ponto de corte natural para uma primeira entrega.

## 8. Resultado da execução (2026-07-26)

### Comandos entregues

| Comando | Papel |
|---|---|
| `composicao:snapshot [--comparar]` | Congela / confere os agregados intocáveis (gate de paridade) |
| `taxas:decompor-composicao [--dry-run] [--reverter]` | ETL da Etapa 3, idempotente e reversível por manifesto |
| `taxas:verificar-composicao` | Auditoria da invariante §3.4 (exit ≠ 0 se divergir) |
| `composicao:conferir-pivo` | Gate da Etapa 6: o pivô está representado nos itens? |
| `composicao:descomissionar-pivo [--forcar]` | Remove o pivô — só executa se o gate passar |

Etapa 6 virou **comando, não migration**, pelo mesmo motivo documentado em
`migrar:descomissionar`: um `php artisan migrate` nunca deve dropar dado
histórico automaticamente, e a suíte precisa do pivô para exercitar a detecção
da N-02.

### Números do ETL

```
finalidades_criadas      3      (Custeio ordinário, Pintura do prédio, Conserto da bomba)
taxas_decompostas      482      (2024-10 a 2026-09: 100,00 + 50,00)
itens_criados         2978      (482 × 2 + 2.014 de backfill)
taxas_backfill        2014
lancamentos_afetados    11      (5 rendimentos → Pintura; 6 contribuições → Bomba)
excecoes                 1      (taxa #2010 — N-02)
```

Verificações finais: `taxas:verificar-composicao` → invariante OK nas 2.496 taxas
(0 sem item, 0 divergentes). `composicao:snapshot --comparar` → **diferença zero**
em saldo total (R$ 11.535,82), soma de `pagamento_taxa` (R$ 151.830,90), receitas,
despesas, valores/status das 2.496 taxas e inadimplência das 13 unidades.

Ciclo de reversão validado contra o banco real: executar → `--reverter` →
snapshot com diferença zero → executar de novo. A segunda execução consecutiva
não cria nada (idempotência) e preserva o manifesto da execução que gravou.

### Arrecadação por finalidade após a decomposição

| Finalidade | Cobrado em taxas | Arrecadado em taxas | Outras receitas | A receber |
|---|---:|---:|---:|---:|
| Custeio ordinário | 202.000,00 | 139.192,90 | 0,00 | 62.807,10 |
| Pintura do prédio | 24.050,00 | 9.018,00 | 1.111,01 | 15.032,00 |
| Conserto da bomba | 0,00 | 0,00 | 180,00 | 0,00 |

(A pintura cobra 24.100,00 em itens; 24.050,00 entram no relatório porque uma
competência está marcada como não contabilizada.)

### Dois bugs encontrados e corrigidos durante a execução

1. **Reversão incompleta da campanha.** O manifesto lia
   `getOriginal('finalidade_id')` *depois* do `update()`, e o Eloquent
   ressincroniza os atributos originais nesse ponto — a reversão "restaurava" a
   campanha para o valor novo. Os valores anteriores passaram a ser capturados
   antes da escrita.
2. **Manifesto sobrescrito por execução sem efeito.** Rodar o ETL uma segunda vez
   gravava um manifesto vazio, apagando o registro de reversão da execução que de
   fato gravou. Agora execução sem efeito preserva o manifesto anterior.

### Pendências deixadas em aberto (decisão do usuário)

1. **Taxa #2010** (unidade 8, competência 06/2024): tem `valor_original` 100,00 e
   uma linha no pivô da campanha registrando 50,00. Ou a linha do pivô é indevida,
   ou faltou cobrar os 50,00. Enquanto não decidida,
   `composicao:descomissionar-pivo` recusa remover a tabela — o que é o
   comportamento desejado, porque depois do drop esse registro só existe nos
   backups.
2. **Relações `@deprecated`**: `CobrancaExtraordinaria::taxasCondominiais()` e
   `TaxaCondominial::cobrancasExtraordinarias()` seguem no código, sem leitor na
   aplicação. Devem sair junto com a tabela.
3. **`ResumoTest`**: falha por conta de trabalho não commitado em
   `Resumo/Index.php` + `index.blade.php` (a view redesenhada não renderiza mais
   as linhas de unidade da matriz, então `assertSee('Casa 01')` quebra). Não tem
   relação com esta evolução — verificado com a versão commitada desses arquivos,
   em que os 4 testes passam.
