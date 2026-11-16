@extends('layouts.app')

@section('title', 'Animacija lestvice')

@section('content')

<style>
    .race-card { padding: 1rem 1.15rem 1.1rem; --row: 46px; --label-w: 13rem; --val-w: 5.5rem; }
    @media (max-width: 575.98px) {
        .race-card { padding: .75rem .6rem .9rem; --row: 38px; --label-w: 8.6rem; --val-w: 4.4rem; }
    }

    .race-head { position: relative; height: 1.4rem; margin-left: var(--label-w); margin-right: var(--val-w); }
    .race-head span {
        position: absolute; top: 0; transform: translateX(-50%);
        font-size: .68rem; font-weight: 700; letter-spacing: .06em; color: var(--text-muted);
        white-space: nowrap;
    }

    .race-board { position: relative; height: calc(var(--row) * 10); overflow: hidden; }
    .race-grid { position: absolute; inset: 0 var(--val-w) 0 var(--label-w); pointer-events: none; }
    .race-grid i { position: absolute; top: 0; bottom: 0; border-left: 1px dashed var(--border-soft); }
    .race-grid b { position: absolute; top: 0; bottom: 0; background: rgba(var(--brand-rgb), .035); }

    .race-stamp {
        position: absolute; right: calc(var(--val-w) + .25rem); bottom: .2rem; text-align: right;
        pointer-events: none; line-height: 1;
    }
    .race-month {
        font-size: clamp(1.6rem, 5vw, 3.2rem); font-weight: 800; letter-spacing: -.03em;
        color: var(--bs-body-color); opacity: .18; font-variant-numeric: tabular-nums;
    }
    .race-count { font-size: .78rem; color: var(--text-muted); margin-top: .3rem; }

    .race-row {
        position: absolute; left: 0; right: 0; top: 0; height: var(--row);
        display: flex; align-items: center; will-change: transform, opacity;
    }
    .race-label { width: var(--label-w); flex-shrink: 0; display: flex; align-items: center; gap: .5rem; padding-right: .6rem; min-width: 0; height: 100%; background: var(--surface); }
    .race-label .rank-num { flex-shrink: 0; }
    .race-name {
        font-weight: 600; font-size: .9rem; color: var(--bs-body-color);
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis; min-width: 0;
    }
    .race-name:hover { color: var(--brand); }
    .race-role { flex-shrink: 0; width: .45rem; height: .45rem; border-radius: 50%; margin-left: auto; }
    .race-role.L { background: var(--leader); }
    .race-role.F { background: var(--follower); }

    .race-track { position: relative; flex: 1; height: 100%; margin-right: var(--val-w); }
    .race-bar {
        position: absolute; left: 0; top: 18%; bottom: 18%; min-width: 3px;
        border-radius: 0 .45rem .45rem 0; background: var(--c);
        background-image: linear-gradient(90deg, rgba(255,255,255,0), rgba(255,255,255,.22));
        box-shadow: 0 4px 12px -8px var(--c);
    }
    .race-val {
        position: absolute; top: 50%; transform: translateY(-50%); padding-left: .4rem;
        display: flex; align-items: center; gap: .3rem; white-space: nowrap;
        font-size: .82rem; font-weight: 700; font-variant-numeric: tabular-nums;
    }

    @media (max-width: 575.98px) {
        .race-label { gap: .35rem; padding-right: .4rem; }
        .race-label .rank-num { width: 1.5rem; height: 1.5rem; font-size: .72rem; }
        .race-name { font-size: .78rem; }
        .race-val { font-size: .72rem; gap: .2rem; padding-left: .25rem; }
        .race-val .division-badge { font-size: .6rem; padding: .15em .45em; }
        .race-head span { font-size: .6rem; }
    }

    .race-controls { display: flex; align-items: center; gap: .75rem; margin-top: 1rem; flex-wrap: wrap; }
    .race-play {
        width: 2.5rem; height: 2.5rem; border-radius: 999px; border: 0; flex-shrink: 0;
        display: grid; place-items: center; color: #fff;
        background: linear-gradient(135deg, var(--brand), var(--brand-2));
        box-shadow: 0 6px 16px -8px rgba(var(--brand-rgb), .8);
    }
    .race-play:hover { filter: brightness(1.08); }
    .race-slider { flex: 1; min-width: 10rem; accent-color: var(--brand); }
    .race-legend { display: flex; flex-wrap: wrap; gap: .4rem 1rem; align-items: center; font-size: .78rem; color: var(--text-muted); margin-top: .9rem; }
    .race-legend .dot { display: inline-block; width: .55rem; height: .55rem; border-radius: 50%; margin-right: .3rem; vertical-align: middle; }
