# Plano de Migração de Dados — Fases de Execução

Referência operacional para o Claude Code executar/apoiar a criação de
migrations, commands e scripts de validação.

## Decisões de estratégia (fechadas)

- **Idempotência por reconstrução**, não por checagem incremental: cada execução
  trunca as tabelas novas e reprocessa tudo a partir do stage — mesma estratégia
  já provada em `app/Console/Commands/MigrarLegado.php`. Qualquer falha,
  re-executar do zero. Sem transação externa envolvendo tudo (TRUNCATE comita
  implicitamente no MySQL); usar transações por chunk nas inserções.
- **Namespace dos commands: `migrar:`** (padrão já existente no projeto —
  `migrar:legado`). NÃO usar o prefixo `migrate:`, que colide com o namespace
  nativo do framework (`migrate:fresh`, `migrate:status`, ...).
- **Inserções em chunks** (ex.: 500 registros) para não estourar memória nem
  segurar locks longos.
- Manter a tabela auxiliar `migration_id_map` (`entidade`, `id_antigo`, `id_novo`,
  unique em `(entidade, id_antigo)`). Atenção: para `pessoas` o mapa é N:1
  (deduplicação por CPF — vários ids antigos podem apontar para o mesmo id novo).
- Cada command deve logar quantidade de registros migrados, divergências e casos
  sinalizados (CPF duplicado com dados divergentes, inquilino sem CPF, etc.).

## Fase 0 — Preparação

- [ ] Criar branch dedicada `feature/nova-modelagem-dados`.
- [~] Copiar banco de produção para ambiente de staging — o banco local de
      desenvolvimento veio do dump de produção (via migrar:legado) e validou o
      ETL; antes do cutover, repetir `migrar:remodelagem` sobre dump FRESCO em
      homologação (pendente de ambiente).
- [x] Confirmar o mapeamento de-para em `02-mapeamento-de-para.md` com o time
      (revisado e aprovado em 2026-07, com correções incorporadas).
- [x] Decisão de negócio sobre `papel = 'level_one'`: RESOLVIDA (2026-07) —
      novo controle de acesso com admin/sindico/proprietario
      (ver "Controle de acesso" em 03-modelo-dados.md); level_one → sindico
      no cutover.
- [x] Sanear duplicidade de mensalidades (N-01) — resolvido manualmente;
      `GROUP BY imovel_id, ano, mes HAVING count(*) > 1` retorna conjunto vazio
      (verificado em 2026-07). O command deve revalidar isso no stage e abortar
      se encontrar duplicata (proteção contra restauração de dump antigo).

## Fase 1 — Novo schema em paralelo — ✅ CONCLUÍDA (2026-07)

- [x] Criar migrations Laravel para todas as tabelas descritas em
      `03-modelo-dados.md` (incluindo as uniques compostas obrigatórias),
      sem remover as tabelas antigas
      (`2026_07_26_100010_create_novo_schema_cadastros.php` e
      `2026_07_26_100020_create_novo_schema_financeiro.php`; reversibilidade
      verificada com rollback + re-migrate).
- [x] Instalar e configurar `owen-it/laravel-auditing` v14 (tabela `audits` do
      pacote; `audit.console` desabilitado por padrão — o ETL não gera audits).
- [x] Criar Models Eloquent com relacionamentos completos, casts de enum PHP
      (`app/Enums`), decimal e datas. A tabela nova de pagamentos nasce como
      `pagamentos_novo` / Model `PagamentoNovo` (colisão com a tabela antiga
      `pagamentos` — rename no cutover, ver 03-modelo-dados.md).
- [x] Criar o serviço único de recálculo de status de taxa
      (`App\Services\StatusTaxaService`, BCMath), usado pela migração E pela
      aplicação. Coberto por testes Unit (`tests/Unit/StatusTaxaServiceTest.php`).
- [x] Criar Factories para todas as novas entidades (suporte a testes).
- [x] `migration_id_map` criada junto com o schema financeiro (infra da Fase 2).

