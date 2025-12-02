<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

use Illuminate\Support\Facades\Storage;

Route::get('/testsftp', function () {
    try {
        Storage::disk('sftp')->put('test.txt', 'Ceci est un test');

        $content = Storage::disk('sftp')->get('test.txt');      // Lire le contenu
        $files = Storage::disk('sftp')->allFiles('/');          // Lister les fichiers
        $exists = Storage::disk('sftp')->exists('test.txt');    // Vérifier existence

        return "
            Connexion SFTP OK, fichier test.txt envoyé !<br>
            Contenu du fichier : $content<br>
            Fichiers sur le serveur : " . implode(', ', $files) . "<br>
            Existe ? " . ($exists ? "Oui" : "Non");
    } catch (\Exception $e) {
        return "Erreur SFTP : " . $e->getMessage();
    }
});
