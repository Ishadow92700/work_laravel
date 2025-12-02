@extends('layouts.app')

@section('content')
<style>
    .card-custom {
        border-radius: 12px;
        border: 1px solid #eee;
        padding: 25px;
        background: #fff;
        transition: 0.2s;
    }
    .card-custom:hover {
        box-shadow: 0 6px 20px rgba(0,0,0,0.08);
    }
    .btn-orange {
        background: #ff7b00;
        color: white;
        font-weight: bold;
        border-radius: 8px;
    }
    .btn-orange:hover {
        background: #e56f00;
    }
    .log-ok { color: #28a745; font-weight: bold; }
    .log-warn { color: #ffc107; font-weight: bold; }
    .log-err { color: #dc3545; font-weight: bold; }
</style>

<div class="container py-5">
    <h1 class="text-center mb-5" style="color:#ff7b00; font-weight:900;">MÉGA MOULINETTE</h1>

    <div class="row g-4 mb-5">
        <div class="col-md-6">
            <div class="card-custom">
                <h4 class="mb-3">Importer fichier ALL</h4>
                <form method="POST" action="{{ route('moulinette.all') }}" enctype="multipart/form-data">
                    @csrf
                    <input type="file" name="file_all" class="form-control mb-3" required>
                    <button class="btn btn-orange w-100">Lancer traitement ALL</button>
                </form>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card-custom">
                <h4 class="mb-3">Importer fichier NOEXP</h4>
                <form method="POST" action="{{ route('moulinette.noexp') }}" enctype="multipart/form-data">
                    @csrf
                    <input type="file" name="file_noexp" class="form-control mb-3" required>
                    <button class="btn btn-orange w-100">Lancer traitement NOEXP</button>
                </form>
            </div>
        </div>
    </div>

    @if(session('logs'))
    <div class="card-custom mt-4">
        <h4 class="mb-3">Logs</h4>
        <table class="table">
            <thead>
                <tr>
                    <th>Status</th>
                    <th>Message</th>
                </tr>
            </thead>
            <tbody>
                @foreach(session('logs') as $log)
                    <tr>
                        <td class="log-{{ strtolower($log['type']) }}">{{ $log['type'] }}</td>
                        <td>{{ $log['message'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>
@endsection
