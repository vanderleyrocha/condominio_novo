<?php

declare(strict_types=1);

namespace App\Livewire\Financeiro\CobrancasExtras;

use App\Actions\Financeiro\SalvarCobrancaExtra;
use App\Models\CobrancaExtra;
use App\Models\User;
use App\Support\DinheiroBr;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Cobranças extras — generalização das taxas de "poupança"/"juros" do legado
 * (BR-MIGRAR-011). Restrito a admin. Mostra o total apurado por cobrança
 * (pivots de mensalidades + receitas vinculadas).
 */
#[Layout('layouts.app')]
class Gestao extends Component
{
    public bool $formAberto = false;

    public ?int $cobrancaId = null;

    #[Validate('required|string|max:255', as: 'Nome')]
    public string $formNome = '';

    #[Validate('required|string', as: 'Valor')]
    public string $formValor = '0,00';

    #[Validate('required|date', as: 'Vigência início')]
    public string $formVigenciaInicio = '';

    #[Validate('nullable|date|after_or_equal:formVigenciaInicio', as: 'Vigência fim')]
    public string $formVigenciaFim = '';

    public bool $formAtiva = true;

    public string $mensagem = '';

    public function mount(): void
    {
        $this->authorize('gerenciar', User::class);
    }

    public function novaCobranca(): void
    {
        $this->resetErrorBag();
        $this->cobrancaId = null;
        $this->formNome = '';
        $this->formValor = '0,00';
        $this->formVigenciaInicio = date('Y-m-d');
        $this->formVigenciaFim = '';
        $this->formAtiva = true;
        $this->formAberto = true;
        $this->mensagem = '';
    }

    public function editar(int $id): void
    {
        $cobranca = CobrancaExtra::query()->findOrFail($id);

        $this->resetErrorBag();
        $this->cobrancaId = $cobranca->id;
        $this->formNome = $cobranca->nome;
        $this->formValor = DinheiroBr::formatar($cobranca->valor);
        $this->formVigenciaInicio = $cobranca->vigencia_inicio->toDateString();
        $this->formVigenciaFim = $cobranca->vigencia_fim?->toDateString() ?? '';
        $this->formAtiva = (bool) $cobranca->ativa;
        $this->formAberto = true;
        $this->mensagem = '';
    }

    public function cancelar(): void
    {
        $this->formAberto = false;
        $this->resetErrorBag();
    }

    public function salvar(SalvarCobrancaExtra $acao): void
    {
        $this->authorize('gerenciar', User::class);
        $this->validate();

        try {
            $valor = DinheiroBr::paraDecimal($this->formValor);
        } catch (\InvalidArgumentException) {
            $this->addError('formValor', 'Valor monetário inválido.');

            return;
        }

        $cobranca = $this->cobrancaId !== null
            ? CobrancaExtra::query()->findOrFail($this->cobrancaId)
            : null;

        $acao->executar([
            'nome' => $this->formNome,
            'valor' => $valor,
            'vigencia_inicio' => $this->formVigenciaInicio,
            'vigencia_fim' => $this->formVigenciaFim !== '' ? $this->formVigenciaFim : null,
            'ativa' => $this->formAtiva,
        ], $cobranca);

        $this->formAberto = false;
        $this->mensagem = $cobranca === null
            ? 'Cobrança extra cadastrada com sucesso.'
            : 'Cobrança extra atualizada com sucesso.';
    }

    public function render()
    {
        $cobrancas = CobrancaExtra::query()
            ->withSum('receitas as total_receitas', 'valor')
            ->withSum('mensalidades as total_mensalidades', 'cobranca_extra_mensalidade.valor')
            ->orderBy('nome')
            ->get();

        return view('livewire.financeiro.cobrancas-extras.gestao', [
            'cobrancas' => $cobrancas,
        ]);
    }
}
