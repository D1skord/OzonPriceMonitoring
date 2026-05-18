<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $userProduct->title }}</title>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body { font-family: system-ui, sans-serif; margin: 0; color: #111827; background: #f1f5f9; }
        main { max-width: 980px; margin: 0 auto; padding: 28px 16px 48px; }
        h1 { font-size: 20px; line-height: 1.3; margin: 0 0 8px; font-weight: 600; }
        .meta { display: flex; flex-wrap: wrap; gap: 16px; margin-bottom: 24px; }
        .stat { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px 16px; min-width: 140px; }
        .stat__label { font-size: 12px; color: #6b7280; margin-bottom: 2px; }
        .stat__value { font-size: 20px; font-weight: 600; color: #0f172a; }
        .stat--min .stat__value { color: #059669; }
        .panel { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 16px 8px 4px; overflow: hidden; }
        .range-btns { display: flex; gap: 6px; flex-wrap: wrap; padding: 0 8px 12px; }
        .range-btn { font-size: 12px; font-weight: 500; padding: 4px 10px; border-radius: 5px; border: 1px solid #d1d5db;
            background: #fff; color: #374151; cursor: pointer; transition: background .15s, color .15s; }
        .range-btn:hover { background: #f3f4f6; }
        .range-btn.active { background: #0284c7; color: #fff; border-color: #0284c7; }
        #chart-main { min-height: 280px; }
        #chart-brush { min-height: 90px; }
    </style>
</head>
<body>
<main>
    <h1>{{ $userProduct->title }}</h1>

    <div class="meta">
        <div class="stat">
            <div class="stat__label">Текущая цена</div>
            <div class="stat__value">
                {{ $userProduct->current_price_minor ? number_format($userProduct->current_price_minor / 100, 0, '.', ' ') . ' ₽' : '—' }}
            </div>
        </div>
        <div class="stat stat--min">
            <div class="stat__label">Исторический минимум</div>
            <div class="stat__value">
                {{ $userProduct->historical_min_price_minor ? number_format($userProduct->historical_min_price_minor / 100, 0, '.', ' ') . ' ₽' : '—' }}
            </div>
        </div>
        <div class="stat">
            <div class="stat__label">Точек данных</div>
            <div class="stat__value">{{ $chartSeries->count() }}</div>
        </div>
    </div>

    <div class="panel">
        <div class="range-btns">
            <button class="range-btn" data-days="7">7 дн</button>
            <button class="range-btn" data-days="30">30 дн</button>
            <button class="range-btn" data-days="90">3 мес</button>
            <button class="range-btn active" data-days="0">Всё время</button>
        </div>
        <div id="chart-main"></div>
        <div id="chart-brush"></div>
    </div>
</main>

<script>
const rawSeries = @json($chartSeries);

const minPrice = {{ $userProduct->historical_min_price_minor ? $userProduct->historical_min_price_minor / 100 : 'null' }};

const fmtPrice = val => new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 0 }).format(val) + ' ₽';

const mainOptions = {
    series: [{ name: 'Цена', data: rawSeries }],
    chart: {
        id: 'main',
        type: 'area',
        height: 300,
        toolbar: { show: true, tools: { download: false, selection: true, zoom: true, zoomin: true, zoomout: true, pan: true, reset: true } },
        animations: { enabled: false },
        locales: [{
            name: 'ru',
            options: {
                toolbar: { download: 'Скачать', selection: 'Выделить', selectionZoom: 'Выделить область', zoomin: 'Увеличить', zoomout: 'Уменьшить', pan: 'Перемещение', reset: 'Сбросить' }
            }
        }],
        defaultLocale: 'ru',
    },
    stroke: { curve: 'stepline', width: 2 },
    fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.35, opacityTo: 0.05 } },
    colors: ['#0284c7'],
    dataLabels: { enabled: false },
    xaxis: { type: 'datetime', labels: { datetimeUTC: false } },
    yaxis: {
        labels: { formatter: fmtPrice },
        tickAmount: 5,
    },
    tooltip: {
        x: { format: 'dd.MM.yyyy HH:mm' },
        y: { formatter: fmtPrice },
    },
    annotations: minPrice ? {
        yaxis: [{
            y: minPrice,
            borderColor: '#059669',
            strokeDashArray: 4,
            label: { text: 'Мин ' + fmtPrice(minPrice), style: { color: '#fff', background: '#059669' } }
        }]
    } : {},
    grid: { borderColor: '#f1f5f9' },
};

const brushOptions = {
    series: [{ name: 'Цена', data: rawSeries }],
    chart: {
        id: 'brush',
        brush: { target: 'main', enabled: true },
        selection: { enabled: true },
        type: 'area',
        height: 90,
        toolbar: { show: false },
        animations: { enabled: false },
    },
    stroke: { curve: 'stepline', width: 1 },
    fill: { type: 'gradient', gradient: { opacityFrom: 0.25, opacityTo: 0.02 } },
    colors: ['#0284c7'],
    dataLabels: { enabled: false },
    xaxis: { type: 'datetime', labels: { datetimeUTC: false }, tooltip: { enabled: false } },
    yaxis: { show: false },
    grid: { borderColor: '#f1f5f9' },
};

const mainChart = new ApexCharts(document.getElementById('chart-main'), mainOptions);
const brushChart = new ApexCharts(document.getElementById('chart-brush'), brushOptions);
mainChart.render();
brushChart.render();

document.querySelectorAll('.range-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.range-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        const days = parseInt(btn.dataset.days);
        const now = Date.now();
        const from = days > 0 ? now - days * 86400000 : rawSeries[0]?.x ?? now;

        mainChart.zoomX(from, now);
    });
});
</script>
</body>
</html>
