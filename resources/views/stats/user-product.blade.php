<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $userProduct->title }}</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: system-ui, sans-serif; margin: 0; color: #111827; background: #f8fafc; }
        main { max-width: 960px; margin: 0 auto; padding: 32px 20px; }
        h1 { font-size: 24px; line-height: 1.25; margin: 0 0 12px; }
        .meta { color: #475569; margin-bottom: 24px; }
        .panel { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; }
        canvas { width: 100%; max-height: 420px; }
    </style>
</head>
<body>
<main>
    <h1>{{ $userProduct->title }}</h1>
    <div class="meta">
        Текущая цена:
        {{ $userProduct->current_price_minor ? number_format($userProduct->current_price_minor / 100, 0, '.', ' ') . ' ₽' : 'неизвестно' }}
        · Исторический минимум:
        {{ $userProduct->historical_min_price_minor ? number_format($userProduct->historical_min_price_minor / 100, 0, '.', ' ') . ' ₽' : 'неизвестно' }}
    </div>
    <div class="panel">
        <canvas id="priceChart"></canvas>
    </div>
</main>
<script>
const labels = @json($snapshots->map(fn ($snapshot) => $snapshot->captured_at->format('d.m H:i'))->values());
const data = @json($snapshots->map(fn ($snapshot) => $snapshot->price_minor / 100)->values());

new Chart(document.getElementById('priceChart'), {
    type: 'line',
    data: {
        labels,
        datasets: [{
            label: 'Цена, ₽',
            data,
            borderColor: '#0284c7',
            backgroundColor: 'rgba(2, 132, 199, 0.12)',
            tension: 0.2,
            fill: true
        }]
    },
    options: {
        responsive: true,
        scales: { y: { beginAtZero: false } }
    }
});
</script>
</body>
</html>
