{{--
    Below the top of a ranking: a name search across everyone ranked, and
    a few totals for the dancers outside the top. No ranks are shown here.

    Expects: $entries (the full ranking), $topCount (how many rows the table shows).
--}}
@php
    $rest      = array_slice($entries, $topCount);
    $restDivs  = [];
    $restPts   = 0;
    foreach ($rest as $e) {
        if ($e['required']) {
            $restDivs[$e['required']] = ($restDivs[$e['required']] ?? 0) + 1;
        }
        $restPts += array_sum($e['points']);
    }
    $searchList = array_map(fn($e) => [
        'id'   => $e['wscid'],
        'name' => $e['name'],
        'role' => $e['role'],
        'div'  => $e['required'],
    ], $entries);
    usort($searchList, fn($a, $b) => strcoll($a['name'], $b['name']));
@endphp

<style>
    .dancer-search { position: relative; max-width: 28rem; }
    .dancer-search .form-control { padding-left: 2.3rem; }
    .dancer-search .search-icon {
        position: absolute; left: .8rem; top: 50%; transform: translateY(-50%);
        width: 1rem; height: 1rem; color: var(--text-muted); pointer-events: none;
    }
    .search-results {
        position: absolute; z-index: 20; left: 0; right: 0; top: calc(100% + .35rem);
        background: var(--surface); border: 1px solid var(--border-soft);
        border-radius: var(--bs-border-radius-lg); box-shadow: var(--shadow-sm);
        max-height: 18rem; overflow-y: auto; padding: .3rem; display: none;
    }
    .search-results.open { display: block; }
    .search-results a {
        display: flex; align-items: center; gap: .6rem; justify-content: space-between;
        padding: .45rem .65rem; border-radius: .5rem; color: var(--bs-body-color); text-decoration: none;
    }
    .search-results a.active, .search-results a:hover { background: rgba(var(--brand-rgb), .08); }
    .search-results .empty { padding: .5rem .65rem; color: var(--text-muted); font-size: .88rem; }
    .rest-stat .val { font-size: 1.5rem; font-weight: 700; line-height: 1.2; }
    .rest-stat .lbl { font-size: .75rem; color: var(--text-muted); font-weight: 600; }
</style>

<div class="card mt-4">
    <div class="card-body p-4">
        <div class="row g-4 align-items-start">
            <div class="col-lg-6">
                <h2 class="h6 fw-bold mb-1">Poišči plesalca</h2>
                <p class="text-muted small mb-3">Vpiši ime in odpri profil plesalca.</p>
                <div class="dancer-search">
                    <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                    <input type="search" class="form-control" id="dancerSearch" placeholder="Ime ali priimek …"
                           autocomplete="off" role="combobox" aria-expanded="false" aria-controls="dancerResults" aria-autocomplete="list">
                    <div class="search-results" id="dancerResults" role="listbox"></div>
                </div>
            </div>

            @if(count($rest))
            <div class="col-lg-6">
                <h2 class="h6 fw-bold mb-3">Ostali plesalci</h2>
                <div class="d-flex flex-wrap gap-4 mb-3">
                    <div class="rest-stat">
                        <div class="val">{{ count($rest) }}</div>
                        <div class="lbl">plesalcev</div>
                    </div>
                    <div class="rest-stat">
                        <div class="val">{{ number_format($restPts, 0, ',', '.') }}</div>
                        <div class="lbl">skupaj točk</div>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    @foreach(['CHA','ALS','ADV','INT','NOV','NEW'] as $div)
                        @if(!empty($restDivs[$div]))
                            <span class="division-badge div-{{ $div }}">{{ $div }}&thinsp;<strong>{{ $restDivs[$div] }}</strong></span>
                        @endif
                    @endforeach
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

<script>
(function () {
    const dancers = @json($searchList);
    const input   = document.getElementById('dancerSearch');
    const box     = document.getElementById('dancerResults');
    const norm    = (s) => s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/đ/g, 'd');
    dancers.forEach(d => { d.key = norm(d.name); });

    const esc = (s) => s.replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    let active = -1;

    function render() {
        const q = norm(input.value.trim());
        active = -1;
        if (!q) { close(); return; }
        const words = q.split(/\s+/);
        const hits = dancers.filter(d => words.every(w => d.key.split(/\s+/).some(p => p.startsWith(w)) || d.key.includes(w))).slice(0, 8);
        box.innerHTML = hits.length
            ? hits.map(d => `<a href="/dancer/${d.id}" role="option">
                    <span>${esc(d.name)}</span>
                    <span class="d-flex gap-1 align-items-center">
                        <span class="role-badge role-${d.role}">${d.role === 'leader' ? 'Leader' : 'Follower'}</span>
                        ${d.div ? `<span class="division-badge div-${d.div}">${d.div}</span>` : ''}
                    </span>
                </a>`).join('')
            : '<div class="empty">Ni zadetkov.</div>';
        box.classList.add('open');
        input.setAttribute('aria-expanded', 'true');
    }

    function close() {
        box.classList.remove('open');
        input.setAttribute('aria-expanded', 'false');
    }

    function highlight(i) {
        const items = box.querySelectorAll('a');
        if (!items.length) return;
        active = (i + items.length) % items.length;
        items.forEach((a, j) => a.classList.toggle('active', j === active));
        items[active].scrollIntoView({ block: 'nearest' });
    }

    input.addEventListener('input', render);
    input.addEventListener('focus', () => { if (input.value.trim()) render(); });
    input.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowDown') { e.preventDefault(); highlight(active + 1); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); highlight(active - 1); }
        else if (e.key === 'Enter') {
            const items = box.querySelectorAll('a');
            const target = items[active >= 0 ? active : 0];
            if (target) { e.preventDefault(); window.location = target.href; }
        }
        else if (e.key === 'Escape') { close(); }
    });
    document.addEventListener('click', (e) => { if (!e.target.closest('.dancer-search')) close(); });
})();
</script>
