# Mapeamento De-Para: Modelo Antigo → Modelo Novo

Este documento é a fonte de verdade para os scripts de migração de dados.
Toda migration/seeder de dados DEVE seguir exatamente este mapeamento.

## 1. proprietarios → pessoas + unidade_pessoa

| Campo Antigo (proprietarios)      | Destino                                  |
|------------------------------------|-------------------------------------------|
| id                                  | pessoas.id (novo, gerar mapa old->new)    |
| nome                                | pessoas.nome                              |
| cpf                                 | pessoas.cpf_cnpj                          |
| telefone                            | pessoas.telefone                          |
| nome_inquilino                      | pessoas.nome (novo registro, se não nulo) |
| cpf_inquilino                       | pessoas.cpf_cnpj (novo registro)          |
| telefone_inquilino                  | pessoas.telefone (novo registro)          |
| responsavel_pagamento               | unidade_pessoa.responsavel_financeiro     |

Regras:
- Para cada proprietario, criar 1 registro em `pessoas` com tipo='fisica'.
- Se `nome_inquilino` não for nulo, criar 2º registro em `pessoas` para o inquilino.
- Criar registro em `unidade_pessoa` com papel='proprietario' sempre.
- Se houver inquilino, criar 2º registro em `unidade_pessoa` com papel='inquilino'.
- `responsavel_financeiro = true` no registro cujo papel corresponda a `responsavel_pagamento`.
- `data_inicio` = created_at do proprietario original (fallback, pois não há data melhor).

Regras de deduplicação (OBRIGATÓRIAS — `pessoas.cpf_cnpj` é unique):
- Antes de inserir em `pessoas`, verificar se já existe registro com o mesmo `cpf_cnpj`
  (comparar apenas dígitos). Se existir, NÃO criar novo registro: reaproveitar o
  `pessoas.id` existente e criar apenas o novo vínculo em `unidade_pessoa`.
  Cobre os casos de pessoa proprietária de várias unidades e de pessoa que é
  proprietária de uma unidade e inquilina de outra.
- Em caso de reaproveitamento com dados divergentes (nome/telefone diferentes para o
  mesmo CPF), prevalece o registro mais recente (maior `updated_at` na origem) e a
  divergência deve ser logada no relatório de migração.
- Inquilino com `cpf_inquilino` nulo: criar a pessoa com `cpf_cnpj = NULL`
  (a unique do MySQL permite múltiplos NULLs). Logar esses casos para saneamento
  posterior pelo time.
- O mapa old->new de pessoas passa a ser N:1 (vários proprietarios antigos podem
  apontar para a mesma pessoa nova) — a tabela `migration_id_map` deve suportar isso.

## 2. imoveis → unidades

| Campo Antigo (imoveis) | Destino                          |
|--------------------------|------------------------------------|
| id                        | unidades.id (mapa old->new)       |
| proprietario_id           | resolvido via unidade_pessoa      |
| nome                      | unidades.identificacao            |

Regras:
- Criar 1 `condominios` único antes de tudo (não existe hoje no schema antigo).
- Todas as unidades migradas apontam para esse condominio_id único.
- `bloco_id` = NULL (não há dado de origem).
- `fracao_ideal` = NULL ou calculada posteriormente (não existe no dado de origem).

## 3. mensalidades → taxas_condominiais

| Campo Antigo (mensalidades) | Destino (taxas_condominiais)     |
|-------------------------------|-------------------------------------|
| id                             | id (mapa old->new)                 |
| imovel_id                      | unidade_id (via mapa)              |
| mes                            | competencia_mes                    |
| ano                            | competencia_ano                    |
| vencimento                     | vencimento                         |
| valor                          | valor_original                     |
| desconto                       | valor_desconto                     |
| acrescimo                      | valor_acrescimo                    |
| valor_pago                     | NÃO migrar direto — coberto por pagamento_taxa (real ou histórico sintetizado, ver abaixo) |
| pago_em                        | vira data_pagamento do pagamento histórico sintetizado (fallback: vencimento) |
| contabilizado                  | contabilizado (DECIDIDO: manter — o ER já prevê a coluna) |