## Fase 2 — Scripts de migração de dados — ✅ CONCLUÍDA (2026-07)

Um Artisan Command por domínio em `app/Console/Commands/Remodelagem/`, na ordem
abaixo (dependências de FK). Ids preservados nas migrações 1:1 (unidades, taxas,
pagamentos, cobranças e pivots), o que torna a resolução de FKs determinística;
`migration_id_map` registra tudo mesmo assim.

1. `migrar:condominios` — cria o condomínio único (nome de `parametros.nome_condominio`).
2. `migrar:pessoas` — proprietários e inquilinos → `pessoas`, com deduplicação
   por CPF (mapa N:1). Ainda NÃO cria vínculos.
3. `migrar:unidades` — `imoveis` → `unidades`.
4. `migrar:vinculos` — cria `unidade_pessoa` (papel, responsável financeiro e
   vigência conforme o de-para; inquilino de proprietário com N imóveis é
   replicado e logado para revisão).
5. `migrar:taxas-condominiais` — `mensalidades` → `taxas_condominiais`
   (status provisório 'aberto'; aborta se houver duplicata na origem).
6. `migrar:pagamentos` — sinal negativo dos estornos preservado;
   `estorno_de_id` = `pagamento_origem_id` (ids preservados).
7. `migrar:pagamento-taxa` — pivot, preservando sinal.
7b. `migrar:pagamentos-historicos` — **descoberta na execução real**: 1.675
    mensalidades do legado têm `valor_pago > 0` sem NENHUMA linha de pivot
    (quitações anteriores ao módulo de pagamentos). Este passo sintetiza 1
    pagamento por mensalidade cobrindo a lacuna (`valor_pago - Σ pivot`),
    atribuído ao responsável financeiro da unidade, com data = `pago_em`
    (fallback vencimento) e rastreio em `migration_id_map`
    (entidade `pagamento_historico`). Sem isso, taxas pagas ficariam 'aberto'.
8. `migrar:recalcular-status-taxas` — serviço único (`StatusTaxaService`,
   BCMath) deriva o `status` de todas as taxas; confere a soma contra
   `mensalidades.valor_pago` (divergência esperada: zero após o passo 7b).
9. `migrar:cobrancas-extraordinarias` — inclui o pivot
   `cobranca_extraordinaria_taxa`.
10. `migrar:lancamentos-financeiros` — despesas + receitas, criando antes os
    `planos_contas` (D-xxx de `despesa_tipos` + R-001/R-002 de receita).
11. `migrar:indices-economicos` — `ipcas` → `indices_economicos` (tipo='ipca').
12. `migrar:users-pessoa` — backfill de `users.pessoa_id` (match por e-mail;
    users não têm CPF) e vínculo de todos ao condomínio único.

Orquestrador `migrar:remodelagem`: trunca todas as tabelas novas, roda os passos
na ordem e emite o relatório de reconciliação (contagens + somas via bccomp);
exit code de falha se qualquer verificação divergir. Commands isolados exigem
destino vazio (ou `--truncar` para reconstruir só o próprio destino).

Resultado da execução no banco local (2026-07-26): reconciliação 100% OK em
~13s — 2.496 taxas, 25 pagamentos migrados + 1.675 históricos sintetizados
(R$ 140.979,07), status: pago=1.745, pago_parcial=25, aberto=726.
Testes: `tests/Feature/Remodelagem/RemodelagemTest.php` (dedupe, estorno,
históricos, idempotência, proteção N-01, guarda de destino).

## Fase 3 — Validação — ✅ parte automatizada CONCLUÍDA (2026-07)

- [x] `migrar:validar-remodelagem` (somente leitura): reconciliação **por
      unidade** (16) e **por competência** (156), cobertura de pagamento taxa a
      taxa (Σ pivot == valor_pago, incluindo históricos sintetizados), status
      derivado vs semântica do legado (2.496 taxas) e checagens de integridade
      (responsável financeiro por unidade, origens polimórficas, sinal de
      estornos). Executado no banco local: **todas as verificações OK**.
