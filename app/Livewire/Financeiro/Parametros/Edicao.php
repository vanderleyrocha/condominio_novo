<?php

declare(strict_types=1);

namespace App\Livewire\Financeiro\Parametros;

use App\Enums\MetodoCorrecao;
use App\Models\User;
use App\Support\DinheiroBr;
use App\Support\ParametrosCondominio;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Parâmetros do condomínio — edição das 7 chaves seed (DA-04),
 * restrita a admin. Toda escrita via ParametrosCondominio::set().
 */
#[Layout('layouts.app')]
class Edicao extends Component
{
    public string $taxaMensalidadePadrao = '';

    public string $dataCorteLevelOne = '';

    public string $nomeCondominio = '';

    public string $subtituloRecibo = '';

    public string $assinaturaRecibo = '';

    public string $metodoCorrecao = '';

    public string $anoInicialFiltroPagamentos = '';

    public string $mensagem = '';

    public function mount(): void
    {
        $this->authorize('gerenciar', User::class);

        $this->taxaMensalidadePadrao = DinheiroBr::formatar(ParametrosCondominio::taxaMensalidadePadrao());
        $this->dataCorteLevelOne = ParametrosCondominio::dataCorteLevelOne();
        $this->nomeCondominio = ParametrosCondominio::nomeCondominio();
        $this->subtituloRecibo = ParametrosCondominio::subtituloRecibo();
        $this->assinaturaRecibo = ParametrosCondominio::assinaturaRecibo();
        $this->metodoCorrecao = ParametrosCondominio::metodoCorrecao()->value;
        $this->anoInicialFiltroPagamentos = (string) ParametrosCondominio::anoInicialFiltroPagamentos();
    }

    public function salvar(): void
    {
        $this->authorize('gerenciar', User::class);

        $this->validate([
            'taxaMensalidadePadrao' => ['required', 'string'],
            'dataCorteLevelOne' => ['required', 'date'],
            'nomeCondominio' => ['required', 'string', 'max:255'],
            'subtituloRecibo' => ['required', 'string', 'max:255'],
            'assinaturaRecibo' => ['required', 'string', 'max:255'],
            'metodoCorrecao' => ['required', Rule::enum(MetodoCorrecao::class)],
            'anoInicialFiltroPagamentos' => ['required', 'integer', 'min:1994', 'max:2100'],
        ], [], [
            'taxaMensalidadePadrao' => 'Taxa de mensalidade padrão',
            'dataCorteLevelOne' => 'Data de corte (level one)',
            'nomeCondominio' => 'Nome do condomínio',
            'subtituloRecibo' => 'Subtítulo do recibo',
            'assinaturaRecibo' => 'Assinatura do recibo',
            'metodoCorrecao' => 'Método de correção',
            'anoInicialFiltroPagamentos' => 'Ano inicial do filtro de pagamentos',
        ]);

        try {
            $taxa = DinheiroBr::paraDecimal($this->taxaMensalidadePadrao);
        } catch (\InvalidArgumentException) {
            $this->addError('taxaMensalidadePadrao', 'Valor monetário inválido.');

            return;
        }

        ParametrosCondominio::set('taxa_mensalidade_padrao', $taxa);
        ParametrosCondominio::set('data_corte_level_one', $this->dataCorteLevelOne);
        ParametrosCondominio::set('nome_condominio', $this->nomeCondominio);
        ParametrosCondominio::set('subtitulo_recibo', $this->subtituloRecibo);
        ParametrosCondominio::set('assinatura_recibo', $this->assinaturaRecibo);
        ParametrosCondominio::set('metodo_correcao', $this->metodoCorrecao);
        ParametrosCondominio::set('ano_inicial_filtro_pagamentos', $this->anoInicialFiltroPagamentos);

        $this->taxaMensalidadePadrao = DinheiroBr::formatar($taxa);
        $this->mensagem = 'Parâmetros atualizados com sucesso.';
    }

    public function render()
    {
        return view('livewire.financeiro.parametros.edicao', [
            'metodos' => MetodoCorrecao::cases(),
        ]);
    }
}
