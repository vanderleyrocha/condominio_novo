<?php

declare(strict_types=1);

namespace App\Livewire\Financeiro\Finalidades;

use App\Actions\Financeiro\SalvarFinalidade;
use App\Models\Finalidade;
use App\Services\RateioPorFinalidadeService;
use App\Support\DinheiroBr;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * CRUD de finalidades — a destinação da arrecadação
 * (docs/migration/05-plano-composicao-taxas.md §3.1, Etapa 5).
 * Mostra ao lado o arrecadado acumulado × meta, que é a pergunta real do
 * síndico: "quanto já juntamos para a pintura?".
 */
#[Layout('layouts.app')]
#[Title('Finalidades')]
class Gestao extends Component
{
    public bool $formAberto = false;

    public ?int $finalidadeId = null;

    #[Validate('required|string|max:100', as: 'Nome')]
    public string $formNome = '';

    #[Validate('nullable|string|max:1000', as: 'Descrição')]
    public string $formDescricao = '';

    #[Validate('nullable|string', as: 'Meta')]
    public string $formMeta = '';

    #[Validate('nullable|date', as: 'Vigência início')]
    public string $formVigenciaInicio = '';

    #[Validate('nullable|date|after_or_equal:formVigenciaInicio', as: 'Vigência fim')]
    public string $formVigenciaFim = '';

    public bool $formAtiva = true;

    /** Recursos carimbados: o saldo desta finalidade sai do disponível para custeio. */
    public bool $formRestrita = false;

    public string $mensagem = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Finalidade::class);
    }

    public function nova(): void
    {
        $this->authorize('create', Finalidade::class);

        $this->resetErrorBag();
        $this->finalidadeId = null;
        $this->formNome = '';
        $this->formDescricao = '';
        $this->formMeta = '';
        $this->formVigenciaInicio = '';
        $this->formVigenciaFim = '';
        $this->formAtiva = true;
        $this->formRestrita = false;
        $this->formAberto = true;
        $this->mensagem = '';
    }

    public function editar(int $id): void
    {
        $finalidade = Finalidade::query()->findOrFail($id);
        $this->authorize('update', $finalidade);

        $this->resetErrorBag();
        $this->finalidadeId = $finalidade->id;
        $this->formNome = $finalidade->nome;
        $this->formDescricao = $finalidade->descricao ?? '';
        $this->formMeta = $finalidade->meta_valor === null ? '' : DinheiroBr::formatar($finalidade->meta_valor);
        $this->formVigenciaInicio = $finalidade->vigencia_inicio?->toDateString() ?? '';
        $this->formVigenciaFim = $finalidade->vigencia_fim?->toDateString() ?? '';
        $this->formAtiva = (bool) $finalidade->ativa;
        $this->formRestrita = (bool) $finalidade->restrita;
        $this->formAberto = true;
        $this->mensagem = '';
    }

    public function cancelar(): void
    {
        $this->formAberto = false;
        $this->resetErrorBag();
    }

    public function salvar(SalvarFinalidade $acao): void
    {
        $this->validate();

        $finalidade = $this->finalidadeId !== null
            ? Finalidade::query()->findOrFail($this->finalidadeId)
            : null;

        $this->authorize($finalidade === null ? 'create' : 'update', $finalidade ?? Finalidade::class);

        try {
            $meta = $this->formMeta === '' ? null : DinheiroBr::paraDecimal($this->formMeta);
        } catch (InvalidArgumentException) {
            $this->addError('formMeta', 'Valor monetário inválido.');

            return;
        }

        $acao->executar([
            'nome' => $this->formNome,
            'descricao' => $this->formDescricao !== '' ? $this->formDescricao : null,
            'meta_valor' => $meta,
            'vigencia_inicio' => $this->formVigenciaInicio !== '' ? $this->formVigenciaInicio : null,
            'vigencia_fim' => $this->formVigenciaFim !== '' ? $this->formVigenciaFim : null,
            'ativa' => $this->formAtiva,
            'restrita' => $this->formRestrita,
        ], $finalidade);

        $this->formAberto = false;
        $this->mensagem = $finalidade === null
            ? 'Finalidade cadastrada com sucesso.'
            : 'Finalidade atualizada com sucesso.';
    }

    public function render()
    {
        $arrecadado = app(RateioPorFinalidadeService::class)
            ->arrecadadoPorFinalidade()
            ->keyBy(fn (array $l): int|string => $l['finalidade']?->id ?? 'sem_finalidade');

        return view('livewire.financeiro.finalidades.gestao', [
            'finalidades' => Finalidade::query()->orderBy('nome')->get(),
            'arrecadado' => $arrecadado,
        ]);
    }
}
