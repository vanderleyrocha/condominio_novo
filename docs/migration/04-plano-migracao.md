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
1b. `migrar:configuracoes` — migração SELETIVA de `parametros` → `configuracoes`
    (taxa_mensalidade_padrao, metodo_correcao, subtitulo_recibo,
    assinatura_recibo, ano_inicial_filtro_pagamentos). `nome_condominio` virou
    `condominios.nome`; `data_corte_level_one` é aposentada no cutover.
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

- [x] **Cadastros** (2026-07): telas novas Pessoas (listagem com busca +
      formulário com CPF/CNPJ validado) e Unidades (CRUD inline + modal de
      vínculos com papel, vigência e transferência de responsável financeiro).
      Actions `SalvarPessoa`/`ExcluirPessoa`/`SalvarUnidade`/`ExcluirUnidade`/
      `VincularPessoa`/`EncerrarVinculo`, Policies `PessoaPolicy`/`UnidadePolicy`
      (admin/sindico; level_one aceito até o remap), rule `CpfOuCnpj`.
      Menu aponta para as telas novas; as telas antigas (Proprietários/Imóveis)
      permanecem acessíveis por URL até a Fase 5.
      ATENÇÃO: edições feitas nas telas novas antes do cutover são descartadas
      pela reconstrução final do `migrar:remodelagem` — usá-las apenas para
      desenvolvimento/homologação até lá.
- [x] **Parâmetros/índices** (2026-07): telas novas Índices Econômicos (séries
      IPCA/IGP-M/INCC com filtro; admin-only — dado global) e Configurações do
      condomínio (admin+sindico; sem a data de corte level_one, aposentada).
      `ConfiguracoesCondominio` substitui `ParametrosCondominio` no modelo novo
      (nome do condomínio via `condominios.nome`); ETL `migrar:configuracoes`.
      `CorrecaoMonetariaService` permanece no schema antigo — será apontado
      para `IndiceEconomico`/`ConfiguracoesCondominio` junto com o módulo
      Mensalidades/Relatórios, onde é usado.
- [x] **Mensalidades/taxas** (2026-07): telas `/taxas` (Listagem, Lançamento,
      EdicaoIndividual, GradeAnual, Relatórios) + `/inadimplencia` (Listagem,
      PorUnidade). MUDANÇA ESTRUTURAL: o valor pago não é mais editável — a
      grade anual gera pagamentos reais pelo delta (`PagarViaGrade`; redução =
      ajuste negativo) e a edição individual só altera o valor devido, com
      status recalculado pelo serviço único. `CorrecaoMonetariaNovaService`
      replica a matemática de paridade lendo `indices_economicos` — validado
      contra o serviço antigo nos dados reais (valores idênticos).
- [x] **Pagamentos** (2026-07): telas `/pagamentos-novo` (Registro com seleção
      pessoa+unidade+forma, FIFO idêntico ao legado; Listagem; Detalhe;
      Estorno com os mesmos tetos RN-16..18/P10). Rotas/namespace transitórios
      `pagamentos-novo`, renomeados na Fase 5 junto com a tabela.
- [x] **Cobranças extraordinárias** (2026-07): `/cobrancas-extraordinarias`
      com método de rateio; apuração via pivot de taxas + lançamentos com
      origem polimórfica.
- [x] **Financeiro consolidado** (2026-07): `/lancamentos` (tela unificada de
      receitas+despesas com plano de contas e criação rápida de plano),
      `/resumo-novo` + intervalo, `/painel-novo` e `PdfNovoController` (7 PDFs;
      reutiliza os templates de dados agregados e ganha versões `-novo` dos
      acoplados a Models antigos). `ResumoFinanceiroNovo` validado contra
      `ResumoFinanceiro` nos dados reais: saldo histórico idêntico (diff 0.00).
- [x] **Acesso** (2026-07): tela de usuários passa a oferecer os papéis novos
      (o select itera o enum) + vínculo `pessoa_id` (obrigatório para papel
      proprietario); `migrar:remapear-papeis` pronto para o cutover
      (level_one → sindico, reversível com --reverter) — NÃO roda no
      orquestrador. Policies dos módulos novos aplicam a matriz
      admin/sindico/proprietario. Global Scope de condomínio adiado para o
      epic multi-condomínio (hoje há 1 condomínio; `condominio_user` já
      populado pelo ETL).

Cutover — comando único `migrar:cutover` (ensaiado no banco local em 2026-07:
ETL final + validação profunda + remap de 16 users level_one → sindico, tudo OK):

- [ ] Modo de manutenção (`php artisan down`).
- [ ] Deploy desta versão (rota `/` já aponta para o painel novo).
- [ ] `php artisan migrate` (schema novo) + `php artisan migrar:cutover`
      (aborta sem remapear se o ETL ou a validação divergirem).
- [x] ~~Renomear tabelas de pagamento no cutover~~ — MOVIDO para a Fase 5
      (decisão 2026-07): renomear junto com a remoção do código antigo evita
      quebrar as telas antigas durante a estabilização e mantém o rollback
      trivial. Os consumidores do modelo novo usam a tabela via
      `PagamentoNovo::getTable()` com alias fixo — o rename da Fase 5 é
      alterar 1 linha no Model + `RENAME TABLE`.
- [ ] Smoke test (fluxos críticos + comparação dos golden files) e `php artisan up`.
- [ ] **Rollback definido**: as tabelas antigas coexistem intactas — se o smoke
      test falhar, `migrar:remapear-papeis --reverter` + deploy da versão
      anterior; nenhuma reversão de dados é necessária. Só há risco real após
      o descomissionamento (Fase 5).
- [ ] Manter backup do banco antigo por 90 dias.

## Fase 5 — Descomissionamento

- [ ] Após estabilização (2-4 semanas), remover tabelas antigas
      (migration de drop) e a `migration_id_map`.
- [ ] Remover código morto: Models/Actions/Policies/telas antigas
      (Proprietario, Imovel, Mensalidade, Pagamento antigo, Receita, Despesa,
      DespesaTipo, Ipca, Parametro, CobrancaExtra), rotas do bloco
      "Schema antigo", `migrar:legado` e os commands `migrar:*`.
- [ ] Renomear os artefatos transitórios: tabela `pagamentos_novo` →
      `pagamentos` (feito no cutover), classe `PagamentoNovo` → `Pagamento`,
      namespaces/rotas `pagamentos-novo` → `pagamentos`, `resumo-novo` →
      `resumo`, `painel-novo` → rota `/`, `pdf-novo.*` → `pdf.*`,
      `PdfNovoController` → `PdfController`, `ResumoFinanceiroNovo` →
      `ResumoFinanceiro`, `CorrecaoMonetariaNovaService` →
      `CorrecaoMonetariaService`, e remover o case deprecated
      `PapelUsuario::LevelOne`.
- [ ] Regravar os golden files contra as telas/PDFs novos (critério de aceite
      já validado por paridade de agregados na Fase 4).
