<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeInterface;
use IntlDateFormatter;

/**
 * Textos em português para os PDFs (paridade com App\Repositories\Util do legado):
 * valor por extenso, data por extenso e nomes dos meses.
 */
final class TextoPtBr
{
    /**
     * @return array<int, string>
     */
    public static function meses(): array
    {
        return [
            1 => 'Janeiro',
            'Fevereiro',
            'Março',
            'Abril',
            'Maio',
            'Junho',
            'Julho',
            'Agosto',
            'Setembro',
            'Outubro',
            'Novembro',
            'Dezembro',
        ];
    }

    /**
     * Port literal de Util::valorPorExtenso() do legado (mesma saída).
     */
    public static function valorPorExtenso(float $valor = 0, bool $exibirMoeda = true, bool $palavraFeminina = false): string
    {
        if ($exibirMoeda) {
            $singular = ['centavo', 'real', 'mil', 'milhão', 'bilhão', 'trilhão', 'quatrilhão'];
            $plural = ['centavos', 'reais', 'mil', 'milhões', 'bilhões', 'trilhões', 'quatrilhões'];
        } else {
            $singular = ['', '', 'mil', 'milhão', 'bilhão', 'trilhão', 'quatrilhão'];
            $plural = ['', '', 'mil', 'milhões', 'bilhões', 'trilhões', 'quatrilhões'];
        }

        $c = ['', 'cem', 'duzentos', 'trezentos', 'quatrocentos', 'quinhentos', 'seiscentos', 'setecentos', 'oitocentos', 'novecentos'];
        $d = ['', 'dez', 'vinte', 'trinta', 'quarenta', 'cinquenta', 'sessenta', 'setenta', 'oitenta', 'noventa'];
        $d10 = ['dez', 'onze', 'doze', 'treze', 'quatorze', 'quinze', 'dezesseis', 'dezessete', 'dezoito', 'dezenove'];
        $u = ['', 'um', 'dois', 'três', 'quatro', 'cinco', 'seis', 'sete', 'oito', 'nove'];

        if ($palavraFeminina) {
            $u = $valor == 1
                ? ['', 'uma', 'duas', 'três', 'quatro', 'cinco', 'seis', 'sete', 'oito', 'nove']
                : ['', 'um', 'duas', 'três', 'quatro', 'cinco', 'seis', 'sete', 'oito', 'nove'];
            $c = ['', 'cem', 'duzentas', 'trezentas', 'quatrocentas', 'quinhentas', 'seiscentas', 'setecentas', 'oitocentas', 'novecentas'];
        }

        $z = 0;

        $valorFormatado = number_format($valor, 2, '.', '.');
        $inteiro = explode('.', $valorFormatado);

        for ($i = 0; $i < count($inteiro); $i++) {
            for ($ii = mb_strlen($inteiro[$i]); $ii < 3; $ii++) {
                $inteiro[$i] = '0'.$inteiro[$i];
            }
        }

        $rt = null;
        $fim = count($inteiro) - ($inteiro[count($inteiro) - 1] > 0 ? 1 : 2);

        for ($i = 0; $i < count($inteiro); $i++) {
            $grupo = $inteiro[$i];
            $rc = (($grupo > 100) && ($grupo < 200)) ? 'cento' : $c[(int) $grupo[0]];
            $rd = ($grupo[1] < 2) ? '' : $d[(int) $grupo[1]];
            $ru = ($grupo > 0) ? (($grupo[1] == 1) ? $d10[(int) $grupo[2]] : $u[(int) $grupo[2]]) : '';

            $r = $rc.(($rc && ($rd || $ru)) ? ' e ' : '').$rd.(($rd && $ru) ? ' e ' : '').$ru;
            $t = count($inteiro) - 1 - $i;
            $r .= $r ? ' '.($grupo > 1 ? $plural[$t] : $singular[$t]) : '';

            if ($grupo == '000') {
                $z++;
            } elseif ($z > 0) {
                $z--;
            }

            if (($t == 1) && ($z > 0) && ($inteiro[0] > 0)) {
                $r .= (($z > 1) ? ' de ' : '').$plural[$t];
            }

            if ($r) {
                $rt = $rt.((($i > 0) && ($i <= $fim) && ($inteiro[0] > 0) && ($z < 1)) ? (($i < $fim) ? ', ' : ' e ') : ' ').$r;
            }
        }

        $rt = mb_substr((string) $rt, 1);

        return $rt !== '' ? trim($rt) : 'zero';
    }

    /**
     * Port de Util::dataExtenso() do legado (IntlDateFormatter pt_BR LONG).
     */
    public static function dataExtenso(DateTimeInterface|string $data): string
    {
        $formatter = new IntlDateFormatter(
            'pt_BR',
            IntlDateFormatter::LONG,
            IntlDateFormatter::NONE,
            'America/Rio_Branco',
            IntlDateFormatter::GREGORIAN,
        );

        if (is_string($data)) {
            $data = new \DateTime($data);
        }

        return (string) $formatter->format($data);
    }
}