Regras de unicidade:
- A duplicidade histórica de mensalidades (N-01) foi saneada manualmente na base
  (verificado em 2026-07: `GROUP BY imovel_id, ano, mes HAVING count(*) > 1` retorna
  conjunto vazio). O novo schema DEVE ter unique composta
  `(unidade_id, competencia_ano, competencia_mes)` — a garantia sobe do nível de
  aplicação (BR-MIGRAR-001) para o nível de banco.
- O command de migração deve, ainda assim, abortar com relatório claro se encontrar
  duplicata no stage (proteção contra dumps antigos).

Regras de status:
- Definição única: `valor_devido = valor_original + valor_acrescimo - valor_desconto`.
- Calcular `status` após migrar `pagamento_taxa`, com
  `valor_pago_total = SUM(pagamento_taxa.valor_aplicado)` da taxa (estornos entram
  na soma com valor negativo e se anulam naturalmente):
  - valor_pago_total <= 0 → status = 'aberto'
  - valor_pago_total >= valor_devido → status = 'pago'
  - 0 < valor_pago_total < valor_devido → status = 'pago_parcial'
- TODA aritmética monetária do recálculo DEVE usar BCMath (`bcadd`/`bcsub`/`bccomp`)
  sobre strings decimais, nunca float — requisito de paridade com o legado
  (ver commit bdc948b, golden files).
- `status` é cache de leitura (ver 03-modelo-dados.md): o mesmo serviço de recálculo
  usado na migração deve ser o usado pela aplicação em produção.

Pagamentos históricos (constatado na execução real — 1.675 casos):
- A maioria das mensalidades pagas do legado tem `valor_pago > 0` SEM linha em
  `pagamento_mensalidades` (quitações anteriores ao módulo de pagamentos).
- Para cada uma, o ETL sintetiza 1 pagamento em `pagamentos_novo` no valor da
  lacuna (`valor_pago - Σ pivot migrado`), vinculado ao responsável financeiro
  da unidade, com `descricao` "Pagamento histórico migrado (mes/ano)" e rastreio
  em `migration_id_map` (entidade `pagamento_historico`, id_antigo = mensalidade.id).
- Invariante final: `Σ pagamento_taxa por taxa == mensalidades.valor_pago`.
- Lacuna negativa (pivot > valor_pago) é inconsistência do legado: nada é
  sintetizado e o caso é logado para triagem manual.

## 4. pagamentos → pagamentos (novo) + resolução de estorno

| Campo Antigo (pagamentos) | Destino                              |
|------------------------------|------------------------------------------|
| id                            | id (mapa old->new)                       |
| proprietario_id               | pessoa_id (via mapa pessoas)             |
| imovel_id                     | unidade_id (via mapa unidades)           |
| pagamento_origem_id            | estorno_de_id (via mapa, se estornado=1) |
| data                          | data_pagamento                           |
| descricao                     | descricao                                |
| valor                         | valor_total (preserva o sinal)           |
| estornado                     | usado para decidir se preenche estorno_de_id |

Regras:
- Se `estornado = 1` e `pagamento_origem_id` não nulo → `estorno_de_id` = mapa(pagamento_origem_id).
- Convenção de sinal (DECIDIDA): estornos permanecem com `valor_total` NEGATIVO,
  como no legado. O `estorno_de_id` identifica a semântica; o sinal negativo garante
  que somas diretas (relatórios, recálculo de status) continuem corretas sem
  tratamento especial. A mesma convenção vale para `pagamento_taxa.valor_aplicado`.
- `imovel_id` é nullable no legado → `unidade_id` é nullable no novo schema.
- `forma_pagamento`: não existe no schema antigo. Definir valor default ('outro' ou 'nao_informado')
  e sinalizar para o time de produto decidir se vale a pena recuperar essa informação de outra fonte.

## 5. pagamento_mensalidades → pagamento_taxa

