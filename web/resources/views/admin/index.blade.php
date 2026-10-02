@extends('layouts.app')

@section('title', 'Upravljanje')

@section('content')
<div class="container" style="max-width:860px;">
    <div class="page-header"><div class="eyebrow">Administracija</div><h1>Posodobi / Upravljanje</h1></div>

    @if (!$authed)
        {{-- Password gate --}}
        @if (session('login_error'))
            <div class="alert alert-danger">{{ session('login_error') }}</div>
        @endif
        <div class="card" style="max-width:380px;">
            <div class="card-body">
                <h5 class="card-title mb-3">Prijava</h5>
                <form method="POST" action="/admin/login">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Geslo</label>
                        <input type="password" name="secret" class="form-control" autofocus required>
                    </div>
                    <button type="submit" class="btn btn-primary">Prijava</button>
                </form>
            </div>
        </div>
    @else
        @if (session('flash_error'))
            <div class="alert alert-danger">{{ session('flash_error') }}</div>
        @endif
        @if (session('flash_success'))
            <div class="alert alert-success">{{ session('flash_success') }}</div>
        @endif

        <div class="row g-4">
            {{-- ── Scraper section ── --}}
            <div class="col-12 col-md-6">
                <div class="card h-100">
                    <div class="card-header fw-semibold">&#x21BB; Posodobitev podatkov</div>
                    <div class="card-body d-flex flex-column">
                        <p class="text-muted small mb-3">Zaženi scraper, ki prenese trenutne točke z WSDC. Postopek traja ~1 minuto.</p>
                        <button id="runBtn" class="btn btn-primary mb-3" type="button">Zaženi posodobitev</button>

                        <div id="scraperStatus" class="fw-semibold small mb-2 d-none"></div>
                        <div id="scraperSpinner" class="d-none mb-2 d-flex align-items-center gap-2 text-muted small">
                            <div class="spinner-border spinner-border-sm" role="status"></div> Scraper teče…
                        </div>
                        <pre id="scraperOutput"
                             style="background:#1e1e1e;color:#d4d4d4;padding:.75rem;border-radius:6px;
                                    font-size:.75rem;flex:1;min-height:200px;max-height:420px;
                                    overflow-y:auto;white-space:pre-wrap;display:none;"></pre>
                    </div>
                </div>
            </div>

            {{-- ── Dancer list section ── --}}
            <div class="col-12 col-md-6">
                <div class="card h-100">
                    <div class="card-header fw-semibold">&#9998; Seznam plesalcev</div>
                    <div class="card-body d-flex flex-column p-0">
                        <div class="px-3 pt-3 pb-2">
                            <form method="POST" action="/admin/dancers" class="row g-2 align-items-end">
                                @csrf
                                <div class="col-auto">
                                    <label class="form-label form-label-sm mb-1">WSDCID</label>
                                    <input type="text" name="wscid" class="form-control form-control-sm"
                                           placeholder="12345" pattern="\d+" inputmode="numeric"
                                           required style="width:6.5rem;">
                                </div>
                                <div class="col">
                                    <label class="form-label form-label-sm mb-1">Ime in priimek</label>
                                    <input type="text" name="name" class="form-control form-control-sm"
                                           placeholder="Jana Novak" required>
                                </div>
                                <div class="col-auto">
                                    <button type="submit" class="btn btn-sm btn-success">Dodaj</button>
                                </div>
                            </form>
                        </div>
                        <div style="overflow-y:auto; max-height:380px;">
                            <table class="table table-sm table-hover mb-0">
                                <thead class="table-dark sticky-top">
                                    <tr>
                                        <th class="ps-3">WSDCID</th>
                                        <th>Ime</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($dancers as $d)
                                    <tr>
                                        <td class="ps-3 font-monospace">
                                            <a href="/dancer/{{ $d['wscid'] }}" class="text-decoration-none">{{ $d['wscid'] }}</a>
                                        </td>
                                        <td>{{ $d['name'] }}</td>
                                        <td class="text-end pe-2">
                                            <form method="POST" action="/admin/dancers/{{ $d['wscid'] }}/delete"
                                                  onsubmit="return confirm('Odstrani {{ addslashes($d['name']) }}?')">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-danger py-0">✕</button>
                                            </form>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <p class="text-muted small px-3 pt-2 pb-2 mb-0">{{ count($dancers) }} plesalcev</p>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
(function () {
    const runBtn  = document.getElementById('runBtn');
    if (!runBtn) return;

    const output  = document.getElementById('scraperOutput');
    const status  = document.getElementById('scraperStatus');
    const spinner = document.getElementById('scraperSpinner');

    runBtn.addEventListener('click', async () => {
        runBtn.disabled = true;
        output.style.display = 'block';
        output.textContent   = '';
        status.textContent   = '';
        status.className     = 'fw-semibold small mb-2';
        status.classList.remove('d-none');
        spinner.classList.remove('d-none');

        try {
            const resp = await fetch('/admin/scrape/start', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
            });
            const json = await resp.json();

            if (!json.ok) {
                finish(false, json.error ?? 'Napaka.');
                return;
            }

            let offset = 0;
            const schedulePoll = () => setTimeout(poll, 500);
            async function poll() {
                try {
                    const t = await fetch('/admin/scrape/tail?offset=' + offset);
                    const d = await t.json();
                    for (const line of d.lines) {
                        output.textContent += line + '\n';
                    }
                    if (d.lines.length) output.scrollTop = output.scrollHeight;
                    offset = d.offset;
                    d.done ? finish(d.exitOk) : schedulePoll();
                } catch (_) { schedulePoll(); }
            }
            schedulePoll();

        } catch (e) {
            finish(false, 'Napaka pri zahtevku: ' + e.message);
        }
    });

    function finish(ok, msg) {
        spinner.classList.add('d-none');
        runBtn.disabled      = false;
        status.textContent   = ok ? '✅ Uspešno posodobljeno.' : ('❌ ' + (msg ?? 'Scraper je javil napako.'));
        status.className     = 'fw-semibold small mb-2 ' + (ok ? 'text-success' : 'text-danger');
    }
})();
</script>
@endpush
