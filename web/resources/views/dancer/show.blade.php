@extends('layouts.app')

@section('title', $name)

@section('content')

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/">Lestvice</a></li>
        <li class="breadcrumb-item active">{{ $name }}</li>
    </ol>
</nav>

<style>
    .profile-hero { position: relative; overflow: hidden; }
    .profile-hero::before {
        content: ''; position: absolute; inset: 0 0 auto 0; height: 5rem;
        background: linear-gradient(120deg, rgba(var(--brand-rgb), .16), rgba(219, 39, 119, .12));
    }
    .profile-hero .card-body { position: relative; padding: 1.5rem; }
    .avatar {
        width: 4.5rem; height: 4.5rem; border-radius: 1.2rem; flex-shrink: 0;
        display: grid; place-items: center;
        font-size: 1.5rem; font-weight: 800; color: #fff; letter-spacing: -.02em;
        background: linear-gradient(135deg, var(--brand), var(--brand-2));
        box-shadow: 0 10px 24px -10px rgba(var(--brand-rgb), .8);
        border: 3px solid var(--surface);
    }
    .role-panel { background: var(--surface-2); border: 1px solid var(--border-soft); border-radius: .8rem; padding: 1rem; height: 100%; }
    .role-panel h6 { font-size: .72rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--text-muted); }
    .pts-tile { flex: 1 1 0; min-width: 3.2rem; text-align: center; padding: .5rem .25rem; border-radius: .6rem; background: var(--surface); border: 1px solid var(--border-soft); }
    .pts-tile .val { font-size: 1.15rem; font-weight: 700; margin-top: .3rem; }
    .pts-tile.zero .val { color: var(--text-muted); opacity: .45; }
    .stat-tile { padding: 1rem 1.15rem; }
    .stat-tile .label { font-size: .8rem; color: var(--text-muted); }
    .stat-tile .val { font-size: 1.6rem; font-weight: 800; letter-spacing: -.02em; }
    .stat-tile .val small { font-size: .85rem; font-weight: 500; color: var(--text-muted); }
    .place { display: inline-grid; place-items: center; min-width: 2.4rem; padding: .2rem .5rem; border-radius: 999px; font-size: .75rem; font-weight: 700; background: var(--surface-2); color: var(--text-muted); border: 1px solid var(--border-soft); }
    .place-1 { background: linear-gradient(135deg, #fde68a, #f59e0b); color: #78350f; border-color: transparent; }
    .place-2 { background: linear-gradient(135deg, #e5e7eb, #9ca3af); color: #1f2937; border-color: transparent; }
    .place-3 { background: linear-gradient(135deg, #fed7aa, #c2410c); color: #fff; border-color: transparent; }
</style>

@php
    $initials = collect(preg_split('/\s+/', trim($name)))->filter()->map(fn($p) => mb_substr($p, 0, 1))->take(2)->implode('');
@endphp

<div class="row g-4 mt-0">

    {{-- ------------------------------------------------------------------ --}}
    {{-- Header card --}}
    {{-- ------------------------------------------------------------------ --}}
    <div class="col-12">
        <div class="card profile-hero">
            <div class="card-body">
                <div class="d-flex align-items-end gap-3 mb-4 flex-wrap" style="padding-top:1.25rem;">
                    <div class="avatar">{{ mb_strtoupper($initials) }}</div>
                    <div>
                        <h1 class="h2 fw-bold mb-1" style="letter-spacing:-.02em;">{{ $name }}</h1>
                        <div class="text-muted small d-flex align-items-center gap-2 flex-wrap">
                            <span>WSCID {{ $dancer['wscid'] }}</span>
                            @if($primary)
                                <span>·</span>
                                <span class="role-badge role-{{ $primary }}">{{ ucfirst($primary) }}</span>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Points grid --}}
                <div class="row g-3">
                    @foreach(['leader','follower'] as $role)
                        @if($dancer[$role])
                        <div class="col-md-6">
                            <div class="role-panel">
                                <h6 class="mb-3 d-flex align-items-center gap-2">
                                    {{ ucfirst($role) }}
                                    @if($primary === $role)
                                        <span class="badge rounded-pill text-bg-secondary" style="font-size:.6rem;letter-spacing:.05em;">PRIMARY</span>
                                    @endif
                                </h6>
                                <div class="d-flex flex-wrap gap-2">
                                    @foreach(['CHA','ALS','ADV','INT','NOV','NEW'] as $div)
                                        @php $pts = $dancer[$role]['points'][$div] ?? 0; @endphp
                                        <div class="pts-tile {{ $pts > 0 ? '' : 'zero' }}">
                                            <span class="division-badge div-{{ $div }}">{{ $div }}</span>
                                            <div class="val">{{ $pts }}</div>
                                        </div>
                                    @endforeach
                                </div>
                                @if($dancer[$role]['level']['required'] ?? null)
                                <div class="mt-3 small text-muted d-flex align-items-center gap-2 flex-wrap">
                                    Zahtevano:
                                    <span class="division-badge div-{{ $dancer[$role]['level']['required'] }}">
                                        {{ $dancer[$role]['level']['required'] }}
                                    </span>
                                    @if(($dancer[$role]['level']['allowed'] ?? null) && $dancer[$role]['level']['allowed'] !== $dancer[$role]['level']['required'])
                                    <span class="ms-2">Lahko tekmuje do:</span>
                                    <span class="division-badge div-{{ $dancer[$role]['level']['allowed'] }}">
                                        {{ $dancer[$role]['level']['allowed'] }}
                                    </span>
                                    @endif
                                </div>
                                @endif
                            </div>
                        </div>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- ------------------------------------------------------------------ --}}
    {{-- Community rankings --}}
    {{-- ------------------------------------------------------------------ --}}
    @if(count($ranks))
    <div class="col-12">
        <h2 class="h6 fw-bold text-uppercase text-muted mb-3" style="letter-spacing:.08em;font-size:.75rem;">🏆 Slovenska skupnostna lestvica</h2>
        <div class="row g-3">
            @foreach($ranks as $label => $r)
            <div class="col-6 col-md-4 col-lg-3">
                <div class="card stat-tile h-100">
                    <div class="label">{{ $label }}</div>
                    <div class="val">#{{ $r['rank'] }} <small>/ {{ $r['total'] }}</small></div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- ------------------------------------------------------------------ --}}
    {{-- Competition history --}}
    {{-- ------------------------------------------------------------------ --}}
    @foreach(['leader','follower'] as $role)
        @if(count($history[$role] ?? []))
        <div class="col-12">
            <div class="card table-card">
                <div class="card-header d-flex align-items-center gap-2">
                    <span class="role-badge role-{{ $role }}">{{ ucfirst($role) }}</span>
                    zgodovina tekmovanj
                    <span class="badge rounded-pill bg-body-secondary text-body border ms-auto">{{ count($history[$role]) }}</span>
                </div>
                <div class="table-responsive">
                <table class="table table-hover align-middle" style="font-size:.92rem;">
                    <thead>
                        <tr>
                            <th>Datum</th>
                            <th>Divizija</th>
                            <th>Tekmovanje</th>
                            <th class="text-end">Pts</th>
                            <th class="text-end">Rezultat</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($history[$role] as $comp)
                        <tr>
                            <td class="text-nowrap text-muted" style="min-width:9rem;">{{ $comp['date'] }}</td>
                            <td><span class="division-badge div-{{ $comp['division'] }}">{{ $comp['division'] }}</span></td>
                            <td style="min-width:14rem;">
                                @if($comp['url'])
                                    <a href="{{ $comp['url'] }}" target="_blank" rel="noopener" class="dancer-link">{{ $comp['event'] }}</a>
                                @else
                                    <span class="fw-semibold">{{ $comp['event'] }}</span>
                                @endif
                                <div class="text-muted" style="font-size:.8rem;">{{ $comp['location'] }}</div>
                            </td>
                            <td class="text-end fw-bold">{{ $comp['points'] }}</td>
                            <td class="text-end">
                                @php
                                    $r = $comp['result'];
                                    $suffix = is_numeric($r) ? ($r==1?'st':($r==2?'nd':($r==3?'rd':'th'))) : '';
                                    $placeClass = in_array((string) $r, ['1','2','3'], true) ? 'place-' . $r : '';
                                @endphp
                                <span class="place {{ $placeClass }}">{{ $r }}{{ $suffix }}</span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
            </div>
        </div>
        @endif
    @endforeach

</div>

@endsection