- [x] Contagens por tabela: cobertas pelo relatório do `migrar:remodelagem`.
- [x] Testes automatizados: `tests/Feature/Remodelagem/RemodelagemTest.php`
      roda o ETL + a validação profunda sobre um legado sintético.
- [ ] Repetir `migrar:remodelagem` + `migrar:validar-remodelagem` sobre dump
      FRESCO de produção em homologação (pendente de ambiente).
- [ ] Regravar os golden files de PDFs/relatórios contra o novo schema —
      critério de aceite da troca; acontece junto com cada módulo da Fase 4.
- [ ] Testar fluxos críticos em homologação (gerar cobrança, registrar
      pagamento, estornar, relatório) — após a Fase 4 dos módulos.

## Fase 4 — Adaptação do código e cutover

A troca de schema toca ~60 arquivos em `app/` (Models, Actions, Policies,
componentes Livewire, `PdfController`, `ResumoFinanceiro`,
`CorrecaoMonetariaService`) e ~30 views Blade. Migrar **por módulo**, na ordem
de menor acoplamento, mantendo a suíte verde a cada módulo:

- [ ] **Cadastros**: `Proprietario`/`Imovel` → `Pessoa`/`Unidade`/`UnidadePessoa`
      (Actions `Salvar*`/`Excluir*`, Policies, Livewire `Cadastros\*`, views).
- [ ] **Parâmetros/índices**: `Ipca` → `IndiceEconomico`, `Parametro` →
      `Configuracao` (migração seletiva de chaves), `CorrecaoMonetariaService`,
      `ParametrosCondominio`.
- [ ] **Mensalidades/taxas**: `Mensalidade` → `TaxaCondominial` (Lançamento,
      GradeAnual, EdicaoIndividual, Listagem, Relatórios, Dividas).
- [ ] **Pagamentos**: `Pagamento`/`PagamentoMensalidade` → novo
      `Pagamento`/`PagamentoTaxa` (Registro, Estorno, Detalhe, Listagem,
      Actions `RegistrarPagamento`/`EstornarPagamento`).
- [ ] **Cobranças extras**: `CobrancaExtra` → `CobrancaExtraordinaria` + rateio.
- [ ] **Financeiro consolidado**: `Receita`/`Despesa`/`DespesaTipo` →
      `LancamentoFinanceiro`/`PlanoConta` (Resumo, PainelInicial, PdfController,
      `ResumoFinanceiro`).
- [ ] **Acesso**: `users.pessoa_id`, `condominio_user`, Global Scope de
      condomínio; reescrever Policies para admin/sindico/proprietario,
      remapear `level_one` → `sindico` nos dados, aposentar a regra
      `data_corte_level_one` e remover o parâmetro correspondente da tela
      de parâmetros.

Cutover:

- [ ] Modo de manutenção.
- [ ] Rodar `migrar:remodelagem` final (reconstrução completa a partir do banco
      de produção atualizado).
- [ ] Renomear tabelas de pagamento: `RENAME TABLE pagamentos TO pagamentos_legado,
      pagamentos_novo TO pagamentos` (atômico no MySQL) + renomear a classe
      `PagamentoNovo` → `Pagamento` (removendo o Model antigo no mesmo commit).
- [ ] Deploy e smoke test (fluxos críticos + comparação dos golden files).
- [ ] **Rollback definido**: as tabelas antigas coexistem intactas — se o smoke
      test falhar, reverter o deploy do código; nenhuma reversão de dados é
      necessária. Só há risco real após o descomissionamento (Fase 5).
- [ ] Manter backup do banco antigo por 90 dias.

## Fase 5 — Descomissionamento

- [ ] Após estabilização (2-4 semanas), remover tabelas antigas
      (migration de drop) e a `migration_id_map`.
- [ ] Remover código morto (Models/Actions/Policies antigos, `migrar:legado`
      e os commands `migrar:*` da remodelagem).
