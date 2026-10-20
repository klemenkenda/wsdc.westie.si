@extends('layouts.app')

@section('title', 'Točke po stopnjah')

@php
    // WSDC Registry Event Rules 2026 (V2026.1C), Chart 5: Points Awarded per Tier.
    // [tier, competitors per role, [1st..5th], [finalist points, up to place] or null]
    $tiers = [
        [1, '5–10',   [3, 2, 1, 0, 0],      null],
        [2, '11–19',  [6, 4, 3, 2, 1],      null],
        [3, '20–39',  [10, 8, 6, 4, 2],     [1, 10]],
        [4, '40–79',  [15, 12, 10, 8, 6],   [1, 12]],
        [5, '80–129', [20, 16, 14, 12, 10], [2, 15]],
        [6, '130+',   [25, 22, 18, 15, 12], [2, 15]],
    ];
    $rulesUrl = 'https://www.worldsdc.com/wp-content/uploads/2026/03/Revised-WSDC-Registry-Event-Rules-Updated-Jan172026-V2026.1B.pdf';
@endphp

@section('content')

<div class="page-header">
    <div class="eyebrow">Pravila WSDC</div>
    <h1>Točke po stopnjah</h1>
    <p>Koliko točk prinese uvrstitev v finale Jack &amp; Jill tekmovanja, glede na stopnjo (tier) tekmovanja.
       Po <a href="{{ $rulesUrl }}" target="_blank" rel="noopener">pravilih WSDC 2026</a> (verzija 2026.1C, veljajo od 1.&nbsp;1.&nbsp;2026).</p>
</div>

<div class="card table-card">
    <div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead>
            <tr>
                <th>Stopnja</th>
                <th>Tekmovalcev na vlogo</th>
                <th class="text-end">1.</th>
                <th class="text-end">2.</th>
                <th class="text-end">3.</th>
                <th class="text-end">4.</th>
                <th class="text-end">5.</th>
                <th class="text-end">Ostali finalisti</th>
            </tr>
        </thead>
        <tbody>
        @foreach($tiers as [$tier, $range, $places, $finalists])
            <tr>
                <td><strong>Tier {{ $tier }}</strong></td>
                <td>{{ $range }}</td>
                @foreach($places as $p)
                    <td class="text-end">
                        @if($p > 0)<strong>{{ $p }}</strong>@else<span class="pts-zero">0</span>@endif
                    </td>
                @endforeach
                <td class="text-end text-nowrap">
                    @if($finalists)
                        <strong>{{ $finalists[0] }}</strong> <span class="text-muted small">(do {{ $finalists[1] }}. mesta)</span>
                    @else
                        <span class="pts-zero">0</span>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</div>

<div class="card mt-3">
    <div class="card-body">
        <ul class="mb-0 small" style="line-height:1.7;">
            <li>Stopnja se določi po številu <strong>različnih tekmovalcev v posamezni vlogi</strong>. Vodje in sledilke istega tekmovanja so lahko v različnih stopnjah – npr. 18 vodij in 35 sledilk: vodje dobijo točke stopnje 2, sledilke stopnje 3.</li>
            <li>Točke dobijo samo uvrščeni v finale; uvrstitev v finale točk še ne zagotavlja.</li>
            <li>Če se tekmovalec v isti diviziji uvrsti dvakrat, dobi točke le za boljšo uvrstitev.</li>
            <li>Pri združenih divizijah se točke podelijo v nižji diviziji (npr. Advanced/All-Star prinese točke Advanced), razen Newcomer/Novice, ki prinese točke Novice.</li>
            <li>Osnutek pravil za 2027 stopnjo 2 deli na 2A (brez predtekmovanja: 3, 2, 1) in 2B (s predtekmovanjem: 6, 4, 3, 2, 1); ostale stopnje ostajajo enake.</li>
        </ul>
    </div>
</div>

@endsection
