<?php

declare(strict_types=1);

namespace App\Livewire\Financeiro\Configuracoes;

use App\Enums\MetodoCorrecao;
use App\Models\Configuracao;
use App\Support\ConfiguracoesCondominio;
use App\Support\DinheiroBr;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Configurações do condomínio (modelo novo — substitui a tela de Parâmetros
 * no cutover). O nome do condomínio edita condominios.nome; as demais chaves
 * vivem em configuracoes, escopadas pelo condomínio. A data de corte
 * (level_one) não existe mais — regra aposentada com o novo controle de acesso.
 */
#[Layout('layouts.app')]
#[Title('Configurações')]
class Edicao extends Component
{
    public string $nomeCondominio = '';

    public string $taxaMensalidadePadrao = '';

    public string $subtituloRecibo = '';

    public string $assinaturaRecibo = '';

    public string $metodoCorrecao = '';

    public string $anoInicialFiltroPagamentos = '';

    public string $mensagem = '';

    public function mount(): void
    {
        $this->authorize('gerenciar', Configuracao::class);

        $this->nomeCondominio = ConfiguracoesCondominio::nomeCondominio();
        $this->taxaMensalidadePadrao = DinheiroBr::formatar(ConfiguracoesCondominio::taxaMensalidadePadrao());
        $this->subtituloRecibo = ConfiguracoesCondominio::subtituloRecibo();
        $this->assinaturaRecibo = ConfiguracoesCondominio::assinaturaRecibo();
        $this->metodoCorrecao = ConfiguracoesCondominio::metodoCorrecao()->value;
        $this->anoInicialFiltroPagamentos = (string) ConfiguracoesCondominio::anoInicialFiltroPagamentos();
    }

    public function salvar(): void
    {
        $this->authorize('gerenciar', Configuracao::class);

        $this->validate([
            'nomeCondominio' => ['required', 'string', 'max:255'],
            'taxaMensalidadePadrao' => ['required', 'string'],
            'subtituloRecibo' => ['required', 'string', 'max:255'],
            'assinaturaRecibo' => ['required', 'string', 'max:255'],
            'metodoCorrecao' => ['required', Rule::enum(MetodoCorrecao::class)],
            'anoInicialFiltroPagamentos' => ['required', 'integer', 'min:1994', 'max:2100'],
        ], [], [
            'nomeCondominio' => 'Nome do condomínio',
            'taxaMensalidadePadrao' => 'Taxa de mensalidade padrão',
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

        ConfiguracoesCondominio::setNomeCondominio($this->nomeCondominio);
        ConfiguracoesCondominio::set('taxa_mensalidade_padrao', $taxa, 'decimal');
        ConfiguracoesCondominio::set('subtitulo_recibo', $this->subtituloRecibo);
        ConfiguracoesCondominio::set('assinatura_recibo', $this->assinaturaRecibo);
        ConfiguracoesCondominio::set('metodo_correcao', $this->metodoCorrecao);
        ConfiguracoesCondominio::set('ano_inicial_filtro_pagamentos', $this->anoInicialFiltroPagamentos, 'int');

        $this->taxaMensalidadePadrao = DinheiroBr::formatar($taxa);
        $this->mensagem = 'Configurações atualizadas com sucesso.';
    }

    public function render()
    {
        return view('livewire.financeiro.configuracoes.edicao', [
            'metodos' => MetodoCorrecao::cases(),
        ]);
    }
}
