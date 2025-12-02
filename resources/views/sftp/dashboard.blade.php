<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Pollen</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50 p-10">
    <h1 class="text-2xl font-bold mb-6">📊 Dernier fichier CSV : {{ $lastFile }}</h1>

    {{-- Messages flash --}}
    @if(session('message'))
        <div class="mb-4 p-4 bg-green-100 text-green-800 rounded">
            {{ session('message') }}
        </div>
    @endif

    {{-- Formulaire upload --}}
    <div class="mb-6 p-4 bg-white shadow rounded">
        <form action="{{ url('/sftp/upload') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <label class="block mb-2 font-semibold">Uploader un nouveau fichier CSV :</label>
            <input type="file" name="csv_file" accept=".csv,.txt" required class="mb-2 p-2 border rounded">
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">Exporter</button>
        </form>
    </div>

    {{-- Boutons actions --}}
    <div class="mb-6">
        <a href="{{ url('/sftp/download?file='.$lastFile) }}" class="px-4 py-2 bg-green-600 text-white rounded mr-2">Importer</a>
    </div>

    {{-- Tableau CSV --}}
<div class="overflow-x-auto bg-white shadow rounded p-4">
    <table class="min-w-full border border-gray-300">
        <thead class="bg-gray-100">
            <tr>
                @foreach(is_iterable($header) ? $header : [] as $col)
                    <th class="px-4 py-2 border">{{ $col }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($data as $row) {{-- ✅ on utilise $data, pas $csvRows --}}
                <tr class="hover:bg-gray-50">
                    @foreach($row as $cell)
                        <td class="border px-4 py-1 font-mono text-sm">{{ $cell }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

</body>
</html>
