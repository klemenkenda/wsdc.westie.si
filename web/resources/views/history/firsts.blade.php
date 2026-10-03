@extends('layouts.app')

@section('title', 'Prvi nastopi')

@section('content')

@php
    $debutCount = count(array_filter($firsts, fn($f) => $f['is_debut']));
@endphp

<div class="page-header d-flex align-items-end justify-content-between flex-wrap gap-3">
    <div>
        <div class="eyebrow">Skozi čas</div>
        <h1>Prvi nastopi</h1>
        <p>
            Prvi rezultat vsakega plesalca v posamezni diviziji in vlogi.
            <strong>Poudarjeni vnosi</strong> označujejo prvi nastop sploh ({{ $debutCount }} plesalcev).
        </p>
    </div>
    <div class="seg">
        <a href="/history" class="btn">Posnetki lestvice</a>
        <a href="/history/firsts" class="btn active">Prvi nastopi</a>
    </div>
</div>

@if(empty($firsts))
    <div class="alert alert-info">Zgodovina še ni na voljo.</div>
@else

<div class="seg mb-3" id="firstsFilter">
    <button type="button" class="btn active" data-filter="all">Vse divizije</button>
    <button type="button" class="btn" data-filter="debut">Samo prvi nastop</button>
</div>

@php
    $byYear = [];
    foreach ($firsts as $f) {
        $byYear[intdiv($f['ym'], 100)][$f['ym']][] = $f;
    }
@endphp

@foreach($byYear as $year => $months)
<div class="firsts-year mb-4">
    <h2 class="h5 fw-bold mb-2">{{ $year }}</h2>
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
                        <span class="badge rounded-pill bg-primary ms-1" style="font-size:.65rem;">Prvi nastop</span>
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
