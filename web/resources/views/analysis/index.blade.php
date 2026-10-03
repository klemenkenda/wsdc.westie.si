@extends('layouts.app')

@section('title', 'Analiza')

@section('content')

@php
    $years      = $pointsData['years'];
    $yearCount  = count($years);
    $lastYear   = $yearCount ? $years[$yearCount - 1] : null;
    $lastPts    = $yearCount ? $pointsData['total'][$yearCount - 1] : 0;
    $prevPts    = $yearCount > 1 ? $pointsData['total'][$yearCount - 2] : null;
    $allPts     = array_sum($pointsData['total']);
    $lastDancers = $yearCount ? $pointsData['dancers'][$yearCount - 1] : 0;
    $divOrder   = ['CHA', 'ALS', 'ADV', 'INT', 'NOV', 'NEW'];
@endphp

<style>
    .stat-tile { padding: 1rem 1.15rem; height: 100%; }
    .stat-tile .stat-label { font-size: .78rem; color: var(--text-muted); font-weight: 600; }
    .stat-tile .stat-value { font-size: 2rem; font-weight: 750; letter-spacing: -.02em; line-height: 1.15; }
    .stat-tile .stat-delta { font-size: .78rem; color: var(--text-muted); }

    .section-title { font-size: 1.05rem; font-weight: 700; margin: 0; }
    .chart-box { position: relative; height: 300px; }
    .chart-box.tall { height: 340px; }
    .chart-box.short { height: 260px; }

    .legend-row { display: flex; flex-wrap: wrap; gap: .35rem; }
    .legend-pill {
        display: inline-flex; align-items: center; gap: .4rem;
        padding: .2rem .65rem; border-radius: 999px;
        border: 1px solid var(--border-soft); background: var(--surface);
        color: var(--bs-body-color); font-size: .75rem; font-weight: 600; line-height: 1.4;
        cursor: pointer; transition: opacity .15s, border-color .15s;
    }
    .legend-pill:hover { border-color: rgba(var(--brand-rgb), .45); }
    .legend-pill .dot { width: .6rem; height: .6rem; border-radius: 50%; background: var(--dot); box-shadow: inset 0 0 0 2px var(--dot); }
    .legend-pill.off { opacity: .5; }
    .legend-pill.off .dot { background: transparent; }

    .points-table th, .points-table td { text-align: right; white-space: nowrap; }
    .points-table th:first-child, .points-table td:first-child { text-align: left; }
</style>

<div class="page-header">
    <div class="eyebrow">Statistika</div>
    <h1>Analiza skupnosti</h1>
    <p>Točke in trendi slovenskih WCS tekmovalcev skozi leta.</p>
</div>

{{-- ── Points ────────────────────────────────────────────────────────── --}}
@if($yearCount)
<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="card stat-tile">
            <div class="stat-label">Vse točke</div>
            <div class="stat-value">{{ number_format($allPts, 0, ',', '.') }}</div>
            <div class="stat-delta">{{ $years[0] }}–{{ $lastYear }}</div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card stat-tile">
            <div class="stat-label">Točke v {{ $lastYear }}</div>
            <div class="stat-value">{{ number_format($lastPts, 0, ',', '.') }}</div>
            @if($prevPts !== null)
            <div class="stat-delta">
                @if($lastPts === $prevPts)
                    enako kot {{ $lastYear - 1 }}
                @else
                    {{ $lastPts > $prevPts ? '▲ +' : '▼ ' }}{{ $lastPts - $prevPts }} glede na {{ $lastYear - 1 }}
                @endif
            </div>
            @endif
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card stat-tile">
            <div class="stat-label">Plesalci s točkami v {{ $lastYear }}</div>
            <div class="stat-value">{{ $lastDancers }}</div>
            @if($yearCount > 1)
            <div class="stat-delta">{{ $pointsData['dancers'][$yearCount - 2] }} v {{ $lastYear - 1 }}</div>
            @endif
        </div>
    </div>
</div>

