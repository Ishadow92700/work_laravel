<!DOCTYPE html>
<html>
<head>
    <title>Traitement fichiers</title>
    <style>
        body { font-family: Arial; background: #fff; color: #333; padding: 20px; }
        .log { margin: 5px 0; padding: 10px; border-radius: 6px; display: flex; align-items: center; }
        .OK { background: #e6ffe6; color: green; }
        .WARN { background: #fff4e6; color: orange; }
        .ERR { background: #ffe6e6; color: red; }
        .circle { width: 15px; height: 15px; border-radius: 50%; display: inline-block; margin-right: 10px; }
        .OK .circle { background: green; }
        .WARN .circle { background: orange; }
        .ERR .circle { background: red; }
    </style>
</head>
<body>
    <h1>Traitement fichiers</h1>

    <form action="{{ route('file.process') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <label>Fichier ALL:</label>
        <input type="file" name="all" required><br><br>
        <label>Fichier NOEXP:</label>
        <input type="file" name="noexp" required><br><br>
        <button type="submit">Traiter</button>
    </form>

    <h2>Logs</h2>
    @if(!empty($logs))
        @foreach($logs as $log)
            <div class="log {{ $log['type'] }}">
                <span class="circle"></span>
                {{ $log['message'] }}
            </div>
        @endforeach
    @endif
</body>
</html>
