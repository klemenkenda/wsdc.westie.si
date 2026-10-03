@extends('layouts.app')

@section('title', 'Prve točke')

@section('content')

@php
    $debutCount = count(array_filter($firsts, fn($f) => $f['is_debut']));
@endphp

<div class="page-header d-flex align-items-end justify-content-between flex-wrap gap-3">
    <div>
        <div class="eyebrow">Skozi čas</div>
        <h1>Prve točke</h1>
        <p>
            Prve točke vsakega plesalca v posamezni diviziji in vlogi.
            <strong>Poudarjeni vnosi</strong> označujejo prve točke sploh ({{ $debutCount }} plesalcev).
        </p>
    </div>
    <div class="seg">
        <a href="/history" class="btn">Posnetki lestvice</a>
        <a href="/history/firsts" class="btn active">Prve točke</a>
    </div>
</div>

@if(empty($firsts))
    <div class="alert alert-info">Zgodovina še ni na voljo.</div>
@else

<h2 class="h5 fw-bold mb-2">Prve točke v Sloveniji</h2>
<p class="text-muted small mb-3">Prvi slovenski plesalci s točkami v posamezni diviziji. Če je bilo v istem mesecu več prvih, so navedeni vsi.</p>
<div class="row g-3 mb-5">
    @foreach(['leader' => 'Leaders', 'follower' => 'Followers'] as $role => $roleLabel)
    <div class="col-lg-6">
        <div class="card table-card h-100">
            <div class="card-header py-2">
                <span class="role-badge role-{{ $role }}">{{ $roleLabel }}</span>
            </div>
            <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle" style="font-size:.88rem;">
                <tbody>
                @foreach($national[$role] as $div => $first)
                    <tr>
                        <td class="ps-3 py-2" style="width:4rem;">
                            <span class="division-badge div-{{ $div }}">{{ $div }}</span>
                        </td>
                        @if($first === null)
                            <td class="py-2 text-muted" colspan="2">Še nihče</td>
                        @else
                            <td class="py-2 text-muted text-nowrap" style="width:6rem;">
                                {{ \Carbon\Carbon::createFromFormat('Ym', (string) $first['ym'])->format('M Y') }}
                            </td>
                            <td class="pe-3 py-2">
                                @foreach($first['entries'] as $e)
                                <div>
                                    <a href="/dancer/{{ $e['wscid'] }}" class="dancer-link fw-bold">{{ $e['name'] }}</a>
                                    <span class="text-muted small">
                                        · {{ $e['event'] }} · {{ $e['result'] ?: '—' }}, {{ $e['points'] }}&nbsp;t.
                                    </span>
                                </div>
                                @endforeach
                            </td>
                        @endif
                    </tr>
                @endforeach
                </tbody>
            </table>
            </div>
        </div>
    </div>
    @endforeach
</div>

<h2 class="h5 fw-bold mb-2">Prve točke po plesalcih</h2>
<div class="seg mb-3" id="firstsFilter">
    <button type="button" class="btn active" data-filter="all">Vse divizije</button>
    <button type="button" class="btn" data-filter="debut">Samo prve točke sploh</button>
</div>

@php
    $byYear = [];
    foreach ($firsts as $f) {
        $byYear[intdiv($f['ym'], 100)][$f['ym']][] = $f;
    }
@endphp

@foreach($byYear as $year => $months)
<div class="firsts-year mb-4">
    <h3 class="h6 fw-bold text-muted mb-2">{{ $year }}</h3>
    <div class="card table-card">
    <div class="table-responsive">
    <table class="table table-hover table-sm mb-0 align-middle" style="font-size:.88rem;">
        <thead>
            <tr>
                <th class="ps-3 py-2" style="width:6rem;">Mesec</th>
                <th class="py-2">Ime</th>
                <th class="py-2">Vloga</th>
                <th class="py-2">Divizija</th>
                <th class="py-2">Dogodek</th>
                <th class="py-2 text-end">Uvrstitev</th>
                <th class="pe-3 py-2 text-end">Točke</th>
            </tr>
        </thead>
        <tbody>
        @foreach($months as $ym => $items)
            @foreach($items as $f)
            <tr class="{{ $f['is_debut'] ? 'is-debut' : 'not-debut' }}">
                <td class="ps-3 py-2 text-muted">
                    {{ \Carbon\Carbon::createFromFormat('Ym', (string) $ym)->format('M') }}
                </td>
                <td class="py-2">
                    <a href="/dancer/{{ $f['wscid'] }}" class="dancer-link {{ $f['is_debut'] ? 'fw-bold' : '' }}">{{ $f['name'] }}</a>
                    @if($f['is_debut'])
                        <span class="badge rounded-pill bg-primary ms-1" style="font-size:.65rem;">Prve točke</span>
                    @endif
                </td>
                <td class="py-2">
                    <span class="role-badge role-{{ $f['role'] }}">{{ ucfirst($f['role']) }}</span>
                </td>
                <td class="py-2">
                    <span class="division-badge div-{{ $f['division'] }}">{{ $f['division'] }}</span>
                </td>
                <td class="py-2">
                    {{ $f['event'] }}
                    @if($f['location'])
                        <div class="text-muted small">{{ $f['location'] }}</div>
                    @endif
                </td>
                <td class="py-2 text-end">{{ $f['result'] ?: '—' }}</td>
                <td class="pe-3 py-2 text-end">
                    @if($f['points'] > 0)
                        <strong>{{ $f['points'] }}</strong>
                    @else
                        <span class="pts-zero">0</span>
                    @endif
                </td>
            </tr>
            @endforeach
        @endforeach
        </tbody>
    </table>
    </div>
    </div>
</div>
@endforeach

<style>
    .debut-only tr.not-debut { display: none; }
    .debut-only .firsts-year:not(:has(tr.is-debut)) { display: none; }
</style>
<script>
document.querySelectorAll('#firstsFilter [data-filter]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        document.querySelectorAll('#firstsFilter [data-filter]').forEach(function (b) {
            b.classList.toggle('active', b === btn);
        });
        document.body.classList.toggle('debut-only', btn.dataset.filter === 'debut');
    });
});
</script>

@endif

@endsection
