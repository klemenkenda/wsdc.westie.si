@extends('layouts.app')

@section('title', 'Zgodovina lestvice')

@section('content')

<div class="page-header d-flex align-items-end justify-content-between flex-wrap gap-3">
    <div>
        <div class="eyebrow">Skozi čas</div>
        <h1>Zgodovina lestvice</h1>
        <p>
            Posnetki skupne lestvice ob vsakem tekmovalnem datumu.
            <strong>Poudarjeni vnosi</strong> označujejo stanje na 31.&nbsp;december.
        </p>
    </div>
    <div class="seg">
        <a href="/history" class="btn active">Posnetki lestvice</a>
        <a href="/history/firsts" class="btn">Prvi nastopi</a>
    </div>
</div>

@if(empty($snapshots))
    <div class="alert alert-info">Zgodovina še ni na voljo.</div>
@else

{{-- Compact navigation --}}
@php
    $byYear = [];
    foreach($snapshots as $i => $snap) {
        $year = substr((string)$snap['ym'], 0, 4);
        $byYear[$year][] = ['i' => $i, 'snap' => $snap];
    }

    $divOrder  = ['CHA','ALS','ADV','INT','NOV','NEW'];

    // For each year, pick the year-end snapshot (or last one) for division counts
    $yearDivCounts = [];
    foreach($byYear as $year => $items) {
        $chosen = null;
        foreach($items as $item) {
            if ($item['snap']['is_year_end']) { $chosen = $item['snap']; break; }
        }
        if (!$chosen) $chosen = $items[0]['snap']; // newest (snapshots are newest-first)
        $counts = [];
        foreach($chosen['rankings'] as $e) {
            $d = $e['required'] ?? null;
            if ($d) $counts[$d] = ($counts[$d] ?? 0) + 1;
        }
        $yearDivCounts[$year] = $counts;
    }
@endphp
<div class="card mb-4">
    <div class="card-body py-3 px-3">
        @foreach($byYear as $year => $items)

        {{-- ── Desktop: single fixed-column row (md+) ── --}}
        <div class="d-none d-md-flex align-items-center gap-0 mb-1" style="min-height:1.8rem;">
            <span class="text-muted fw-semibold" style="width:3rem;flex-shrink:0;font-size:.82rem;">{{ $year }}</span>
            @foreach($divOrder as $div)
            <span style="width:3.6rem;flex-shrink:0;text-align:center;font-size:.65rem;">
                @if(!empty($yearDivCounts[$year][$div]))
                    <span class="division-badge div-{{ $div }}">{{ $div }}&thinsp;<strong>{{ $yearDivCounts[$year][$div] }}</strong></span>
                @endif
            </span>
            @endforeach
            <span class="vr mx-2 align-self-stretch opacity-25" style="flex-shrink:0;"></span>
            <span class="d-flex flex-wrap gap-1">
            @foreach($items as $item)
                <a href="#snap-{{ $item['i'] }}"
                   class="badge rounded-pill text-decoration-none {{ $item['snap']['is_year_end'] ? 'bg-primary' : 'bg-body-secondary text-body border' }}"
                   style="font-size:.72rem;"
                   onclick="openSnap('collapse-{{ $item['i'] }}')"
                >{{ \Carbon\Carbon::createFromFormat('Ym', (string)$item['snap']['ym'])->format('M') }}</a>
            @endforeach
            </span>
        </div>

        {{-- ── Mobile: two stacked rows aligned under year label (< md) ── --}}
        <div class="d-flex d-md-none align-items-start mb-2 pb-1 border-bottom">
            {{-- Year label: fixed width, aligns both rows --}}
            <span class="text-muted fw-semibold pt-1 me-1" style="min-width:2.8rem;flex-shrink:0;font-size:.82rem;">{{ $year }}</span>
            {{-- Right column: div badges on top, month links below --}}
            <div class="d-flex flex-column gap-1 flex-grow-1">
                <div class="d-flex flex-wrap gap-1">
                    @foreach($divOrder as $div)
                        @if(!empty($yearDivCounts[$year][$div]))
                            <span class="division-badge div-{{ $div }}" style="font-size:.65rem;">{{ $div }}&thinsp;<strong>{{ $yearDivCounts[$year][$div] }}</strong></span>
                        @endif
                    @endforeach
                </div>
                <div class="d-flex flex-wrap gap-1">
                    @foreach($items as $item)
                        <a href="#snap-{{ $item['i'] }}"
                           class="badge rounded-pill text-decoration-none {{ $item['snap']['is_year_end'] ? 'bg-primary' : 'bg-body-secondary text-body border' }}"
                           style="font-size:.72rem;"
                           onclick="openSnap('collapse-{{ $item['i'] }}')"
                        >{{ \Carbon\Carbon::createFromFormat('Ym', (string)$item['snap']['ym'])->format('M') }}</a>
                    @endforeach
                </div>
            </div>
        </div>

        @endforeach
    </div>
</div>

<script>
function openSnap(collapseId) {
    var el = document.getElementById(collapseId);
    if (el && !el.classList.contains('show')) {
        var bsCollapse = new bootstrap.Collapse(el, { toggle: false });
        bsCollapse.show();
    }
}
</script>

