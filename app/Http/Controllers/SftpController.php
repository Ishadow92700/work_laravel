<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Controller;

class SftpController extends Controller
{
    // 🟢 1️⃣ Afficher le dernier CSV
    // 🟢 1️⃣ Afficher le dernier CSV
public function dashboard()
{
    try {
        // Log pour debug (facultatif)
        \Log::info('Connexion SFTP : début récupération des fichiers');

        // Récupère uniquement les fichiers (pas les sous-dossiers)
        $files = Storage::disk('sftp')->files();

        \Log::info('Connexion SFTP : fichiers récupérés', ['count' => count($files)]);

        if (empty($files)) {
            return "Aucun fichier trouvé sur le SFTP.";
        }

        // 🔹 Garde uniquement les fichiers CSV au format spécifique
        $files = array_filter($files, function ($f) {
            $name = basename($f);
            return preg_match('/^releve_ventes_\d{6}_\d{14}\.csv$/', $name);
        });

        if (empty($files)) {
            return "Aucun fichier CSV trouvé sur le SFTP.";
        }

        // Trie par date de modification pour avoir le plus récent
        usort($files, function ($a, $b) {
            return Storage::disk('sftp')->lastModified($b) <=> Storage::disk('sftp')->lastModified($a);
        });

        $lastFile = $files[0]; // le plus récent

        // Récupère le contenu du CSV
        $csvContent = Storage::disk('sftp')->get($lastFile);

        // 🔧 Convertir en UTF-8 si ce n’est pas déjà le cas
        if (!mb_check_encoding($csvContent, 'UTF-8')) {
            $csvContent = mb_convert_encoding($csvContent, 'UTF-8', 'Windows-1252');
        }

        // Séparer les lignes
        $lines = array_filter(explode("\n", $csvContent));

        // Header fixe selon tes besoins
        $csvHeader = [
            'Code éditeur',   
            'Code fournisseur', 
            'Code maison',    
            'Adresse mail',   
            'Pays',           
            'EAN du livre',   
            'Nom du titre',   
            'Nb titres éditeur'
        ];

        // Convertir toutes les lignes en tableaux
        $rows = array_map(fn($line) => str_getcsv($line, ';'), $lines);

        $finalRows = [];
        foreach ($rows as $row) {
            $editeur = $row[2] ?? '';       // colonne C
            $fournisseur = $row[3] ?? '';   // colonne D
            $maison = $row[4] ?? '';        // récupère depuis la BD si nécessaire
            $mail = $row[10] ?? '';         // colonne K
            $pays = 'FR';
            $ean = $row[13] ?? '';          // colonne N
            $titre = $row[14] ?? '';        // colonne O

            // compter combien de titres pour cet éditeur sur cette page
            $nbTitres = count(array_filter($rows, fn($r) => ($r[2] ?? '') === $editeur));

            $finalRows[] = [
                $editeur,
                $fournisseur,
                $maison,
                $mail,
                $pays,
                $ean,
                $titre,
                $nbTitres
            ];
        }

        // Retour à la vue ou JSON si la vue n'existe pas
        return view()->exists('sftp.dashboard')
            ? view('sftp.dashboard', [
                'lastFile' => $lastFile,
                'header' => $csvHeader,
                'data' => $finalRows,
            ])
            : response()->json([
                'lastFile' => $lastFile,
                'header' => $csvHeader,
                'data' => $finalRows,
            ]);

    } catch (\Exception $e) {
        return "Erreur SFTP : " . $e->getMessage();
    }
}

    // 🟡 2️⃣ Télécharger le fichier
    public function download(Request $request)
    {
        $file = $request->query('file');
        $disk = Storage::disk('sftp');

        if ($disk->exists($file)) {
            $stream = $disk->readStream($file);
            if ($stream === false) {
                return response("Impossible d'ouvrir le fichier.", 500);
            }

            // Déterminer le type MIME
            $mime = null;
            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if ($extension === 'csv') {
                $mime = 'text/csv';
            } elseif ($extension === 'txt') {
                $mime = 'text/plain';
            }
            $mime = $mime ?? 'application/octet-stream';
            $name = basename($file);

            // Récupérer la taille du fichier (facultatif)
            try {
                $size = $disk->size($file);
            } catch (\Throwable $e) {
                $size = null;
            }

            // Stream du fichier
            return response()->stream(function () use ($stream) {
                fpassthru($stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }, 200, array_filter([
                'Content-Type' => $mime,
                'Content-Length' => $size !== null ? (string) $size : null,
                'Content-Disposition' => 'attachment; filename="'.$name.'"',
            ]));
        }

        return "Fichier introuvable";
    }

    // 🔴 3️⃣ Supprimer un fichier
    public function delete(Request $request)
    {
        $file = $request->query('file');
        if (Storage::disk('sftp')->exists($file)) {
            Storage::disk('sftp')->delete($file);
            return redirect('/sftp/dashboard')->with('message', "$file supprimé !");
        }
        return "Fichier introuvable";
    }

    // 🔵 4️⃣ Uploader un nouveau CSV
    public function upload(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt'
        ]);

        $file = $request->file('csv_file');
        $filename = $file->getClientOriginalName();

        Storage::disk('sftp')->putFileAs('', $file, $filename);

        return redirect('/sftp/dashboard')->with('message', "$filename uploadé !");
    }
}
