import Chart from 'chart.js/auto';

function parseJsonScript(scriptId) {
    const el = document.getElementById(scriptId);
    if (!el) return null;
    try {
        return JSON.parse(el.textContent || 'null');
    } catch {
        return null;
    }
}

function buildChartConfig(dataset) {
    const slots = Array.isArray(dataset?.slots) ? dataset.slots : [];
    const labels = slots.map((s) => String(s.slot_number ?? ''));
    const reserved = slots.map((s) => Number(s.reserved ?? 0));
    const pending = slots.map((s) => Number(s.pending ?? 0));
    const capacity = Number(dataset?.capacity ?? 0);

    const totals = slots.map((s) => Number(s.total ?? (Number(s.reserved ?? 0) + Number(s.pending ?? 0))));
    const maxTotal = Math.max(0, ...totals, capacity);

    return {
        type: 'bar',
        data: {
            labels,
            datasets: [
                {
                    label: 'Rezervisano',
                    data: reserved,
                    backgroundColor: '#1f6feb',
                    borderWidth: 0,
                    stack: 'cap',
                },
                {
                    label: 'Pending',
                    data: pending,
                    backgroundColor: '#f2cc60',
                    borderWidth: 0,
                    stack: 'cap',
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: {
                    stacked: true,
                    title: { display: true, text: 'Slot' },
                    ticks: { maxRotation: 0, autoSkip: true },
                },
                y: {
                    stacked: true,
                    beginAtZero: true,
                    // Allow "soft overflow" above capacity.
                    suggestedMax: maxTotal,
                    title: { display: true, text: 'Popunjenost' },
                },
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        title(items) {
                            const i = items?.[0]?.dataIndex ?? 0;
                            const s = slots[i] || {};
                            const n = Number(s.slot_number ?? i + 1);
                            const label = String(s.time_label ?? '');
                            return `Slot ${n}: ${label}`;
                        },
                        label(ctx) {
                            const i = ctx.dataIndex ?? 0;
                            const s = slots[i] || {};
                            const r = Number(s.reserved ?? 0);
                            const p = Number(s.pending ?? 0);
                            const t = r + p;
                            if (ctx.datasetIndex === 0) return `Rezervisano: ${r}`;
                            if (ctx.datasetIndex === 1) return `Pending: ${p}`;
                            return `Ukupno: ${t}`;
                        },
                        afterBody(items) {
                            const i = items?.[0]?.dataIndex ?? 0;
                            const s = slots[i] || {};
                            const r = Number(s.reserved ?? 0);
                            const p = Number(s.pending ?? 0);
                            const t = r + p;
                            return [`Ukupno: ${t}`];
                        },
                    },
                },
            },
        },
        plugins: [
            // Capacity marker line (drawn after datasets).
            {
                id: 'capacityMarker',
                afterDatasetsDraw(chart) {
                    const cap = Number(dataset?.capacity ?? 0);
                    if (!cap || cap <= 0) return;

                    const { ctx, chartArea, scales } = chart;
                    const y = scales.y.getPixelForValue(cap);
                    if (y < chartArea.top || y > chartArea.bottom) return;

                    ctx.save();
                    ctx.strokeStyle = '#111827';
                    ctx.lineWidth = 1;
                    ctx.setLineDash([4, 4]);
                    ctx.beginPath();
                    ctx.moveTo(chartArea.left, y);
                    ctx.lineTo(chartArea.right, y);
                    ctx.stroke();
                    ctx.restore();
                },
            },
        ],
    };
}

function initOne(canvas) {
    const scriptId = canvas.getAttribute('data-capacity-chart-script-id');
    if (!scriptId) return;

    const dataset = parseJsonScript(scriptId);
    if (!dataset) return;

    const config = buildChartConfig(dataset);
    // eslint-disable-next-line no-new
    new Chart(canvas, config);
}

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('canvas.js-daily-capacity-chart').forEach((canvas) => initOne(canvas));
});