<div class="accordion d-flex flex-column gap-2" id="historyAccordion">
@foreach($snapshots as $i => $snap)
    @php
        $headerId  = 'heading-' . $i;
        $collapseId = 'collapse-' . $i;
        $isOpen    = false;
    @endphp

    <div id="snap-{{ $i }}" class="accordion-item ">

        <h2 class="accordion-header" id="{{ $headerId }}">
            <button
                class="accordion-button {{ $isOpen ? '' : 'collapsed' }} {{ $snap['is_year_end'] ? 'fw-bold' : '' }}"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#{{ $collapseId }}"
                aria-expanded="{{ $isOpen ? 'true' : 'false' }}"
                aria-controls="{{ $collapseId }}"
            >
                <span class="flex-grow-1">
                    @if($snap['is_year_end'])
                        🗓️ <span class="ms-1">{{ $snap['label'] }}</span>
                        <span class="badge rounded-pill bg-primary ms-2" style="font-size:.7rem;">Konec leta</span>
                    @else
                        {{ $snap['label'] }}
                    @endif
                </span>
                <span class="text-muted fw-normal small me-3" style="width:7rem;text-align:right;flex-shrink:0;">
                    {{ count($snap['rankings']) }} plesalcev
                </span>
            </button>
        </h2>

        <div
            id="{{ $collapseId }}"
            class="accordion-collapse collapse {{ $isOpen ? 'show' : '' }}"
            aria-labelledby="{{ $headerId }}"
            data-bs-parent=""
        >
            <div class="accordion-body p-0">
                @if(empty($snap['rankings']))
                    <p class="p-3 text-muted mb-0">Ni podatkov za ta datum.</p>
                @else

                {{-- Year-end division stats --}}
                @if($snap['is_year_end'])
                @php
                    $divCounts = [];
                    foreach($snap['rankings'] as $e) {
                        $d = $e['required'] ?? 'N/A';
                        $divCounts[$d] = ($divCounts[$d] ?? 0) + 1;
                    }
                    $divOrder = ['CHA','ALS','ADV','INT','NOV','NEW'];
                    $colorsMap = [
                        'CHA' => 'bg-purple text-white',
                        'ALS' => 'bg-primary text-white',
                        'ADV' => 'bg-success text-white',
                        'INT' => 'bg-warning text-dark',
                        'NOV' => 'bg-info text-dark',
                        'NEW' => 'bg-secondary text-white',
                    ];
                @endphp
                <div class="d-flex flex-wrap gap-3 p-3 border-bottom" style="background:var(--surface-2);">
                    <span class="fw-semibold text-muted small align-self-center">Razdelitev po divizijah:</span>
                    @foreach($divOrder as $div)
                        @if(isset($divCounts[$div]))
                        <div class="text-center">
                            <span class="division-badge div-{{ $div }} d-block mb-1">{{ $div }}</span>
                            <span class="fw-bold" style="font-size:1.1rem;">{{ $divCounts[$div] }}</span>
                        </div>
                        @endif
                    @endforeach
                    <div class="text-center ms-auto align-self-center">
                        <span class="text-muted small">Skupaj</span><br>
                        <span class="fw-bold" style="font-size:1.1rem;">{{ count($snap['rankings']) }}</span>
                    </div>
                </div>
                @endif
                <div class="table-responsive">
                <table class="table table-hover table-sm mb-0 align-middle" style="font-size:.88rem;">
                    <thead>
                        <tr>
                            <th class="ps-3 col-rank py-2">#</th>
                            <th class="py-2">Ime</th>
                            <th class="py-2">Vloga</th>
                            <th class="py-2">Divizija</th>
                            <th class="col-pts py-2">CHA</th>
                            <th class="col-pts py-2">ALS</th>
                            <th class="col-pts py-2">ADV</th>
                            <th class="col-pts py-2">INT</th>
                            <th class="col-pts py-2">NOV</th>
                            <th class="col-pts pe-3 py-2">NEW</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($snap['rankings'] as $e)
                        <tr>
                            <td class="ps-3 py-2">
                                <span class="{{ $snap['is_year_end'] ? 'fw-bold' : '' }}">{{ $e['rank'] }}</span>
                            </td>
                            <td class="py-2">
                                <a href="/dancer/{{ $e['wscid'] }}" class="dancer-link {{ $snap['is_year_end'] ? 'fw-bold' : '' }}">
                                    {{ $e['name'] }}
                                </a>
                            </td>
                            <td class="py-2">
                                <span class="role-badge role-{{ $e['role'] }}">{{ ucfirst($e['role']) }}</span>
                            </td>
                            <td class="py-2">
                                @if($e['required'])
                                    <span class="division-badge div-{{ $e['required'] }}">{{ $e['required'] }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            @foreach(['CHA','ALS','ADV','INT','NOV','NEW'] as $div)
                            <td class="col-pts py-2">
                                @if(($e['points'][$div] ?? 0) > 0)
                                    <strong>{{ $e['points'][$div] }}</strong>
                                @else
                                    <span class="pts-zero">0</span>
                                @endif
                            </td>
                            @endforeach
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
                @endif
            </div>
        </div>
    </div>
@endforeach
</div>

@endif

@endsection
