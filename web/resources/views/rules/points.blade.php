@extends('layouts.app')

@section('title', 'Točke po stopnjah')

@php
    // WSDC Registry Event Rules 2026 (V2026.1C), Chart 5: Points Awarded per Tier.
    // Chart 6: Competition Rounds Based on Tier Size (finals are always held).
    // [tier, competitors per role, [1st..5th], [finalist points, up to place] or null,
    //  [number of rounds, prelims, quarters, semis]]
    $tiers = [
        [1, '5–10',   [3, 2, 1, 0, 0],      null,     ['1',   'uneven', null,          null]],
        [2, '11–19',  [6, 4, 3, 2, 1],      null,     ['1–2', 'optional', null,        null]],
        [3, '20–39',  [10, 8, 6, 4, 2],     [1, 10],  ['2',   'yes', null,             'optional']],
        [4, '40–79',  [15, 12, 10, 8, 6],   [1, 12],  ['3',   'yes', 'optional',       'waivable']],
        [5, '80–129', [20, 16, 14, 12, 10], [2, 15],  ['3–4', 'yes', 'encouraged',     'yes']],
        [6, '130+',   [25, 22, 18, 15, 12], [2, 15],  ['3–4', 'yes', 'encouraged',     'yes']],
    ];
    $roundNames = ['Predtekmovanje', 'Četrtfinale', 'Polfinale', 'Finale'];
    // status => [pill class, tooltip]
    $roundStatus = [
        'yes'        => ['round-yes', 'obvezno'],
        'waivable'   => ['round-yes', 'obvezno – razen tik nad mejo stopnje (*)'],
        'encouraged' => ['round-encouraged', 'priporočeno'],
        'optional'   => ['round-optional', 'neobvezno'],
        'uneven'     => ['round-optional', 'le pri neenakem številu vodij in sledilk'],
    ];
    $roundsWord = fn (string $n) => match ($n) { '1' => 'krog', '2', '1–2' => 'kroga', default => 'krogi' };
    $rulesUrl = 'https://www.worldsdc.com/wp-content/uploads/2026/03/Revised-WSDC-Registry-Event-Rules-Updated-Jan172026-V2026.1B.pdf';
@endphp

@section('content')

<style>
    .round-count {
        display: inline-block; min-width: 3.6em; margin-right: .3rem;
        font-size: .75rem; font-weight: 600; color: var(--text-muted);
    }
    .round-pill {
        --c: #94a3b8;
        display: inline-block;
        font-size: .7rem; font-weight: 600;
        padding: .18em .6em; border-radius: 999px; line-height: 1.3;
        white-space: nowrap; cursor: default;
        color: color-mix(in srgb, var(--c) 82%, #000);
        background: color-mix(in srgb, var(--c) 14%, transparent);
        border: 1px solid color-mix(in srgb, var(--c) 35%, transparent);
    }
    [data-bs-theme="dark"] .round-pill {
        color: color-mix(in srgb, var(--c) 65%, #fff);
        background: color-mix(in srgb, var(--c) 20%, transparent);
    }
    .round-yes        { --c: #10b981; }
    .round-encouraged { --c: #f59e0b; }
    .round-optional   { --c: #94a3b8; border-style: dashed; background: transparent; }
    [data-bs-theme="dark"] .round-optional { background: transparent; }
</style>

<div class="page-header">
    <div class="eyebrow">Pravila WSDC</div>
    <h1>Točke po stopnjah</h1>
    <p>Koliko točk prinese uvrstitev v finale Jack &amp; Jill tekmovanja, glede na stopnjo (tier) tekmovanja, in koliko krogov (predtekmovanje, četrtfinale, polfinale, finale) mora tekmovanje imeti.
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
                <th class="border-start">Krogi</th>
            </tr>
        </thead>
        <tbody>
        @foreach($tiers as [$tier, $range, $places, $finalists, $rounds])
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
                <td class="border-start text-nowrap">
                    <span class="round-count">{{ $rounds[0] }} {{ $roundsWord($rounds[0]) }}</span>
                    @foreach([...array_slice($rounds, 1), 'yes'] as $i => $r)
                        @continue(!$r)
                        <span class="round-pill {{ $roundStatus[$r][0] }}" title="{{ $roundNames[$i] }}: {{ $roundStatus[$r][1] }}">{{ $roundNames[$i] }}{{ $r === 'waivable' ? '*' : '' }}</span>
                    @endforeach
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
    <div class="round-legend small text-muted px-3 pb-3">
        Krogi:
        <span class="round-pill round-yes">obvezno</span>
        <span class="round-pill round-encouraged">priporočeno</span>
        <span class="round-pill round-optional">neobvezno</span>
    </div>
</div>

<div class="card mt-3">
    <div class="card-body">
        <ul class="mb-0 small" style="line-height:1.7;">
            <li>Stopnja se določi po številu <strong>različnih tekmovalcev v posamezni vlogi</strong>. Vodje in sledilke istega tekmovanja so lahko v različnih stopnjah – npr. 18 vodij in 35 sledilk: vodje dobijo točke stopnje 2, sledilke stopnje 3.</li>
            <li>Točke dobijo samo uvrščeni v finale; uvrstitev v finale točk še ne zagotavlja.</li>
            <li>Finale je vedno na sporedu; število krogov (z njim vred) je najmanjše obvezno. Če so vodje in sledilke v različnih stopnjah, število krogov določa <strong>višja stopnja</strong>.</li>
            <li>* Krog se lahko izpusti, če število tekmovalcev za največ 10&nbsp;% preseže zgornjo mejo prejšnje stopnje – npr. tekmovanje stopnje 4 s 43 sledilkami (in manj kot 39 vodji) ima lahko le predtekmovanje in finale.</li>
            <li>Če se tekmovalec v isti diviziji uvrsti dvakrat, dobi točke le za boljšo uvrstitev.</li>
            <li>Pri združenih divizijah se točke podelijo v nižji diviziji (npr. Advanced/All-Star prinese točke Advanced), razen Newcomer/Novice, ki prinese točke Novice.</li>
            <li>Osnutek pravil za 2027 stopnjo 2 deli na 2A (brez predtekmovanja: 3, 2, 1) in 2B (s predtekmovanjem: 6, 4, 3, 2, 1); ostale stopnje ostajajo enake.</li>
        </ul>
    </div>
</div>

@endsection