</style>

<div class="page-header d-flex align-items-end justify-content-between flex-wrap gap-3">
    <div>
        <div class="eyebrow">Skozi čas</div>
        <h1>Animacija lestvice</h1>
        <p>
            Najboljših 10 na skupni lestvici, mesec za mesecem. Dolžina stolpca pokaže
            divizijo in napredek proti naslednji diviziji.
        </p>
    </div>
    @include('partials.history-tabs', ['current' => 'race'])
</div>

@if(empty($race['frames']))
    <div class="alert alert-info">Zgodovina še ni na voljo.</div>
@else

<div class="card race-card" id="race">
    <div class="race-head" id="raceHead"></div>
    <div class="race-board" id="raceBoard">
        <div class="race-grid" id="raceGrid"></div>
        <div class="race-stamp">
            <div class="race-month" id="raceMonth"></div>
            <div class="race-count" id="raceCount"></div>
        </div>
    </div>

    <div class="race-controls">
        <button type="button" class="race-play" id="racePlay" aria-label="Predvajaj"></button>
        <input type="range" class="race-slider form-range" id="raceSlider" min="0" max="{{ count($race['frames']) - 1 }}" step="any" value="0" aria-label="Mesec">
        <div class="seg" id="raceSpeed" role="group" aria-label="Hitrost">
            <button type="button" class="btn active" data-speed="1">1×</button>
            <button type="button" class="btn" data-speed="2">2×</button>
            <button type="button" class="btn" data-speed="4">4×</button>
        </div>
    </div>

    <div class="race-legend">
        @foreach(['NEW', 'NOV', 'INT', 'ADV', 'ALS', 'CHA'] as $div)
            <span><span class="dot" style="background: var(--div-{{ $div }})"></span>{{ $div }}</span>
        @endforeach
        <span class="ms-sm-auto"><span class="dot" style="background: var(--leader)"></span>leader</span>
        <span><span class="dot" style="background: var(--follower)"></span>follower</span>
    </div>
</div>

@endif

@endsection

