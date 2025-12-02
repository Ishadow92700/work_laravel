@extends('layouts.app')

@section('content')

<style>
    body {
        background: #f7f7f7 !important;
        font-family: 'Inter', sans-serif;
    }

    .card-modern {
        backdrop-filter: blur(12px);
        background: rgba(255, 255, 255, 0.65);
        border-radius: 18px;
        border: 1px solid rgba(255, 255, 255, 0.3);
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
        padding: 2.5rem;
    }

    h1 {
        font-weight: 700;
        font-size: 2.4rem;
        color: #f3a41e;
        letter-spacing: -0.5px;
    }

    label {
        font-weight: 600;
        color: #333;
        margin-bottom: 6px;
    }

    .form-control {
        border-radius: 12px;
        padding: 12px 14px;
        border: 1px solid #ddd;
    }

    .btn-primary-modern {
        background: #f3a41e;
        border: none;
        color: #fff;
        padding: 14px 28px;
        border-radius: 14px;
        font-weight: 600;
        transition: 0.2s ease-in-out;
        font-size: 1.05rem;
    }

    .btn-primary-modern:hover {
        background: #ffb947;
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(243,164,30,0.4);
    }

    .btn-download {
        background: #28a745;
        border: none;
        padding: 12px 22px;
        border-radius: 10px;
        font-weight: 600;
        color: #fff;
        transition: 0.2s ease;
    }

    .btn-download:hover {
        background: #32c758;
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(40,167,69,0.35);
    }
</style>


<div class="container py-5">

    <h1 class="text-center mb-5">
        Moulinette Laravel
    </h1>

    <div class="card-modern mx-auto" style="max-width: 700px;">

        <form method="POST" action="{{ url('moulinette/process') }}" enctype="multipart/form-data">
            @csrf

            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <label for="file_all">Fichier “all commande”</label>
                    <input id="file_all" type="file" name="file_all" class="form-control" accept=".xlsx,.txt,.csv" required>
                    @error('file_all')
                        <div class="text-danger mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label for="file_noexp">Fichier “fbm no exp”</label>
                    <input id="file_noexp" type="file" name="file_noexp" class="form-control" accept=".xlsx,.txt,.csv" required>
                    @error('file_noexp')
                        <div class="text-danger mt-1">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="text-center">
                <button type="submit" class="btn-primary-modern">
                    Générer Yoyoamaz
                </button>
            </div>

        </form>

        @if(session('success'))
            <div class="mt-4 text-center">
                <a href="{{ url('moulinette/download') }}" class="btn-download">
                    Télécharger Yoyoamaz
                </a>
            </div>
        @endif

    </div>
</div>

@endsection
