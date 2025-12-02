<!DOCTYPE html>
<html>
<head>
    <title>Notices</title>
</head>
<body>
    <h1>Page Notices</h1>

    <!-- Bouton Importer -->
    <form action="{{ route('notices.import') }}" method="POST">
        @csrf
        <button type="submit">Importer</button>
    </form>

    <h2>Dernières notices</h2>
    <ul>
        @foreach($notices as $notice)
            <li>{{ $notice->titre }} - {{ $notice->date_publication }}</li>
        @endforeach
    </ul>

    <!-- Message succès / erreur -->
    @if(session('message'))
        <p>{{ session('message') }}</p>
    @endif
</body>
</html>
