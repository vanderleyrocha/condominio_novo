<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\PapelUsuario;
use App\Http\Controllers\PdfController;
use App\Models\Pagamento;
use App\Models\TaxaCondominial;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Gera os documentos equivalentes aos golden files do legado
 * (_reversa_sdd/screens/golden/manifest.yaml) para comparação de paridade —
 * portado para o schema novo na Fase 5 (ids preservados pelo ETL: as mesmas
 * entidades produzem documentos comparáveis).
 * Saída em storage/app/golden-novo/.
 */
class GoldenGerar extends Command
{
    protected $signature = 'golden:gerar {--saida=golden-novo}';

    protected $description = 'Gera PDFs equivalentes aos golden files do legado para comparação de paridade';

    public function handle(): int
    {
        $dir = storage_path('app/'.$this->option('saida'));
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $admin = User::query()->where('papel', PapelUsuario::Admin)->firstOrFail();
        Auth::login($admin);

        $pdf = app(PdfController::class);

        $salvar = function (string $nome, callable $gerar) use ($dir): void {
            try {
                $resposta = $gerar();
                file_put_contents("{$dir}/{$nome}.pdf", $resposta->getContent());
                $this->info("OK  {$nome}");
            } catch (\Throwable $e) {
                $this->error("FAIL {$nome}: ".$e->getMessage());
            }
        };

        // Ids preservados na migração: mesmas entidades dos golden files do legado
        foreach ([2137, 2287, 2308] as $id) {
            $salvar("recibo_mensalidade_{$id}", fn () => $pdf->reciboTaxa(TaxaCondominial::findOrFail($id)));
        }

        foreach ([1, 2] as $id) {
            $salvar("recibo_pagamento_{$id}", fn () => $pdf->reciboPagamento(Pagamento::findOrFail($id)));
        }

        foreach ([1, 2, 3] as $id) {
            $salvar("dividas_imovel_{$id}", fn () => $pdf->inadimplenciaPorUnidade(Unidade::findOrFail($id)));
        }

        $salvar('dividas_consolidado', fn () => $pdf->inadimplenciaConsolidada());
        $salvar('resumo_historico', fn () => $pdf->resumoHistorico());
        $salvar('resumo_intervalo_2025', fn () => $pdf->resumoIntervalo(
            Request::create('/pdf/resumo/intervalo', 'GET', ['de' => '2025-01-01', 'ate' => '2025-12-31']),
        ));
        $salvar('despesas_2025', fn () => $pdf->despesasPorPeriodo(
            Request::create('/pdf/despesas', 'GET', ['data_inicial' => '2025-01-01', 'data_final' => '2025-12-31']),
        ));

        $this->line("Saída em: {$dir}");

        return self::SUCCESS;
    }
}
