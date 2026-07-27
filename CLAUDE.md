# Contexto do Projeto — Migração de Modelo de Dados

Este projeto é uma aplicação de administração de condomínio em PHP/Laravel + MySQL,
atualmente em processo de migração de um modelo de dados legado (sem planejamento
prévio) para um modelo de dados moderno e normalizado.

## Documentos de referência (leia antes de gerar código)

- `docs/migration/01-diagrama-er.mmd` — Diagrama ER completo do modelo novo.
- `docs/migration/02-mapeamento-de-para.md` — Mapeamento campo a campo do modelo
  antigo para o novo. É a fonte de verdade para qualquer script de migração de dados.
- `docs/migration/03-modelo-dados.md` — Especificação de cada tabela do modelo novo,
  convenções de nomenclatura e regras obrigatórias (soft deletes, decimal, etc.).
- `docs/migration/04-plano-migracao.md` — Checklist de fases de execução da migração.
- `docs/migration/05-plano-composicao-taxas.md` — **Leitura obrigatória para
  qualquer código de taxas condominiais ou de receita.** A taxa condominial é um
  contêiner de itens (`itens_taxa_condominial`), não um valor único, e toda fonte
  de receita tem finalidade (destinação). Contém as decisões fechadas D-01..D-06,
  a invariante `valor_original = SUM(itens.valor)` e a regra de rateio em cascata.

## Regras para geração de código nesta migração

- Sempre seguir as convenções descritas em `03-modelo-dados.md`.
- Nunca remover ou alterar tabelas do schema antigo diretamente; o schema antigo
  deve conviver com o novo até a Fase 4 (cutover) do plano.
- Toda migration de dados deve ser idempotente e reversível.
- Ao criar Models Eloquent novos, seguir nomenclatura em português conforme já
  usado no projeto (ex.: `Unidade`, `Pessoa`, `TaxaCondominial`), mantendo
  consistência com o código legado.
- Ao gerar Artisan Commands de migração de dados, seguir a ordem de execução
  definida em `04-plano-migracao.md` (Fase 2), pois há dependências de FK.
- Sempre gerar testes (Pest ou PHPUnit, conforme padrão já usado no projeto)
  para scripts de migração de dados críticos (financeiro).
- Nunca escrever em `taxas_condominiais.valor_original` nem em
  `taxas_condominiais.status` diretamente: são caches derivados, de escrita
  exclusiva de `ComposicaoTaxaService` e `StatusTaxaService`. Depois de mexer na
  composição, conferir com `php artisan taxas:verificar-composicao`.
- O valor configurado em `taxa_mensalidade_padrao` é o da taxa ORDINÁRIA
  (item ordem 0). Contribuições recorrentes são itens próprios, somados por cima
  — nunca embutir contribuição no valor da ordinária.