| Campo Antigo               | Destino                       |
|------------------------------|----------------------------------|
| pagamento_id                 | pagamento_id (via mapa)         |
| mensalidade_id                | taxa_condominial_id (via mapa)  |
| valor                        | valor_aplicado (preserva o sinal — negativo em estornos) |

Migração direta 1:1, apenas trocando as FKs pelos IDs remapeados.

## 6. cobrancas_extras → cobrancas_extraordinarias

| Campo Antigo         | Destino                          |
|------------------------|-------------------------------------|
| id                      | id (mapa old->new)                 |
| nome                    | nome                                |
| valor                  | valor_total                         |
| vigencia_inicio         | vigencia_inicio                    |
| vigencia_fim            | vigencia_fim                       |
| ativa                   | ativa                               |

- `condominio_id` = condomínio único criado na fase 1.
- `metodo_rateio` = 'manual' como default (schema antigo não guarda a regra de rateio).

## 7. cobranca_extra_mensalidade → cobranca_extraordinaria_taxa

Migração direta 1:1, trocando FKs pelos IDs remapeados (cobranca_extra_id → cobranca_extraordinaria_id,
mensalidade_id → taxa_condominial_id).

## 8. despesas + despesa_tipos + receitas → lancamentos_financeiros

| Campo Antigo (despesas)   | Destino                              |
|------------------------------|------------------------------------------|
| despesa_tipo_id               | plano_conta_id (criar planos_contas a partir de despesa_tipos) |
| data                          | data_lancamento e data_competencia (mesma data, ajustar depois se necessário) |
| descricao                     | descricao                                |
| valor                         | valor                                    |
| contabilizado                  | contabilizado                            |
| —                             | natureza = 'despesa'                     |

| Campo Antigo (receitas)   | Destino                              |
|------------------------------|------------------------------------------|
| data                          | data_lancamento e data_competencia       |
| descricao                     | descricao                                |
| valor                         | valor                                    |
| contabilizado                  | contabilizado                            |
| cobranca_extra_id              | origem_type = 'CobrancaExtraordinaria', origem_id = mapa(cobranca_extra_id) |
| —                             | natureza = 'receita'                     |

Regras:
- Criar `planos_contas` a partir de `despesa_tipos`, com tipo='despesa'.
- Criar 1 `planos_contas` genérico "Receita de Taxa Condominial" com tipo='receita' para
  as receitas que não têm origem de cobrança extra.
- Criar 1 `planos_contas` "Cobranças Extraordinárias" com tipo='receita' para as
  receitas que TÊM `cobranca_extra_id` — `plano_conta_id` é NOT NULL, todo
  lançamento precisa de classificação (a origem polimórfica é rastreio, não
  classificação contábil).

## 9. ipcas → indices_economicos

| Campo Antigo | Destino          |
|----------------|--------------------|
| ano            | ano                |
| mes            | mes                |
| indice         | indice             |
| —              | tipo = 'ipca'      |

## 10. users

| Campo Antigo   | Destino                                            |
|-------------------|-------------------------------------------------------|
| papel ('admin')    | manter 'admin'                                        |
| papel ('level_one') | 'sindico' — DECIDIDO (2026-07); remap executado no cutover (Fase 4), junto com a reescrita das Policies e a aposentadoria da regra data_corte_level_one |
| —                  | pessoa_id = NULL por padrão, exceto se houver vínculo identificável por e-mail/CPF com algum proprietario migrado |
| —                  | criar registro em condominio_user vinculando cada user ao condomínio único da fase 1 |

## Tabelas sem alteração estrutural relevante

- `accesses`, `sessions`, `jobs`, `job_batches`, `failed_jobs`, `cache`, `cache_locks`,
  `migrations`, `password_reset_tokens` — mantidas como estão (infraestrutura Laravel).
- `parametros` — mantida em paralelo; avaliar migração seletiva para `configuracoes`
  apenas das chaves que forem, de fato, configuração (não regra de negócio financeira).
