<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class SendCsvToSftp extends Command
{
    protected $signature = 'send:csv-sftp';
    protected $description = 'Envoie tous les fichiers CSV du dossier local vers le SFTP';

    public function handle()
    {
        $files = Storage::disk('local')->files('uploads');

        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'csv') {
                try {
                    $filename = basename($file);
                    Storage::disk('sftp')->put($filename, Storage::disk('local')->get($file));
                    $this->info("Fichier $filename envoyé avec succès !");
                } catch (\Exception $e) {
                    $this->error("Erreur pour $filename : " . $e->getMessage());
                }
            }
        }
    }
}
