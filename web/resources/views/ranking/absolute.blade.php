@extends('layouts.app')

@section('title', 'Skupna lestvica')

@section('content')

<div class="page-header d-flex align-items-end justify-content-between flex-wrap gap-3">
    <div>
        <div class="eyebrow">Slovenska WCS skupnost</div>
        <h1>Skupna lestvica</h1>
        <p>Najboljših 10 plesalcev ne glede na vlogo. Vsak je zastopan s svojo najboljšo vlogo.</p>
    </div>
    @include('partials.ranking-tabs', ['current' => 'absolute'])
</div>

@if(count($entries) === 0)
    <div class="alert alert-info">Podatki še niso na voljo. Zaženite scraper.</div>
@else
<div class="card table-card">
<div class="table-responsive">
<table class="table table-hover align-middle">
    <thead>
        <tr>
            <th class="col-rank">#</th>
            <th>Ime</th>
            <th>Vloga</th>
            <th>Divizija</th>
            <th>Lahko pleše</th>
            <th class="col-pts">CHA</th>
            <th class="col-pts">ALS</th>
            <th class="col-pts">ADV</th>
            <th class="col-pts">INT</th>
            <th class="col-pts">NOV</th>
            <th class="col-pts">NEW</th>
            <th>Najboljši rezultat</th>
        </tr>
    </thead>
    <tbody>
        @foreach(array_slice($entries, 0, $topCount) as $e)
        <tr>
            <td class="col-rank"><span class="rank-num {{ $e['rank'] <= 3 ? 'rank-' . $e['rank'] : '' }}">{{ $e['rank'] }}</span></td>
            <td>
                <a href="/dancer/{{ $e['wscid'] }}" class="dancer-link">{{ $e['name'] }}</a>
            </td>
            <td class="text-nowrap">
                <span class="role-badge role-{{ $e['role'] }}">{{ ucfirst($e['role']) }}</span>
                @if($e['primary'] !== $e['role'])
                    <span class="secondary-role">(secondary)</span>
                @endif
            </td>
            <td>
                @if($e['required'])
                    <span class="division-badge div-{{ $e['required'] }}">{{ $e['required'] }}</span>
                @else
                    <span class="text-muted">—</span>
                @endif
            </td>
            <td>
                @if($e['allowed'] && $e['allowed'] !== $e['required'])
                    <span class="division-badge div-{{ $e['allowed'] }}">{{ $e['allowed'] }}</span>
                @else
                    <span class="text-muted">—</span>
                @endif
            </td>
            @foreach(['CHA','ALS','ADV','INT','NOV','NEW'] as $div)
            <td class="col-pts">
                @if(($e['points'][$div] ?? 0) > 0)
                    <strong>{{ $e['points'][$div] }}</strong>
                @else
                    <span class="pts-zero">0</span>
                @endif
            </td>
            @endforeach
            <td>
                @if($e['best'])
                    <div class="best-event">
                        <span class="division-badge div-{{ $e['best']['level'] }} me-1">{{ $e['best']['level'] }}</span>
                        {{ $e['best']['event_name'] }}
                    </div>
                    <div class="best-meta">{{ $e['best']['event_date'] }} · {{ $e['best']['points'] }} pts · {{ $e['best']['result'] }}{{ is_numeric($e['best']['result']) ? ($e['best']['result'] == 1 ? 'st' : ($e['best']['result'] == 2 ? 'nd' : ($e['best']['result'] == 3 ? 'rd' : 'th'))) : '' }}</div>
                @else
                    <span class="text-muted">—</span>
                @endif
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
</div>
</div>

@include('partials.rising-stars', ['rising' => $rising])
@include('partials.ranking-rest', ['entries' => $entries, 'topCount' => $topCount])
@endif

@endsection
