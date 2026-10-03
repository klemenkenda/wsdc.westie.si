{{-- Dancers outside the top 10 with the most points in the last 12 months. --}}
@php
    // Slovenian dual and plural: 1 točka, 2 točki, 3–4 točke, 5+ točk (by the last two digits).
    $tock = fn(int $n) => match (true) {
        $n % 100 === 1                  => 'točka',
        $n % 100 === 2                  => 'točki',
        in_array($n % 100, [3, 4], true) => 'točke',
        default                         => 'točk',
    };
@endphp
@if(count($rising))
<div class="mt-4">
    <h2 class="h6 fw-bold mb-1">Vzhajajoče zvezde</h2>
    <p class="text-muted small mb-3">Največ točk v zadnjih 12 mesecih izven najboljših {{ $top }}.</p>
    <div class="row g-3">
        @foreach($rising as $star)
        @php $e = $star['entry']; @endphp
        <div class="col-md-4">
            <a href="/dancer/{{ $e['wscid'] }}" class="card rising-card h-100 text-decoration-none">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rising-icon" aria-hidden="true">★</div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-bold text-body text-truncate">{{ $e['name'] }}</div>
                        <div class="d-flex gap-1 mt-1">
                            <span class="role-badge role-{{ $e['role'] }}">{{ ucfirst($e['role']) }}</span>
                            @if($e['required'])
                                <span class="division-badge div-{{ $e['required'] }}">{{ $e['required'] }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="rising-pts text-nowrap">+{{ $star['points'] }}</div>
                        @if($star['top_div'])
                        <div class="text-muted text-nowrap" style="font-size:.7rem;">
                            +{{ $star['top_points'] }} {{ $star['top_div'] }} {{ $tock($star['top_points']) }}
                        </div>
                        @endif
                    </div>
                </div>
            </a>
        </div>
        @endforeach
    </div>
</div>

<style>
    .rising-card { transition: transform .15s, box-shadow .15s, border-color .15s; }
    .rising-card:hover { transform: translateY(-2px); border-color: rgba(var(--brand-rgb), .35); }
    .rising-icon {
        width: 2.4rem; height: 2.4rem; flex-shrink: 0; border-radius: .7rem;
        display: grid; place-items: center; font-size: 1.1rem;
        color: #b45309; background: linear-gradient(135deg, #fef3c7, #fde68a);
    }
    [data-bs-theme="dark"] .rising-icon { color: #fde68a; background: rgba(245, 158, 11, .18); }
    .rising-pts { font-size: 1.3rem; font-weight: 750; color: var(--brand); line-height: 1.1; }
</style>
@endif
