<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8"/>
    <title>Stock Amazon - Dashboard</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .card { border: 1px solid #ddd; padding: 16px; margin-bottom: 20px; border-radius: 6px; }
        h2 { margin-top: 0; }
        input[type="file"] { display:block; margin-bottom:10px; }
        .success { color: green; }
        .error { color: red; }
    </style>
</head>
<body>

    <h1>Moulinette Amazon — Dashboard</h1>

    @if(session('success'))
        <div class="success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="error">{{ session('error') }}</div>
    @endif

    {{-- 1) Moulinette --}}
    <div class="card">
        <h2>1. Fusion commandes (Moulinette)</h2>
        <form action="{{ url('/moulinette/process') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <label>Fichier ALL (export complet) :</label>
            <input type="file" name="file_all" required>
            <label>Fichier NOEXP (adresse / shipping pref)</label>
            <input type="file" name="file_noexp" required>
            <button type="submit">Lancer Fusion</button>
        </form>
        <form action="{{ url('/moulinette/download') }}" method="GET" style="margin-top:8px;">
            <button type="submit">Télécharger résultat Moulinette</button>
        </form>
    </div>

    {{-- 2) Ajouter Nouveaux Produits --}}
    <div class="card">
        <h2>2. Ajouter nouveaux produits (Qlik → Template Amazon)</h2>
        <form action="{{ url('/ajouter/process') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <label>Fichier Qlik PRODUITS (EAN, Qty) :</label>
            <input type="file" name="file_produits" required>
            <label>Fichier Qlik EAN_PRICE (EAN, Price) :</label>
            <input type="file" name="file_ean_price" required>
            <label>Template Excel 'Ajouter' :</label>
            <input type="file" name="file_template_ajouter" accept=".xlsx,.xls" required>

            <h4>Paramètres (modifiable)</h4>
            <label>Type :</label><input type="text" name="type" value="EAN" required>
            <label>Canal :</label><input type="text" name="canal" value="DEFAULT" required>
            <label>Statut :</label><input type="text" name="statut" value="Nouveau" required>
            <label>Temps de traitement :</label><input type="number" name="temps_traitement" value="2" required>

            <button type="submit">Lancer Ajout Produits</button>
        </form>

        <form action="{{ url('/ajouter/download') }}" method="GET" style="margin-top:8px;">
            <button type="submit">Télécharger fichier Ajouter</button>
        </form>
    </div>

    {{-- 3) Maj Stock --}}
    <div class="card">
        <h2>3. Mise à jour des stocks (Qlik stock → Template Maj)</h2>
        <form action="{{ url('/maj/process') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <label>Fichier Qlik STOCK (EAN, Qty) :</label>
            <input type="file" name="file_stock" required>
            <label>Template Excel pour MAJ :</label>
            <input type="file" name="file_template_maj" accept=".xlsx,.xls" required>

            <label>Nom de la feuille cible (optionnel) :</label>
            <input type="text" name="sheet_to_write" placeholder="Maj Quantités">

            <button type="submit">Lancer MAJ Stock</button>
        </form>

        <form action="{{ url('/maj/download') }}" method="GET" style="margin-top:8px;">
            <button type="submit">Télécharger fichier MAJ Stock</button>
        </form>
    </div>

</body>
</html>