@push('scripts')
@if(!empty($race['frames']))
<script>
(function () {
    const DATA   = @json($race);
    const TOP    = 10;
    const LEVELS = ['NEW', 'NOV', 'INT', 'ADV', 'ALS', 'CHA'];
    // Points in the required division that force the move to the next one
    // (the thresholds HistoryService uses); CHA has no ceiling.
    const CAP    = { NEW: 16, NOV: 30, INT: 45, ADV: 60, ALS: 150, CHA: 150 };

    const months = DATA.months;
    const N      = months.length;
    const fmt    = new Intl.DateTimeFormat('sl', { month: 'long', year: 'numeric' });
    const monthLabel = ym => fmt.format(new Date(Math.floor(ym / 100), ym % 100 - 1, 1));

    // Bar length: division level plus progress towards the next division.
    const value = (div, pts) => LEVELS.indexOf(div) + Math.min(pts / CAP[div], 0.97);

    // Per frame: wscid -> { idx, rank, div, pts, role, v } and the axis maximum.
    const frames = DATA.frames.map(f => {
        const map = new Map();
        let max = 1;
        f.top.forEach(([id, rank, div, pts, role], idx) => {
            const v = value(div, pts);
            map.set(id, { idx, rank, div, pts, role, v });
            max = Math.max(max, v);
        });
        return { map, count: f.count, max: Math.min(Math.ceil(max + 0.05), LEVELS.length) };
    });

    // Fast through the early years, slowing towards the present.
    const durations = frames.map((_, i) => {
        const p = N > 1 ? i / (N - 1) : 1;
        return 140 + 1260 * Math.pow(p, 2.4);
    });

    const board  = document.getElementById('raceBoard');
    const grid   = document.getElementById('raceGrid');
    const head   = document.getElementById('raceHead');
    const monthEl = document.getElementById('raceMonth');
    const countEl = document.getElementById('raceCount');
    const playBtn = document.getElementById('racePlay');
    const slider  = document.getElementById('raceSlider');

    // ── Axis: one band per division ───────────────────────────────────────
    const bands = LEVELS.map((name, k) => {
        const line  = document.createElement('i');
        const shade = document.createElement('b');
        const label = document.createElement('span');
        label.textContent = name;
        grid.append(line, shade);
        head.append(label);
        return { line, shade, label, k };
    });

    function drawAxis(max) {
        bands.forEach(({ line, shade, label, k }) => {
            const l = k / max * 100, r = (k + 1) / max * 100;
            const visible = k < max;
            line.style.display  = visible ? '' : 'none';
            line.style.left     = l + '%';
            shade.style.display = visible && k % 2 ? '' : 'none';
            shade.style.left    = l + '%';
            shade.style.width   = (Math.min(r, 100) - l) + '%';
            label.style.display = visible ? '' : 'none';
            label.style.left    = (l + Math.min(r, 100)) / 2 + '%';
            label.style.opacity = Math.max(0, Math.min(1, (max - k) * 1.5 - 0.3));
        });
    }

    // ── Rows, created lazily per dancer ───────────────────────────────────
    const rows = new Map();
    function rowFor(id) {
        let r = rows.get(id);
        if (r) return r;
        const el = document.createElement('div');
        el.className = 'race-row';
        el.innerHTML =
            '<div class="race-label"><span class="rank-num"></span>' +
            '<a class="race-name" href="/dancer/' + id + '"></a><span class="race-role"></span></div>' +
            '<div class="race-track"><div class="race-bar"></div><span class="race-val">' +
            '<span class="division-badge"></span><span class="race-pts"></span></span></div>';
        el.querySelector('.race-name').textContent = DATA.names[id] || ('#' + id);
        el.querySelector('.race-name').title = DATA.names[id] || '';
        board.insertBefore(el, grid.nextSibling);
        r = {
            el,
            rank:  el.querySelector('.rank-num'),
            role:  el.querySelector('.race-role'),
            bar:   el.querySelector('.race-bar'),
            val:   el.querySelector('.race-val'),
            badge: el.querySelector('.division-badge'),
            pts:   el.querySelector('.race-pts'),
            state: {},
        };
        rows.set(id, r);
        return r;
    }

    // Write to the DOM only when a value actually changes.
    function set(r, key, v, fn) {
        if (r.state[key] !== v) { r.state[key] = v; fn(v); }
    }

    const lerp   = (a, b, t) => a + (b - a) * t;
    const smooth = t => t * t * (3 - 2 * t);
    let rowH = 46;
    function measure() { rowH = board.clientHeight / TOP; }

    // ── Targets for an interpolated playhead f in [0, N-1] ────────────────
    // The playhead gives where each bar should be; the drawn bars then glide
    // towards that (see step()), so even the quick early months move smoothly.
    function targets(f) {
        const i  = Math.min(Math.floor(f), N - 1);
        const j  = Math.min(i + 1, N - 1);
        const A  = frames[i], B = frames[j];
        // Short frames move linearly so the fast part flows; long ones ease.
        const raw = f - i;
        const w   = Math.max(0, Math.min(1, (durations[i] - 200) / 600));
        const t   = lerp(raw, smooth(raw), w);
        const late = t >= 0.5;

        const out = new Map();
        new Set([...A.map.keys(), ...B.map.keys()]).forEach(id => {
            const a = A.map.get(id), b = B.map.get(id);
            const from = a || b, to = b || a;
            const cur = late ? to : from;
            out.set(id, {
                y:    lerp(a ? a.idx : TOP, b ? b.idx : TOP, t),
                op:   lerp(a ? 1 : 0, b ? 1 : 0, t),
                v:    lerp(from.v, to.v, t),
                pts:  from.div === to.div ? Math.round(lerp(from.pts, to.pts, t)) : cur.pts,
                rank: (a && b ? cur : to).rank,
                div:  cur.div,
                role: cur.role,
            });
        });
        return { rows: out, max: lerp(A.max, B.max, t), frame: late ? j : i };
    }

    // Drawn state, eased towards the targets with time constant TAU (ms).
    const TAU = 280;
    const view = { max: null };
    const stamp = { state: {} };

    // Draws one step and returns true once everything sits on its target.
    function step(f, dt) {
        const T = targets(f);
        const k = dt === Infinity ? 1 : 1 - Math.exp(-dt / TAU);
        let settled = true;

        view.max = view.max === null ? T.max : view.max + (T.max - view.max) * k;
        if (Math.abs(T.max - view.max) > 0.002) settled = false;
        drawAxis(view.max);

        T.rows.forEach((tg, id) => rowFor(id));
        rows.forEach((r, id) => {
            const tg = T.rows.get(id);
            const d  = r.d || (r.d = tg ? { y: tg.y, op: 0, v: tg.v } : { y: TOP, op: 0, v: 0 });
            const ty = tg ? tg.y : TOP, top = tg ? tg.op : 0, tv = tg ? tg.v : d.v;

            d.y  += (ty - d.y) * k;
            d.op += (top - d.op) * k;
            d.v  += (tv - d.v) * k;
            if (Math.abs(ty - d.y) > 0.005 || Math.abs(top - d.op) > 0.005 || Math.abs(tv - d.v) > 0.002) settled = false;

            if (!tg && d.op < 0.01) {
                set(r, 'disp', 'none', x => r.el.style.display = x);
                return;
            }
            set(r, 'disp', '', x => r.el.style.display = x);

            const pct = Math.max(0, d.v / view.max * 100);
            r.el.style.transform = 'translateY(' + (d.y * rowH).toFixed(1) + 'px)';
            set(r, 'op', d.op.toFixed(2), x => r.el.style.opacity = x);
            // Keep the higher-ranked row on top while rows cross.
            set(r, 'z', String(TOP - Math.round(d.y)), x => r.el.style.zIndex = x);
            r.bar.style.width = pct + '%';
            r.val.style.left  = pct + '%';

            if (!tg) return; // leaving rows keep their last labels
            set(r, 'rank', tg.rank, x => {
                r.rank.textContent = x;
                r.rank.className = 'rank-num' + (x <= 3 ? ' rank-' + x : '');
            });
            set(r, 'div', tg.div, x => {
                r.bar.style.setProperty('--c', 'var(--div-' + x + ')');
                r.badge.className = 'division-badge div-' + x;
                r.badge.textContent = x;
            });
            set(r, 'role', tg.role, x => {
                r.role.className = 'race-role ' + x;
                r.role.title = x === 'L' ? 'leader' : 'follower';
            });
            set(r, 'pts', tg.pts, x => r.pts.textContent = x);
        });

        set(stamp, 'm', T.frame, k => {
            monthEl.textContent = monthLabel(months[k]);
            const c = frames[k].count;
            countEl.textContent = c + ' ' + (c % 100 === 1 ? 'plesalec' : c % 100 === 2 ? 'plesalca'
                : (c % 100 === 3 || c % 100 === 4) ? 'plesalci' : 'plesalcev') + ' s točkami';
        });

        return settled;
    }

    // ── Playback ──────────────────────────────────────────────────────────
    let pos = 0, playing = false, last = 0, speed = 1, running = false;
    const ICON_PLAY  = '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M7 4.5v15l13-7.5z"/></svg>';
    const ICON_PAUSE = '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M6 4h4v16H6zM14 4h4v16h-4z"/></svg>';
    const ICON_AGAIN = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/></svg>';

    function updateButton() {
        const atEnd = pos >= N - 1;
        playBtn.innerHTML = playing ? ICON_PAUSE : atEnd ? ICON_AGAIN : ICON_PLAY;
        playBtn.setAttribute('aria-label', playing ? 'Ustavi' : atEnd ? 'Ponovno' : 'Predvajaj');
    }

    // One loop drives both playback and the glide; it stops once idle.
    function loop(now) {
        const dt = Math.min(now - last, 100);
        last = now;
        if (playing) {
            let budget = dt * speed;
            while (budget > 0 && pos < N - 1) {
                const i = Math.floor(pos);
                const left = (i + 1 - pos) * durations[i];
                if (budget >= left) { pos = i + 1; budget -= left; }
                else { pos += budget / durations[i]; budget = 0; }
            }
            slider.value = pos;
            if (pos >= N - 1) { playing = false; updateButton(); }
        }
        const settled = step(pos, dt);
        if (playing || !settled) requestAnimationFrame(loop);
        else running = false;
    }
    function kick() {
        if (running) return;
        running = true;
        last = performance.now();
        requestAnimationFrame(loop);
    }

    function play() {
        if (pos >= N - 1) pos = 0;
        playing = true;
        updateButton();
        kick();
    }
    function pause() {
        playing = false;
        updateButton();
    }

    playBtn.addEventListener('click', () => playing ? pause() : play());
    slider.addEventListener('input', () => {
        pause();
        pos = parseFloat(slider.value);
        updateButton();
        kick();
    });
    document.querySelectorAll('#raceSpeed [data-speed]').forEach(btn => {
        btn.addEventListener('click', () => {
            speed = parseFloat(btn.dataset.speed);
            document.querySelectorAll('#raceSpeed .btn').forEach(b => b.classList.toggle('active', b === btn));
        });
    });
    window.addEventListener('resize', () => { measure(); step(pos, Infinity); });

    measure();
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reduced) {
        pos = N - 1;
        slider.value = pos;
        step(pos, Infinity);
        updateButton();
    } else {
        step(0, Infinity);
        updateButton();
        setTimeout(play, 600);
    }
})();
</script>
@endif
@endpush
