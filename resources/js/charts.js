import Chart from 'chart.js/auto';

const gridColor = '#3f3f46';
const tickColor = '#a1a1aa';
const barColor = '#3987e5';
const tooltipBackground = '#27272a';

function formatValue(prefix, value) {
    const formatted = new Intl.NumberFormat().format(value);

    return prefix ? `${prefix}${formatted}` : formatted;
}

document.addEventListener('alpine:init', () => {
    Alpine.data('barChart', ({ labels, data, horizontal = false, valuePrefix = '' }) => ({
        chart: null,

        init() {
            const valueAxis = {
                beginAtZero: true,
                grid: { color: gridColor, drawTicks: false },
                border: { display: false },
                ticks: {
                    color: tickColor,
                    precision: 0,
                    callback: (value) => formatValue(valuePrefix, value),
                },
            };

            const categoryAxis = {
                grid: { display: false },
                border: { display: false },
                ticks: { color: tickColor },
            };

            this.chart = new Chart(this.$refs.canvas, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{
                        data,
                        backgroundColor: barColor,
                        borderRadius: 4,
                        borderSkipped: false,
                        maxBarThickness: 24,
                    }],
                },
                options: {
                    indexAxis: horizontal ? 'y' : 'x',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: tooltipBackground,
                            titleColor: '#e4e4e7',
                            bodyColor: '#ffffff',
                            padding: 10,
                            cornerRadius: 6,
                            displayColors: false,
                            callbacks: {
                                label: (context) => formatValue(valuePrefix, context.parsed[horizontal ? 'x' : 'y']),
                            },
                        },
                    },
                    scales: horizontal
                        ? { x: valueAxis, y: categoryAxis }
                        : { x: categoryAxis, y: valueAxis },
                },
            });
        },

        destroy() {
            this.chart?.destroy();
        },
    }));

    Alpine.data('doughnutChart', ({ labels, data, colors, valuePrefix = '' }) => ({
        chart: null,

        init() {
            this.chart = new Chart(this.$refs.canvas, {
                type: 'doughnut',
                data: {
                    labels,
                    datasets: [{
                        data,
                        backgroundColor: colors,
                        borderColor: tooltipBackground,
                        borderWidth: 2,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '65%',
                    plugins: {
                        // A custom HTML legend (name + exact amount) is rendered
                        // alongside the canvas instead — see the Cost Breakdown
                        // section in trips/show.blade.php.
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: tooltipBackground,
                            titleColor: '#e4e4e7',
                            bodyColor: '#ffffff',
                            padding: 10,
                            cornerRadius: 6,
                            callbacks: {
                                label: (context) => `${context.label}: ${formatValue(valuePrefix, context.parsed)}`,
                            },
                        },
                    },
                },
            });
        },

        destroy() {
            this.chart?.destroy();
        },
    }));
});
