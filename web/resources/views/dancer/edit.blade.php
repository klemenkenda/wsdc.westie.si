@extends('layouts.app')

@section('title', 'Urejanje plesalcev')

@section('content')
<div class="container" style="max-width: 640px;">
    <div class="page-header"><div class="eyebrow">Administracija</div><h1>Urejanje plesalcev</h1></div>

    @if (!$authed)
        @if (session('login_error'))
            <div class="alert alert-danger">{{ session('login_error') }}</div>
        @endif
        <div class="card">
            <div class="card-body">
                <h5 class="card-title mb-3">Prijava</h5>
                <form method="POST" action="/dancers/login">
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

        {{-- Add dancer --}}
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title mb-3">Dodaj plesalca</h5>
                <form method="POST" action="/dancers" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-auto">
                        <label class="form-label">WSDCID</label>
                        <input type="text" name="wscid" class="form-control" placeholder="npr. 12345"
                               pattern="\d+" inputmode="numeric" required style="width:7rem;">
                    </div>
                    <div class="col">
                        <label class="form-label">Ime in priimek</label>
                        <input type="text" name="name" class="form-control" placeholder="npr. Jana Novak" required>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-success">Dodaj</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Dancer list --}}
        <div class="card">
            <div class="card-body p-0">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th class="ps-3">WSDCID</th>
                            <th>Ime</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($dancers as $d)
                        <tr>
                            <td class="ps-3">
                                <a href="/dancer/{{ $d['wscid'] }}" class="text-decoration-none font-monospace">{{ $d['wscid'] }}</a>
                            </td>
                            <td>{{ $d['name'] }}</td>
                            <td class="text-end pe-2">
                                <form method="POST" action="/dancers/{{ $d['wscid'] }}/delete"
                                      onsubmit="return confirm('Odstrani {{ addslashes($d['name']) }}?')">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-danger py-0">Odstrani</button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <p class="text-muted small mt-2">
            Skupaj {{ count($dancers) }} plesalcev.
            Po spremembi zaženite <strong>↻ Posodobi</strong>, da osvežite podatke.
        </p>
    @endif
</div>
@endsection
