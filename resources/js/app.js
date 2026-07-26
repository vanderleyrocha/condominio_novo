import Chart from 'chart.js/auto';

const moedaBr = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });

function montarDatasets(dados) {
    return {
        labels: dados.anos,
        datasets: [
            {
                label: 'Receitas',
                data: dados.receitas,
                backgroundColor: '#2563eb', // --color-brand
                borderRadius: 4,
            },
            {
                label: 'Despesas',
                data: dados.despesas,
                backgroundColor: '#dc2626', // red-600, mesma semântica dos stat-cards
                borderRadius: 4,
            },
        ],
    };
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('graficoResumo', (dados) => ({
        chart: null,

        init() {
            this.chart = new Chart(this.$refs.canvas, {
                type: 'bar',
                data: montarDatasets(dados),
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
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
            this.chart.data = montarDatasets(dados);
            this.chart.update();
        },

        destroy() {
            this.chart?.destroy();
        },
    }));
});
