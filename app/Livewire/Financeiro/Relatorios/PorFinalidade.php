<?php

declare(strict_types=1);

namespace App\Livewire\Financeiro\Relatorios;

use App\Models\Finalidade;
use App\Models\TaxaCondominial;
use App\Services\RateioPorFinalidadeService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Relatório de arrecadação por finalidade — o payoff da composição de taxas
 * (Etapa 5 de docs/migration/05-plano-composicao-taxas.md).
 *
 * Soma as duas fontes de receita afetadas a uma finalidade:
 *  (a) rateio em cascata das aplicações em pagamento_taxa de taxas
 *      contabilizadas — a parcela de cada item efetivamente paga;
 *  (b) lançamentos financeiros de receita com aquela finalidade.
 *
 * Descontando as despesas lançadas contra a finalidade, chega ao SALDO do
 * fundo — que é o que o Resumo financeiro segrega do disponível para custeio
 * quando a finalidade é marcada como vinculada (`restrita`).
 *
 * A leitura é ACUMULADA, não por intervalo: a pergunta é "quanto já
 * arrecadamos para X". `$ate` congela a leitura numa data.
 */
#[Layout('layouts.app')]
#[Title('Arrecadação por finalidade')]
class PorFinalidade extends Component
{
    #[Url(as: 'ate')]
    public string $ate = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Finalidade::class);
    }

    public function render()
    {
        $ate = $this->ate !== '' ? $this->ate : null;

        $linhas = app(RateioPorFinalidadeService::class)->arrecadadoPorFinalidade($ate);

        return view('livewire.financeiro.relatorios.por-finalidade', [
            'linhas' => $linhas,
            'totalArrecadado' => $linhas->reduce(
                fn (string $carry, array $l): string => bcadd($carry, $l['arrecadado'], 2),
                '0.00'
            ),
            'totalGasto' => $linhas->reduce(
                fn (string $carry, array $l): string => bcadd($carry, $l['gasto'], 2),
                '0.00'
            ),
            'totalSaldo' => $linhas->reduce(
                fn (string $carry, array $l): string => bcadd($carry, $l['saldo'], 2),
                '0.00'
            ),
            'totalAReceber' => $linhas->reduce(
                fn (string $carry, array $l): string => bcadd($carry, $l['a_receber'], 2),
                '0.00'
            ),
            'temTaxaSemComposicao' => TaxaCondominial::query()
                ->whereDoesntHave('itens')
                ->exists(),
        ]);
    }
}
