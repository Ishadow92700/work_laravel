<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>YOYOAMAZ - Moulinette Laravel</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <style>
        body { font-family: Inter, Arial, sans-serif; padding: 20px; max-width: 900px; margin:auto; }
        .card { border:1px solid #eee; padding:16px; border-radius:8px; box-shadow: 0 2px 6px rgba(0,0,0,0.04); margin-bottom:16px;}
        .btn { padding:10px 16px; border-radius:6px; display:inline-block; text-decoration:none; background:#2563eb; color:#fff;}
        .log { background:#0b1220; color:#e6eef8; padding:12px; font-family: monospace; height:260px; overflow:auto; white-space:pre; border-radius:6px;}
        .status { display:inline-block; padding:6px 10px; border-radius:6px; color:#fff; margin-right:8px;}
        .ok{background:#16a34a;} .err{background:#dc2626;} .warn{background:#f59e0b;}
        label {display:block; margin-top:8px;}
    </style>
</head>
<body>
    <h2>YOYOAMAZ — Moulinette (Laravel)</h2>
    <div class="card">
        <form method="post" action="{{ route('upload.process') }}" enctype="multipart/form-data">
            @csrf
            <label>Fichier ALL (xlsx / csv / txt tabulé)</label>
            <input type="file" name="file_all" required>
            <label>Fichier NO EXP (txt tabulé / csv / xlsx)</label>
            <input type="file" name="file_noexp" required>
            <div style="margin-top:12px;">
                <button class="btn" type="submit">Lancer la moulinette</button>
            </div>
        </form>
    </div>

    <div class="card">
        <div>
            <span class="status ok">OK</span> = Succès
            <span class="status warn">WARN</span> = Avertissement
            <span class="status err">ERR</span> = Erreur
        </div>
        <h4>Logs</h4>
        <div class="log" id="logBox">
            @if(isset($log))
                {!! implode("\n", $log) !!}
            @else
                Prêt. Charge les fichiers ALL et NO EXP puis clique sur "Lancer la moulinette".
            @endif
        </div>

        @if(isset($success) && $success && isset($download))
            <div style="margin-top:12px;">
                <a class="btn" href="{{ $download }}">Télécharger le TXT tabulé (a_envoyer_... .txt)</a>
            </div>
        @endif

        @if(isset($error))
            <div style="margin-top:12px; color:#dc2626;">Erreur : {{ $error }}</div>
        @endif
    </div>
</body>
</html>
