1. Diagnóstico do Modelo Atual
Problemas estruturais identificados
Ausência de conceito de "Condomínio": Não existe uma tabela condominios. Tudo parece assumir um único condomínio (imoveis, proprietarios sem vínculo a uma entidade maior). Isso trava qualquer evolução para multi-condomínio (SaaS).
Inquilino modelado como colunas soltas em proprietarios: nome_inquilino, cpf_inquilino, telefone_inquilino deveriam ser uma entidade própria (inquilinos ou um contatos polimórfico), permitindo múltiplos contatos, histórico de troca de inquilino, etc.
responsavel_pagamento como enum amarra a regra de negócio à estrutura da tabela. Se amanhã existir "responsável misto" (ex.: proprietário paga condomínio, inquilino paga água), o modelo quebra.
Falta de tabela de "unidades/blocos": imoveis parece ser a unidade, mas não há hierarquia (bloco/torre/apto), nem fração ideal, nem vagas de garagem vinculadas.
mensalidades misturando dados de cobrança e pagamento parcial (valor_pago, pago_em na própria linha) — mas também existe pagamento_mensalidades (N:N com pagamentos). Isso é redundante e propenso a inconsistência: dois lugares guardam "quanto foi pago".
pagamentos com pagamento_origem_id autorreferente sem comentário/documentação — parece modelar estorno, mas já existe coluna estornado em separado. Duas formas de representar a mesma coisa.
cobrancas_extras e cobranca_extra_mensalidade: modelo de rateio de despesas extraordinárias está ok, mas não há tabela de rateio por fração ideal automática, nem histórico de reajuste.
receitas e despesas são "achatadas": não há categorização robusta (plano de contas), nem centro de custo, nem vínculo com imoveis/proprietarios quando aplicável (ex.: multa individual vira despesa genérica).
ipcas isolado: índice de reajuste não está vinculado a nenhuma regra de contrato/reajuste de taxa condominial.
parametros como chave-valor genérico: funciona, mas mistura configurações de sistema com regras de negócio (ex.: taxa de multa, juros) — isso deveria estar em tabelas de regras versionadas.
Sem soft deletes: nenhuma tabela tem deleted_at. Em sistema financeiro, exclusão física é arriscada (auditoria, histórico).
Sem auditoria financeira: não há log de alterações em mensalidades/pagamentos, crítico para prestação de contas de condomínio.
users sem vínculo com proprietarios: não dá para saber se o usuário logado é síndico, proprietário com acesso ao portal, ou porteiro.
2. Proposta de Novo Modelo de Dados
2.1 Domínio: Estrutura do Condomínio
condominios
  id, nome, cnpj, endereco, cidade, uf, cep, created_at, updated_at, deleted_at

blocos
  id, condominio_id (FK), nome, created_at, updated_at, deleted_at

unidades  (substitui "imoveis")
  id, condominio_id (FK), bloco_id (FK nullable), identificacao (ex: "Apto 101"),
  fracao_ideal decimal(8,6), area decimal(8,2), vagas_garagem tinyint,
  created_at, updated_at, deleted_at
Por quê: hoje imoveis é "plano" (sem condomínio, sem bloco, sem fração ideal). Fração ideal é essencial para ratear despesas proporcionalmente — hoje isso não existe no seu banco.

2.2 Domínio: Pessoas
pessoas
  id, nome, cpf_cnpj, email, telefone, tipo enum('fisica','juridica'),
  created_at, updated_at, deleted_at

unidade_pessoa  (substitui proprietarios + campos de inquilino)
  id, unidade_id (FK), pessoa_id (FK),
  papel enum('proprietario','inquilino','procurador'),
  responsavel_financeiro boolean,
  data_inicio date, data_fim date nullable,
  created_at, updated_at
Por quê: isso elimina os campos nome_inquilino/cpf_inquilino fixos, permite histórico (quem morou onde e quando), múltiplos responsáveis, e generaliza proprietário/inquilino como "pessoa com papel". Também prepara o terreno para portal do proprietário/inquilino logarem.

