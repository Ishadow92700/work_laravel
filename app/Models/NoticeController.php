<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use App\Models\Notice;
use Carbon\Carbon;

class NoticeController extends Controller
{

    public function index()
    {
        // On récupère éventuellement quelques notices à afficher
        $notices = Notice::latest()->take(10)->get();

        return view('notices.index', compact('notices'));
    }
    public function import()
    {
        // 1. Récupérer les notices des 3 dernières semaines
        $threeWeeksAgo = Carbon::now()->subWeeks(3);
        $notices = Notice::where('date_publication', '>=', $threeWeeksAgo)->get();

        if ($notices->isEmpty()) {
            return back()->with('message', 'Aucune notice trouvée pour les 3 dernières semaines.');
        }

        // 2. Générer un CSV
        $filename = 'notices_' . now()->format('Ymd_His') . '.csv';
        $csvData = "ID,Titre,Contenu,Date Publication\n";

        foreach ($notices as $notice) {
            $csvData .= "{$notice->id},\"{$notice->titre}\",\"{$notice->contenu}\",{$notice->date_publication}\n";
        }

        // 3. Sauvegarder le fichier localement (storage/app)
        Storage::put($filename, $csvData);

        // 4. Envoyer vers SFTP
        // Configuration SFTP dans config/filesystems.php et .env
        $sftp = Storage::disk('sftp');
        $sftp->put($filename, $csvData);

        return back()->with('message', "Fichier $filename importé avec succès sur le SFTP !");
    }
}
