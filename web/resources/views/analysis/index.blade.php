@extends('layouts.app')

@section('title', 'Analiza')

@section('content')

<div class="page-header">
    <div class="eyebrow">Statistika</div>
    <h1>Analiza skupnosti</h1>
    <p>Trendi in vloge slovenskih WCS tekmovalcev skozi vse zabeležene posnetke lestvice.</p>
</div>

{{-- Controls --}}
<div class="d-flex flex-wrap gap-2 align-items-center mb-4">
    <div class="seg" role="group" id="snapToggle">
        <input type="radio" class="btn-check" name="snapRange" id="snapAll" value="all" checked autocomplete="off">
        <label class="btn" for="snapAll">Vsi posnetki</label>
        <input type="radio" class="btn-check" name="snapRange" id="snapYe" value="yearend" autocomplete="off">
        <label class="btn" for="snapYe">Samo konec leta</label>
    </div>
    <div class="seg" role="group" id="fillToggle">
        <input type="radio" class="btn-check" name="fillMode" id="fillNo" value="false" checked autocomplete="off">
        <label class="btn" for="fillNo">Lines</label>
        <input type="radio" class="btn-check" name="fillMode" id="fillYes" value="true" autocomplete="off">
        <label class="btn" for="fillYes">Area</label>
    </div>
</div>

{{-- Division chart --}}
<div class="card mb-4">
    <div class="card-header d-flex align-items-center">
        <span class="fw-semibold">Plesalci po divizijah</span>
        <div class="ms-auto d-flex gap-1 flex-wrap" id="divLegend">
            @foreach(['CHA','ALS','ADV','INT','NOV','NEW'] as $div)
            <button class="btn btn-xs div-toggle-btn active" data-div="{{ $div }}"
                style="font-size:.7rem;padding:.15rem .55rem;border-radius:999px;font-weight:700;letter-spacing:.03em;border:2px solid transparent;cursor:pointer;"
            >{{ $div }}</button>
            @endforeach
        </div>
    </div>
    <div class="card-body" style="position:relative;height:340px;">
        <canvas id="divChart"></canvas>
    </div>
</div>

{{-- Role chart --}}
<div class="card mb-4">
    <div class="card-header d-flex align-items-center">
        <span class="fw-semibold">Leaders vs Followers</span>
        <div class="ms-auto d-flex gap-1" id="roleLegend">
            <button class="btn btn-xs role-toggle-btn active" data-role="Leaders"
                style="font-size:.7rem;padding:.15rem .55rem;border-radius:999px;font-weight:700;letter-spacing:.03em;border:2px solid transparent;cursor:pointer;background:#6366f1;color:#fff;border-color:#6366f1;"
            >Leaders</button>
            <button class="btn btn-xs role-toggle-btn active" data-role="Followers"
                style="font-size:.7rem;padding:.15rem .55rem;border-radius:999px;font-weight:700;letter-spacing:.03em;border:2px solid transparent;cursor:pointer;background:#f43f5e;color:#fff;border-color:#f43f5e;"
            >Followers</button>
        </div>
    </div>
    <div class="card-body" style="position:relative;height:240px;">
        <canvas id="roleChart"></canvas>
    </div>
