/** Chart.js charts. Each <canvas data-chart='{...}'> describes its own chart; colours come from the theme tokens. */
import Chart from 'chart.js/auto';

const css = (name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim();
const palette = () => [css('--c-brand'), css('--c-warn'), css('--c-info'), css('--c-danger'), css('--c-ok'), css('--c-muted'), '#8e6bbf', '#c2703d'];
const charts = [];

function formatter(config) {
    if (!config.money) return (value) => new Intl.NumberFormat().format(value);
    return (value) => {
        try {
            return new Intl.NumberFormat(document.documentElement.lang || 'en', { style: 'currency', currency: config.currency, maximumFractionDigits: 0, notation: Math.abs(value) >= 100000 ? 'compact' : 'standard' }).format(value);
        } catch {
            return `${config.currency} ${value}`;
        }
    };
}

function build(canvas) {
    const config = JSON.parse(canvas.dataset.chart);
    const colors = palette();
    const format = formatter(config);
    const round = config.type === 'doughnut' || config.type === 'pie';

    const datasets = config.datasets.map((dataset, i) => ({
        label: dataset.label,
        data: dataset.data,
        borderColor: round ? css('--c-surface') : colors[i % colors.length],
        backgroundColor: round ? dataset.data.map((_, j) => colors[j % colors.length]) : config.type === 'line' ? 'transparent' : colors[i % colors.length],
        borderWidth: round ? 2 : config.type === 'line' ? 2 : 0,
        borderRadius: config.type === 'bar' ? 3 : 0,
        tension: 0.3,
        pointRadius: config.type === 'line' && dataset.data.length > 40 ? 0 : 2,
        maxBarThickness: 36,
    }));

    return new Chart(canvas, {
        type: config.type,
        data: { labels: config.labels, datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? false : { duration: 400 },
            interaction: { mode: round ? 'nearest' : 'index', intersect: false },
            plugins: {
                legend: { display: round || datasets.length > 1, position: round ? 'right' : 'bottom', labels: { color: css('--c-muted'), boxWidth: 10, boxHeight: 10, usePointStyle: true } },
                tooltip: { callbacks: { label: (item) => `${round ? item.label : item.dataset.label}: ${format(round ? item.parsed : item.parsed.y)}` } },
            },
            scales: round ? {} : {
                x: { grid: { display: false }, ticks: { color: css('--c-muted'), maxRotation: 0, autoSkipPadding: 16 }, border: { color: css('--c-line') } },
                y: { beginAtZero: true, grid: { color: css('--c-line') }, ticks: { color: css('--c-muted'), callback: format }, border: { display: false } },
            },
        },
    });
}

function renderAll() {
    charts.splice(0).forEach((chart) => chart.destroy());
    document.querySelectorAll('canvas[data-chart]').forEach((canvas) => {
        canvas.closest('[data-chart-box]')?.querySelector('.skeleton')?.remove();
        charts.push(build(canvas));
    });
}

renderAll();

// Redraw with the new theme colours when the theme changes
new MutationObserver(renderAll).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