<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
    <div class="seg" role="group" aria-label="Razčlenitev točk">
        <input type="radio" class="btn-check" name="pointsMode" id="pmDiv" value="division" checked autocomplete="off">
        <label class="btn" for="pmDiv">Po divizijah</label>
        <input type="radio" class="btn-check" name="pointsMode" id="pmRole" value="role" autocomplete="off">
        <label class="btn" for="pmRole">Po vlogah</label>
        <input type="radio" class="btn-check" name="pointsMode" id="pmTotal" value="total" autocomplete="off">
        <label class="btn" for="pmTotal">Skupaj</label>
    </div>
    <div class="legend-row ms-sm-2" id="pointsLegend"></div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">Točke po letih</div>
            <div class="card-body">
                <div class="chart-box"><canvas id="pointsBar" role="img" aria-label="Točke slovenskih plesalcev po letih"></canvas></div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">Kumulativne točke</div>
            <div class="card-body">
                <div class="chart-box"><canvas id="pointsCum" role="img" aria-label="Kumulativne točke slovenskih plesalcev"></canvas></div>
            </div>
        </div>
    </div>
</div>

<details class="mb-5">
    <summary class="text-muted small" style="cursor:pointer;">Pokaži tabelo</summary>
    <div class="card table-card mt-2">
    <div class="table-responsive">
    <table class="table table-sm mb-0 points-table" style="font-size:.85rem;">
        <thead>
            <tr>
                <th class="ps-3">Leto</th>
                @foreach($divOrder as $div)<th>{{ $div }}</th>@endforeach
                <th>Leaders</th>
                <th>Followers</th>
                <th>Skupaj</th>
                <th>Kumulativno</th>
                <th class="pe-3">Plesalci</th>
            </tr>
        </thead>
        <tbody>
            @php $running = 0; @endphp
            @foreach($years as $i => $year)
            @php $running += $pointsData['total'][$i]; @endphp
            <tr>
                <td class="ps-3 fw-semibold">{{ $year }}</td>
                @foreach($divOrder as $div)
                    <td>{!! $pointsData['divisions'][$div][$i] ?: '<span class="pts-zero">0</span>' !!}</td>
                @endforeach
                <td>{{ $pointsData['roles']['leader'][$i] }}</td>
                <td>{{ $pointsData['roles']['follower'][$i] }}</td>
                <td class="fw-bold">{{ $pointsData['total'][$i] }}</td>
                <td>{{ $running }}</td>
                <td class="pe-3">{{ $pointsData['dancers'][$i] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    </div>
    </div>
</details>
@endif

{{-- ── Dancers over time ─────────────────────────────────────────────── --}}
<div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
    <h2 class="section-title">Plesalci skozi čas</h2>
    <div class="d-flex flex-wrap gap-2">
        <div class="seg" role="group" aria-label="Posnetki">
            <input type="radio" class="btn-check" name="snapRange" id="snapAll" value="all" checked autocomplete="off">
            <label class="btn" for="snapAll">Vsi posnetki</label>
            <input type="radio" class="btn-check" name="snapRange" id="snapYe" value="yearend" autocomplete="off">
            <label class="btn" for="snapYe">Samo konec leta</label>
        </div>
        <div class="seg" role="group" aria-label="Prikaz">
            <input type="radio" class="btn-check" name="fillMode" id="fillNo" value="false" checked autocomplete="off">
            <label class="btn" for="fillNo">Črte</label>
            <input type="radio" class="btn-check" name="fillMode" id="fillYes" value="true" autocomplete="off">
            <label class="btn" for="fillYes">Površine</label>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header d-flex align-items-center flex-wrap gap-2">
        <span>Plesalci po divizijah</span>
        <div class="legend-row ms-auto" id="divLegend"></div>
    </div>
    <div class="card-body">
        <div class="chart-box tall"><canvas id="divChart" role="img" aria-label="Število plesalcev po divizijah skozi čas"></canvas></div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header d-flex align-items-center flex-wrap gap-2">
        <span>Leaders in followers</span>
        <div class="legend-row ms-auto" id="roleLegend"></div>
    </div>
    <div class="card-body">
        <div class="chart-box short"><canvas id="roleChart" role="img" aria-label="Število leaderjev in followerjev skozi čas"></canvas></div>
    </div>
</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
(function () {
    const P = @json($pointsData);
    const S = @json($chartData);
    const DIVS = ['CHA', 'ALS', 'ADV', 'INT', 'NOV', 'NEW'];

    // Divisions are ordered, so they share one hue stepped light (NEW) to
    // dark (CHA); in dark mode the ramp flips so CHA is the brightest.
    // Both ramps and the role pairs pass the colour-vision checks.
    const RAMP = {
        light: { NEW: '#9aa6f8', NOV: '#7c84f3', INT: '#6366f1', ADV: '#4338ca', ALS: '#312e81', CHA: '#1e1b4b' },
        dark:  { NEW: '#4f46e5', NOV: '#6366f1', INT: '#818cf8', ADV: '#a5b4fc', ALS: '#c7d2fe', CHA: '#e0e7ff' },
    };
    const ROLE = {
        light: { leader: '#6366f1', follower: '#f43f5e' },
        dark:  { leader: '#7c83f0', follower: '#f2546f' },
    };

    const theme = () => document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light';
    const cssVar = (n) => getComputedStyle(document.documentElement).getPropertyValue(n).trim();

    function colors() {
        const t = theme();
        return {
            div: RAMP[t], role: ROLE[t], total: ROLE[t].leader,
            surface: cssVar('--surface') || '#ffffff',
            grid: cssVar('--border-soft') || '#e5e7eb',
            muted: cssVar('--text-muted') || '#64748b',
            text: getComputedStyle(document.body).color,
        };
    }

    function rgba(hex, a) {
        const n = parseInt(hex.slice(1), 16);
        return `rgba(${n >> 16}, ${(n >> 8) & 255}, ${n & 255}, ${a})`;
    }

    const cumsum = (arr) => { let s = 0; return arr.map(v => (s += v)); };

    Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
    Chart.defaults.font.size = 12;

    // ── Shared chart pieces ────────────────────────────────────────────────
    function tooltip(c, withTotal) {
        return {
            backgroundColor: c.surface, borderColor: c.grid, borderWidth: 1,
            titleColor: c.text, bodyColor: c.text, footerColor: c.muted,
            titleFont: { weight: '600' }, footerFont: { weight: '600' },
            padding: 10, cornerRadius: 8, boxPadding: 4, caretPadding: 6,
            usePointStyle: true,
            itemSort: (a, b) => b.datasetIndex - a.datasetIndex,
            callbacks: {
                labelColor: (ctx) => ({ borderColor: ctx.dataset._color, backgroundColor: ctx.dataset._color }),
                labelPointStyle: () => ({ pointStyle: 'circle', rotation: 0 }),
                footer: (items) => withTotal && items.length > 1
                    ? 'Skupaj: ' + items.reduce((s, i) => s + i.parsed.y, 0)
                    : '',
            },
        };
    }

    function baseOptions(c, stacked, withTotal) {
        return {
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 350 },
            interaction: { mode: 'index', intersect: false },
            layout: { padding: { top: 20, right: 4 } },
            plugins: { legend: { display: false }, tooltip: tooltip(c, withTotal) },
            scales: {
                x: {
                    stacked,
                    grid: { display: false },
                    border: { color: c.grid },
                    ticks: { color: c.muted, maxRotation: 0, autoSkipPadding: 14 },
                },
                y: {
                    stacked,
                    beginAtZero: true,
                    grid: { color: c.grid, drawTicks: false },
                    border: { display: false },
                    ticks: { color: c.muted, precision: 0, padding: 8 },
                },
            },
        };
    }

    // Writes the stack total above columns, or only at the end of an area.
    const totalLabels = {
        id: 'totalLabels',
        afterDatasetsDraw(chart, args, opts) {
            if (!opts || !opts.enabled) return;
            const metas = chart.getSortedVisibleDatasetMetas();
            if (!metas.length) return;
            const n = chart.data.labels.length;
            const ctx = chart.ctx;
            ctx.save();
            ctx.font = "600 11px Inter, system-ui, sans-serif";
            ctx.fillStyle = opts.color;
            ctx.textBaseline = 'bottom';
            ctx.textAlign = opts.lastOnly ? 'right' : 'center';
            for (let i = opts.lastOnly ? n - 1 : 0; i < n; i++) {
                let sum = 0;
                metas.forEach(m => { sum += chart.data.datasets[m.index].data[i] || 0; });
                if (!sum) continue;
                const top = Math.min(...metas.map(m => m.data[i].y));
                ctx.fillText(sum.toLocaleString('sl-SI'), metas[0].data[i].x, top - 5);
            }
            ctx.restore();
        },
    };

    function renderLegend(el, items, hiddenSet, onToggle) {
        el.innerHTML = '';
        if (items.length < 2) return; // a single series is named by the chart title
        items.forEach(it => {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'legend-pill' + (hiddenSet.has(it.key) ? ' off' : '');
            b.setAttribute('aria-pressed', hiddenSet.has(it.key) ? 'false' : 'true');
            b.innerHTML = `<span class="dot" style="--dot:${it.color}"></span>${it.label}`;
            b.addEventListener('click', () => {
                hiddenSet.has(it.key) ? hiddenSet.delete(it.key) : hiddenSet.add(it.key);
                b.classList.toggle('off', hiddenSet.has(it.key));
                b.setAttribute('aria-pressed', hiddenSet.has(it.key) ? 'false' : 'true');
                onToggle(it.key);
            });
            el.appendChild(b);
        });
    }

    function toggleIn(charts, key, hiddenSet) {
        charts.forEach(ch => {
            if (!ch) return;
            ch.data.datasets.forEach((ds, i) => {
                if (ds._key === key) ch.setDatasetVisibility(i, !hiddenSet.has(key));
            });
            ch.update();
        });
    }

    // Stacked areas: solid fills separated by a 2px surface-coloured edge.
    // A single area: a 2px line over a light wash.
    function areaDataset(s, c, multi, data, hidden, extra = {}) {
        return Object.assign({
            label: s.label, _key: s.key, _color: s.color, data, hidden,
            borderColor: multi ? c.surface : s.color,
            borderWidth: 2,
            backgroundColor: multi ? rgba(s.color, .9) : rgba(s.color, .12),
            fill: multi ? 'stack' : 'origin',
            cubicInterpolationMode: 'monotone', // smooth, but never overshoots the data
            pointRadius: 0,
            pointHoverRadius: 5,
            pointBackgroundColor: s.color,
            pointHoverBackgroundColor: s.color,
            pointBorderColor: c.surface,
            pointHoverBorderColor: c.surface,
            pointBorderWidth: 2,
            pointHoverBorderWidth: 2,
        }, extra);
    }

    // ── Points per year + cumulative ───────────────────────────────────────
    const pts = { mode: 'division', hidden: new Set(), bar: null, cum: null };

    function pointSeries(c) {
        if (pts.mode === 'division') {
            // NEW at the bottom of the stack, CHA on top.
            return DIVS.slice().reverse().map(d => ({ key: d, label: d, color: c.div[d], data: P.divisions[d] }));
        }
        if (pts.mode === 'role') {
            return [
                { key: 'leader',   label: 'Leaders',   color: c.role.leader,   data: P.roles.leader },
                { key: 'follower', label: 'Followers', color: c.role.follower, data: P.roles.follower },
            ];
        }
        return [{ key: 'total', label: 'Točke', color: c.total, data: P.total }];
    }

    function buildPoints() {
        if (!document.getElementById('pointsBar')) return;
        pts.bar && pts.bar.destroy();
        pts.cum && pts.cum.destroy();

        const c = colors();
        const series = pointSeries(c);
        const multi = series.length > 1;
        const labels = P.years.map(String);

        pts.bar = new Chart(document.getElementById('pointsBar'), {
            type: 'bar',
            data: {
                labels,
                datasets: series.map(s => ({
                    label: s.label, _key: s.key, _color: s.color, data: s.data,
                    hidden: pts.hidden.has(s.key),
                    backgroundColor: s.color,
                    hoverBackgroundColor: s.color,
                    borderColor: c.surface,
                    borderWidth: multi ? { top: 2 } : 0,
                    borderSkipped: 'start',
                    borderRadius: 3,
                    maxBarThickness: 28,
                    categoryPercentage: .72,
                    barPercentage: .92,
                })),
            },
            options: Object.assign(baseOptions(c, true, true), {}),
            plugins: [totalLabels],
        });
        pts.bar.options.plugins.totalLabels = { enabled: true, color: c.text };
        pts.bar.update('none');

        pts.cum = new Chart(document.getElementById('pointsCum'), {
            type: 'line',
            data: {
                labels,
                datasets: series.map(s => areaDataset(s, c, multi, cumsum(s.data), pts.hidden.has(s.key))),
            },
            options: baseOptions(c, multi, true),
            plugins: [totalLabels],
        });
        pts.cum.options.plugins.totalLabels = { enabled: true, lastOnly: true, color: c.text };
        pts.cum.update('none');

        // Legend reads top division first.
        const legendItems = pts.mode === 'division' ? series.slice().reverse() : series;
        renderLegend(document.getElementById('pointsLegend'), legendItems, pts.hidden,
            (key) => toggleIn([pts.bar, pts.cum], key, pts.hidden));
    }

    document.querySelectorAll('input[name="pointsMode"]').forEach(el => {
        el.addEventListener('change', () => { pts.mode = el.value; buildPoints(); });
    });

    // ── Dancers over time (snapshots) ──────────────────────────────────────
    const snap = { yearEndOnly: false, area: false, hiddenDiv: new Set(), hiddenRole: new Set(), div: null, role: null };

    function snapSlice() {
        const idx = S.isYearEnd.map((v, i) => (!snap.yearEndOnly || v) ? i : -1).filter(i => i >= 0);
        const labels = idx.map(i => {
            const lbl = S.labels[i];
            if (!snap.yearEndOnly) return lbl;
            const m = lbl.match(/\d{4}/);
            return m ? m[0] : lbl;
        });
        return { idx, labels, isYearEnd: idx.map(i => S.isYearEnd[i]) };
    }

    function snapDatasets(series, c, d, hiddenSet) {
        const multi = snap.area;
        return series.map(s => {
            const data = d.idx.map(i => s.data[i]);
            if (multi) return areaDataset(s, c, true, data, hiddenSet.has(s.key));
            // Lines: year-end snapshots carry a ringed dot, the rest show on hover.
            return areaDataset(s, c, false, data, hiddenSet.has(s.key), {
                fill: false,
                pointRadius: d.isYearEnd.map(v => v ? 3.5 : 0),
                pointHoverRadius: 5,
            });
        });
    }

    function snapOptions(c, d) {
        const o = baseOptions(c, snap.area, snap.area);
        o.plugins.tooltip.callbacks.title = (items) => {
            const i = items[0].dataIndex;
            return items[0].label + (d.isYearEnd[i] && !snap.yearEndOnly ? ' · konec leta' : '');
        };
        return o;
    }

    function buildSnaps() {
        snap.div && snap.div.destroy();
        snap.role && snap.role.destroy();

        const c = colors();
        const d = snapSlice();

        const divSeries = DIVS.slice().reverse().map(k => ({
            key: k, label: k, color: c.div[k],
            data: S.divisionDatasets.find(x => x.label === k).data,
        }));
        const roleSeries = S.roleDatasets.map(r => ({
            key: r.role, label: r.label, color: c.role[r.role], data: r.data,
        }));

        snap.div = new Chart(document.getElementById('divChart'), {
            type: 'line',
            data: { labels: d.labels, datasets: snapDatasets(divSeries, c, d, snap.hiddenDiv) },
            options: snapOptions(c, d),
        });
        snap.role = new Chart(document.getElementById('roleChart'), {
            type: 'line',
            data: { labels: d.labels, datasets: snapDatasets(roleSeries, c, d, snap.hiddenRole) },
            options: snapOptions(c, d),
        });

        renderLegend(document.getElementById('divLegend'), divSeries.slice().reverse(), snap.hiddenDiv,
            (key) => toggleIn([snap.div], key, snap.hiddenDiv));
        renderLegend(document.getElementById('roleLegend'), roleSeries, snap.hiddenRole,
            (key) => toggleIn([snap.role], key, snap.hiddenRole));
    }

    document.querySelectorAll('input[name="snapRange"]').forEach(el => {
        el.addEventListener('change', () => { snap.yearEndOnly = el.value === 'yearend'; buildSnaps(); });
    });
    document.querySelectorAll('input[name="fillMode"]').forEach(el => {
        el.addEventListener('change', () => { snap.area = el.value === 'true'; buildSnaps(); });
    });

    // ── Initial render and theme switching ─────────────────────────────────
    buildPoints();
    buildSnaps();
    document.addEventListener('themechange', () => { buildPoints(); buildSnaps(); });
})();
</script>
@endpush
