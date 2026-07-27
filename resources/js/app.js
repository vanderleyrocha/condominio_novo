import Chart from 'chart.js/auto';

const moedaBr = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });

function montarDatasets(dados) {
    return {
        labels: [...dados.anos],
        datasets: [
            {
                label: 'Receitas',
                data: [...dados.receitas],
                backgroundColor: '#2563eb', // --color-brand
                borderRadius: 4,
            },
            {
                label: 'Despesas',
                data: [...dados.despesas],
                backgroundColor: '#dc2626', // red-600, mesma semântica dos stat-cards
                borderRadius: 4,
            },
        ],
    };
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('graficoResumo', (dados) => {
        // A instância do Chart fica no closure, e não como propriedade do
        // componente: dentro do x-data ela viraria um Proxy reativo profundo,
        // e o Chart.js quebra ao percorrer os próprios internos proxiados
        // (stack overflow no toRaw e opções sem identidade em update()).
        let chart = null;

        return {
            init() {
                chart = new Chart(this.$refs.canvas, {
                    type: 'bar',
                    data: montarDatasets(dados),
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        // Hover na coluna do ano mostra receita e despesa juntas,
                        // mesmo quando a barra é pequena demais para acertar com o cursor.
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: { position: 'bottom' },
                            tooltip: {
                                callbacks: {
                                    label: (contexto) => `${contexto.dataset.label}: ${moedaBr.format(contexto.parsed.y)}`,
                                },
                            },
                        },
                        scales: {
                            y: {
                                ticks: {
                                    callback: (valor) => moedaBr.format(valor),
                                },
                            },
                        },
                    },
                });
            },

            atualizar(dados) {
                if (! chart) {
                    return;
                }

                chart.data = montarDatasets(dados);
                chart.update();
            },

            destroy() {
                chart?.destroy();
                chart = null;
            },
        };
    });
});
