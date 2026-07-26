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
