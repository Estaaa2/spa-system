{{--
    Chart.js setup shared by Reports, Decision Support and Revenue (reports & finance revamp).
    - Pinned version: an unpinned CDN URL would pull a future major release untested.
    - Charts fill a fixed-height box (maintainAspectRatio: false), so they stay readable on
      phones instead of shrinking with the width.
    - Colours follow the OS theme (darkMode: 'media'), same as the rest of the business side.
    Include once, before the page's own chart script: @include('partials.insights-charts')
--}}
@once
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.6.1/dist/chart.umd.min.js"></script>
<script>
window.LevictasCharts = (function () {
    if (typeof Chart === 'undefined') return null;

    const dark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;

    Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
    Chart.defaults.font.size = 11;
    Chart.defaults.color = dark ? '#9ca3af' : '#6b7280';          // gray-400 / gray-500
    Chart.defaults.borderColor = dark ? '#374151' : '#f3f4f6';    // gray-700 / gray-100
    Chart.defaults.maintainAspectRatio = false;
    Chart.defaults.plugins.legend.labels.boxWidth = 12;
    Chart.defaults.plugins.legend.labels.usePointStyle = true;
    Chart.defaults.plugins.tooltip.backgroundColor = dark ? '#f9fafb' : '#111827';
    Chart.defaults.plugins.tooltip.titleColor = dark ? '#111827' : '#ffffff';
    Chart.defaults.plugins.tooltip.bodyColor = dark ? '#374151' : '#e5e7eb';
    Chart.defaults.plugins.tooltip.padding = 10;
    Chart.defaults.plugins.tooltip.cornerRadius = 12;
    Chart.defaults.plugins.tooltip.displayColors = false;

    // Brand brown, lighter in dark mode (#C4A97D, the dark-mode brand token).
    const colors = {
        primary: dark ? '#C4A97D' : '#8B7355',
        muted:   dark ? '#5C4D39' : '#D8CBB5',
        faint:   dark ? 'rgba(196,169,125,0.18)' : 'rgba(139,115,85,0.18)',
        fill:    dark ? 'rgba(196,169,125,0.12)' : 'rgba(139,115,85,0.08)',
        palette: dark
            ? ['#C4A97D', '#8B7355', '#E3D3B5', '#6F5430', '#A8916E', '#5C4D39', '#D2A85B', '#9C836A']
            : ['#8B7355', '#C4A97D', '#6F5430', '#D8CBB5', '#A8916E', '#5C4D39', '#D2A85B', '#9C836A'],
    };

    function niceMax(values) {
        const m = Math.max(...values, 0);
        if (m <= 5)  return 5;
        if (m <= 10) return 10;
        if (m <= 20) return 20;
        return Math.ceil(m * 1.2);
    }
    function step(max) {
        if (max <= 5)  return 1;
        if (max <= 10) return 2;
        if (max <= 20) return 5;
        if (max <= 50) return 10;
        return Math.ceil(max / 5);
    }
    /** Count axis: whole numbers, starts at zero, no vertical grid noise. */
    function countAxis(values) {
        const raw = niceMax(values);
        const max = Math.ceil(raw / step(raw)) * step(raw);   // top tick lands on a step
        return { beginAtZero: true, max, ticks: { stepSize: step(max), precision: 0 }, grid: { drawTicks: false }, border: { display: false } };
    }
    /** Peso axis: ₱1.2k style ticks so the axis doesn't eat a phone's width. */
    function pesoAxis(values) {
        const max = Math.max(...values, 0);
        return {
            beginAtZero: true,
            suggestedMax: max <= 0 ? 100 : Math.ceil(max * 1.15),
            ticks: { callback: v => peso(v, true), maxTicksLimit: 6 },
            grid: { drawTicks: false },
            border: { display: false },
        };
    }
    function categoryAxis(maxTicks) {
        return { grid: { display: false }, border: { display: false }, ticks: { maxTicksLimit: maxTicks || 10, maxRotation: 0, autoSkipPadding: 8 } };
    }
    function peso(value, compact) {
        const n = Number(value || 0);
        if (compact && Math.abs(n) >= 1000) {
            return '₱' + new Intl.NumberFormat('en-PH', { notation: 'compact', maximumFractionDigits: 1 }).format(n);
        }
        return '₱' + n.toLocaleString('en-PH', { minimumFractionDigits: compact ? 0 : 2, maximumFractionDigits: compact ? 0 : 2 });
    }
    /** "2026-11-03" → "Nov 3" (parsed as local date, not UTC). */
    function shortDate(ymd) {
        const [y, m, d] = String(ymd).slice(0, 10).split('-').map(Number);   // also takes "Y-m-d H:i:s"
        if (!y || !m || !d) return ymd;
        return new Date(y, m - 1, d).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    }

    return { dark, colors, countAxis, pesoAxis, categoryAxis, peso, shortDate };
})();
</script>
@endonce