</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
(function () {
    // ── Raw data from PHP ──────────────────────────────────────────────────
    const raw = @json($chartData);

    // ── Helpers ────────────────────────────────────────────────────────────
    const divColors = {
        CHA: '#8b5cf6', ALS: '#3b82f6', ADV: '#10b981',
        INT: '#f59e0b', NOV: '#06b6d4', NEW: '#94a3b8',
    };

    function filteredData(yearEndOnly) {
        const keep = raw.isYearEnd.map((v, i) => yearEndOnly ? v : true);
        const idx  = keep.map((v, i) => v ? i : -1).filter(i => i >= 0);
        const labels = idx.map(i => {
            const lbl = raw.labels[i];
            if (yearEndOnly) {
                // Extract the 4-digit year from any label format
                const m = lbl.match(/\d{4}/);
                return m ? m[0] : lbl;
            }
            return lbl;
        });
        return {
            labels,
            isYearEnd: idx.map(i => raw.isYearEnd[i]),
            divisionDatasets: raw.divisionDatasets.map(ds => ({
                ...ds,
                data: idx.map(i => ds.data[i]),
            })),
            roleDatasets: raw.roleDatasets.map(ds => ({
                ...ds,
                data: idx.map(i => ds.data[i]),
            })),
        };
    }

    function pointStyles(isYearEnd) {
        return isYearEnd.map(v => v ? 'star' : 'circle');
    }

    function pointSizes(isYearEnd) {
        return isYearEnd.map(v => v ? 7 : 4);
    }

    // ── Division colours for legend buttons ───────────────────────────────
    const divBg = {
        CHA: { bg: '#8b5cf6', fg: '#fff' },
        ALS: { bg: '#3b82f6', fg: '#fff' },
        ADV: { bg: '#10b981', fg: '#fff' },
        INT: { bg: '#f59e0b', fg: '#fff' },
        NOV: { bg: '#06b6d4', fg: '#fff' },
        NEW: { bg: '#94a3b8', fg: '#fff' },
    };

    document.querySelectorAll('.div-toggle-btn').forEach(btn => {
        const div = btn.dataset.div;
        const c = divBg[div];
        btn.style.background    = c.bg;
        btn.style.color         = c.fg;
        btn.style.borderColor   = c.bg;
    });

    // ── Build charts ───────────────────────────────────────────────────────
    let yearEndOnly = false;
    let fillMode    = false;

    const data = () => filteredData(yearEndOnly);

    function makeDivDatasets(d) {
        const ptStyle = pointStyles(d.isYearEnd);
        const ptSize  = pointSizes(d.isYearEnd);
        return d.divisionDatasets.map(ds => ({
            ...ds,
            fill:              fillMode,
            pointStyle:        ptStyle,
            pointRadius:       ptSize,
            pointHoverRadius:  ptSize.map(s => s + 2),
            hidden:            divChart.data.datasets.find(x => x.label === ds.label)?.hidden ?? false,
        }));
    }

    function makeRoleDatasets(d) {
        const ptStyle = pointStyles(d.isYearEnd);
        const ptSize  = pointSizes(d.isYearEnd);
        return d.roleDatasets.map(ds => ({
            ...ds,
            fill:              fillMode,
            pointStyle:        ptStyle,
            pointRadius:       ptSize,
            pointHoverRadius:  ptSize.map(s => s + 2),
            hidden:            roleChart.data.datasets.find(x => x.label === ds.label)?.hidden ?? false,
        }));
    }

    const gridColor = () => getComputedStyle(document.documentElement).getPropertyValue('--border-soft').trim() || '#e5e7eb';
    const tickColor = () => getComputedStyle(document.documentElement).getPropertyValue('--text-muted').trim() || '#64748b';
    Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
    Chart.defaults.color = tickColor();

    const commonOptions = {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    title: (items) => {
                        const i = items[0].dataIndex;
                        const ye = data().isYearEnd[i];
                        return items[0].label + (ye ? '  ★ year-end' : '');
                    }
                }
            }
        },
        scales: {
            x: { grid: { color: gridColor() }, border: { display: false } },
            y: {
                beginAtZero: true,
                ticks: { stepSize: 1, precision: 0 },
                grid: { color: gridColor() },
                border: { display: false },
            }
        }
    };

    const divChart = new Chart(document.getElementById('divChart'), {
        type: 'line',
        data: { labels: [], datasets: [] },
        options: {
            ...commonOptions,
            plugins: {
                ...commonOptions.plugins,
                legend: { display: false },
            },
        }
    });

    const roleChart = new Chart(document.getElementById('roleChart'), {
        type: 'line',
        data: { labels: [], datasets: [] },
        options: {
            ...commonOptions,
            plugins: {
                ...commonOptions.plugins,
                legend: { display: false },
            },
        }
    });

    function refreshCharts() {
        const d = data();

        divChart.data.labels   = d.labels;
        divChart.data.datasets = makeDivDatasets(d);
        divChart.options.scales.y.stacked = fillMode;
        divChart.update();

        roleChart.data.labels   = d.labels;
        roleChart.data.datasets = makeRoleDatasets(d);
        roleChart.options.scales.y.stacked = fillMode;
        roleChart.update();
    }

    refreshCharts();

    // ── Re-colour chart chrome when the theme is switched ──────────────────
    document.addEventListener('themechange', () => {
        [divChart, roleChart].forEach(ch => {
            ch.options.scales.x.grid.color = gridColor();
            ch.options.scales.y.grid.color = gridColor();
            ch.options.scales.x.ticks.color = tickColor();
            ch.options.scales.y.ticks.color = tickColor();
            ch.update();
        });
    });

    // ── Legend toggle buttons ──────────────────────────────────────────────
    document.querySelectorAll('.div-toggle-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const div   = btn.dataset.div;
            const ds    = divChart.data.datasets.find(d => d.label === div);
            if (!ds) return;
            ds.hidden = !ds.hidden;
            btn.classList.toggle('active', !ds.hidden);
            btn.style.opacity = ds.hidden ? '0.35' : '1';
            divChart.update();
        });
    });

    // ── Role legend toggle buttons ─────────────────────────────────────────
    document.querySelectorAll('.role-toggle-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const role = btn.dataset.role;
            const ds   = roleChart.data.datasets.find(d => d.label === role);
            if (!ds) return;
            ds.hidden = !ds.hidden;
            btn.classList.toggle('active', !ds.hidden);
            btn.style.opacity = ds.hidden ? '0.35' : '1';
            roleChart.update();
        });
    });

    // ── Snapshot range toggle ──────────────────────────────────────────────
    document.querySelectorAll('input[name="snapRange"]').forEach(el => {
        el.addEventListener('change', () => {
            yearEndOnly = el.value === 'yearend';
            refreshCharts();
        });
    });

    // ── Fill / lines toggle ────────────────────────────────────────────────
    document.querySelectorAll('input[name="fillMode"]').forEach(el => {
        el.addEventListener('change', () => {
            fillMode = el.value === 'true';
            refreshCharts();
        });
    });
})();
</script>
@endpush
