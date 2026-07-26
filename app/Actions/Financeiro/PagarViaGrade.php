<?php

declare(strict_types=1);

namespace App\Actions\Financeiro;

use App\Enums\FormaPagamento;
use App\Models\Pagamento;
use App\Models\PagamentoTaxa;
use App\Models\TaxaCondominial;
use App\Models\User;
use App\Services\StatusTaxaService;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Grade anual no modelo novo (substitui AtualizarGradeAnual). A célula da
 * grade representa o TOTAL PAGO da taxa; como pagamento agora é primeira
 * classe (pagamento_taxa), a diferença digitada vira um pagamento real:
 *  - delta > 0 → pagamento "via grade anual";
 *  - delta < 0 → ajuste negativo "via grade anual" (redução explícita);
 * atribuído ao responsável financeiro vigente da unidade, com recálculo do
 * status pelo serviço único. Persistência seletiva: célula sem mudança
 * (tolerância de meio centavo, como no legado) é ignorada.
 */
class PagarViaGrade
{
    public function __construct(private readonly StatusTaxaService $statusService) {}

    /**
     * @param  array<int, string>  $valoresPagos  [taxa_id => total pago alvo]
     * @return int quantidade de células efetivamente gravadas
     */
    public function executar(array $valoresPagos, User $ator): int
    {
        return DB::transaction(function () use ($valoresPagos, $ator): int {
            $gravadas = 0;

            foreach ($valoresPagos as $taxaId => $valorAlvo) {
                $taxa = TaxaCondominial::query()->lockForUpdate()->find($taxaId);

                if ($taxa === null) {
                    continue;
                }

                $somaAtual = (string) ($taxa->pagamentoTaxas()->sum('valor_aplicado') ?: '0');
                $delta = bcsub(
                    number_format((float) $valorAlvo, 2, '.', ''),
                    $somaAtual,
                    2
                );

                // Persistência seletiva — tolerância de meio centavo (paridade)
                if (abs((float) $delta) < 0.005) {
                    continue;
                }

                if (! Gate::forUser($ator)->allows('pagarViaGrade', TaxaCondominial::class)) {
                    throw new AuthorizationException(
                        "Sem permissão para alterar a taxa {$taxa->competencia_mes}/{$taxa->competencia_ano}."
                    );
                }

                $responsavel = $taxa->unidade->vinculos()
                    ->whereNull('data_fim')
                    ->orderByDesc('responsavel_financeiro')
                    ->value('pessoa_id');

                if ($responsavel === null) {
                    throw new DomainException(
                        "A unidade {$taxa->unidade->identificacao} não tem pessoa vinculada para receber o pagamento."
                    );
                }

                $rotulo = bccomp($delta, '0', 2) > 0 ? 'Pagamento' : 'Ajuste (redução)';

                $pagamento = Pagamento::query()->create([
                    'unidade_id' => $taxa->unidade_id,
                    'pessoa_id' => $responsavel,
                    'data_pagamento' => now()->toDateString(),
                    'descricao' => "{$rotulo} via grade anual {$taxa->competencia_mes}/{$taxa->competencia_ano}",
                    'valor_total' => $delta,
                    'forma_pagamento' => FormaPagamento::NaoInformado,
                ]);

                PagamentoTaxa::query()->create([
                    'pagamento_id' => $pagamento->id,
                    'taxa_condominial_id' => $taxa->id,
                    'valor_aplicado' => $delta,
                ]);

                $this->statusService->recalcular($taxa);

                $gravadas++;
            }

            return $gravadas;
        });
    }
}