2.3 Domínio: Cobrança e Financeiro
planos_contas
  id, condominio_id (FK), codigo, descricao, tipo enum('receita','despesa'),
  created_at, updated_at

lancamentos_financeiros  (substitui receitas + despesas)
  id, condominio_id (FK), plano_conta_id (FK), unidade_id (FK nullable),
  data_competencia date, data_lancamento date, descricao, valor decimal(12,2),
  natureza enum('receita','despesa'), contabilizado boolean,
  origem_type varchar(255) nullable, origem_id bigint nullable,  -- polimórfico
  created_at, updated_at, deleted_at

taxas_condominiais  (substitui parte da lógica de "mensalidades")
  id, unidade_id (FK), competencia_mes tinyint, competencia_ano smallint,
  vencimento date, valor_original decimal(10,2), valor_desconto decimal(10,2),
  valor_acrescimo decimal(10,2), status enum('aberto','pago','pago_parcial','cancelado'),
  created_at, updated_at, deleted_at

pagamentos
  id, unidade_id (FK), pessoa_id (FK), data_pagamento date,
  valor_total decimal(12,2), forma_pagamento enum('pix','boleto','dinheiro','transferencia'),
  estorno_de_id bigint nullable (FK self), created_at, updated_at

pagamento_taxa  (N:N entre pagamentos e taxas_condominiais)
  id, pagamento_id (FK), taxa_condominial_id (FK), valor_aplicado decimal(10,2)

cobrancas_extraordinarias
  id, condominio_id (FK), nome, valor_total decimal(12,2),
  metodo_rateio enum('fracao_ideal','igual','manual'),
  vigencia_inicio date, vigencia_fim date, ativa boolean,
  created_at, updated_at

cobranca_extraordinaria_taxa  (substitui cobranca_extra_mensalidade)
  id, cobranca_extraordinaria_id (FK), taxa_condominial_id (FK), valor decimal(10,2)
Principais mudanças:

Elimina a redundância valor_pago/pago_em na tabela de taxa versus a tabela pivot de pagamento — o status e os valores pagos passam a ser derivados da soma de pagamento_taxa, com um campo status mantido por performance (mas recalculado, não fonte de verdade).
origem_type/origem_id polimórfico em lancamentos_financeiros permite rastrear se uma despesa veio de um contrato, de uma cobrança extra, etc., sem precisar de N tabelas específicas.
estorno_de_id único e claro para modelar estornos (elimina a duplicidade estornado + pagamento_origem_id).
2.4 Domínio: Índices e Reajuste
indices_economicos  (substitui ipcas, generalizado)
  id, tipo enum('ipca','igpm','outro'), ano smallint, mes tinyint,
  indice decimal(8,4), created_at, updated_at

regras_reajuste
  id, condominio_id (FK), indice_economico_tipo, periodicidade_meses,
  data_base date, created_at, updated_at
2.5 Domínio: Configuração e Auditoria
configuracoes
  id, condominio_id (FK nullable = configuração global), chave, valor, tipo_dado,
  created_at, updated_at

auditorias  (log genérico)
  id, user_id (FK), auditable_type, auditable_id, evento (created/updated/deleted),
  valores_antigos json, valores_novos json, created_at
Por quê: parametros genérico continua útil para config de sistema, mas regras de negócio financeiras (multa, juros) merecem tabela própria e versionável (regras_reajuste). A tabela auditorias cobre a lacuna crítica de rastreabilidade financeira — recomendo o pacote owen-it/laravel-auditing para isso, que já gera esse tipo de estrutura automaticamente.

2.6 Usuários e Acesso
users
  id, name, email, password, papel enum('admin','sindico','porteiro','morador'),
  pessoa_id (FK nullable), ...  -- vincula user ao registro de pessoa quando for morador
3. Plano de Migração (Passo a Passo)
Dado que é Laravel + MySQL, a estratégia recomendada é migração incremental com convivência temporária dos dois modelos, para reduzir risco.

