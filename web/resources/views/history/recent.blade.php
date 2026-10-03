@extends('layouts.app')

@section('title', 'Zadnje točke')

@section('content')

<div class="page-header d-flex align-items-end justify-content-between flex-wrap gap-3">
    <div>
        <div class="eyebrow">Skozi čas</div>
        <h1>Zadnje točke</h1>
        <p>Zadnjih {{ count($events) }} dogodkov, na katerih so slovenski plesalci osvojili WSDC točke.</p>
    </div>
    @include('partials.history-tabs', ['current' => 'recent'])
</div>

@if(empty($events))
    <div class="alert alert-info">Zgodovina še ni na voljo.</div>
@else

<div class="d-flex flex-column gap-3">
@foreach($events as $ev)
    <div class="card table-card">
        <div class="card-header d-flex align-items-baseline justify-content-between flex-wrap gap-2" style="background:var(--surface-2);">
            <div>
                @if($ev['url'])
                    <a href="{{ $ev['url'] }}" class="text-reset" target="_blank" rel="noopener">{{ $ev['event'] }}</a>
                @else
                    {{ $ev['event'] }}
                @endif
                @if($ev['location'])
                    <span class="text-muted fw-normal small ms-1">{{ $ev['location'] }}</span>
                @endif
            </div>
            <div class="text-muted fw-normal small text-nowrap">
                {{ \Carbon\Carbon::createFromFormat('Ym', (string) $ev['ym'])->format('M Y') }}
                · <strong class="text-body">{{ $ev['points'] }}</strong>&nbsp;t.
            </div>
        </div>
        <div class="table-responsive">
        <table class="table table-hover table-sm mb-0 align-middle" style="font-size:.88rem;">
            <tbody>
            @foreach($ev['entries'] as $e)
                <tr>
                    <td class="ps-3 py-2">
                        <a href="/dancer/{{ $e['wscid'] }}" class="dancer-link">{{ $e['name'] }}</a>
                    </td>
                    <td class="py-2" style="width:7rem;">
                        <span class="role-badge role-{{ $e['role'] }}">{{ ucfirst($e['role']) }}</span>
                    </td>
                    <td class="py-2" style="width:4rem;">
                        <span class="division-badge div-{{ $e['division'] }}">{{ $e['division'] }}</span>
                    </td>
                    <td class="py-2 text-end" style="width:5rem;">{{ $e['result'] ?: '—' }}</td>
                    <td class="pe-3 py-2 text-end" style="width:4rem;"><strong>{{ $e['points'] }}</strong></td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
    </div>
@endforeach
</div>

@endif

@endsection