Fase 0 — Preparação (1-2 dias)
Criar branch dedicada e ambiente de staging com cópia fiel do banco de produção.
Mapear todas as queries e Models Eloquent atuais que acessam as tabelas antigas (grep por Proprietario::, Mensalidade::, etc.).
Escrever um dicionário de mapeamento de-para (tabela antiga → tabela nova, campo antigo → campo novo) — usar como documento vivo durante todo o processo.
Fase 1 — Criar o novo schema em paralelo (2-3 dias)
Criar as novas migrations Laravel para todas as tabelas do novo modelo, sem apagar as antigas.
Rodar php artisan migrate em staging.
Criar os novos Models Eloquent (Condominio, Unidade, Pessoa, UnidadePessoa, LancamentoFinanceiro, TaxaCondominial, etc.) com relacionamentos completos.
Fase 2 — Scripts de migração de dados (3-5 dias)
Escrever um script/Artisan Command por domínio, executado em ordem de dependência:

MigrateCondominios — como só existe 1 condomínio implícito hoje, criar registro único.
MigrateUnidades — imoveis → unidades, vinculando ao condomínio criado.
MigratePessoas — extrair proprietarios (papel proprietário) e os campos *_inquilino (papel inquilino) para pessoas + unidade_pessoa.
MigrateTaxasCondominiais — mensalidades → taxas_condominiais, com status calculado a partir de valor_pago vs valor.
MigratePagamentos — pagamentos → novo pagamentos, resolvendo pagamento_origem_id/estornado em estorno_de_id.
MigratePagamentoTaxa — pagamento_mensalidades → pagamento_taxa.
MigrateCobrancasExtras — cobrancas_extras + cobranca_extra_mensalidade → novas tabelas equivalentes.
MigrateFinanceiro — receitas + despesas → lancamentos_financeiros (unificando com natureza).
MigrateIndices — ipcas → indices_economicos.
MigrateUsers — atualizar users vinculando pessoa_id quando aplicável.
Cada script deve:

Rodar dentro de uma transaction.
Ser idempotente (pode rodar de novo sem duplicar).
Gerar log de quantos registros migrados / divergências encontradas.
Fase 3 — Validação (2-3 dias)
Escrever testes de reconciliação: somar totais financeiros do modelo antigo vs novo (ex.: soma de valor_pago em mensalidades deve bater com soma em pagamento_taxa).
Validar contagens de registros por tabela.
Rodar a aplicação em modo "shadow" apontando para o novo schema em ambiente de homologação, com usuários reais testando fluxos críticos (gerar cobrança, registrar pagamento, emitir relatório).
Fase 4 — Corte (cutover) (1 dia, fora de horário de pico)
Colocar aplicação em modo de manutenção.
Rodar migração final de dados incrementais (delta desde o último dump de teste).
Trocar os Models/Controllers da aplicação para os novos (idealmente isso já foi feito por trás de feature flags/branch).
Deploy e smoke test dos fluxos críticos (login, emissão de boleto, baixa de pagamento).
Manter backup completo do banco antigo por período de segurança (ex.: 90 dias).
Fase 5 — Descomissionamento (após período de estabilização)
Após 2-4 semanas sem incidentes, remover tabelas antigas (proprietarios, imoveis, mensalidades, pagamento_mensalidades, cobranca_extra_mensalidade, receitas, despesas, ipcas).
Remover código morto (Models e Controllers antigos).
4. Recomendações Técnicas Complementares
Soft deletes em tudo que é financeiro: adicionar deleted_at em unidades, pessoas, taxas_condominiais, pagamentos, lancamentos_financeiros.
Casts monetários: usar decimal(12,2) de forma consistente (hoje há mistura de decimal(10,2) em quase tudo, ok, mas padronizar para valores maiores em condomínios grandes).
Enums como tabelas de domínio (opcional, mas recomendado se o negócio crescer): trocar enum('proprietario','inquilino') por tabela papeis se for necessário adicionar papéis dinamicamente sem migration.
Índices compostos: manter os índices já existentes no modelo atual (imovel_id, ano, mes) — eles são bons, replicar equivalentes em unidade_id, competencia_ano, competencia_mes.
Documentar o modelo com um diagrama ER antes de codificar — posso gerar um diagrama Mermaid do modelo proposto se você quiser visualizar as relações antes de seguir.
